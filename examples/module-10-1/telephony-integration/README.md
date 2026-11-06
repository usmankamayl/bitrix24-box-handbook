# Пример: Интеграция сторонней АТС с Bitrix24 (REST-телефония)

## Описание

Два PHP-скрипта реализуют типичный флоу интеграции телефонии:

- `index.php` — принимает событие "входящий звонок" от АТС, регистрирует звонок в Bitrix24 и показывает карточку менеджеру
- `finish.php` — принимает событие "звонок завершён", закрывает звонок в CRM и прикрепляет запись разговора

**Флоу:**

```
АТС получает звонок
    → GET index.php?phone=+7999...&ext=100&ats_call_id=ATS1
        → telephony.externalcall.register (создаёт лид, возвращает CALL_ID)
        → telephony.externalcall.show (карточка у менеджера)
    → разговор идёт...
    → GET finish.php?ats_call_id=ATS1&duration=120&record_url=...
        → telephony.externalcall.finish (звонок в историю CRM)
        → telephony.externalcall.attachrecord (запись разговора)
```

## Установка

1. Размести `index.php` и `finish.php` на HTTPS-сервере (Bitrix24 требует HTTPS для вебхуков)

2. Создай **входящий вебхук** в Bitrix24:
   - Портал → Разработчикам → Другое → Входящий вебхук
   - Права: `telephony`, `crm`, `user`
   - Скопируй URL вида `https://portal.bitrix24.ru/rest/1/xxxxx/`

3. Пропиши webhook URL в обоих файлах:
   ```php
   define('B24_WEBHOOK', 'https://portal.bitrix24.ru/rest/1/xxxxx/');
   ```

4. Настрой свою АТС:
   - При входящем звонке → `GET index.php?phone={caller}&ext={ext}&ats_call_id={id}`
   - После завершения → `GET finish.php?ats_call_id={id}&duration={sec}&record_url={url}`

5. У сотрудника в Bitrix24 укажи **внутренний номер** (UF_PHONE_INNER):
   - Пользователь → Редактировать → Внутренний номер = `100` (или какой у тебя ext)

## Тестирование без АТС

```bash
# Симулируем входящий звонок
curl "https://your-domain.com/telephony/index.php?phone=%2B79991234567&ext=100&ats_call_id=TEST001"

# Симулируем завершение через 10 секунд
curl "https://your-domain.com/telephony/finish.php?ats_call_id=TEST001&duration=10&status=200&record_url=https://example.com/test.mp3"
```

## Что демонстрирует

| Метод | Файл | Что делает |
|-------|------|------------|
| `user.get` | index.php | Поиск пользователя по внутреннему номеру |
| `telephony.externalcall.register` | index.php | Регистрация звонка, создание лида |
| `telephony.externalcall.show` | index.php | Всплывающая карточка у менеджера |
| `telephony.externalcall.finish` | finish.php | Завершение звонка, запись в историю |
| `telephony.externalcall.attachrecord` | finish.php | Прикрепление записи разговора |

## Файлы

- `index.php` — webhook для входящего звонка
- `finish.php` — webhook для завершения звонка
- `calls.json` — файл-"база" активных звонков (создаётся автоматически)
- `calls.log` — лог всех событий (создаётся автоматически)

## Для собеседования

- Почему `attachrecord` вызывается после `finish`, а не наоборот
- Что делает `CRM_CREATE=1` и когда лучше `CRM_CREATE=0`
- Зачем сохранять `CALL_ID` между запросами (calls.json)
- Чем REST-телефония отличается от SIP-коннектора
- Что произойдёт если не вызвать `finish`
