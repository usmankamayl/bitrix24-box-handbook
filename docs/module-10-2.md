# Модуль 10.2: Открытые линии и чат-боты (Bitrix24)

## Теория

### Что такое Открытые линии (Open Channels)

**Открытые линии** — это инструмент Bitrix24 для общения с клиентами из разных каналов (мессенджеры, соцсети, онлайн-чат) в одном интерфейсе. Все сообщения собираются в единую очередь, с которой работают операторы.

**Основные концепции:**

- **Источники (коннекторы)** — откуда приходят сообщения: VK, Telegram, WhatsApp, Facebook, онлайн-чат на сайте, Viber
- **Очередь** — список операторов, которые обрабатывают обращения (равномерное распределение или по порядку)
- **Конвертация** — обращение можно превратить в лид или сделку CRM автоматически или вручную

**Как работает:**

```
Клиент пишет в VK/Telegram/WhatsApp
              ↓
   Коннектор передаёт в Bitrix24
              ↓
  Сообщение попадает в Открытую линию
              ↓
  Распределяется на свободного оператора
              ↓
  Оператор отвечает (из чата Bitrix24)
              ↓
  При необходимости — создаётся лид/сделка
```

---

### Настройка Открытой линии

**Через интерфейс:** CRM → Контакт-центр → Открытые линии → Создать

**Основные параметры:**

- **Название линии** — например, "Поддержка клиентов"
- **Очередь операторов** — список сотрудников
- **Распределение обращений:**
  - Равномерно — каждому по очереди
  - Точно как задано — в порядке очереди
  - Равномерно с учётом нагрузки — кто меньше занят
- **Действия при обращении:**
  - Создавать лид автоматически
  - Использовать существующий контакт/компанию
  - Ничего не создавать (только чат)
- **Соглашение о персональных данных** — показывать форму согласия

---

### REST API Открытых линий (imopenlines.*)

#### Получение списка линий

```php
// Получить все открытые линии
$lines = CRest::call('imopenlines.config.list.get', []);
// Возвращает массив линий с ID, названием, очередью

foreach ($lines['result'] as $line) {
    echo "ID: {$line['ID']}, Название: {$line['LINE_NAME']}\n";
}
```

#### Получение сессии диалога

```php
// Получить информацию о сессии (диалоге)
$session = CRest::call('imopenlines.session.get', [
    'SESSION_ID' => 12345
]);
```

#### Перевод диалога на оператора

```php
// Перевести диалог на другого оператора
CRest::call('imopenlines.operator.transfer', [
    'CHAT_ID' => 789,
    'USER_ID' => 42,       // ID нового оператора
    'LEAVE' => 'Y'         // Y = текущий оператор выходит из диалога
]);
```

#### Создание лида из диалога

```php
// Вручную сконвертировать диалог в лид
CRest::call('imopenlines.crm.lead.add', [
    'CHAT_ID' => 789
]);
```

---

### Импорт сообщений через коннектор

Если пишешь **свой коннектор** для мессенджера, сообщения импортируются через `imconnector.*` методы:

```php
// Отправить сообщение в открытую линию от клиента
CRest::call('imconnector.send.messages', [
    'CONNECTOR' => 'my_telegram_connector',  // ID твоего коннектора
    'LINE' => 1,                             // ID открытой линии
    'MESSAGES' => [
        [
            'user' => [
                'id' => 'user_telegram_123',
                'name' => 'Иван Петров',
                'avatar' => 'https://example.com/avatar.jpg',
            ],
            'message' => [
                'id' => 'msg_456',
                'date' => time(),
                'text' => 'Здравствуйте, есть вопрос по заказу',
            ],
            'chat' => [
                'id' => 'chat_789',
                'name' => 'Telegram чат',
            ],
        ]
    ]
]);
```

**Структура сообщения:**

- `user` — данные отправителя (id, имя, аватар)
- `message` — текст, дата, ID сообщения во внешней системе
- `chat` — информация о чате/канале
- `session` — опционально, для группировки сообщений

---

### Чат-боты в Bitrix24

**Чат-бот** — это автоматизированный пользователь, который отвечает на сообщения в чатах Bitrix24 или в открытых линиях.

**Типы ботов:**

