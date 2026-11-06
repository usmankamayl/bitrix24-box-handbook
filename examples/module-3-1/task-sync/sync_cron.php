<?php
/**
 * Скрипт для периодической синхронизации задач через cron
 * Запускайте этот файл по расписанию для автоматической синхронизации
 *
 * Пример cron: */5 * * * * /usr/bin/php /path/to/sync_cron.php
 */

require_once __DIR__ . '/TaskManager.php';
require_once __DIR__ . '/TaskSynchronizer.php';

// Конфигурация
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/ваш_webhook_код/';
$SYNC_LOG_FILE = __DIR__ . '/sync.log';
$MAPPING_FILE = __DIR__ . '/task_mapping.json';

// Инициализация
$taskManager = new TaskManager($WEBHOOK_URL);
$synchronizer = new TaskSynchronizer($taskManager, $SYNC_LOG_FILE, $MAPPING_FILE);

// Логируем начало синхронизации
$timestamp = date('Y-m-d H:i:s');
file_put_contents($SYNC_LOG_FILE, "[$timestamp] === Cron sync started ===\n", FILE_APPEND);

try {
    // 1. Получить задачи из вашей внешней системы
    // Здесь должна быть ваша логика получения задач
    // Например, из базы данных, API и т.д.
    $externalTasks = getExternalTasks();

    // 2. Синхронизировать в Bitrix24
    $result = $synchronizer->syncFromExternal($externalTasks);

    // Логируем результаты
    $created = count($result['created']);
    $updated = count($result['updated']);
    $errors = count($result['errors']);

    file_put_contents(
        $SYNC_LOG_FILE,
        "[$timestamp] Results: created=$created, updated=$updated, errors=$errors\n",
        FILE_APPEND
    );

    // 3. Синхронизировать изменения из Bitrix24 обратно
    $changedTasks = $synchronizer->syncToBitrix();

    // Сохранить изменения в вашу внешнюю систему
    saveToExternalSystem($changedTasks);

    file_put_contents(
        $SYNC_LOG_FILE,
        "[$timestamp] Exported " . count($changedTasks) . " tasks from Bitrix24\n",
        FILE_APPEND
    );

} catch (Exception $e) {
    file_put_contents(
        $SYNC_LOG_FILE,
        "[$timestamp] ERROR: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
}

file_put_contents($SYNC_LOG_FILE, "[$timestamp] === Cron sync finished ===\n\n", FILE_APPEND);

/**
 * Получить задачи из вашей внешней системы
 * Замените эту функцию на реальную логику получения задач
 */
function getExternalTasks()
{
    // Пример: получение из базы данных
    /*
    $db = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
    $stmt = $db->query('SELECT * FROM tasks WHERE needs_sync = 1');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    */

    // Пример: получение из файла JSON
    /*
    $json = file_get_contents(__DIR__ . '/external_tasks.json');
    return json_decode($json, true);
    */

    // Заглушка для примера
    return [];
}

/**
 * Сохранить изменения в вашу внешнюю систему
 * Замените эту функцию на реальную логику сохранения
 */
function saveToExternalSystem($tasks)
{
    // Пример: сохранение в базу данных
    /*
    $db = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');

    foreach ($tasks as $task) {
        if ($task['action'] === 'create') {
            // Создать новую задачу
            $stmt = $db->prepare('INSERT INTO tasks (bitrix_id, title, description) VALUES (?, ?, ?)');
            $stmt->execute([
                $task['bitrix_id'],
                $task['data']['title'],
                $task['data']['description']
            ]);
        } else {
            // Обновить существующую
            $stmt = $db->prepare('UPDATE tasks SET title = ?, description = ?, completed = ? WHERE external_id = ?');
            $stmt->execute([
                $task['data']['title'],
                $task['data']['description'],
                $task['data']['completed'],
                $task['external_id']
            ]);
        }
    }
    */

    // Пример: сохранение в файл JSON
    /*
    $existingData = [];
    if (file_exists(__DIR__ . '/external_tasks.json')) {
        $existingData = json_decode(file_get_contents(__DIR__ . '/external_tasks.json'), true);
    }

    foreach ($tasks as $task) {
        if ($task['external_id']) {
            // Обновить существующую задачу
            foreach ($existingData as &$existing) {
                if ($existing['id'] === $task['external_id']) {
                    $existing = array_merge($existing, $task['data']);
                    break;
                }
            }
        } else {
            // Добавить новую задачу
            $task['data']['id'] = 'ext-' . $task['bitrix_id'];
            $existingData[] = $task['data'];
        }
    }

    file_put_contents(
        __DIR__ . '/external_tasks.json',
        json_encode($existingData, JSON_PRETTY_PRINT)
    );
    */
}
