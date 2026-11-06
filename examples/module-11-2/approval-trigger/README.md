# Триггер «Решение банка» — полный цикл ожидания события

Демонстрирует механику пользовательского триггера: БП ставит сделку «в ожидание», внешняя система (банк) позже будит триггер через `bizproc.event.send` и передаёт параметры решения.

## Файлы

- `crest.php` — REST-клиент
- `install.php` — регистрация триггера `bizproc.activity.add` с `TYPE => 'TRIGGER'`
- `wait.php` — HANDLER триггера: сохраняет `event_token` в `waiting.json`
- `bank_callback.php` — endpoint для внешней системы: находит `event_token` по `deal_id` и вызывает `bizproc.event.send`

## Схема работы

```
1. Сделка переходит на стадию с триггером
        ↓
2. Bitrix POST → wait.php {event_token, document_id}
        ↓
3. wait.php сохраняет token в waiting.json (ключ — ID сделки)
        ↓
4. Банк решает заявку и POST → bank_callback.php {deal_id, decision, sum}
        ↓
5. bank_callback.php → bizproc.event.send {event_token, RETURN_VALUES}
        ↓
6. БП продолжается, получает DECISION, APPROVED_SUM, COMMENT
```

## Как использовать

1. Впишите URL входящего вебхука в `crest.php` (нужны права `bizproc`, `crm`).
2. В `install.php` укажите реальный URL `wait.php`.
3. Откройте `install.php` — триггер зарегистрирован.
4. В CRM: Сделка → Триггеры → добавьте «Решение банка» на нужной стадии.
5. Далее в БП/роботах используйте `DECISION` в условии для ветвления (approved → выставить счёт, rejected → уведомить менеджера).

## Ключевые моменты для собеседования

- `event_token` живёт в БД приложения до момента пробуждения.
- `RETURN_VALUES` в `bizproc.event.send` соответствуют `RETURN_PROPERTIES` при регистрации.
- Один и тот же код может работать для сделки, лида, контакта — ветвите по `document_id`.
- В продакшене вместо `waiting.json` — реальная БД с TTL (токен действует 30 дней).
