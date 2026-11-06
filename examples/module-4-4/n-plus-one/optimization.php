<?php
/**
 * Сравнение подходов к загрузке связанных данных.
 *
 * Показывает три варианта на одном примере:
 * список заказов с информацией о пользователях.
 *
 * Senior-задача: не просто "использовать with",
 * а понимать КОГДА какой вариант оптимален.
 */

namespace Vendor\Shop\Optimization;

use Bitrix\Main\Loader;

Loader::includeModule('sale');

// ============================================================
// ВАРИАНТ 1: N+1 — анти-паттерн (не делай так)
// ============================================================

/**
 * Классическая ошибка: получаем заказы, потом для каждого — пользователя.
 * На 100 заказах = 101 запрос вместо 2.
 */
function getOrders_NPlusOne(): array
{
    $orders = [];
    $res = \CSaleOrder::GetList(
        ['ID' => 'DESC'],
        ['!STATUS_ID' => 'DRAFT'],
        false,
        ['nTopCount' => 100],
        ['ID', 'USER_ID', 'PRICE', 'DATE_INSERT']
    );

    while ($order = $res->Fetch()) {
        // N запросов — по одному на каждый заказ
        $user = \CUser::GetByID($order['USER_ID'])->Fetch();
        $order['USER'] = $user;
        $orders[] = $order;
    }
    return $orders;
}


// ============================================================
// ВАРИАНТ 2: Batch-load — два запроса (хорошо)
// ============================================================

/**
 * Собираем ID пользователей, загружаем всех одним запросом.
 * 2 запроса вместо 101. Идеально для сложных связей.
 */
function getOrders_BatchLoad(): array
{
    $orders = [];
    $userIds = [];

    // Запрос 1: заказы
    $res = \CSaleOrder::GetList(
        ['ID' => 'DESC'],
        ['!STATUS_ID' => 'DRAFT'],
        false,
        ['nTopCount' => 100],
        ['ID', 'USER_ID', 'PRICE', 'DATE_INSERT']
    );

    while ($order = $res->Fetch()) {
        $orders[$order['ID']] = $order;
        $userIds[] = $order['USER_ID'];
    }

    // Запрос 2: все пользователи одним запросом
    $userIds = array_unique($userIds);
    $users = [];
    $userRes = \CUser::GetList(
        'id', 'asc',
        ['ID' => implode('|', $userIds)],
        ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']]
    );
    while ($u = $userRes->Fetch()) {
        $users[$u['ID']] = $u;
    }

    // Соединяем в PHP — быстро, без дополнительных запросов
    foreach ($orders as &$order) {
        $order['USER'] = $users[$order['USER_ID']] ?? null;
    }

    return array_values($orders);
}


// ============================================================
// ВАРИАНТ 3: ORM с runtime reference — один запрос (лучшее)
// ============================================================

/**
 * Один запрос с JOIN. Идеально когда нужны поля связанной таблицы
 * в выборке И для фильтрации/сортировки.
 */
function getOrders_Runtime(): array
{
    return \Bitrix\Sale\OrderTable::getList([
        'filter' => ['!STATUS_ID' => 'DRAFT'],
        'select' => [
            'ID',
            'USER_ID',
            'PRICE',
            'DATE_INSERT',
            // Поля пользователя через JOIN — всё в одном запросе
            'USER_NAME'  => 'USER.NAME',
            'USER_EMAIL' => 'USER.EMAIL',
        ],
        'runtime' => [
            'USER' => [
                'data_type' => \Bitrix\Main\UserTable::class,
                'reference' => ['=this.USER_ID' => 'ref.ID'],
            ],
        ],
        'limit' => 100,
        'order' => ['ID' => 'DESC'],
    ])->fetchAll();
}


// ============================================================
// ВАРИАНТ 4: ORM with() — для коллекций
// ============================================================

/**
 * Новый API — загрузка связанных коллекций.
 * Полезно когда нужны не только скалярные поля,
 * а полноценные объекты связанной таблицы.
 */
function getOrders_With(): array
{
    $result = \Bitrix\Sale\OrderTable::getList([
        'filter' => ['!STATUS_ID' => 'DRAFT'],
        'select' => ['ID', 'USER_ID', 'PRICE'],
        'limit'  => 100,
        'order'  => ['ID' => 'DESC'],
    ]);

    $orders = [];
    while ($order = $result->fetchObject()) {
        // with() загружает связанные сущности пакетно
        $order->fill(['USER']);
        $orders[] = $order;
    }

    // Теперь у каждого заказа есть $order->getUser()
    // Всего 2 запроса: заказы + пользователи (пакетно)

    return $orders;
}


// ============================================================
// БЕНЧМАРК: сравнение подходов
// ============================================================

function benchmark(): void
{
    $methods = [
        'N+1'       => 'getOrders_NPlusOne',
        'BatchLoad' => 'getOrders_BatchLoad',
        'Runtime'   => 'getOrders_Runtime',
    ];

    echo str_repeat('=', 60) . "\n";
    echo "BENCHMARK: Loading 100 orders with users\n";
    echo str_repeat('=', 60) . "\n";

    foreach ($methods as $name => $method) {
        // Прогрев
        $method();

        // Замер
        $start = microtime(true);
        $result = $method();
        $time = microtime(true) - $start;

        // Подсчёт запросов (грубо — через SqlTracker было бы точнее)
        printf(
            "%-12s: %.4fs | %d orders\n",
            $name, $time, count($result)
        );
    }
}

// ============================================================
// КОГДА какой вариант использовать?
// ============================================================

/*
| Ситуация                        | Рекомендуемый вариант |
|---------------------------------|----------------------|
| Нужны поля связанной таблицы    | Runtime (JOIN)        |
| в фильтре или сортировке        |                      |
|---------------------------------|----------------------|
| Связанная таблица сложная       | BatchLoad            |
| (вычисляемые поля, обработчики) |                      |
|---------------------------------|----------------------|
| Коллекции (1:N, N:M)            | with() / BatchLoad   |
|---------------------------------|----------------------|
| Внешний API, не БД              | BatchLoad (2 запроса)|
|---------------------------------|----------------------|
| Просто нужны 2 поля из связи    | Runtime (1 запрос)   |
|---------------------------------|----------------------|
| Всегда                          | НЕ N+1!              |
*/
