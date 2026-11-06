# Пример: Чат-бот «Эхо» для Bitrix24

## Описание

Простой чат-бот на REST API Bitrix24, который демонстрирует основные операции с `imbot`:

- Регистрация бота через `imbot.register` при установке
- Приём событий `ONBOTMESSAGEADD`, `ONBOTCOMMANDADD`, `ONBOTWELCOME`
- Эхо-ответ на любое сообщение
- Клавиатура с кнопками (команда, ссылка, команда с параметрами)
- Обработка команды `/help`

## Файлы

| Файл | Назначение |
|------|------------|
| `install.php` | Регистрирует бота через `imbot.register` и команду `/help` через `imbot.command.register`. Сохраняет `BOT_ID` в `bot_id.txt`. |
| `handler.php` | Обработчик событий. Принимает POST от Bitrix24, отвечает эхо, клавиатурой и справкой. |

## Установка

### 1. Размести файлы на HTTPS-сервере

```
https://your-domain.com/module-10-2/chatbot-echo/
├── install.php
├── handler.php
└── crest.php          # SDK для REST API (скачай с github.com/bitrix-tools)
```

### 2. Зарегистрируй приложение на dev.bitrix24.ru

- **Тип:** Внешнее приложение
- **Scope:** `imbot`, `imopenlines` (если работаешь с открытыми линиями)
- **Install URL:** `https://your-domain.com/module-10-2/chatbot-echo/install.php`

### 3. Установи приложение на портал

Bitrix24 вызовет `install.php` → бот зарегистрируется → в `bot_id.txt` появится его ID.

### 4. Добавь бота в чат

- Внутренний чат: добавь бота в групповой чат или напиши ему лично
- Открытая линия: в настройках линии назначь бота первым в очереди

## Что демонстрирует

| Функционал | Где в коде |
|------------|------------|
| `imbot.register` | `install.php`, блок «ШАГ 1» |
| `imbot.command.register` | `install.php`, блок «ШАГ 3» |
| `imbot.message.add` (эхо) | `handler.php`, блок `ONBOTMESSAGEADD` |
| `KEYBOARD` с кнопками | `handler.php`, эхо-ответ |
| `COMMAND` + `COMMAND_PARAMS` | `handler.php`, `ONBOTCOMMANDADD` |
| `ONBOTWELCOME` | `handler.php`, приветственное сообщение |
| Определение открытой линии | `handler.php`, проверка `CHAT_ENTITY_TYPE === 'LINES'` |

## Отладка

Все входящие запросы логируются в `bot_log.txt`. Если бот не отвечает — смотри лог и проверяй, что `handler.php` отвечает HTTP 200.

## Для собеседования

- Чем `BOT_ID` отличается от `DIALOG_ID`
- Зачем нужен `OPENLINE => 'Y'` при регистрации
- Как отличить сообщение из открытой линии от внутреннего (`CHAT_ENTITY_TYPE`)
- Почему handler должен отвечать HTTP 200 быстро (иначе Bitrix24 повторит запрос)
