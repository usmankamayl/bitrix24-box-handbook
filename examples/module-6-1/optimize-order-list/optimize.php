<?php
/**
 * Задача 6.1.1 — Оптимизация медленного списка заказов
 *
 * Исходная проблема: страница "Мои заказы" грузится 8 сек на 5000 заказов.
 * Показываем по 20 на страницу.
 *
 * ПОКАЗЫВАЕМ ПОЭТАПНО: как senior диагностирует и чинит.
 */

use Bitrix\Main\DB\SqlTracker;
use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Main\Type\DateTime;

Loader::includeModule('sale');

// ============================================================================
// ЭТАП 0: Диагностика — включаем SqlTracker и смотрим, что реально происходит
// ============================================================================

$tracker = new SqlTracker();
$tracker->start();

// ... (тут выполняется текущий код страницы)

$tracker->stop();
echo "Запросов: " . $tracker->getCounter() . "\n";
echo "Время: " . $tracker->getTime() . " сек\n";

// Типичный вывод на больной странице:
//   Запросов: 147          <-- ВОТ проблема (N+1)
//   Время: 7.83 сек
//
// На здоровой странице:
//   Запросов: 3            <-- список + 1 batchLoad на свойства + count
//   Время: 0.12 сек


// ============================================================================
// ВАРИАНТ A — Как было (плохо): N+1
// ============================================================================

function getOrdersBad(int $userId): array
{
    $result = Order::getList([
        'select' => ['ID', 'PRICE', 'DATE_INSERT'],
        'filter' => ['USER_ID' => $userId],
        'limit' => 20,
        'offset' => 0,
        'order' => ['DATE_INSERT' => 'DESC'],
    ]);

    $orders = [];
    while ($row = $result->fetch()) {
        // Каждый load() — это запросы:
        //   - сам заказ
        //   - корзина + товары
        //   - свойства
        //   - оплаты
        //   - отгрузки
        // Т.е. 5+ запросов на каждый заказ.
        $order = Order::load($row['ID']);

        $orders[] = [
            'ID' => $row['ID'],
            'PRICE' => $row['PRICE'],
            'DATE' => $row['DATE_INSERT'],
            'ITEMS_COUNT' => $order->getBasket()->count(),
            'STATUS_NAME' => $order->getField('STATUS_ID'), // ещё запрос
            'CITY' => $order->getPropertyCollection()
                ->getItemByOrderPropertyCode('CITY')
                ->getValue(), // NPE если нет свойства
        ];
    }
    return $orders; // 20 заказов × 6 запросов = 120+ запросов
}


// ============================================================================
// ВАРИАНТ B — Оптимально: один запрос на список + batch для свойств
// ============================================================================

function getOrdersGood(int $userId): array
{
    // Шаг 1: плоский список без load()
    $result = Order::getList([
        'select' => ['ID', 'PRICE', 'DATE_INSERT', 'STATUS_ID'],
        'filter' => ['USER_ID' => $userId],
        'limit' => 20,
        'order' => ['DATE_INSERT' => 'DESC'],
    ]);

    $orders = [];
    $orderIds = [];
    while ($row = $result->fetch()) {
        $orders[$row['ID']] = [
            'ID' => $row['ID'],
            'PRICE' => $row['PRICE'],
            'DATE' => $row['DATE_INSERT'],
            'STATUS_NAME' => $row['STATUS_ID'],
            'ITEMS_COUNT' => 0,
            'CITY' => '',
        ];
        $orderIds[] = $row['ID'];
    }

    if (empty($orderIds)) {
        return [];
    }

    // Шаг 2: batch-загрузка количества товаров в заказах одним запросом
    $basketRes = \Bitrix\Sale\Internals\BasketTable::getList([
        'select' => ['ORDER_ID', 'CNT' => new \Bitrix\Main\DB\SqlExpression('COUNT(*)')],
        'filter' => ['=ORDER_ID' => $orderIds],
        'group' => ['ORDER_ID'],
    ]);
    while ($row = $basketRes->fetch()) {
        if (isset($orders[$row['ORDER_ID']])) {
            $orders[$row['ORDER_ID']]['ITEMS_COUNT'] = (int)$row['CNT'];
        }
    }

    // Шаг 3: batch-загрузка свойства CITY одним запросом
    $propsRes = \Bitrix\Sale\Internals\OrderPropsValueTable::getList([
        'select' => ['ORDER_ID', 'VALUE'],
        'filter' => [
            '=ORDER_ID' => $orderIds,
            'ORDER_PROPS.CODE' => 'CITY', // через связь к b_sale_order_props
        ],
    ]);
    while ($row = $propsRes->fetch()) {
        if (isset($orders[$row['ORDER_ID']])) {
            $orders[$row['ORDER_ID']]['CITY'] = $row['VALUE'];
        }
    }

    return array_values($orders); // всего 3 запроса
}


// ============================================================================
// ВАРИАНТ C — Курсорная пагинация (когда OFFSET большой)
// ============================================================================
//
// LIMIT 20 OFFSET 40000 — сканирует 40020 строк чтобы выкинуть 40000.
// Проблема: "страница 2000" на большом архиве — та же боль.
// Решение: вместо "номера страницы" возвращаем "ID последнего элемента".

function getOrdersCursor(int $userId, ?int $lastId = null, int $limit = 20): array
{
    $filter = ['USER_ID' => $userId];
    if ($lastId !== null) {
        // "<" потому что ORDER BY ID DESC — следующая страница = ID меньше
        $filter['<ID'] = $lastId;
    }

    $result = Order::getList([
        'select' => ['ID', 'PRICE', 'DATE_INSERT', 'STATUS_ID'],
        'filter' => $filter,
        'limit' => $limit + 1, // +1 чтобы понять, есть ли следующая страница
        'order' => ['ID' => 'DESC'],
    ]);

    $items = [];
    while ($row = $result->fetch()) {
        $items[] = $row;
    }

    $hasMore = count($items) > $limit;
    if ($hasMore) {
        array_pop($items); // убираем лишний
    }

    // Клиент запоминает lastId = ID последнего элемента, на следующий запрос передаёт его.
    return [
        'items' => $items,
        'next_cursor' => $hasMore ? end($items)['ID'] : null,
    ];
}


// ============================================================================
// ВАРИАНТ D — Кеш (применяется ТОЛЬКО после оптимизации запросов!)
// ============================================================================

function getOrdersCached(int $userId, int $page): array
{
    $cache = \Bitrix\Main\Application::getInstance()->getCache();
    $cacheKey = "order_list_u{$userId}_p{$page}";
    $cacheTtl = 300; // 5 минут — не год

    // ВАЖНО: тегируем кеш, чтобы инвалидировать при изменении заказов.
    // Тег 'sale_order_u{userId}' добавляем через событие OnSaleOrderSaved.
    if ($cache->initCache($cacheTtl, $cacheKey, 'sale_orders', 'sale_order_u' . $userId)) {
        return $cache->getVars();
    }

    if ($cache->startDataCache()) {
        $cache->startTagCache('sale_orders');
        $cache->registerTag('sale_order_u' . $userId);
        $cache->registerTag('sale_order_all'); // общий тег при любом изменении

        $data = getOrdersGood($userId);

        $cache->endTagCache();
        $cache->endDataCache($data);
        return $data;
    }

    return getOrdersGood($userId);
}

// Регистрация инвалидации — в init.php:
// AddEventHandler('sale', 'OnSaleOrderSaved', function($event) {
//     \Bitrix\Main\Application::getInstance()->getTaggedCache()->clearByTag('sale_order_all');
// });
