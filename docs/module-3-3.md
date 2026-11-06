# Модуль 3.3: Почтовые события

## Теория

### Что такое почтовые события в Bitrix

Почтовые события (Mail Events) — это механизм автоматической отправки email-уведомлений в ответ на различные действия в системе. Bitrix использует систему шаблонов для формирования писем и триггеров для их отправки.

### Структура почтовых событий

**Почтовое событие состоит из трех компонентов:**

1. **Тип почтового события** (`EVENT_TYPE`) — идентификатор события и его описание
2. **Шаблон письма** (`EVENT_MESSAGE`) — как будет выглядеть письмо
3. **Отправка события** — вызов через код

### 1. Типы почтовых событий

Тип события определяет:
- Уникальный ID события (например, `USER_REGISTER`)
- Название события
- Описание
- Список доступных переменных для шаблона

**Стандартные типы событий Bitrix:**
- `USER_REGISTER` — регистрация пользователя
- `NEW_USER` — создан новый пользователь
- `USER_PASS_REQUEST` — запрос восстановления пароля
- `USER_PASS_CHANGED` — пароль изменен
- `FEEDBACK_FORM` — отправка формы обратной связи
- `SALE_NEW_ORDER` — новый заказ
- `SALE_ORDER_PAID` — заказ оплачен

### 2. Шаблоны писем

Шаблон определяет:
- **От кого** (`EMAIL_FROM`, `EMAIL_FROM_NAME`)
- **Кому** (`EMAIL_TO`)
- **Тему** (`SUBJECT`)
- **Тело письма** (`MESSAGE`, `MESSAGE_PHP`)
- **Формат** (текст/HTML)
- **Сайт**, для которого действует шаблон

**Макросы в шаблонах:**
```
#NAME# — переменная будет заменена на значение
#=PHP_CODE# — выполнение PHP-кода
```

### 3. API для работы с почтовыми событиями

#### Отправка почтового события

```php
\Bitrix\Main\Mail\Event::send([
    'EVENT_NAME' => 'MY_EVENT_TYPE',
    'LID' => 's1', // ID сайта
    'C_FIELDS' => [
        'EMAIL' => 'user@example.com',
        'NAME' => 'Иван Иванов',
        'MESSAGE' => 'Текст сообщения'
    ]
]);
```

#### Старый API (все еще используется)

```php
CEvent::Send(
    'MY_EVENT_TYPE',     // Тип события
    's1',                // ID сайта
    [
        'EMAIL' => 'user@example.com',
        'NAME' => 'Иван Иванов'
    ],
    'N',                 // Дубли (Y/N)
    123                  // ID шаблона (необязательно)
);
```

### Создание собственного типа события

#### Через миграцию или установку модуля

```php
use Bitrix\Main\Mail\Internal\EventTypeTable;

EventTypeTable::add([
    'LID' => 'ru',
    'EVENT_NAME' => 'CUSTOM_NOTIFICATION',
    'NAME' => 'Пользовательское уведомление',
    'DESCRIPTION' => "#EMAIL# - Email получателя\n#NAME# - Имя получателя\n#MESSAGE# - Текст сообщения"
]);
```

#### Через административный интерфейс

**Путь:** Настройки → Настройки продукта → Почтовые события → Типы событий → Добавить тип

### Создание шаблона письма

```php
use Bitrix\Main\Mail\Internal\EventMessageTable;

EventMessageTable::add([
    'EVENT_NAME' => 'CUSTOM_NOTIFICATION',
    'LID' => 's1',
    'ACTIVE' => 'Y',
    'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
    'EMAIL_TO' => '#EMAIL#',
    'SUBJECT' => 'Уведомление для #NAME#',
    'BODY_TYPE' => 'html',
    'MESSAGE' => '
        <html>
        <body>
            <h1>Здравствуйте, #NAME#!</h1>
            <p>#MESSAGE#</p>
        </body>
        </html>
    '
]);
```

### Работа с вложениями