- **Чат-бот (chatbot)** — работает во внутренних чатах Bitrix24
- **Бот открытой линии** — отвечает клиентам первым, пока не подключится оператор

**Жизненный цикл бота:**

1. Регистрация (install.php)
2. Получение событий (handler.php)
3. Ответы на сообщения
4. Удаление (uninstall)

---

### Регистрация чат-бота

#### imbot.register

```php
CRest::call('imbot.register', [
    'CODE' => 'echo_bot',                    // Уникальный код бота
    'TYPE' => 'B',                           // B = бот, H = человек (виртуальный юзер)
    'EVENT_HANDLER' => 'https://your-domain.com/handler.php',
    'EVENT_MESSAGE_ADD' => 'https://your-domain.com/handler.php',
    'EVENT_MESSAGE_UPDATE' => '',
    'EVENT_MESSAGE_DELETE' => '',
    'EVENT_WELCOME_MESSAGE' => 'https://your-domain.com/handler.php',
    'EVENT_BOT_DELETE' => 'https://your-domain.com/handler.php',
    'PROPERTIES' => [
        'NAME' => 'Эхо-бот',                 // Имя бота
        'LAST_NAME' => '',
        'COLOR' => 'RED',                    // Цвет в чате (RED, GREEN, BLUE...)
        'EMAIL' => 'bot@example.com',
        'WORK_POSITION' => 'Помощник',
        'PERSONAL_WWW' => 'https://example.com',
        'PERSONAL_GENDER' => 'M',            // M/F
        'PERSONAL_BIRTHDAY' => '',
        'PERSONAL_PHOTO' => '',              // URL аватара
    ],
    'OPENLINE' => 'Y'                        // Y = бот может работать в открытых линиях
]);
```

**Параметры:**

- `CODE` — уникальный идентификатор бота
- `TYPE` — `B` (бот) или `H` (человек-пользователь)
- `EVENT_HANDLER` — URL для всех событий (fallback)
- `EVENT_MESSAGE_ADD` — URL для новых сообщений
- `OPENLINE` — `Y` для работы в открытых линиях

#### imbot.update — обновление бота

```php
CRest::call('imbot.update', [
    'BOT_ID' => 123,                         // ID бота (из imbot.register)
    'FIELDS' => [
        'PROPERTIES' => [
            'NAME' => 'Новое имя бота',
        ]
    ]
]);
```

#### imbot.unregister — удаление бота

```php
CRest::call('imbot.unregister', [
    'BOT_ID' => 123
]);
```

---

### События чат-бота

#### onBotMessageAdd — новое сообщение для бота

Когда пользователь пишет боту, Bitrix24 отправляет POST на `EVENT_MESSAGE_ADD`.

**Формат входящего запроса:**

```php
// handler.php
$data = json_decode(file_get_contents('php://input'), true);

/*
$data содержит:
{
    "event": "ONBOTMESSAGEADD",
    "data": {
        "BOT_ID": 123,
        "DIALOG_ID": "chat456",         // ID диалога
        "MESSAGE": "Привет!",           // Текст сообщения
        "FROM_USER_ID": 42,             // ID отправителя
        "CHAT_ID": 456,
        "CHAT_ENTITY_TYPE": "LINES",    // LINES для открытых линий
        "CHAT_ENTITY_ID": "livechat|1|789|12"
    },
    "ts": 1699123456,
    "auth": {
        "access_token": "...",
        "domain": "your.bitrix24.ru"
    }
}
*/

$message = $data['data']['MESSAGE'];
$dialogId = $data['data']['DIALOG_ID'];
$userId = $data['data']['FROM_USER_ID'];
```

#### onBotCommandAdd — команда боту

Если пользователь пишет `/help`, приходит событие `ONBOTCOMMANDADD`:

```php
$data = json_decode(file_get_contents('php://input'), true);

if ($data['event'] === 'ONBOTCOMMANDADD') {
    $command = $data['data']['COMMAND'];      // "/help"
    $context = $data['data']['COMMAND_CONTEXT']; // Контекст (например, ID сделки)
    $dialogId = $data['data']['DIALOG_ID'];
}
```

---

### Отправка сообщений ботом

#### imbot.message.add — простое сообщение

