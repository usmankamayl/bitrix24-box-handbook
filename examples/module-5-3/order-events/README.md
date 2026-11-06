# Кейс 3: Обработчики жизненного цикла заказа + интеграция с ERP

## Что решает

«При оплате заказа — отправить его в 1С/ERP/CRM» — классическая senior-задача. Ошибки:

- `file_get_contents`/`curl` прямо в `OnSaleOrderPaid` → `Order::save()` висит 30s, пользователь видит «бесконечный спиннер»;
- `Order::save()` внутри `OnSaleOrderSaved` → рекурсия и deadlock;
- Нет идемпотентности → webhook от платёжки ретраит → в ERP уходят дубли;
- Нет отличия `OnSaleOrderPaid` от `OnSaleStatusOrderChange` → событие ловится дважды или не ловится вовсе;
- Все ошибки — `Exception` → агент падает и заказ теряется.

## Структура

```
order-events/
├── init.php                  # регистрация обработчиков
├── lib/
│   └── OrderEvents.php       # логика + очередь + идемпотентность
└── README.md
```

## Ключевые приёмы

1. **`OnSaleOrderPaid` ≠ `OnSaleStatusOrderChange`.** Первое — факт оплаты (все `Payment` PAYED=Y). Второе — любая смена статуса. Если подписаться только на `OnSaleStatusOrderChange` — пропустите событие, если заказ стал 'P' автоматически до webhook'а. Подписывайте **оба**, но дедуплицируйте по ключу.

2. **Очередь на `CAgent`, не синхронный HTTP.** Событие ставит задачу → `Order::save()` завершается за миллисекунды. Агент стартует через 30s в фоне и уже может ходить в ERP долго.

3. **Идемпотентность через `Option` или HL.** Ключ `order_id + event + status` — повторный вызов не шлёт дубль. В реале — HL-блок с `UNIQUE (order_id, event_key)` + `created_at` для ретраев.

4. **Не `Order::save()` внутри события сохранения.** `OnSaleOrderSaved` — после коммита, `Order::save()` там вызовет то же событие рекурсивно. Изменения полей — в `OnSaleOrderBeforeSaved` (объект ещё в памяти) или через `Order::setField()` + отдельный save в агенте.

5. **`OnSaleOrderBeforeSaved` — для блокировок.** Бросок `Result::addError()` откатит транзакцию заказа. Пример: запрет вернуть заказ из 'DE' обратно в 'P'.

6. **Параметры события — `Bitrix\Main\Event`, а не ID.** `$event->getParameter('ENTITY')` — объект `Order`. Не путать со старыми событиями `OnOrder*`, которые дают `ID` и требуют `Order::load()`.

7. **Логирование в файл, не в Exception.** Ошибка в фоновой задаче не должна ронять агента — возвращаем строку для ретрая или пустую строку для завершения.

8. **`$event->getParameter('RESULT')`** — в Before-событиях даёт `Result` для добавления ошибок, которые откатят транзакцию.

## Установка

1. `lib/OrderEvents.php` → `/local/php_interface/include/acme/lib/OrderEvents.php` (или в модуль).
2. `init.php` → подключить в `/local/php_interface/init.php`.
3. Настройки модуля `acme.sale` — в `Option::set()` или через страницу настроек:
   - `erp_url` — URL ERP
   - `erp_notify_statuses` — список статусов через запятую (P,F,DE)
4. **Создать HL-блок OrderExportLog:**

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$result = HighloadBlockTable::add([
    'NAME' => 'OrderExportLog',
    'TABLE_NAME' => 'acme_order_export_log',
]);

// В админке → Highload-блоки → OrderExportLog → добавить поля:
// - UF_ORDER_ID (Целое число, обязательное)
// - UF_EVENT_KEY (Строка, длина 255, обязательное)
// - UF_TS (Дата/время)

// В БД добавить UNIQUE KEY:
ALTER TABLE acme_order_export_log 
ADD UNIQUE KEY uk_order_event (UF_ORDER_ID, UF_EVENT_KEY);
```
4. **Создать HL-блок OrderExportLog:**

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$result = HighloadBlockTable::add([
    'NAME' => 'OrderExportLog',
    'TABLE_NAME' => 'acme_order_export_log',
]);

// В админке → Highload-блоки → OrderExportLog → добавить поля:
// - UF_ORDER_ID (Целое число, обязательное)
// - UF_EVENT_KEY (Строка, длина 255, обязательное)
// - UF_TS (Дата/время)

// В БД добавить UNIQUE KEY:
ALTER TABLE acme_order_export_log 
ADD UNIQUE KEY uk_order_event (UF_ORDER_ID, UF_EVENT_KEY);
```

## Тестирование

- Оформить заказ → оплатить → проверить что в `b_agent` есть задача.
- Запустить `CAgent::Execute()` вручную или `php /bitrix/php_interface/cron_events.php`.
- Поднять webhook от платёжки дважды → убедиться что в ERP ушло одно событие.
- Заблокировать изменение статуса `DE → P` через `OnSaleOrderBeforeSaved` → убедиться что заказ откатился.