```php
\Bitrix\Main\Mail\Event::send([
    'EVENT_NAME' => 'MY_EVENT',
    'LID' => 's1',
    'C_FIELDS' => [
        'EMAIL' => 'user@example.com'
    ],
    'FILE' => [
        [
            'name' => 'document.pdf',
            'path' => '/path/to/file.pdf',
            'type' => 'application/pdf'
        ]
    ]
]);
```

### Отложенная отправка

По умолчанию письма добавляются в очередь и отправляются агентом `CMailEventSender::SendEventStep()`

**Настройка агента:**
- **Путь:** Настройки → Настройки продукта → Параметры системы → Использовать cron для отправки почтовых событий

**Ручной запуск очереди:**
```php
\Bitrix\Main\Mail\EventSender::sendEvents();
```

### Отладка почтовых событий

#### Проверка отправки в лог

```php
\Bitrix\Main\Config\Option::set('main', 'event_log_mail_event', 'Y');
```

#### Логирование в файл

```php
if (\Bitrix\Main\Mail\Event::send([...]) > 0) {
    AddMessage2Log('Email отправлен успешно', 'mail_events');
} else {
    AddMessage2Log('Ошибка отправки email', 'mail_events');
}
```

#### Просмотр очереди

**Путь:** Настройки → Инструменты → Почтовые события

### Частые ошибки

#### 1. Письмо не отправляется

**Причины:**
- Не создан тип события
- Не создан или неактивен шаблон
- Неверный ID сайта
- Агент отправки отключен

**Решение:**
```php
// Проверить наличие типа
$type = \Bitrix\Main\Mail\Internal\EventTypeTable::getList([
    'filter' => ['EVENT_NAME' => 'MY_EVENT']
])->fetch();

// Проверить наличие шаблона
$template = \Bitrix\Main\Mail\Internal\EventMessageTable::getList([
    'filter' => ['EVENT_NAME' => 'MY_EVENT', 'ACTIVE' => 'Y']
])->fetch();
```

#### 2. Макросы не подставляются

**Причина:** Неверные названия переменных в `C_FIELDS`

**Решение:** Имена в `C_FIELDS` должны совпадать с описанием типа события

```php
// В описании типа: #USER_NAME#
// В коде:
'C_FIELDS' => [
    'USER_NAME' => 'Иван' // а не 'NAME'
]
```

#### 3. HTML не работает

**Причина:** Тип письма установлен как "text"

**Решение:**
```php
EventMessageTable::update($id, [
    'BODY_TYPE' => 'html' // вместо 'text'
]);
```

#### 4. Письма дублируются

**Причина:** Несколько активных шаблонов для одного события

**Решение:** Отключить лишние шаблоны или использовать фильтр по ID шаблона в `CEvent::Send()`

### Продвинутые техники

#### 1. Условия в шаблоне (PHP)

```php
// В поле MESSAGE шаблона:
Здравствуйте, #NAME#!

#=if (#STATUS# == 'VIP'):=#
    Вы VIP-клиент, для вас специальное предложение!
#=endif=#

#=if (#DISCOUNT# > 10):=#
    Ваша скидка: #DISCOUNT#%
#=endif=#
```

#### 2. Массовая рассылка

```php
$users = \Bitrix\Main\UserTable::getList([
    'select' => ['ID', 'EMAIL', 'NAME'],
    'filter' => ['ACTIVE' => 'Y']
]);

foreach ($users as $user) {
    \Bitrix\Main\Mail\Event::send([
        'EVENT_NAME' => 'NEWSLETTER',
        'LID' => 's1',
        'C_FIELDS' => [
            'EMAIL' => $user['EMAIL'],
            'NAME' => $user['NAME']
        ]
    ]);
}
```

#### 3. Транзакционные письма с отслеживанием

```php
$result = \Bitrix\Main\Mail\Event::send([
    'EVENT_NAME' => 'ORDER_CONFIRM',
    'LID' => 's1',
    'C_FIELDS' => [
        'ORDER_ID' => $orderId,
        'EMAIL' => $email
    ]
]);

if ($result->isSuccess()) {
    $eventId = $result->getId();
    // Сохранить $eventId для отслеживания
}
```

