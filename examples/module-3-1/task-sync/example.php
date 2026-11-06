<?php
/**
 * Пример использования синхронизации задач между внешней системой и Bitrix24
 *
 * Демонстрирует двустороннюю синхронизацию с отслеживанием изменений
 */

require_once __DIR__ . '/TaskManager.php';
require_once __DIR__ . '/TaskSynchronizer.php';

// ===== КОНФИГУРАЦИЯ =====
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/ваш_webhook_код/';
$SYNC_LOG_FILE = __DIR__ . '/sync.log';
$MAPPING_FILE = __DIR__ . '/task_mapping.json';

// ===== ИНИЦИАЛИЗАЦИЯ =====
$taskManager = new TaskManager($WEBHOOK_URL);
$synchronizer = new TaskSynchronizer($taskManager, $SYNC_LOG_FILE, $MAPPING_FILE);

echo "=== Синхронизация задач с Bitrix24 ===\n\n";

// ===== ПРИМЕР 1: Синхронизация задач ИЗ внешней системы В Bitrix24 =====
echo "=== 1. Синхронизация из внешней системы в Bitrix24 ===\n";

// Имитация данных из внешней системы (например, из вашей CRM или таск-менеджера)
$externalTasks = [
    [
        'id' => 'ext-001',
        'title' => 'Разработать REST API интеграцию',
        'description' => 'Создать модуль для синхронизации данных',
        'responsible_id' => 1,
        'deadline' => date('Y-m-d', strtotime('+7 days')),
        'completed' => false
    ],
    [
        'id' => 'ext-002',
        'title' => 'Написать документацию',
        'description' => 'Подробное описание API методов',
        'responsible_id' => 1,
        'deadline' => date('Y-m-d', strtotime('+14 days')),
        'completed' => false
    ],
    [
        'id' => 'ext-003',
        'title' => 'Провести код-ревью',
        'description' => 'Проверить качество кода интеграции',
        'responsible_id' => 1,
        'deadline' => date('Y-m-d', strtotime('+3 days')),
        'completed' => false
    ]
];

echo "Синхронизируем " . count($externalTasks) . " задач из внешней системы...\n";

$result = $synchronizer->syncFromExternal($externalTasks);

echo "✓ Создано задач: " . count($result['created']) . "\n";
foreach ($result['created'] as $created) {
    echo "  - External ID {$created['external_id']} → Bitrix24 ID {$created['bitrix_id']}\n";
}

echo "✓ Обновлено задач: " . count($result['updated']) . "\n";
foreach ($result['updated'] as $updated) {
    echo "  - External ID {$updated['external_id']} → Bitrix24 ID {$updated['bitrix_id']}\n";
}

if (!empty($result['errors'])) {
    echo "✗ Ошибки: " . count($result['errors']) . "\n";
    foreach ($result['errors'] as $error) {
        echo "  - External ID {$error['external_id']}: {$error['error']}\n";
    }
}

echo "\n";

// ===== ПРИМЕР 2: Повторная синхронизация с обновлением =====
echo "=== 2. Повторная синхронизация с изменениями ===\n";

// Имитируем изменения в задачах
$externalTasks[0]['title'] = 'Разработать REST API интеграцию (приоритет высокий)';
$externalTasks[0]['description'] = 'СРОЧНО: Создать модуль для синхронизации данных';
$externalTasks[1]['completed'] = true; // Задача завершена

echo "Синхронизируем обновленные задачи...\n";

$result = $synchronizer->syncFromExternal($externalTasks);

echo "✓ Создано задач: " . count($result['created']) . "\n";
echo "✓ Обновлено задач: " . count($result['updated']) . "\n";

echo "\n";

// ===== ПРИМЕР 3: Синхронизация ИЗ Bitrix24 ВО внешнюю систему =====
echo "=== 3. Синхронизация из Bitrix24 во внешнюю систему ===\n";

// Получаем измененные задачи с определенной даты
$sinceDate = date('Y-m-d H:i:s', strtotime('-1 hour'));
echo "Получаем задачи, измененные с $sinceDate...\n";

$changedTasks = $synchronizer->syncToBitrix($sinceDate);

echo "Найдено измененных задач: " . count($changedTasks) . "\n";

