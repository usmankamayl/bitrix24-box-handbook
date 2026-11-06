<?php
/**
 * Задача 6.1.3 — "Найди баг" — 5 классических ошибок
 *
 * Формат: показываем плохой код, потом — исправленный + объяснение.
 * На собеседовании такие задачи проверяют внимательность и понимание последствий.
 */

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;

Loader::includeModule('sale');

// ============================================================================
// БАГ 1: N+1 в цикле
// ============================================================================
// ❌ ПЛОХО

function printOrdersBad(int $userId): void
{
    $result = Order::getList([
        'select' => ['ID'],
        'filter' => ['USER_ID' => $userId],
        'limit' => 100,
    ]);
    while ($row = $result->fetch()) {
        $order = Order::load($row['ID']);              // ЗАПРОС
        echo $order->getBasket()->count() . "\n";      // ЕЩЁ ЗАПРОС
        echo $order->getField('STATUS_ID') . "\n";     // ЕЩЁ ЗАПРОС
    }
    // 100 заказов × 5+ запросов каждый = 500+ запросов
}

// ✅ ХОРОШО

function printOrdersGood(int $userId): void
{
    $result = Order::getList([
        'select' => ['ID', 'STATUS_ID'],
        'filter' => ['USER_ID' => $userId],
        'limit' => 100,
    ]);
    $orderIds = [];
    $data = [];
    while ($row = $result->fetch()) {
        $orderIds[] = $row['ID'];
        $data[$row['ID']] = $row;
    }

    // Одним запросом — количество товаров
    $basketRes = \Bitrix\Sale\Internals\BasketTable::getList([
        'select' => ['ORDER_ID', 'CNT' => new \Bitrix\Main\DB\SqlExpression('COUNT(*)')],
        'filter' => ['=ORDER_ID' => $orderIds],
        'group' => ['ORDER_ID'],
    ]);
    while ($row = $basketRes->fetch()) {
        $data[$row['ORDER_ID']]['ITEMS_COUNT'] = (int)$row['CNT'];
    }

    foreach ($data as $order) {
        echo $order['ITEMS_COUNT'] ?? 0;
        echo $order['STATUS_ID'] . "\n";
    }
    // 2 запроса вместо 500
}

// ============================================================================
// БАГ 2: save() внутри события → рекурсия / дедлок
// ============================================================================
// ❌ ПЛОХО

AddEventHandler('sale', 'OnSaleOrderSaved', function($event) {
    /** @var Order $order */
    $order = $event->getParameter('ENTITY');

    $order->setField('COMMENTS', 'Обработано ' . date('c'));
    $order->save();  // БАГ! Вызывает OnSaleOrderSaved снова → бесконечный цикл / дедлок
});

// ✅ ХОРОШО

AddEventHandler('sale', 'OnSaleOrderSaved', function($event) {
    /** @var Order $order */
    $order = $event->getParameter('ENTITY');

    if (!$event->getParameter('IS_NEW')) {
        return; // только на создание
    }

    // Вариант A: изменения ДО сохранения — через OnSaleOrderBeforeSaved
    // (там изменения применяются без рекурсии)

    // Вариант B: тяжёлая работа — через агента (отложенно, вне транзакции)
    \CAgent::AddAgent(
        '\\Acme\\OrderMail::sendConfirmation(' . $order->getId() . ');',
        'acme',
        'N',
        60
    );
});

// ============================================================================
// БАГ 3: IDOR — любой может прочитать чужой заказ
// ============================================================================
// ❌ ПЛОХО

// /api/order.php?id=123
$orderId = (int)$_REQUEST['id'];
$order = Order::load($orderId);
if (!$order) {
    die('not found');
}
echo json_encode([
    'price' => $order->getPrice(),
    'user_id' => $order->getUserId(),
]);
// Любой пользователь подставит чужой ID и увидит заказ.

// ✅ ХОРОШО

$orderId = (int)$_REQUEST['id'];
$order = Order::load($orderId);

if (!$order) {
    http_response_code(404);
    die('{"error":"not found"}');
}

// ПРОВЕРКА ВЛАДЕЛЬЦА — обязательно до вывода данных
if ($order->getUserId() !== $USER->GetID()) {
    http_response_code(403);
    die('{"error":"forbidden"}');
}

// + CSRF для изменяющих операций:
if (!check_bitrix_sessid()) {
    http_response_code(403);
    die('{"error":"sessid"}');
}

echo json_encode([
    'price' => $order->getPrice(),
    'user_id' => $order->getUserId(),
]);

// ============================================================================
// БАГ 4: SQL-инъекция в legacy-коде
// ============================================================================
// ❌ ПЛОХО

function getOrderLegacy($id) {
    global $DB;
    $id = $_GET['id']; // НЕ экранируем!
    $rs = $DB->Query("SELECT * FROM b_sale_order WHERE ID = " . $id);
    // Злоумышленник: ?id=1 UNION SELECT password FROM b_user--
    return $rs->Fetch();
}

// ✅ ХОРОШО — D7 ORM (всегда предпочтительнее)

function getOrderSafe(int $id): ?array {
    $row = Order::getList([
        'select' => ['ID', 'PRICE', 'STATUS_ID'],
        'filter' => ['ID' => $id],
    ])->fetch();
    return $row ?: null;
}

// ✅ ХОРОШО — legacy с экранированием (если без ORM никак)

function getOrderLegacySafe($id) {
    global $DB;
    $id = (int)$id; // приведение типа — минимум для числа
    // Или: $DB->ForSql($id) для строк
    $rs = $DB->Query("SELECT * FROM b_sale_order WHERE ID = " . $id);
    return $rs->Fetch();
}

// ============================================================================
// БАГ 5: Кеш без тегов → устаревшие данные навсегда
// ============================================================================
// ❌ ПЛОХО

function getOrderCached(int $orderId): array {
    $cache = \Bitrix\Main\Application::getInstance()->getCache();
    $key = "order_{$orderId}";

    if ($cache->initCache(3600, $key, 'orders')) {
        return $cache->getVars();
    }
    if ($cache->startDataCache()) {
        $order = Order::load($orderId);
        $data = [
            'price' => $order->getPrice(),
            'status' => $order->getField('STATUS_ID'),
        ];
        $cache->endDataCache($data);
        return $data;
    }
    // Заказ обновился — кеш на час устарел. Пользователь видит старые данные.
}

// ✅ ХОРОШО — тегированный кеш с инвалидацией

function getOrderCachedSafe(int $orderId): array {
    $cache = \Bitrix\Main\Application::getInstance()->getCache();
    $key = "order_{$orderId}";

    if ($cache->initCache(3600, $key, 'orders')) {
        return $cache->getVars();
    }
    if ($cache->startDataCache()) {
        $cache->startTagCache('orders');
        $cache->registerTag('order_' . $orderId);          // инвалидация по конкретному заказу
        $cache->registerTag('orders_all');                  // общий тег при любом изменении
        $order = Order::load($orderId);
        $data = [
            'price' => $order->getPrice(),
            'status' => $order->getField('STATUS_ID'),
        ];
        $cache->endTagCache();
        $cache->endDataCache($data);
        return $data;
    }
}

// Где-то в init.php — инвалидируем при сохранении:
AddEventHandler('sale', 'OnSaleOrderSaved', function($event) {
    $order = $event->getParameter('ENTITY');
    $tagged = \Bitrix\Main\Application::getInstance()->getTaggedCache();
    $tagged->clearByTag('order_' . $order->getId());
    $tagged->clearByTag('orders_all');
});
