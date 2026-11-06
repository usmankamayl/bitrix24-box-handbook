# Code Review #3: Интеграция с внешним API доставки

## Файлы

- `bad_integration.php` — плохой код для разбора (8+ ошибок)
- `good_integration.php` — исправленная версия: outbox + worker + circuit breaker + webhook с подписью
- `review_analysis.md` — полный разбор

## Как работать

1. Прочитай `bad_integration.php` — найди проблемы.
2. Обрати внимание на три зоны: событие `OnSaleOrderSaved`, HTTP-вызов, webhook от курьера.
3. Сравни с `good_integration.php` — там событие только ставит задачу в очередь, HTTP делает worker.

## Что тренирует

- Синхронный HTTP в событии — почему это табу (транзакция ждёт внешний API)
- `save()` внутри события — рекурсия
- Webhook без подписи — подделка статусов
- Секреты в коде vs `Option`
- Таймауты, retry, circuit breaker, `Idempotency-Key`
