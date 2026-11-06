<?php
/**
 * Задача 6.1.2 — Типовые SQL-задачи через D7 ORM
 *
 * Пять "классических" задач, которые спрашивают на собеседовании.
 * Показываем: префиксы filter, RuntimeEntity, агрегации, связи.
 */

use Bitrix\Main\Loader;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity;
use Bitrix\Sale\Internals\OrderTable;
use Bitrix\Sale\Internals\BasketTable;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Main\Type\DateTime;

Loader::includeModule('sale');

// ============================================================================
// ЗАДАЧА 1: Заказы за последние 30 дней со статусом N и суммой > 5000
// ============================================================================
// Проверяет: знание префиксов фильтра, DateTime, select

$orders = OrderTable::getList([
    'select' => ['ID', 'PRICE', 'DATE_INSERT', 'STATUS_ID'],
    'filter' => [
        'STATUS_ID' => 'N',
        '>=DATE_INSERT' => new DateTime('-30 days'),
        '>PRICE' => 5000,
    ],
    'order' => ['DATE_INSERT' => 'DESC'],
    'limit' => 50,
])->fetchAll();


// ============================================================================
// ЗАДАЧА 2: Топ-10 товаров по количеству продаж (GROUP BY + SUM)
// ============================================================================
// Проверяет: SqlExpression для агрегатов, group, order по агрегату

$topProducts = BasketTable::getList([
    'select' => [
        'PRODUCT_ID',
        'NAME',
        'TOTAL_QTY' => new SqlExpression('SUM(QUANTITY)'),
        'TOTAL_SUM' => new SqlExpression('SUM(PRICE * QUANTITY)'),
    ],
    'filter' => [
        // только из оплаченных заказов
        'ORDER.PAYED' => 'Y',
        '>=ORDER.DATE_INSERT' => new DateTime('-90 days'),
    ],
    'group' => ['PRODUCT_ID', 'NAME'],
    'order' => ['TOTAL_QTY' => 'DESC'],
    'limit' => 10,
])->fetchAll();


// ============================================================================
// ЗАДАЧА 3: Заказы, где есть свойство CITY = 'Москва' (связь через RuntimeEntity)
// ============================================================================
// Проверяет: join по условию, связь 1:N, фильтрация по свойству

$moscowOrders = OrderTable::getList([
    'select' => ['ID', 'PRICE', 'USER_ID'],
    'filter' => [
        // фильтруем через связь к значениям свойств
        'PROP_VALUE.ORDER_PROPS.CODE' => 'CITY',
        'PROP_VALUE.VALUE' => 'Москва',
    ],
    'runtime' => [
        // Join к значениям свойств
        new Entity\ReferenceField(
            'PROP_VALUE',
            OrderPropsValueTable::class,
            ['=this.ID' => 'ref.ORDER_ID'],
            ['join_type' => 'INNER']
        ),
    ],
    'order' => ['ID' => 'DESC'],
    'limit' => 50,
])->fetchAll();


// ============================================================================
// ЗАДАЧА 4: Сумма продаж по месяцам за год (DATE_FORMAT + GROUP BY)
// ============================================================================
// Проверяет: агрегация по вычисляемому полю, формат даты в группировке

$salesByMonth = OrderTable::getList([
    'select' => [
        'MONTH' => new SqlExpression("DATE_FORMAT(DATE_INSERT, '%Y-%m')"),
        'ORDERS_COUNT' => new SqlExpression('COUNT(*)'),
        'TOTAL_SUM' => new SqlExpression('SUM(PRICE)'),
        'AVG_CHECK' => new SqlExpression('AVG(PRICE)'),
    ],
    'filter' => [
        'PAYED' => 'Y',
        '>=DATE_INSERT' => new DateTime('-1 year'),
    ],
    'group' => ['MONTH'],
    'order' => ['MONTH' => 'ASC'],
])->fetchAll();


// ============================================================================
// ЗАДАЧА 5: Менеджеры и их заказы (self-join через связь RESPONSIBLE_ID)
// ============================================================================
// Проверяет: фильтр по нескольким условиям, distinct, IN-подзапрос

// Найти менеджеров, у которых более 10 выполненных заказов за месяц
$activeManagers = OrderTable::getList([
    'select' => [
        'RESPONSIBLE_ID',
        'ORDERS_COUNT' => new SqlExpression('COUNT(*)'),
    ],
    'filter' => [
        'STATUS_ID' => 'F', // выполнен
        '>=DATE_INSERT' => new DateTime('-30 days'),
        '!RESPONSIBLE_ID' => false, // не null
    ],
    'group' => ['RESPONSIBLE_ID'],
    'having' => [new SqlExpression('COUNT(*) > 10')],
    'order' => ['ORDERS_COUNT' => 'DESC'],
])->fetchAll();

// Или: найти заказы этих менеджеров
$managerIds = array_column($activeManagers, 'RESPONSIBLE_ID');
$orders = OrderTable::getList([
    'select' => ['ID', 'RESPONSIBLE_ID', 'PRICE'],
    'filter' => [
        '=@RESPONSIBLE_ID' => $managerIds, // IN
        'STATUS_ID' => 'F',
    ],
])->fetchAll();


// ============================================================================
// БОНУС: сложная задача — "заказы с товаром X, но без товара Y"
// ============================================================================
// Проверяет: два join к одной таблице через разные runtime-поля,
//            фильтрация "есть в одном — нет в другом"

$ordersWithXWithoutY = OrderTable::getList([
    'select' => ['ID'],
    'filter' => [
        'HAS_X.PRODUCT_ID' => 123,   // товар X
        'HAS_Y.PRODUCT_ID' => null,  // а товара Y нет
    ],
    'runtime' => [
        // INNER JOIN — должна быть позиция с X
        new Entity\ReferenceField(
            'HAS_X',
            BasketTable::class,
            [
                '=this.ID' => 'ref.ORDER_ID',
                '=ref.PRODUCT_ID' => new SqlExpression('123'),
            ],
            ['join_type' => 'INNER']
        ),
        // LEFT JOIN — позиция с Y не должна существовать
        new Entity\ReferenceField(
            'HAS_Y',
            BasketTable::class,
            [
                '=this.ID' => 'ref.ORDER_ID',
                '=ref.PRODUCT_ID' => new SqlExpression('456'),
            ],
            ['join_type' => 'LEFT']
        ),
    ],
    'group' => ['ID'],
])->fetchAll();
