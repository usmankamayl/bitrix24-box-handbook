<?php
/**
 * Создание задачи с чек-листом, наблюдателями, дедлайном и привязкой к сделке
 *
 * Сценарий: при закрытии сделки менеджеру ставится задача "Подготовить документы"
 * с чек-листом из трёх пунктов, наблюдателем-руководителем и дедлайном +3 дня.
 *
 * Используемые методы REST API:
 *   - tasks.task.add        — создание задачи
 *   - task.checklistitem.add — добавление пунктов чек-листа
 *   - batch                 — отправка чек-листа одним запросом
 */

// ============================================================================
// НАСТРОЙКИ — замени на свои данные
// ============================================================================

// URL входящего вебхука с правами task (Задачи)
define('WEBHOOK', 'https://your-portal.bitrix24.ru/rest/1/YOUR_WEBHOOK_KEY/');

// ID исполнителя задачи (user.get — посмотри ID нужного пользователя)
define('RESPONSIBLE_ID', 15);

// ID постановщика (кто создаёт задачу — обычно отдел продаж или робот)
define('CREATED_BY', 1);

// ID наблюдателя — руководитель отдела
define('AUDITOR_ID', 3);

// ID сделки, к которой привязываем задачу (из crm.deal.list или события)
define('DEAL_ID', 123);

// ============================================================================
// ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ: вызов REST API
// ============================================================================

/**
 * Выполняет вызов метода REST API через cURL
 *
 * @param string $method Имя метода (например 'tasks.task.add')
 * @param array  $params Параметры метода
 * @return array         Ответ Bitrix24 в виде массива
 */
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
// ШАГ 1. Создаём задачу
// ============================================================================

// Формируем дедлайн: +3 рабочих дня от сегодня, 18:00
// ISO 8601 — единственный надёжный формат для REST API
$deadline = (new DateTime('+3 days'))
    ->setTime(18, 0)
    ->format('Y-m-d\TH:i:sP');

echo "=== Создание задачи ===\n";

$taskResult = callRest('tasks.task.add', [
    'fields' => [

        // Название — обязательное поле
        'TITLE' => 'Подготовить документы по сделке #' . DEAL_ID,

        // Описание — поддерживает BB-коды: [b], [i], [url]
        'DESCRIPTION' => 'Сделка [b]#' . DEAL_ID . '[/b] успешно закрыта. '
            . 'Необходимо подготовить и отправить клиенту пакет документов. '
            . 'Ссылка на сделку: [url=/crm/deal/details/' . DEAL_ID . '/]Открыть в CRM[/url]',

        // Исполнитель — один пользователь
        'RESPONSIBLE_ID' => RESPONSIBLE_ID,

        // Постановщик — кто ставит задачу
        'CREATED_BY' => CREATED_BY,

        // Наблюдатели — массив ID (руководитель следит за выполнением)
        'AUDITORS' => [AUDITOR_ID],

        // Соисполнители — массив ID (если нужно добавить коллег)
        // 'ACCOMPLICES' => [7, 9],

        // Дедлайн в формате ISO 8601
        'DEADLINE' => $deadline,

        // Приоритет: 0 = низкий, 1 = средний, 2 = высокий
        'PRIORITY' => 2,

        // Контроль постановщиком: задача уйдёт на проверку после "Завершить"
        'TASK_CONTROL' => 'Y',

        // Привязка к сделке CRM — ВСЕГДА массив строк с префиксами
        // D_ = сделка, C_ = контакт, CO_ = компания, L_ = лид
        'UF_CRM_TASK' => ['D_' . DEAL_ID],

        // Теги для поиска и фильтрации
        'TAGS' => ['документы', 'сделка-' . DEAL_ID, 'автозадача'],

        // Учёт времени (для списания часов)
        'ALLOW_TIME_TRACKING' => 'Y',

        // Оценка времени в секундах (4 часа)
        'TIME_ESTIMATE' => 4 * 3600,
    ],
]);

