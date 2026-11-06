<?php
/**
 * Примеры использования LeadBatchUpdater
 *
 * Расположение: /local/php_interface/examples/batch_lead_update_examples.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/LeadBatchUpdater.php';

use Vettich\LeadBatchUpdater;

// Инициализация REST клиента (замените на ваш способ инициализации)
// Например, через CRest или Bitrix24 SDK
$client = CRest::getInstance(); // или ваш метод получения клиента

// ============================================================
// ПРИМЕР 1: Обновить статус всех новых лидов старше месяца
// ============================================================

echo "=== ПРИМЕР 1: Обновление старых лидов ===\n\n";

$updater = new LeadBatchUpdater($client);

// Фильтр: все лиды со статусом NEW, созданные более месяца назад
$filter = [
    'STATUS_ID' => 'NEW',
    '<DATE_CREATE' => date('Y-m-d', strtotime('-1 month'))
];

// Обновляем статус на "В работе"
$updater->updateLeadStatusByFilter($filter, 'IN_PROCESS');

// Выводим отчет
$updater->printReport();

// ============================================================
// ПРИМЕР 2: Массовое обновление ответственного для конкретных лидов
// ============================================================

echo "\n=== ПРИМЕР 2: Смена ответственного ===\n\n";

$updater2 = new LeadBatchUpdater($client);
$updater2->setBatchSize(30); // Устанавливаем размер пакета

// ID лидов для обновления
$leadIds = [123, 456, 789, 1011, 1213];

// Обновляем ответственного и комментарий
$fields = [
    'ASSIGNED_BY_ID' => 5, // ID нового ответственного
    'COMMENTS' => 'Лид переназначен автоматически'
];

$updater2->updateLeads($leadIds, $fields);
$updater2->printReport();

// ============================================================
// ПРИМЕР 3: Перевод всех неактивных лидов в архив
// ============================================================

echo "\n=== ПРИМЕР 3: Архивация неактивных лидов ===\n\n";

$updater3 = new LeadBatchUpdater($client);

// Фильтр: лиды без активности более 6 месяцев
$filter = [
    '<DATE_MODIFY' => date('Y-m-d', strtotime('-6 months')),
    '!STATUS_ID' => 'CONVERTED' // Исключаем конвертированные
];

// Переводим в статус "Не актуально" и добавляем метку
$updater3->updateLeadStatusByFilter($filter, 'JUNK', [
    'UF_CRM_ARCHIVED' => 1, // Пользовательское поле "Архивный"
    'COMMENTS' => 'Автоматически перемещено в архив из-за неактивности'
]);

$updater3->printReport();

// ============================================================
// ПРИМЕР 4: Обновление пользовательских полей для группы лидов
// ============================================================

echo "\n=== ПРИМЕР 4: Обновление пользовательских полей ===\n\n";

$updater4 = new LeadBatchUpdater($client);

// Фильтр: все лиды определенного источника
$filter = [
    'SOURCE_ID' => 'WEBFORM', // Лиды с веб-формы
    'STATUS_ID' => 'NEW'
];

// Добавляем теги и пользовательское поле
$updater4->updateLeadStatusByFilter($filter, 'NEW', [
    'UF_CRM_TAGS' => ['веб-форма', 'требует обработки'],
    'UF_CRM_PRIORITY' => 'high'
]);

$updater4->printReport();

// ============================================================
// ПРИМЕР 5: Программная обработка результатов без вывода в консоль
// ============================================================

echo "\n=== ПРИМЕР 5: Программная обработка ===\n\n";

$updater5 = new LeadBatchUpdater($client);

$leadIds = range(100, 150); // Лиды с ID от 100 до 150

$results = $updater5->updateLeads($leadIds, [
    'STATUS_ID' => 'IN_PROCESS'
]);

// Получаем отчет как массив
$report = $updater5->getReport();

// Можем сохранить в БД или отправить уведомление
if ($report['errors'] > 0) {
    // Отправить email администратору
    echo "Обнаружены ошибки при обновлении: {$report['errors']}\n";

    // Логируем детали
    foreach ($report['error_details'] as $error) {
        error_log("Lead update error: " . json_encode($error));
    }
} else {
    echo "Все лиды успешно обновлены!\n";
}

echo "Процент успеха: {$report['success_rate']}%\n";
echo "Время выполнения: {$report['execution_time']} сек\n";

// ============================================================
// ПРИМЕР 6: Интеграция с агентом Bitrix
// ============================================================

/**
 * Агент для автоматической архивации старых лидов
 * Добавить в /bitrix/php_interface/init.php:
 *
 * CAgent::AddAgent(
 *     "ArchiveOldLeads();",
 *     "",
 *     "N",
 *     86400, // Раз в сутки
 *     "",
 *     "Y",
 *     "",
 *     30
 * );
 */
function ArchiveOldLeads()
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/LeadBatchUpdater.php';

    $client = CRest::getInstance();
    $updater = new LeadBatchUpdater($client);

    // Архивируем лиды старше 3 месяцев без активности
    $filter = [
        '<DATE_MODIFY' => date('Y-m-d', strtotime('-3 months')),
        'STATUS_ID' => 'NEW'
    ];

    $updater->updateLeadStatusByFilter($filter, 'JUNK');

    $report = $updater->getReport();

    // Логируем результат
    error_log("Archived {$report['success']} old leads. Errors: {$report['errors']}");

    return "ArchiveOldLeads();"; // Возвращаем для повторного запуска
}

// ============================================================
// ПРИМЕР 7: CLI скрипт для запуска из командной строки
// ============================================================

/**
 * Запуск из командной строки:
 * php /path/to/batch_lead_update_cli.php --status=NEW --new-status=IN_PROCESS
 */

if (php_sapi_name() === 'cli') {
    $options = getopt('', ['status:', 'new-status:', 'days-old:']);

    if (isset($options['status']) && isset($options['new-status'])) {
        $updater = new LeadBatchUpdater($client);

        $filter = ['STATUS_ID' => $options['status']];

        // Если указан возраст лидов
        if (isset($options['days-old'])) {
            $filter['<DATE_CREATE'] = date('Y-m-d', strtotime("-{$options['days-old']} days"));
        }

        echo "Обновление лидов...\n";
        echo "Фильтр: " . json_encode($filter, JSON_UNESCAPED_UNICODE) . "\n";
        echo "Новый статус: {$options['new-status']}\n\n";

        $updater->updateLeadStatusByFilter($filter, $options['new-status']);
        $updater->printReport();
    } else {
        echo "Использование:\n";
        echo "  php batch_lead_update_cli.php --status=NEW --new-status=IN_PROCESS [--days-old=30]\n";
    }
}
