<?php
/**
 * Примеры работы со статистикой заказов
 *
 * Демонстрирует:
 * - Агрегатные функции (COUNT, SUM, AVG, MIN, MAX)
 * - Группировку данных (GROUP BY)
 * - Runtime-поля для вычислений
 * - Построение сложных аналитических запросов
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

use Custom\Orders\OrderTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Entity;

// ============================================================
// Подготовка: создание тестовых данных
// ============================================================

echo "<h2>Создание тестовых заказов</h2>";

$testOrders = [
    ['ORDER_NUMBER' => 'ORD-2024-001', 'USER_ID' => 1, 'STATUS' => 'DELIVERED', 'TOTAL_AMOUNT' => 5000, 'DISCOUNT_AMOUNT' => 500, 'FINAL_AMOUNT' => 4500, 'IS_PAID' => 'Y', 'DELIVERY_CITY' => 'Москва', 'DELIVERY_REGION' => 'Московская область', 'SOURCE' => 'website', 'PAYMENT_METHOD' => 'card'],
    ['ORDER_NUMBER' => 'ORD-2024-002', 'USER_ID' => 2, 'STATUS' => 'DELIVERED', 'TOTAL_AMOUNT' => 3000, 'DISCOUNT_AMOUNT' => 0, 'FINAL_AMOUNT' => 3000, 'IS_PAID' => 'Y', 'DELIVERY_CITY' => 'Санкт-Петербург', 'DELIVERY_REGION' => 'Ленинградская область', 'SOURCE' => 'mobile_app', 'PAYMENT_METHOD' => 'card'],
    ['ORDER_NUMBER' => 'ORD-2024-003', 'USER_ID' => 1, 'STATUS' => 'PROCESSING', 'TOTAL_AMOUNT' => 7500, 'DISCOUNT_AMOUNT' => 1000, 'FINAL_AMOUNT' => 6500, 'IS_PAID' => 'Y', 'DELIVERY_CITY' => 'Москва', 'DELIVERY_REGION' => 'Московская область', 'SOURCE' => 'website', 'PAYMENT_METHOD' => 'card'],
    ['ORDER_NUMBER' => 'ORD-2024-004', 'USER_ID' => 3, 'STATUS' => 'NEW', 'TOTAL_AMOUNT' => 1500, 'DISCOUNT_AMOUNT' => 0, 'FINAL_AMOUNT' => 1500, 'IS_PAID' => 'N', 'DELIVERY_CITY' => 'Новосибирск', 'DELIVERY_REGION' => 'Новосибирская область', 'SOURCE' => 'marketplace', 'PAYMENT_METHOD' => 'cash'],
    ['ORDER_NUMBER' => 'ORD-2024-005', 'USER_ID' => 2, 'STATUS' => 'CANCELLED', 'TOTAL_AMOUNT' => 2000, 'DISCOUNT_AMOUNT' => 0, 'FINAL_AMOUNT' => 2000, 'IS_PAID' => 'N', 'DELIVERY_CITY' => 'Санкт-Петербург', 'DELIVERY_REGION' => 'Ленинградская область', 'SOURCE' => 'mobile_app', 'PAYMENT_METHOD' => 'card'],
    ['ORDER_NUMBER' => 'ORD-2024-006', 'USER_ID' => 4, 'STATUS' => 'DELIVERED', 'TOTAL_AMOUNT' => 12000, 'DISCOUNT_AMOUNT' => 2000, 'FINAL_AMOUNT' => 10000, 'IS_PAID' => 'Y', 'DELIVERY_CITY' => 'Екатеринбург', 'DELIVERY_REGION' => 'Свердловская область', 'SOURCE' => 'website', 'PAYMENT_METHOD' => 'card'],
    ['ORDER_NUMBER' => 'ORD-2024-007', 'USER_ID' => 5, 'STATUS' => 'SHIPPED', 'TOTAL_AMOUNT' => 4500, 'DISCOUNT_AMOUNT' => 500, 'FINAL_AMOUNT' => 4000, 'IS_PAID' => 'Y', 'DELIVERY_CITY' => 'Казань', 'DELIVERY_REGION' => 'Татарстан', 'SOURCE' => 'website', 'PAYMENT_METHOD' => 'card'],
    ['ORDER_NUMBER' => 'ORD-2024-008', 'USER_ID' => 1, 'STATUS' => 'DELIVERED', 'TOTAL_AMOUNT' => 8000, 'DISCOUNT_AMOUNT' => 800, 'FINAL_AMOUNT' => 7200, 'IS_PAID' => 'Y', 'DELIVERY_CITY' => 'Москва', 'DELIVERY_REGION' => 'Московская область', 'SOURCE' => 'mobile_app', 'PAYMENT_METHOD' => 'card'],
    ['ORDER_NUMBER' => 'ORD-2024-009', 'USER_ID' => 3, 'STATUS' => 'PROCESSING', 'TOTAL_AMOUNT' => 3500, 'DISCOUNT_AMOUNT' => 0, 'FINAL_AMOUNT' => 3500, 'IS_PAID' => 'N', 'DELIVERY_CITY' => 'Новосибирск', 'DELIVERY_REGION' => 'Новосибирская область', 'SOURCE' => 'marketplace', 'PAYMENT_METHOD' => 'cash'],
    ['ORDER_NUMBER' => 'ORD-2024-010', 'USER_ID' => 6, 'STATUS' => 'NEW', 'TOTAL_AMOUNT' => 2500, 'DISCOUNT_AMOUNT' => 250, 'FINAL_AMOUNT' => 2250, 'IS_PAID' => 'N', 'DELIVERY_CITY' => 'Краснодар', 'DELIVERY_REGION' => 'Краснодарский край', 'SOURCE' => 'website', 'PAYMENT_METHOD' => 'card'],
];

foreach ($testOrders as $order) {
    $result = OrderTable::add($order);
    if ($result->isSuccess()) {
        echo "✅ Заказ {$order['ORDER_NUMBER']} создан<br>";
    }
}

// ============================================================
// 1. Общая статистика продаж
// ============================================================

echo "<h2>1. Общая статистика продаж</h2>";

$dateFrom = new DateTime('2024-01-01 00:00:00');
$dateTo = new DateTime('2024-12-31 23:59:59');

$stats = OrderTable::getSalesStatistics($dateFrom, $dateTo);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Метрика</th><th>Значение</th></tr>";
echo "<tr><td>Всего заказов</td><td>{$stats['TOTAL_COUNT']}</td></tr>";
echo "<tr><td>Общая выручка</td><td>" . number_format($stats['TOTAL_REVENUE'], 2, '.', ' ') . " ₽</td></tr>";
echo "<tr><td>Средний чек</td><td>" . number_format($stats['AVG_ORDER'], 2, '.', ' ') . " ₽</td></tr>";
echo "<tr><td>Минимальный заказ</td><td>" . number_format($stats['MIN_ORDER'], 2, '.', ' ') . " ₽</td></tr>";
echo "<tr><td>Максимальный заказ</td><td>" . number_format($stats['MAX_ORDER'], 2, '.', ' ') . " ₽</td></tr>";
echo "<tr><td>Оплаченных заказов</td><td>{$stats['PAID_COUNT']}</td></tr>";
echo "</table>";

// ============================================================
// 2. Статистика по статусам
// ============================================================

echo "<h2>2. Распределение заказов по статусам</h2>";

$statusStats = OrderTable::getStatusStatistics();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Статус</th><th>Количество</th><th>Сумма</th></tr>";

$statusNames = [
    'NEW' => 'Новый',
    'PROCESSING' => 'В обработке',
    'SHIPPED' => 'Отправлен',
    'DELIVERED' => 'Доставлен',
    'CANCELLED' => 'Отменен',
];

foreach ($statusStats as $stat) {
    $statusName = $statusNames[$stat['STATUS']] ?? $stat['STATUS'];
    echo "<tr>";
    echo "<td>{$statusName}</td>";
    echo "<td>{$stat['COUNT']}</td>";
    echo "<td>" . number_format($stat['TOTAL_AMOUNT'], 2, '.', ' ') . " ₽</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================================
// 3. Топ покупателей
// ============================================================

echo "<h2>3. Топ-5 покупателей</h2>";

$topBuyers = OrderTable::getTopBuyers(5);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID пользователя</th><th>Заказов</th><th>Потрачено</th><th>Средний чек</th></tr>";

foreach ($topBuyers as $buyer) {
    echo "<tr>";
    echo "<td>Пользователь #{$buyer['USER_ID']}</td>";
    echo "<td>{$buyer['ORDERS_COUNT']}</td>";
    echo "<td>" . number_format($buyer['TOTAL_SPENT'], 2, '.', ' ') . " ₽</td>";
    echo "<td>" . number_format($buyer['AVG_ORDER'], 2, '.', ' ') . " ₽</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================================
// 4. Статистика по городам
// ============================================================

echo "<h2>4. Статистика по городам доставки</h2>";

$cityStats = OrderTable::getCityStatistics();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Город</th><th>Заказов</th><th>Выручка</th><th>Средний чек</th></tr>";

foreach ($cityStats as $city) {
    echo "<tr>";
    echo "<td>{$city['DELIVERY_CITY']}</td>";
    echo "<td>{$city['ORDERS_COUNT']}</td>";
    echo "<td>" . number_format($city['TOTAL_REVENUE'], 2, '.', ' ') . " ₽</td>";
    echo "<td>" . number_format($city['AVG_ORDER'], 2, '.', ' ') . " ₽</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================================
// 5. Конверсия оплаты по источникам
// ============================================================

echo "<h2>5. Конверсия оплаты по источникам</h2>";

$conversionStats = OrderTable::getPaymentConversionBySource();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Источник</th><th>Всего заказов</th><th>Оплаченных</th><th>Конверсия</th></tr>";

$sourceNames = [
    'website' => 'Сайт',
    'mobile_app' => 'Мобильное приложение',
    'marketplace' => 'Маркетплейс',
];

foreach ($conversionStats as $stat) {
    $sourceName = $sourceNames[$stat['SOURCE']] ?? $stat['SOURCE'];
    echo "<tr>";
    echo "<td>{$sourceName}</td>";
    echo "<td>{$stat['TOTAL_ORDERS']}</td>";
    echo "<td>{$stat['PAID_ORDERS']}</td>";
    echo "<td>{$stat['CONVERSION_RATE']}%</td>";
    echo "</tr>";
}
echo "</table>";

// ============================================================
// 6. Дополнительные примеры
// ============================================================

echo "<h2>6. Дополнительные аналитические запросы</h2>";

// Средняя скидка по заказам
$result = OrderTable::getList([
    'select' => ['AVG_DISCOUNT', 'TOTAL_DISCOUNT'],
    'runtime' => [
        new Entity\ExpressionField('AVG_DISCOUNT', 'AVG(%s)', ['DISCOUNT_AMOUNT']),
        new Entity\ExpressionField('TOTAL_DISCOUNT', 'SUM(%s)', ['DISCOUNT_AMOUNT']),
    ],
]);

$discountStats = $result->fetch();
echo "<p><strong>Средняя скидка:</strong> " . number_format($discountStats['AVG_DISCOUNT'], 2, '.', ' ') . " ₽</p>";
echo "<p><strong>Всего скидок:</strong> " . number_format($discountStats['TOTAL_DISCOUNT'], 2, '.', ' ') . " ₽</p>";

// Статистика по способам оплаты
echo "<h3>Статистика по способам оплаты:</h3>";
$result = OrderTable::getList([
    'select' => ['PAYMENT_METHOD', 'COUNT', 'TOTAL'],
    'runtime' => [
        new Entity\ExpressionField('COUNT', 'COUNT(*)'),
        new Entity\ExpressionField('TOTAL', 'SUM(%s)', ['FINAL_AMOUNT']),
    ],
    'group' => ['PAYMENT_METHOD'],
    'order' => ['COUNT' => 'DESC'],
]);

echo "<ul>";
while ($row = $result->fetch()) {
    $method = $row['PAYMENT_METHOD'] ?: 'Не указан';
    echo "<li><strong>{$method}:</strong> {$row['COUNT']} заказов на сумму " . number_format($row['TOTAL'], 2, '.', ' ') . " ₽</li>";
}
echo "</ul>";

// Количество заказов по регионам
echo "<h3>Топ-3 региона:</h3>";
$result = OrderTable::getList([
    'select' => ['DELIVERY_REGION', 'COUNT'],
    'filter' => ['!DELIVERY_REGION' => false],
    'runtime' => [
        new Entity\ExpressionField('COUNT', 'COUNT(*)'),
    ],
    'group' => ['DELIVERY_REGION'],
    'order' => ['COUNT' => 'DESC'],
    'limit' => 3,
]);

echo "<ol>";
while ($row = $result->fetch()) {
    echo "<li>{$row['DELIVERY_REGION']} — {$row['COUNT']} заказов</li>";
}
echo "</ol>";

// Процент отмененных заказов
$totalOrders = OrderTable::getCount();
$cancelledOrders = OrderTable::getCount(['=STATUS' => 'CANCELLED']);
$cancelRate = round(($cancelledOrders / $totalOrders) * 100, 2);

echo "<p><strong>Процент отмененных заказов:</strong> {$cancelRate}% ({$cancelledOrders} из {$totalOrders})</p>";

// Средний срок доставки (для доставленных заказов)
echo "<h3>Анализ времени доставки:</h3>";
$deliveredOrders = OrderTable::getList([
    'select' => ['ID', 'DATE_CREATE', 'DATE_DELIVERED', 'DELIVERY_TIME'],
    'filter' => [
        '=STATUS' => 'DELIVERED',
        '!DATE_DELIVERED' => false,
    ],
    'runtime' => [
        new Entity\ExpressionField(
            'DELIVERY_TIME',
            'DATEDIFF(%s, %s)',
            ['DATE_DELIVERED', 'DATE_CREATE']
        ),
    ],
]);

$deliveryTimes = [];
while ($order = $deliveredOrders->fetch()) {
    if ($order['DELIVERY_TIME'] !== null) {
        $deliveryTimes[] = $order['DELIVERY_TIME'];
    }
}

if (!empty($deliveryTimes)) {
    $avgDeliveryTime = round(array_sum($deliveryTimes) / count($deliveryTimes), 1);
    echo "<p><strong>Средний срок доставки:</strong> {$avgDeliveryTime} дней</p>";
    echo "<p><strong>Минимальный срок:</strong> " . min($deliveryTimes) . " дней</p>";
    echo "<p><strong>Максимальный срок:</strong> " . max($deliveryTimes) . " дней</p>";
}

// Динамика заказов по месяцам (если есть данные за несколько месяцев)
echo "<h3>Распределение заказов по месяцам:</h3>";
$result = OrderTable::getList([
    'select' => ['MONTH', 'COUNT', 'REVENUE'],
    'runtime' => [
        new Entity\ExpressionField('MONTH', 'DATE_FORMAT(%s, "%%Y-%%m")', ['DATE_CREATE']),
        new Entity\ExpressionField('COUNT', 'COUNT(*)'),
        new Entity\ExpressionField('REVENUE', 'SUM(%s)', ['FINAL_AMOUNT']),
    ],
    'group' => ['MONTH'],
    'order' => ['MONTH' => 'ASC'],
]);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Месяц</th><th>Заказов</th><th>Выручка</th></tr>";
while ($row = $result->fetch()) {
    echo "<tr>";
    echo "<td>{$row['MONTH']}</td>";
    echo "<td>{$row['COUNT']}</td>";
    echo "<td>" . number_format($row['REVENUE'], 2, '.', ' ') . " ₽</td>";
    echo "</tr>";
}
echo "</table>";

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
