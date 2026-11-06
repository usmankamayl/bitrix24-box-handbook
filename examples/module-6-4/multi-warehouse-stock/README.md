# Кейс 2: Мультисклад — остатки и резервирование

## Что показывает

Товар на нескольких складах + заказ резервирует остаток + синхронизация с 1С.

## Архитектурные решения

### 1. **Остаток = AMOUNT − QUANTITY_RESERVED**

- `AMOUNT` — физический остаток на складе.
- `QUANTITY_RESERVED` — зарезервировано заказами.
- **Доступно** = `max(0, AMOUNT − RESERVED)` — то, что можно продать.

### 2. **Атомарный резерв — `UPDATE ... SET QUANTITY_RESERVED + ?`**

```sql
UPDATE b_catalog_store_product
SET QUANTITY_RESERVED = QUANTITY_RESERVED + ?
WHERE ID = ?
```

- Не `read → +qty → write` — race condition между чтением и записью.
- Атомарный `UPDATE` — безопасно при параллельных заказах.

### 3. **Резерв внутри транзакции заказа**

```php
AddEventHandler('sale', 'OnSaleOrderBeforeSaved', function($event) {
    $stock->reserveForOrder($order); // внутри той же транзакции
});
```

- `OnSaleOrderBeforeSaved` — в транзакции `save()`. Если резерв не удался — `Result::addError` — заказ не сохранится. Атомарно.

### 4. **Снятие резерва — компенсирующая транзакция**

При `OnSaleOrderCanceled` — `releaseForOrder` снимает резерв. Это **компенсация**, а не rollback (заказ уже в БД, просто отменён).

### 5. **1С пушит дельту, не real-time**

- **Anti-pattern:** при открытии карточки запрашиваем 1С → если 1С лежит, сайт лежит.
- **Правильно:** 1С раз в N минут пушит изменения → Bitrix обновляет копию → фронт читает локально.

### 6. **Суммарный остаток — `b_catalog_product.QUANTITY`**

```sql
UPDATE b_catalog_product p
SET QUANTITY = (SELECT SUM(AMOUNT) FROM b_catalog_store_product sp WHERE sp.PRODUCT_ID = p.ID)
WHERE p.ID = ?
```

- `QUANTITY` — агрегат для быстрого отображения ("в наличии").
- `b_catalog_store_product` — детализация по складам.

## Как отвечать на собеседовании

> «Остаток — `AMOUNT − QUANTITY_RESERVED`, атомарный `UPDATE` для резерва. Резерв в `OnSaleOrderBeforeSaved` — внутри транзакции заказа, атомарно. Отмена — компенсирующая транзакция, не rollback. 1С пушит дельту в Bitrix — не делаем real-time запросы к 1С. Суммарный остаток в `b_catalog_product` для быстрого отображения.»

## Что спросят

- «Что если два заказа одновременно резервируют последний товар?» — атомарный `UPDATE` + проверка `QUANTITY_RESERVED < AMOUNT` в `WHERE`. Либо `SELECT FOR UPDATE` в транзакции.
- «Как быть с «висячими» резервами при сбое?» — таблица `acme_reservations` с `order_id` + `expires_at`, крон снимает просроченные.
- «Почему `OnSaleOrderBeforeSaved`, а не `OnSaleOrderSaved`?» — `Before` внутри транзакции — ошибка откатит заказ. `Saved` — после commit, откатить уже нельзя.