```php
CRest::call('imbot.message.add', [
    'BOT_ID' => 123,
    'DIALOG_ID' => 'chat456',                // Или user ID для личного чата
    'MESSAGE' => 'Привет! Я бот.',
]);
```

#### С клавиатурой (кнопки)

```php
CRest::call('imbot.message.add', [
    'BOT_ID' => 123,
    'DIALOG_ID' => 'chat456',
    'MESSAGE' => 'Выберите действие:',
    'KEYBOARD' => [
        'BUTTONS' => [
            [
                'TEXT' => 'Помощь',
                'BG_COLOR' => '#29619b',
                'TEXT_COLOR' => '#fff',
                'COMMAND' => '/help',          // Отправит /help при нажатии
            ],
            [
                'TEXT' => 'Открыть сайт',
                'LINK' => 'https://example.com', // Откроет ссылку
            ],
            [
                'TEXT' => 'Заказать звонок',
                'COMMAND' => '/callback',
                'COMMAND_PARAMS' => 'order_call', // Параметры команды
            ],
        ],
        'HIDE_BY_CLICK' => 'Y',              // Скрыть клавиатуру после клика
    ],
]);
```

**Параметры кнопки:**

- `TEXT` — текст на кнопке
- `COMMAND` — команда, которая отправится боту
- `COMMAND_PARAMS` — дополнительные параметры команды
- `LINK` — URL, который откроется
- `BG_COLOR`, `TEXT_COLOR` — цвета кнопки

#### С вложениями

```php
CRest::call('imbot.message.add', [
    'BOT_ID' => 123,
    'DIALOG_ID' => 'chat456',
    'MESSAGE' => 'Смотрите:',
    'ATTACH' => [
        'BLOCKS' => [
            [
                'LINK' => [
                    'NAME' => 'Наш сайт',
                    'LINK' => 'https://example.com',
                    'PREVIEW' => 'https://example.com/preview.jpg',
                ]
            ],
            [
                'IMAGE' => [
                    'LINK' => 'https://example.com/image.jpg',
                ]
            ],
            [
                'GRID' => [                    // Сетка (несколько колонок)
                    [
                        'DISPLAY' => 'COLUMN',
                        'NAME' => 'Поле 1:',
                        'VALUE' => 'Значение 1',
                    ]
                ]
            ],
        ]
    ],
]);
```

---

### imbot.chat.add — создание чата от имени бота

```php
CRest::call('imbot.chat.add', [
    'BOT_ID' => 123,
    'USERS' => [42, 55],                     // ID пользователей
    'TITLE' => 'Чат с клиентом',
    'MESSAGE' => 'Добро пожаловать!',
]);
```

---

### imbot.command.register — регистрация команды

```php
CRest::call('imbot.command.register', [
    'BOT_ID' => 123,
    'COMMAND' => '/help',
    'COMMON' => 'Y',                         // Доступна всем
    'HIDDEN' => 'N',                         // Показывать в списке команд
    'EXTRANET_SUPPORT' => 'N',
    'SONET_SUPPORT' => 'N',
    'EVENT_COMMAND_ADD' => 'https://your-domain.com/handler.php',
    'PROPERTIES' => [
        'COMMAND' => '/help',
        'TITLE' => 'Помощь',
        'PARAMS' => '',
    ],
]);
```

---

### Коннекторы (imconnector.*)

**Коннектор** — это мост между Bitrix24 и внешним мессенджером.

**Встроенные коннекторы:**
- `livechat` — онлайн-чат на сайте
- `facebookcomments`, `facebookbot` — Facebook/Instagram
- `vkgroup` — VK
- `telegrambot` — Telegram
- `whatsappbymessages`, `whatsappbytwilio` — WhatsApp

#### Регистрация своего коннектора

```php
// imconnector.register — создать свой коннектор
CRest::call('imconnector.register', [
    'ID' => 'my_messenger',                  // Уникальный ID коннектора
    'NAME' => 'My Messenger',                // Название
    'ICON' => [
        'DATA_IMAGE' => base64_encode(file_get_contents('icon.png')),
    ],
    'PLACEMENT' => 'SETTINGS',               // Где отображается настройка
    'PLACEMENT_HANDLER' => 'https://your-domain.com/settings.php',
]);
```

