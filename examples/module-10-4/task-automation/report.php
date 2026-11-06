<?php
/**
 * Отчёт по просроченным задачам отдела
 *
 * Выводит список задач с истёкшим дедлайном, сгруппированный по исполнителям.
 * Для большого количества задач использует batch-запросы (пагинация).
 *
 * Используемые методы REST API:
 *   - tasks.task.list   — список задач с фильтром по дедлайну и статусу
 *   - user.get          — имена исполнителей для отчёта
 *   - batch             — загрузка нескольких страниц за один запрос
 */

// ============================================================================
// НАСТРОЙКИ
// ============================================================================

define('WEBHOOK', 'https://your-portal.bitrix24.ru/rest/1/YOUR_WEBHOOK_KEY/');

// ID сотрудников отдела (можно получить через user.search с фильтром UF_DEPARTMENT)
$departmentUsers = [15, 16, 17, 18];

// ============================================================================
// ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ
// ============================================================================

function callRest(string $method, array $params = []): array
{
    $url = WEBHOOK . $method . '.json';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'CURL_ERROR', 'error_description' => $error];
    }

    return json_decode($response, true) ?? ['error' => 'PARSE_ERROR'];
}

// ============================================================================
// ШАГ 1. Первый запрос — узнаём общее количество просроченных задач
// ============================================================================

echo "=== Отчёт по просроченным задачам ===\n";
echo "Дата отчёта: " . date('d.m.Y H:i') . "\n";
echo "Сотрудники: " . implode(', ', $departmentUsers) . "\n\n";

$today = date('Y-m-d');

// Фильтр:
//   <DEADLINE    — дедлайн раньше сегодняшнего дня
//   !STATUS      — исключаем завершённые (5), отложенные (6), отклонённые (7)
//   RESPONSIBLE_ID — массив ID = задачи любого из этих сотрудников
$filter = [
    '<DEADLINE'      => $today,
    '!STATUS'        => [5, 6, 7],
    'RESPONSIBLE_ID' => $departmentUsers,
];

// Только нужные поля — не выбираем лишнее
$select = ['ID', 'TITLE', 'RESPONSIBLE_ID', 'DEADLINE', 'STATUS', 'GROUP_ID', 'PRIORITY'];

$firstPage = callRest('tasks.task.list', [
    'filter' => $filter,
    'select' => $select,
    'order'  => ['DEADLINE' => 'asc'], // сначала самые просроченные
    'start'  => 0,
]);

if (isset($firstPage['error'])) {
    die("Ошибка запроса: {$firstPage['error_description']}\n");
}

$tasks    = $firstPage['result']['tasks'] ?? [];
$total    = $firstPage['total'] ?? count($tasks);
$pageSize = 50; // tasks.task.list возвращает максимум 50

echo "Найдено просроченных задач: {$total}\n\n";

// ============================================================================
// ШАГ 2. Если задач больше 50 — догружаем остальные через batch
// ============================================================================

if ($total > $pageSize) {
    $pages = (int)ceil($total / $pageSize);

    echo "Загрузка страниц 2–{$pages} через batch...\n";

    // Формируем batch-команды: одна команда = одна страница
    $batchCommands = [];
    for ($page = 1; $page < $pages; $page++) {
        $batchCommands['page_' . $page] = 'tasks.task.list?' . http_build_query([
            'filter' => $filter,
            'select' => $select,
            'order'  => ['DEADLINE' => 'asc'],
            'start'  => $page * $pageSize, // 50, 100, 150...
        ]);
    }

    $batchResult = callRest('batch', [
        'halt' => 0,
        'cmd'  => $batchCommands,
    ]);

    // Собираем задачи со всех страниц
    foreach ($batchResult['result']['result'] ?? [] as $pageData) {
        foreach ($pageData['tasks'] ?? [] as $task) {
            $tasks[] = $task;
        }
    }

    echo "Всего загружено: " . count($tasks) . " задач\n\n";
}

// ============================================================================
// ШАГ 3. Получаем имена исполнителей через batch
// ============================================================================

// Собираем уникальные ID ответственных
$responsibleIds = array_unique(array_column($tasks, 'responsibleId'));
$responsibleIds = array_values(array_filter($responsibleIds));

// Batch-запрос user.get для каждого ID
$userCommands = [];
foreach ($responsibleIds as $uid) {
    $userCommands['user_' . $uid] = 'user.get?' . http_build_query([
        'ID'     => $uid,
        'FILTER' => ['ACTIVE' => 'Y'],
    ]);
}

$usersMap = [];
if (!empty($userCommands)) {
    $userResult = callRest('batch', [
        'halt' => 0,
        'cmd'  => $userCommands,
    ]);

    foreach ($userResult['result']['result'] ?? [] as $key => $userData) {
        $uid   = (int)str_replace('user_', '', $key);
        $user  = $userData[0] ?? null;
        $usersMap[$uid] = $user
            ? trim($user['NAME'] . ' ' . $user['LAST_NAME'])
            : "ID {$uid}";
    }
}

// ============================================================================
// ШАГ 4. Группируем задачи по исполнителям и выводим отчёт
// ============================================================================

// Названия статусов для читаемости отчёта
$statusNames = [
    1 => 'Новая',
    2 => 'Ждёт выполнения',
    3 => 'В работе',
    4 => 'На проверке',
    6 => 'Отложена',
    7 => 'Отклонена',
];

// Группируем по ID исполнителя
$byUser = [];
foreach ($tasks as $task) {
    $uid = (int)$task['responsibleId'];
    $byUser[$uid][] = $task;
}

// Сортируем пользователей по количеству просроченных задач (больше = выше)
uasort($byUser, fn($a, $b) => count($b) - count($a));

// Выводим отчёт
$totalOverdue = 0;

foreach ($byUser as $uid => $userTasks) {
    $userName = $usersMap[$uid] ?? "ID {$uid}";
    $count    = count($userTasks);
    $totalOverdue += $count;

    echo str_repeat('-', 60) . "\n";
    echo "Исполнитель: {$userName} (ID: {$uid}) — {$count} задач\n";
    echo str_repeat('-', 60) . "\n";

    foreach ($userTasks as $task) {
        // Считаем дни просрочки
        $deadline    = new DateTime($task['deadline']);
        $daysOverdue = (new DateTime('today'))->diff($deadline)->days;

        $statusName = $statusNames[$task['status']] ?? "Статус {$task['status']}";

        printf(
            "  [%s] %s\n",
            str_pad($task['id'], 5, ' ', STR_PAD_LEFT),
            $task['title']
        );
        printf(
            "         Дедлайн: %s | Просрочено: %d дн. | Статус: %s\n",
            $deadline->format('d.m.Y'),
            $daysOverdue,
            $statusName
        );
    }

    echo "\n";
}

// ============================================================================
// ШАГ 5. Итоговая сводка
// ============================================================================

echo str_repeat('=', 60) . "\n";
echo "ИТОГО просроченных задач: {$totalOverdue}\n";
echo "Сотрудников с просрочками: " . count($byUser) . " из " . count($departmentUsers) . "\n";

// Самая старая просроченная задача
if (!empty($tasks)) {
    $oldest = array_reduce($tasks, function ($carry, $task) {
        return (!$carry || strtotime($task['deadline']) < strtotime($carry['deadline']))
            ? $task
            : $carry;
    });

    $maxDays = (new DateTime('today'))->diff(new DateTime($oldest['deadline']))->days;
    echo "Самая старая просрочка: {$maxDays} дн. — «{$oldest['title']}»\n";
}