#### 4. Кастомный From с проверкой домена

```php
EventMessageTable::add([
    'EVENT_NAME' => 'MY_EVENT',
    'EMAIL_FROM' => 'noreply@mydomain.com',
    'EMAIL_FROM_NAME' => 'Моя Компания',
    // ...
]);
```

**Важно:** Email в `EMAIL_FROM` должен существовать или быть разрешен на почтовом сервере

### Интеграция с внешними почтовыми сервисами

#### SMTP настройки

**Путь:** Настройки → Настройки продукта → Параметры системы → Почтовые настройки

```php
// Программная настройка SMTP
\Bitrix\Main\Config\Option::set('main', 'smtp_server', 'smtp.gmail.com');
\Bitrix\Main\Config\Option::set('main', 'smtp_port', '587');
\Bitrix\Main\Config\Option::set('main', 'smtp_login', 'user@gmail.com');
\Bitrix\Main\Config\Option::set('main', 'smtp_password', 'password');
```

#### Webhook для статистики (например, SendGrid, Mailgun)

```php
// webhook.php
use Bitrix\Main\Mail\Internal\EventLogTable;

$data = json_decode(file_get_contents('php://input'), true);

if ($data['event'] === 'delivered') {
    EventLogTable::add([
        'EMAIL' => $data['email'],
        'STATUS' => 'delivered',
        'TIMESTAMP' => time()
    ]);
}
```

---

## Вопросы для собеседования

### Базовые вопросы

**1. Что такое почтовые события в Bitrix?**

Почтовые события — это механизм для автоматической отправки email-уведомлений при возникновении определенных событий в системе. Состоит из типа события, шаблона письма и кода отправки.

**2. Из каких компонентов состоит почтовое событие?**

- Тип события (`EVENT_TYPE`) — определяет ID и описание
- Шаблон письма (`EVENT_MESSAGE`) — содержит структуру письма
- Код отправки — вызов `Event::send()` или `CEvent::Send()`

**3. Как отправить почтовое событие через D7?**

```php
\Bitrix\Main\Mail\Event::send([
    'EVENT_NAME' => 'MY_EVENT',
    'LID' => 's1',
    'C_FIELDS' => [
        'EMAIL' => 'user@example.com',
        'NAME' => 'Иван'
    ]
]);
```

**4. В чем разница между `Event::send()` и `CEvent::Send()`?**

- `Event::send()` — новый D7 API с объектно-ориентированным подходом
- `CEvent::Send()` — старый API, но все еще широко используется
- Оба работают, но D7 API предпочтителен для нового кода

**5. Как создать собственный тип почтового события?**

Через `EventTypeTable::add()`:
```php
EventTypeTable::add([
    'LID' => 'ru',
    'EVENT_NAME' => 'CUSTOM_EVENT',
    'NAME' => 'Название события',
    'DESCRIPTION' => "#EMAIL# - Email\n#NAME# - Имя"
]);
```

**6. Что такое макросы в шаблонах писем?**

Макросы — это переменные вида `#NAME#`, которые заменяются на значения из массива `C_FIELDS` при отправке письма.

**7. Как добавить вложение к письму?**

Через параметр `FILE`:
```php
Event::send([
    'EVENT_NAME' => 'MY_EVENT',
    'LID' => 's1',
    'C_FIELDS' => [...],
    'FILE' => [
        ['name' => 'file.pdf', 'path' => '/path/to/file.pdf']
    ]
]);
```

**8. Где хранятся письма перед отправкой?**

В таблице `b_event` — это очередь почтовых событий. Письма отправляются агентом `CMailEventSender::SendEventStep()`.

**9. Как посмотреть очередь писем?**

**Админка:** Настройки → Инструменты → Почтовые события

**Код:**
```php
$events = \Bitrix\Main\Mail\Internal\EventTable::getList([
    'filter' => ['SUCCESS_EXEC' => 'N']
]);
```

**10. Почему письмо может не отправиться?**

