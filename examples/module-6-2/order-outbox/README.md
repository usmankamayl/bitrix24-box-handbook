# Кейс 3: «Заказ создан, но письмо/1С/склад не узнали» — Transactional Outbox

## Что показывает

Классическая боль: на `OnSaleOrderSaved` навешано 5 обработчиков. Один падает (SMTP timeout, 1С лежит) — либо заказ откатывается, либо часть консьюмеров молчит.

## Анти-паттерн vs решение

```php
// ❌ Всё в событии — синхронно, в транзакции
AddEventHandler('sale', 'OnSaleOrderSaved', function($event) {
    sendEmail();     // SMTP timeout 30с → checkout висит
    exportTo1C();    // 1С упала → Exception → rollback заказа!
    notifySlack();
});
```

```php
// ✅ Outbox: в событии только INSERT (быстро, та же транзакция)
AddEventHandler('sale', 'OnSaleOrderSaved', function($event) {
    $order = $event->getParameter('ENTITY');
    $conn->queryExecute(
        "INSERT INTO acme_outbox (EVENT_TYPE, ENTITY_ID, PAYLOAD) VALUES ('order.saved', ?, ?)",
        [$order->getId(), $payload]
    );
    // checkout завершается за ~50мс
});
```

## Ключевые решения

### 1. INSERT в той же транзакции — атомарность

- Заказ сохранился → событие в outbox. Заказ не сохранился → и события нет.
- Никакого «заказ есть, а письмо не отправлено» и наоборот.

### 2. Worker с optimistic lock

```php
$conn->queryExecute(
    "UPDATE acme_outbox SET STATUS='processing' WHERE ID=? AND STATUS IN ('new','failed')",
    [$job['ID']]
);
if ($conn->getAffectedRowsCount() === 0) {
    return; // другой worker уже взял — race condition побеждена
}
```

Два воркера на разных нодах не возьмут один job дважды.

### 3. Exponential backoff для retry

```php
$delay = 30 * (2 ** ($retry - 1)); // 30s, 60s, 120s, 240s, 480s
```

Не спамим упавший 1С каждую секунду.

### 4. Dead letter — не бесконечный retry

После `MAX_RETRY = 5` job остаётся `failed` — для ручного разбора. Без этого сломанный job блокирует очередь вечно.

### 5. Dedup на консьюмере

```php
$exported = Option::get('acme_erp', 'exported_' . $orderId, 'N');
if ($exported === 'Y') return; // уже отправили — пропускаем
```

Даже если outbox пришлёт дубль (retry, баг) — консьюмер не выполнит дважды.

### 6. Мониторинг — без него outbox не работает

```sql
-- Dead letter: не смогли после 5 попыток
SELECT * FROM acme_outbox WHERE STATUS='failed' AND RETRY_COUNT >= 5;

-- Stuck: worker умер в середине
SELECT * FROM acme_outbox WHERE STATUS='processing' AND CREATED_AT < NOW() - INTERVAL 1 HOUR;
```

## Три кита outbox

| Проблема | Как решается |
|----------|-------------|
| Потеря события при падении консьюмера | INSERT в транзакции — событие в БД, не в памяти |
| Блокировка checkout тяжёлой работой | Событие ~50мс, работа — в worker |
| Каскадные падения (1С → заказ) | Каждый консьюмер try/catch независимо |

## Senior-ответ на собеседовании

> «Transactional outbox: в событии только INSERT в outbox-таблицу в той же транзакции — атомарно с заказом. Worker разгребает очередь, каждый консьюмер независим. Retry с exponential backoff, dead letter после 5 попыток. Мониторинг — dead letter и stuck jobs. Плюсы: заказ не откатится если лежит 1С, checkout быстрый. Минусы: событие не мгновенное, задержка до секунд — приемлемо для email/1С, неприемлемо для «показать номер заказа».»

## Что спросят

- «Чем outbox отличается от простого `CAgent`?» — `CAgent` — битриксовая очередь, без гарантий атомарности и без retry-логики. Outbox — паттерн, который можно реализовать и через `CAgent`, но сам он транзакционность не даёт.
- «А если worker умрёт между `STATUS='processing'` и `done`?» — job останется `processing`. Мониторинг «stuck» покажет. Retry не сработает автоматически — нужен watchdog или ручной requeue.
- «Почему `SELECT` без `FOR UPDATE`?» — `UPDATE ... WHERE STATUS IN (...)` с `affected_rows` — это optimistic lock, без блокировок.
