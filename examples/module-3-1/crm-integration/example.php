<?php
/**
 * Пример использования DealManager для работы со сделками через REST API
 *
 * Демонстрирует основные операции: создание, получение, обновление, удаление сделок
 */

require_once __DIR__ . '/CRestClient.php';
require_once __DIR__ . '/DealManager.php';

// ===== КОНФИГУРАЦИЯ =====
// Замените на URL вашего webhook из Bitrix24
// Получить webhook: Настройки → Разработчикам → Входящий вебхук
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/ваш_webhook_код/';

// ===== ИНИЦИАЛИЗАЦИЯ =====
$client = new CRestClient($WEBHOOK_URL);
$dealManager = new DealManager($client);

// ===== ПРИМЕР 1: Создание новой сделки =====
echo "=== Создание новой сделки ===\n";

$dealId = $dealManager->createDeal([
    'TITLE' => 'Продажа CRM-системы',
    'STAGE_ID' => 'NEW',
    'OPPORTUNITY' => 150000,
    'CURRENCY_ID' => 'RUB',
    'ASSIGNED_BY_ID' => 1, // ID ответственного
    'COMMENTS' => 'Клиент заинтересован в интеграции'
]);

if ($dealId) {
    echo "✓ Сделка создана, ID: $dealId\n\n";
} else {
    echo "✗ Ошибка создания: " . $client->getLastError() . "\n\n";
    exit;
}

// ===== ПРИМЕР 2: Получение сделки =====
echo "=== Получение данных сделки ===\n";

$deal = $dealManager->getDeal($dealId);

if ($deal) {
    echo "ID: {$deal['ID']}\n";
    echo "Название: {$deal['TITLE']}\n";
    echo "Стадия: {$deal['STAGE_ID']}\n";
    echo "Сумма: {$deal['OPPORTUNITY']} {$deal['CURRENCY_ID']}\n\n";
}

// ===== ПРИМЕР 3: Обновление сделки =====
echo "=== Обновление сделки ===\n";

$updated = $dealManager->updateDeal($dealId, [
    'OPPORTUNITY' => 180000,
    'COMMENTS' => 'Согласовали скидку'
]);

if ($updated) {
    echo "✓ Сделка обновлена\n\n";
}

// ===== ПРИМЕР 4: Перевод на другую стадию =====
echo "=== Перевод сделки на стадию 'В работе' ===\n";

$moved = $dealManager->moveToStage($dealId, 'PREPARATION');

if ($moved) {
    echo "✓ Сделка переведена на стадию PREPARATION\n\n";
}

// ===== ПРИМЕР 5: Добавление комментария =====
echo "=== Добавление комментария ===\n";

$commentAdded = $dealManager->addComment($dealId, 'Отправили коммерческое предложение');

if ($commentAdded) {
    echo "✓ Комментарий добавлен\n\n";
}

// ===== ПРИМЕР 6: Получение списка сделок с фильтром =====
echo "=== Получение новых сделок ===\n";

$newDeals = $dealManager->getDeals(
    ['STAGE_ID' => 'NEW'], // Фильтр
    ['DATE_CREATE' => 'DESC'], // Сортировка
    ['ID', 'TITLE', 'OPPORTUNITY', 'DATE_CREATE'] // Поля
);

echo "Найдено новых сделок: " . count($newDeals) . "\n";
foreach ($newDeals as $deal) {
    echo "- [{$deal['ID']}] {$deal['TITLE']} - {$deal['OPPORTUNITY']} руб.\n";
}
echo "\n";

// ===== ПРИМЕР 7: Поиск по диапазону суммы =====
echo "=== Сделки от 100 000 до 500 000 ===\n";

$deals = $dealManager->getDealsByAmountRange(100000, 500000);

echo "Найдено сделок: " . count($deals) . "\n";
foreach ($deals as $deal) {
    echo "- {$deal['TITLE']}: {$deal['OPPORTUNITY']} {$deal['CURRENCY_ID']}\n";
}
echo "\n";

// ===== ПРИМЕР 8: Статистика по сделкам =====
echo "=== Статистика по сделкам ===\n";

$stats = $dealManager->getStatistics();

echo "Всего сделок: {$stats['total_count']}\n";
echo "Общая сумма: " . number_format($stats['total_amount'], 2, '.', ' ') . " руб.\n";
echo "\nПо стадиям:\n";

foreach ($stats['by_stage'] as $stageId => $stageStats) {
    echo "  $stageId: {$stageStats['count']} шт., "
        . number_format($stageStats['amount'], 2, '.', ' ') . " руб.\n";
}
echo "\n";

// ===== ПРИМЕР 9: Массовое создание сделок (batch) =====
echo "=== Массовое создание сделок ===\n";

$newDeals = [
    [
        'TITLE' => 'Тестовая сделка 1',
        'OPPORTUNITY' => 50000,
        'STAGE_ID' => 'NEW'
    ],
    [
        'TITLE' => 'Тестовая сделка 2',
        'OPPORTUNITY' => 75000,
        'STAGE_ID' => 'NEW'
    ],
    [
        'TITLE' => 'Тестовая сделка 3',
        'OPPORTUNITY' => 100000,
        'STAGE_ID' => 'NEW'
    ]
];

$batchResult = $dealManager->createBatch($newDeals);

echo "Создано сделок: " . count($batchResult) . "\n";
foreach ($batchResult as $key => $dealId) {
    echo "- $key: ID $dealId\n";
}
echo "\n";

// ===== ПРИМЕР 10: Удаление сделки (опционально) =====
// Раскомментируйте, если хотите удалить тестовую сделку
/*
echo "=== Удаление сделки ===\n";

$deleted = $dealManager->deleteDeal($dealId);

if ($deleted) {
    echo "✓ Сделка удалена\n\n";
}
*/

echo "=== Примеры выполнены ===\n";