**Когда коннектор зарегистрирован:**
- Появляется в списке источников открытой линии
- Можешь импортировать сообщения через `imconnector.send.messages`
- Можешь отправлять сообщения обратно через `imconnector.send.status.delivery`

#### Отправка статуса доставки

```php
// Уведомить мессенджер, что сообщение доставлено
CRest::call('imconnector.send.status.delivery', [
    'CONNECTOR' => 'my_messenger',
    'LINE' => 1,
    'MESSAGES' => [
        ['id' => 'msg_456', 'chat' => ['id' => 'chat_789']],
    ]
]);
```

---

### Полный пример: эхо-бот

```php
// install.php — регистрация бота при установке приложения
$bot = CRest::call('imbot.register', [
    'CODE' => 'echo_bot',
    'TYPE' => 'B',
    'EVENT_MESSAGE_ADD' => 'https://your-domain.com/handler.php',
    'EVENT_WELCOME_MESSAGE' => 'https://your-domain.com/handler.php',
    'PROPERTIES' => [
        'NAME' => 'Эхо-бот',
        'COLOR' => 'GREEN',
    ],
    'OPENLINE' => 'Y',
]);

file_put_contents('bot_id.txt', $bot['result']); // Сохраняем BOT_ID
```

```php
// handler.php — обработчик событий
$input = json_decode(file_get_contents('php://input'), true);

if ($input['event'] === 'ONBOTMESSAGEADD') {
    $botId = $input['data']['BOT_ID'];
    $dialogId = $input['data']['DIALOG_ID'];
    $message = $input['data']['MESSAGE'];

    // Отправляем эхо
    CRest::call('imbot.message.add', [
        'BOT_ID' => $botId,
        'DIALOG_ID' => $dialogId,
        'MESSAGE' => "Вы написали: {$message}",
    ]);
}
```

---

## Вопросы для собеседования

### Q1: Что такое Открытая линия в Bitrix24?

**Ответ:**
Открытая линия — это канал связи с клиентами через внешние мессенджеры (VK, Telegram, WhatsApp) и онлайн-чат на сайте. Все обращения собираются в одну очередь, обрабатываются операторами и могут конвертироваться в лиды или сделки CRM.

---

### Q2: Как зарегистрировать чат-бота в Bitrix24?

**Ответ:**
Через REST-метод `imbot.register`:

```php
CRest::call('imbot.register', [
    'CODE' => 'my_bot',
    'TYPE' => 'B',
    'EVENT_MESSAGE_ADD' => 'https://example.com/handler.php',
    'PROPERTIES' => ['NAME' => 'Мой бот'],
    'OPENLINE' => 'Y',  // для работы в открытых линиях
]);
```

Метод возвращает `BOT_ID`, который сохраняешь для дальнейших вызовов.

---

### Q3: Какие события приходят чат-боту?

**Ответ:**

| Событие | Когда приходит |
|---------|----------------|
| `ONBOTMESSAGEADD` | Пользователь написал боту |
| `ONBOTMESSAGEUPDATE` | Сообщение изменено |
| `ONBOTMESSAGEDELETE` | Сообщение удалено |
| `ONBOTWELCOME` | Добавили бота в чат / начался диалог |
| `ONBOTCOMMANDADD` | Пользователь отправил команду (`/help`) |
| `ONBOTDELETE` | Бота удалили из чата |

---

### Q4: Как бот отправляет сообщение?

**Ответ:**
Через `imbot.message.add`:

```php
CRest::call('imbot.message.add', [
    'BOT_ID' => 123,
    'DIALOG_ID' => 'chat456',   // или USER_ID для личного чата
    'MESSAGE' => 'Текст сообщения',
]);
```

`DIALOG_ID` берется из входящего события `ONBOTMESSAGEADD`.

---

### Q5: Как добавить кнопки в сообщение бота?

**Ответ:**
Через параметр `KEYBOARD` в `imbot.message.add`:

```php
'KEYBOARD' => [
    'BUTTONS' => [
        ['TEXT' => 'Да', 'COMMAND' => '/yes'],
        ['TEXT' => 'Сайт', 'LINK' => 'https://example.com'],
    ],
    'HIDE_BY_CLICK' => 'Y',
]
```

Кнопки могут отправлять команду боту (`COMMAND`) или открывать ссылку (`LINK`).