- Не создан тип события или шаблон
- Шаблон неактивен
- Неверный ID сайта в `LID`
- Агент отправки отключен
- Проблемы с почтовым сервером

### Продвинутые вопросы

**11. Как использовать PHP-код в шаблоне письма?**

```php
// В поле MESSAGE шаблона:
#=if (#AMOUNT# > 1000):=#
    Крупный заказ!
#=endif=#
```

Синтаксис: `#=PHP_CODE=#`

**12. Можно ли отправить письмо сразу, минуя очередь?**

Да, через прямую отправку с помощью класса `Bitrix\Main\Mail\Mail`:
```php
$mail = new \Bitrix\Main\Mail\Mail();
$mail->to('user@example.com')
    ->subject('Тема')
    ->message('Текст')
    ->send();
```

Но стандартный подход — использовать Event::send() и агент.

**13. Как отследить успешность отправки?**

```php
$result = Event::send([...]);
if ($result->isSuccess()) {
    $eventId = $result->getId();
    // Сохранить ID для отслеживания
}
```

Затем проверить статус в таблице `b_event`.

**14. Как сделать массовую рассылку без перегрузки?**

Использовать агент с порционной отправкой:
```php
function sendNewsletterAgent() {
    $users = UserTable::getList([
        'limit' => 50,
        'filter' => ['!UF_NEWSLETTER_SENT' => 'Y']
    ]);
    
    foreach ($users as $user) {
        Event::send([...]);
        UserTable::update($user['ID'], ['UF_NEWSLETTER_SENT' => 'Y']);
    }
    
    return '\sendNewsletterAgent();';
}
```

**15. Как настроить SMTP для отправки через внешний сервис?**

**Админка:** Настройки → Параметры системы → Почтовые настройки

Или программно через `Option::set()` для параметров `smtp_server`, `smtp_port`, `smtp_login`, `smtp_password`.

**16. Можно ли отправить письмо нескольким получателям одновременно?**

Да, указать несколько адресов через запятую:
```php
'EMAIL_TO' => 'user1@example.com, user2@example.com'
```

Или в шаблоне: `#EMAIL1#, #EMAIL2#`

**17. Как отключить дублирование писем?**

В старом API четвертый параметр:
```php
CEvent::Send('MY_EVENT', 's1', $fields, 'Y'); // Y = проверять дубли
```

Или отключить дублирующиеся шаблоны в админке.

**18. Как логировать отправку писем?**

```php
Option::set('main', 'event_log_mail_event', 'Y');
```

Письма появятся в **Журнале событий** (Настройки → Инструменты → Журнал событий).

**19. Что делать, если макросы не подставляются?**

Проверить:
1. Имена в `C_FIELDS` совпадают с описанием типа события
2. Макросы в шаблоне написаны в верхнем регистре: `#NAME#`
3. Шаблон активен и привязан к нужному сайту

**20. Как отправить HTML-письмо?**

Установить `BODY_TYPE` в 'html':
```php
EventMessageTable::add([
    'BODY_TYPE' => 'html',
    'MESSAGE' => '<html><body><h1>Привет!</h1></body></html>'
]);
```

---

## Практические задачи

### Задача 1: Уведомление о регистрации
Создать систему отправки welcome-письма при регистрации пользователя с персонализированным контентом.

### Задача 2: Напоминание о брошенной корзине
Реализовать механизм отправки напоминания пользователям, которые добавили товары в корзину, но не оформили заказ в течение 24 часов.

### Задача 3: Еженедельный дайджест
Создать систему еженедельной рассылки дайджеста новостей с возможностью отписки.

**Решения задач находятся в `examples/module-3-3/`**

---

## Полезные ссылки

- [Официальная документация: Почтовые события](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2816)
- [API: Event::send](https://dev.1c-bitrix.ru/api_d7/bitrix/main/mail/event/send.php)
- [API: CEvent](https://dev.1c-bitrix.ru/api_help/main/reference/cevent/index.php)
- [Работа с шаблонами писем](https://dev.1c-bitrix.ru/api_help/main/reference/ceventmessage/index.php)