// Проверяем результат
if (isset($taskResult['error'])) {
    die("Ошибка создания задачи: {$taskResult['error']} — {$taskResult['error_description']}\n");
}

// ID созданной задачи
$taskId = $taskResult['result']['task']['id'] ?? null;

if (!$taskId) {
    die("Не удалось получить ID задачи. Ответ:\n" . print_r($taskResult, true));
}

echo "Задача создана: ID = {$taskId}\n";
echo "Дедлайн: {$deadline}\n";
echo "Ссылка: https://your-portal.bitrix24.ru/company/personal/user/" . RESPONSIBLE_ID . "/tasks/task/view/{$taskId}/\n\n";

// ============================================================================
// ШАГ 2. Добавляем чек-лист через batch (все пункты одним запросом)
// ============================================================================

// Пункты чек-листа — порядок через SORT_INDEX
$checklistItems = [
    'Сформировать договор из шаблона',
    'Согласовать договор с юристом',
    'Отправить документы клиенту и отметить в сделке',
];

echo "=== Добавление чек-листа ({$taskId}) ===\n";

// Формируем команды для batch — ключ = имя команды, значение = строка запроса
$batchCommands = [];
foreach ($checklistItems as $index => $title) {
    $batchCommands['item_' . $index] = 'task.checklistitem.add?' . http_build_query([
        'TASK_ID' => $taskId,
        'FIELDS'  => [
            'TITLE'       => $title,
            'IS_COMPLETE' => 'N',
            'SORT_INDEX'  => ($index + 1) * 10, // 10, 20, 30 — удобно для вставки между пунктами
        ],
    ]);
}

// Один HTTP-запрос вместо трёх отдельных
$batchResult = callRest('batch', [
    'halt' => 0, // продолжать выполнение даже если одна команда упала
    'cmd'  => $batchCommands,
]);

// Разбираем результат batch
if (isset($batchResult['error'])) {
    echo "Ошибка batch: {$batchResult['error_description']}\n";
} else {
    foreach ($batchResult['result']['result'] ?? [] as $cmd => $itemResult) {
        $itemId = $itemResult['id'] ?? 'ошибка';
        echo "  Пункт [{$cmd}]: ID = {$itemId}\n";
    }

    // Ошибки отдельных команд внутри batch
    foreach ($batchResult['result']['result_error'] ?? [] as $cmd => $err) {
        echo "  Ошибка в [{$cmd}]: {$err['error_description']}\n";
    }
}

// ============================================================================
// ШАГ 3. Добавляем первый комментарий с инструкцией
// ============================================================================

echo "\n=== Добавление комментария ===\n";

$commentResult = callRest('task.commentitem.add', [
    'TASKID' => $taskId,
    'FIELDS' => [
        'AUTHOR_ID'    => CREATED_BY,
        'POST_MESSAGE' => 'Задача создана автоматически при закрытии сделки #' . DEAL_ID . '. '
            . 'Шаблоны документов в папке: /docs/templates/. '
            . 'При вопросах — пишите в чат отдела продаж.',
    ],
]);

if (isset($commentResult['error'])) {
    echo "Ошибка комментария: {$commentResult['error_description']}\n";
} else {
    $commentId = $commentResult['result'] ?? '?';
    echo "Комментарий добавлен: ID = {$commentId}\n";
}

// ============================================================================
// ИТОГ
// ============================================================================

echo "\n=== Готово ===\n";
echo "Задача ID: {$taskId}\n";
echo "Исполнитель: user/" . RESPONSIBLE_ID . "\n";
echo "Наблюдатель: user/" . AUDITOR_ID . "\n";
echo "Привязка: сделка D_" . DEAL_ID . "\n";
echo "Чек-лист: " . count($checklistItems) . " пункта\n";
echo "Дедлайн: {$deadline}\n";