---

### Q6: Что такое коннектор и зачем писать свой?

**Ответ:**
Коннектор — это интерфейс между Bitrix24 и внешним мессенджером. Свой коннектор пишут, если нужной интеграции нет в стандартном наборе (например, для кастомного мессенджера или специфичной логики). Регистрируется через `imconnector.register`, сообщения импортируются через `imconnector.send.messages`.

---

### Q7: Как отличить сообщение от клиента в открытой линии от внутреннего сообщения?

**Ответ:**
По полю `CHAT_ENTITY_TYPE` в событии `ONBOTMESSAGEADD`:

- `LINES` — открытая линия (сообщение от клиента)
- `IM` — внутренний чат Bitrix24
- `CRM` — чат из CRM-сущности

Также `CHAT_ENTITY_ID` для открытой линии содержит префикс `livechat|`.

---

### Q8: Чем чат-бот отличается от бота открытой линии?

**Ответ:**
Технически это один механизм (`imbot`). Разница в параметре `OPENLINE`:
- `OPENLINE => 'N'` — бот работает только во внутренних чатах
- `OPENLINE => 'Y'` — бот может быть назначен на открытую линию и отвечать клиентам первым (до подключения оператора)

---

## Практические задачи

- **Задача 1:** Чат-бот «Эхо» — регистрация и обработка сообщений
- **Задача 2:** Бот с клавиатурой — кнопки и команды
- **Задача 3:** Свой коннектор для Telegram

**Решения задач находятся в `examples/module-10-2/`**

---

## Полезные ссылки

- **REST API чат-ботов:** https://apidocs.bitrix24.ru/api-reference/chat-bots/
- **Методы imbot:** https://apidocs.bitrix24.ru/api-reference/chat-bots/imbot-register.html
- **Открытые линии:** https://apidocs.bitrix24.ru/api-reference/openlines/
- **Коннекторы:** https://apidocs.bitrix24.ru/api-reference/imconnector/
- **События:** https://apidocs.bitrix24.ru/api-reference/chat-bots/events/

---

## Частые ошибки

### Ошибка 1: Бот не получает события

**Причина:** `EVENT_MESSAGE_ADD` недоступен с сервера Bitrix24 (нет HTTPS, firewall, неверный URL).

**Решение:** Проверь, что URL отвечает HTTP 200 извне. Используй ngrok для локальной разработки.

### Ошибка 2: `imbot.register` возвращает ошибку

**Причина:** `CODE` уже занят или `PROPERTIES` не содержит обязательного поля `NAME`.

**Решение:** Используй уникальный `CODE`, добавь `NAME` в `PROPERTIES`.

### Ошибка 3: Кнопки не работают

**Причина:** `COMMAND` написан без `/` или `COMMAND_PARAMS` передан с ошибкой.

**Решение:** Команда должна начинаться с `/` (например, `/yes`). Параметры — строка.

### Ошибка 4: Бот в открытой линии не отвечает клиентам

**Причина:** При регистрации не указан `OPENLINE => 'Y'` или бот не назначен на линию в настройках.

**Решение:** Перерегистрируй бота с `OPENLINE => 'Y'`, затем в настройках открытой линии назначь бота первым в очереди.

### Ошибка 5: `DIALOG_ID` неверный при ответе

**Причина:** Используется `CHAT_ID` вместо `DIALOG_ID` или наоборот.

**Решение:** Для ответа используй `DIALOG_ID` из входящего события — это строка вида `chat456` или числовой `USER_ID` для личного чата.

---

## Вопросы для самопроверки

1. Что такое Открытая линия и какие источники поддерживаются?
2. Как зарегистрировать чат-бота через REST API?
3. Какие события приходят боту?
4. Как отправить сообщение с кнопками?
5. Как зарегистрировать команду `/help` для бота?
6. Что такое коннектор и как написать свой?
7. Как передать сообщение из мессенджера в открытую линию?
8. Как определить, что сообщение пришло из открытой линии?
9. Чем `imbot.register` отличается от `imbot.update`?
10. Что делает параметр `OPENLINE => 'Y'` при регистрации бота?

---

**Предыдущий модуль:** [Модуль 10.1: Открытые линии — основы](module-10-1.md)
