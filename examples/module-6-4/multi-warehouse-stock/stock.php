<?php
/**
 * Кейс 6.4.2 — Мультисклад: остатки и резервирование
 *
 * Задача: товар есть на 3 складах. Показываем суммарный остаток.
 * При заказе — резервируем на конкретных складах.
 * 1С синхронизирует остатки (пушит дельту, не real-time запрос).
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Catalog\StoreProductTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Sale\Order;

Loader::includeModule('catalog');
Loader::includeModule('sale');

// ============================================================================
// 1. Сервис остатков — единая точка для чтения
// ============================================================================

final class StockService
{
    /**
     * Суммарный доступный остаток по всем складам.
     * Доступный = QUANTITY - RESERVED.
     */
    public function getAvailable(int $productId): int
    {
        $result = StoreProductTable::getList([
            'select' => [
                'TOTAL' => new \Bitrix\Main\DB\SqlExpression('SUM(AMOUNT)'),
                'RESERVED' => new \Bitrix\Main\DB\SqlExpression('SUM(QUANTITY_RESERVED)'),
            ],
            'filter' => ['=PRODUCT_ID' => $productId],
        ])->fetch();

        $total = (int)($result['TOTAL'] ?? 0);
        $reserved = (int)($result['RESERVED'] ?? 0);
        return max(0, $total - $reserved);
    }

    /**
     * Детализация по складам — для страницы товара
     * ("в наличии на складе Москва — 5, на складе СПб — 3").
     */
    public function getByStore(int $productId): array
    {
        $result = StoreProductTable::getList([
            'select' => [
                'STORE_ID',
                'AMOUNT',
                'QUANTITY_RESERVED',
                'STORE.TITLE',
            ],
            'filter' => ['=PRODUCT_ID' => $productId],
        ]);

        $stores = [];
        while ($row = $result->fetch()) {
            $available = max(0, (int)$row['AMOUNT'] - (int)$row['QUANTITY_RESERVED']);
            $stores[] = [
                'store_id' => (int)$row['STORE_ID'],
                'store_name' => $row['CATALOG_STORE_PRODUCT_STORE_TITLE'] ?? "Склад {$row['STORE_ID']}",
                'amount' => (int)$row['AMOUNT'],
                'reserved' => (int)$row['QUANTITY_RESERVED'],
                'available' => $available,
            ];
        }
        return $stores;
    }

    /**
     * Резервирование товара при создании заказа.
     * Распределяем по складам с приоритетом (больше остаток — раньше).
     *
     * КРИТИЧНО: вызывается внутри транзакции сохранения заказа —
     * либо весь резерв, либо ничего.
     */
    public function reserveForOrder(Order $order): void
    {
        $conn = Application::getConnection();
        $conn->startTransaction();

        try {
            foreach ($order->getBasket() as $item) {
                $productId = $item->getProductId();
                $need = (int)$item->getQuantity();

                // Берём склады с доступным остатком, по убыванию
                $stores = StoreProductTable::getList([
                    'select' => ['ID', 'AMOUNT', 'QUANTITY_RESERVED'],
                    'filter' => [
                        '=PRODUCT_ID' => $productId,
                        '>AMOUNT' => new \Bitrix\Main\DB\SqlExpression('QUANTITY_RESERVED'),
                    ],
                    'order' => ['AMOUNT' => 'DESC'],
                ]);

                foreach ($stores as $store) {
                    if ($need <= 0) {
                        break;
                    }

                    $available = (int)$store['AMOUNT'] - (int)$store['QUANTITY_RESERVED'];
                    $take = min($need, $available);

                    // UPDATE ... SET QUANTITY_RESERVED = QUANTITY_RESERVED + ?
                    // — атомарно, без read-modify-write
                    $conn->queryExecute(
                        "UPDATE b_catalog_store_product
                         SET QUANTITY_RESERVED = QUANTITY_RESERVED + ?
                         WHERE ID = ?",
                        [$take, $store['ID']]
                    );

                    $need -= $take;
                }

                if ($need > 0) {
                    // Не хватило остатка — откатываем всё
                    throw new \RuntimeException(
                        "Not enough stock for product {$productId}: need {$item->getQuantity()}"
                    );
                }
            }
            $conn->commitTransaction();
        } catch (\Throwable $e) {
            $conn->rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Снятие резерва при отмене заказа — компенсирующая транзакция.
     */
    public function releaseForOrder(Order $order): void
    {
        // Тут нужна таблица acme_reservations с историей:
        // кто на каком складе сколько зарезервировал.
        // Упрощённо — снимаем пропорционально.
        $conn = Application::getConnection();
        foreach ($order->getBasket() as $item) {
            $productId = $item->getProductId();
            $qty = (int)$item->getQuantity();

            // Находим резервы этого заказа и снимаем
            // (в реальном проекте — таблица связи order_id → reservation)
            $conn->queryExecute(
                "UPDATE b_catalog_store_product
                 SET QUANTITY_RESERVED = GREATEST(0, QUANTITY_RESERVED - ?)
                 WHERE PRODUCT_ID = ?",
                [$qty, $productId]
            );
        }
    }
}

// ============================================================================
// 2. Синхронизация с 1С — пуш дельты, НЕ real-time запрос
// ============================================================================
//
// Anti-pattern: при открытии карточки товара запрашиваем 1С —
// если 1С лежит, сайт лежит.
//
// Senior-вариант: 1С пушит изменения в Bitrix файлом/REST,
// Bitrix держит актуальную копию.

final class OneCStockSync
{
    /**
     * Обработка дельты от 1С — файл/REST endpoint.
     *
     * Формат от 1С:
     *   [
     *     {'sku': 'ABC-123', 'store': 'msk', 'qty': 50},
     *     {'sku': 'ABC-123', 'store': 'spb', 'qty': 30},
     *   ]
     */
    public function applyDelta(array $delta): array
    {
        $conn = Application::getConnection();
        $stats = ['updated' => 0, 'errors' => 0];

        $conn->startTransaction();
        try {
            foreach ($delta as $row) {
                // Находим товар по XML_ID (артикулу)
                $product = ProductTable::getList([
                    'select' => ['ID'],
                    'filter' => ['XML_ID' => $row['sku']],
                ])->fetch();

                if (!$product) {
                    // Товар не найден в каталоге — логируем, но не падаем
                    $stats['errors']++;
                    continue;
                }

                // Находим склад по коду
                $store = \Bitrix\Catalog\StoreTable::getList([
                    'select' => ['ID'],
                    'filter' => ['XML_ID' => $row['store']],
                ])->fetch();

                if (!$store) {
                    $stats['errors']++;
                    continue;
                }

                // Upsert: есть запись — UPDATE, нет — INSERT
                $existing = StoreProductTable::getList([
                    'select' => ['ID'],
                    'filter' => [
                        'PRODUCT_ID' => $product['ID'],
                        'STORE_ID' => $store['ID'],
                    ],
                ])->fetch();

                if ($existing) {
                    StoreProductTable::update($existing['ID'], [
                        'AMOUNT' => $row['qty'],
                    ]);
                } else {
                    StoreProductTable::add([
                        'PRODUCT_ID' => $product['ID'],
                        'STORE_ID' => $store['ID'],
                        'AMOUNT' => $row['qty'],
                    ]);
                }

                // Обновляем суммарный остаток в b_catalog_product
                $this->updateTotalQuantity($product['ID']);
                $stats['updated']++;
            }
            $conn->commitTransaction();
        } catch (\Throwable $e) {
            $conn->rollbackTransaction();
            throw $e;
        }

        // Инвалидация кеша — разово после импорта
        Application::getInstance()->getTaggedCache()->clearByTag('catalog_stock');

        return $stats;
    }

    private function updateTotalQuantity(int $productId): void
    {
        // Суммируем остатки по всем складам
        $conn = Application::getConnection();
        $conn->queryExecute(
            "UPDATE b_catalog_product p
             SET QUANTITY = (
                 SELECT COALESCE(SUM(AMOUNT), 0)
                 FROM b_catalog_store_product sp
                 WHERE sp.PRODUCT_ID = p.ID
             )
             WHERE p.ID = ?",
            [$productId]
        );
    }
}

// ============================================================================
// 3. Интеграция с заказом — резерв при сохранении
// ============================================================================
//
// В OnSaleOrderBeforeSaved (внутри транзакции) — резервируем.
// Если не хватило — Result::addError — заказ не сохранится.

AddEventHandler('sale', 'OnSaleOrderBeforeSaved', function($event) {
    $order = $event->getParameter('ENTITY');
    $result = $event->getParameter('RESULT');

    if (!$event->getParameter('IS_NEW')) {
        return; // только при создании
    }

    try {
        $stock = new StockService();
        $stock->reserveForOrder($order);
    } catch (\Throwable $e) {
        // Откатываем сохранение заказа — недостаток товара
        $result->addError(new \Bitrix\Main\Error(
            'Недостаточно товара на складе: ' . $e->getMessage()
        ));
    }
});

// При отмене — снимаем резерв (компенсация)
AddEventHandler('sale', 'OnSaleOrderCanceled', function($event) {
    $order = $event->getParameter('ENTITY');
    $stock = new StockService();
    $stock->releaseForOrder($order);
});
