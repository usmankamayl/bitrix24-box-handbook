# Двусторонняя интеграция Bitrix24 ↔ ERP

Полный цикл: активити ставит заказ в очередь → worker отправляет в ERP → результат возвращается в БП. Плюс обработчик `onCrmDealUpdate` с защитой от петли.

## Файлы

- `crest.php` — REST-клиент
- `queue.php` — файловая очередь + таблица маппинга `deal_id ↔ erp_id`
- `install.php` — регистрация активити `ERP_EXPORT_ORDER` и события `onCrmDealUpdate`
- `export.php` — HANDLER активити: ставит задачу в очередь и подтверждает приём
- `worker.php` — cron-обработчик: забирает задачи, экспортирует в ERP, завершает активити
- `on_deal_update.php` — обработчик события обновления сделки с loop-prevention

## Схема

```
[Робот на стадии "В работе"]
        ↓
Bitrix POST → export.php (мгновенно: ставим в очередь, bizproc.event.send)
        ↓
Ответ 200 OK — БП ждёт
        ↓
cron: worker.php
   1) забирает задачу из queue/
   2) GET crm.deal.get
   3) POST/PUT в ERP (по маппингу — create или update)
   4) bizproc.activity.completed → возвращаем ERP_ORDER_ID
        ↓
[БП продолжается, видит статус]
```

Обратная синхронизация:
```
Bitrix событие onCrmDealUpdate → on_deal_update.php
   1) проверяем application_token
   2) смотрим mapping — если последняя запись наша и свежая → эхо, пропускаем
   3) иначе — реагируем (лог, можно писать в ERP)
```

## Ключевые паттерны для собеседования

- **Асинхронная схема**: handler → event.send → очередь → worker → activity.completed.
- **Маппинг ID**: `mapping.json` (в продакшене — таблица БД), `external_id` в ERP.
- **Идемпотентность**: `Idempotency-Key: b24_deal_{id}` — повтор не создаёт дубль.
- **Loop-prevention**: `sync_source` и временная метка — отсекаем свои же эха.
- **Application token**: проверка подлинности входящего события от Bitrix.
- **Cron worker**: отделяет «приём» от «выполнения», retry-friendly.

## Настройка

1. `crest.php` — URL входящего вебхука (`crm`, `bizproc`, `event`).
2. `install.php` — реальные URL для `export.php` и `on_deal_update.php`.
3. `worker.php` — `ERP_API_URL`, `ERP_API_KEY`.
4. `on_deal_update.php` — `EXPECTED_TOKEN` (тот же, что в `event.bind`).
5. Cron: `* * * * * php /path/worker.php`.
