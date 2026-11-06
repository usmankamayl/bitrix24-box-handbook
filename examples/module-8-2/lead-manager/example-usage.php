<?php
/**
 * ПРИМЕР ИСПОЛЬЗОВАНИЯ LeadManager
 *
 * Расположение этого файла в проекте:
 * /admin/leads_example.php
 *
 * Как использовать:
 * 1. Скопировать LeadManager.php в /local/php_interface/classes/
 * 2. В /local/php_interface/init.php добавить:
 *    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/LeadManager.php';
 * 3. Открыть /admin/leads_example.php в браузере
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Local\Bitrix24\LeadManager;

// URL входящего webhook (получите из Bitrix24)
$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/входящий_webhook_токен';

$manager = new LeadManager($webhookUrl);

// ============================================================================
// ПРИМЕР 1: Создание нового лида
// ============================================================================
echo '<h3>Пример 1: Создание лида</h3>';

$leadId = $manager->createLead([
    'TITLE' => 'Потенциальный клиент из веб-формы',
    'NAME' => 'Иван',
    'LAST_NAME' => 'Иванов',
    'SECOND_NAME' => 'Сергеевич',
    'PHONE' => [
        ['VALUE' => '+7 (999) 123-45-67', 'VALUE_TYPE' => 'WORK'],
        ['VALUE' => '+7 (999) 987-65-43', 'VALUE_TYPE' => 'MOBILE']
    ],
    'EMAIL' => [
        ['VALUE' => 'ivanov@example.com', 'VALUE_TYPE' => 'WORK']
    ],
    'SOURCE_ID' => 'WEB',      // Источник: веб-форма
    'STATUS_ID' => 'NEW',      // Статус: новый
    'ASSIGNED_BY_ID' => 1      // Ответственный (ID пользователя)
]);

if ($leadId) {
    echo "✅ Лид создан с ID: $leadId\n";
} else {
    echo "❌ Ошибка при создании лида\n";
}

// ============================================================================
// ПРИМЕР 2: Получение информации о лиде
// ============================================================================
echo '<h3>Пример 2: Получение лида</h3>';

if ($leadId) {
    $lead = $manager->getLead($leadId);

    if ($lead) {
        echo 'Информация о лиде: ' . print_r($lead, true) . "\n";
        echo "ID: {$lead['ID']}\n";
        echo "Название: {$lead['TITLE']}\n";
        echo "Статус: {$lead['STATUS_ID']}\n";
        echo "Дата создания: {$lead['DATE_CREATE']}\n";

        // Телефоны
        if (!empty($lead['PHONE'])) {
            echo "Телефоны:\n";
            foreach ($lead['PHONE'] as $phone) {
                echo "  - {$phone['VALUE']} ({$phone['VALUE_TYPE']})\n";
            }
        }
    }
}

// ============================================================================
// ПРИМЕР 3: Обновление лида
// ============================================================================
echo '<h3>Пример 3: Обновление лида</h3>';

if ($leadId) {
    $updated = $manager->updateLead($leadId, [
        'STATUS_ID' => 'IN_PROCESS',    // Перевести в "В работе"
        'ASSIGNED_BY_ID' => 2,          // Назначить другому сотруднику
        'COMMENTS' => 'Клиент интересуется услугой, назначена встреча на 15:00'
    ]);

    if ($updated) {
        echo "✅ Лид обновлен успешно\n";
    } else {
        echo "❌ Ошибка при обновлении лида\n";
    }
}

// ============================================================================
// ПРИМЕР 4: Добавление телефона
// ============================================================================
echo '<h3>Пример 4: Добавление телефона</h3>';

if ($leadId) {
    $added = $manager->addPhone($leadId, '+7 (999) 555-55-55', 'HOME');

    if ($added) {
        echo "✅ Телефон добавлен\n";
    } else {
        echo "❌ Ошибка при добавлении телефона\n";
    }
}

// ============================================================================
// ПРИМЕР 5: Поиск лидов
// ============================================================================
echo '<h3>Пример 5: Поиск лидов</h3>';

// Найти все новые лиды
$newLeads = $manager->searchLeads(
    ['STATUS_ID' => 'NEW'],
    ['ID', 'TITLE', 'NAME', 'PHONE', 'EMAIL'],
    ['DATE_CREATE' => 'DESC']
);

echo "Найдено новых лидов: " . count($newLeads) . "\n";

foreach ($newLeads as $lead) {
    echo "- #{$lead['ID']}: {$lead['TITLE']}\n";
}

// ============================================================================
// ПРИМЕР 6: Сложная фильтрация
// ============================================================================
echo '<h3>Пример 6: Поиск лидов за последнюю неделю</h3>';

$weekAgo = date('Y-m-d', strtotime('-7 days'));

$recentLeads = $manager->searchLeads(
    [
        // Статус в работе или новые
        'STATUS_ID' => ['NEW', 'IN_PROCESS'],
        // Дата создания за последнюю неделю
        '>=DATE_CREATE' => $weekAgo,
        // С назначенным ответственным
        '!ASSIGNED_BY_ID' => false
    ],
    ['ID', 'TITLE', 'DATE_CREATE', 'ASSIGNED_BY_ID'],
    ['DATE_CREATE' => 'DESC']
);

echo "Найдено актуальных лидов: " . count($recentLeads) . "\n";

// ============================================================================
// ПРИМЕР 7: Получение списка доступных полей
// ============================================================================
echo '<h3>Пример 7: Доступные поля лида</h3>';

$fields = $manager->getFields();

echo "Обязательные поля:\n";
foreach ($fields as $fieldName => $fieldInfo) {
    if ($fieldInfo['isRequired']) {
        echo "  - $fieldName: {$fieldInfo['title']}\n";
    }
}

echo "\nВсе поля (первые 10):\n";
$count = 0;
foreach ($fields as $fieldName => $fieldInfo) {
    if ($count++ < 10) {
        echo "  - $fieldName: {$fieldInfo['title']}\n";
    }
}

// ============================================================================
// ПРИМЕР 8: Удаление лида
// ============================================================================
echo '<h3>Пример 8: Удаление лида</h3>';

// Раскомментировать, если нужно удалить тестовый лид
/*
if ($leadId) {
    $deleted = $manager->deleteLead($leadId);

    if ($deleted) {
        echo "✅ Лид удален\n";
    } else {
        echo "❌ Ошибка при удалении лида\n";
    }
}
*/

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