foreach ($changedTasks as $task) {
    echo "\n";
    echo "  Bitrix24 ID: {$task['bitrix_id']}\n";
    echo "  External ID: " . ($task['external_id'] ?? 'новая') . "\n";
    echo "  Действие: {$task['action']}\n";
    echo "  Название: {$task['data']['title']}\n";
    echo "  Статус: " . ($task['data']['completed'] ? 'завершена' : 'в работе') . "\n";
}

echo "\n";

// ===== ПРИМЕР 4: Работа с конкретной задачей =====
echo "=== 4. Работа с конкретной задачей через TaskManager ===\n";

// Создаем новую задачу напрямую
$newTaskId = $taskManager->createTask([
    'TITLE' => 'Тестовая задача через API',
    'DESCRIPTION' => 'Проверка создания задачи',
    'RESPONSIBLE_ID' => 1,
    'DEADLINE' => date('c', strtotime('+5 days'))
]);

if ($newTaskId) {
    echo "✓ Создана задача ID: $newTaskId\n";

    // Получаем данные задачи
    $task = $taskManager->getTask($newTaskId);
    echo "  Название: {$task['title']}\n";
    echo "  Дедлайн: {$task['deadline']}\n";

    // Добавляем комментарий
    $commentId = $taskManager->addComment($newTaskId, 'Автоматический комментарий из синхронизации');
    if ($commentId) {
        echo "  ✓ Добавлен комментарий\n";
    }

    // Обновляем задачу
    $taskManager->updateTask($newTaskId, [
        'DESCRIPTION' => 'Обновленное описание задачи'
    ]);
    echo "  ✓ Задача обновлена\n";

    // Завершаем задачу
    $taskManager->completeTask($newTaskId);
    echo "  ✓ Задача завершена\n";

    echo "\n";
}

// ===== ПРИМЕР 5: Получение задач по фильтру =====
echo "=== 5. Получение задач по фильтру ===\n";

// Мои активные задачи
$myTasks = $taskManager->getTasks(
    ['RESPONSIBLE_ID' => 1, 'REAL_STATUS' => [1, 2, 3]], // Статусы: новая, принята, в работе
    ['ID', 'TITLE', 'DEADLINE', 'STATUS'],
    ['DEADLINE' => 'ASC']
);

echo "Найдено активных задач: " . count($myTasks) . "\n";
foreach (array_slice($myTasks, 0, 5) as $task) {
    $deadline = $task['deadline'] ?? 'не указан';
    echo "  - [{$task['id']}] {$task['title']} (дедлайн: $deadline)\n";
}

echo "\n";

// ===== ПРИМЕР 6: Статистика синхронизации =====
echo "=== 6. Статистика синхронизации ===\n";

$stats = $synchronizer->getStatistics();

echo "Всего синхронизированных задач: {$stats['total_synced_tasks']}\n";
echo "Последняя синхронизация: {$stats['last_sync_time']}\n";

if (!empty($stats['mapping'])) {
    echo "\nСвязи задач (External → Bitrix24):\n";
    foreach ($stats['mapping'] as $extId => $bitrixId) {
        echo "  $extId → $bitrixId\n";
    }
}

echo "\n";

// ===== ПРИМЕР 7: Симуляция периодической синхронизации =====
echo "=== 7. Симуляция периодической синхронизации ===\n";
echo "В реальном проекте это можно запускать по cron каждые N минут\n\n";

echo "Пример команды cron для синхронизации каждые 5 минут:\n";
echo "*/5 * * * * /usr/bin/php " . __DIR__ . "/sync_cron.php\n\n";

// ===== ИНФОРМАЦИЯ О ЛОГАХ =====
echo "=== Логи синхронизации ===\n";
echo "Лог файл: $SYNC_LOG_FILE\n";
echo "Файл связей: $MAPPING_FILE\n";

if (file_exists($SYNC_LOG_FILE)) {
    $logSize = filesize($SYNC_LOG_FILE);
    echo "Размер лога: " . number_format($logSize) . " байт\n";

    // Показываем последние 5 строк лога
    $logLines = file($SYNC_LOG_FILE);
    $lastLines = array_slice($logLines, -5);

    echo "\nПоследние записи в логе:\n";
    foreach ($lastLines as $line) {
        echo "  " . trim($line) . "\n";
    }
}

echo "\n=== Синхронизация завершена ===\n";
