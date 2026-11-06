
# Модуль 11.1: Пользовательские активити (Custom Activity) в Битрикс24

## Теория

### Что такое пользовательское активити

**Пользовательское активити (custom activity / действие бизнес-процесса)** — это собственное действие, которое разработчик добавляет в конструктор бизнес-процессов и роботов Bitrix24. Пользователь портала перетаскивает его в схему БП или добавляет как робота на стадию сделки, а выполнение кода происходит на вашей стороне (для REST-приложения) или внутри модуля (для коробки).

**Типичные сценарии:**
- Отправка SMS / сообщения в мессенджер (свой шлюз)
- Вызов внешнего API (1С, складская система, банк)
- Генерация документа (PDF, договор по шаблону)
- Нестандартная бизнес-логика, которой нет в штатных действиях
- Интеграция с внутренними системами компании

---

### Два способа создать активити

| Способ | Где работает | Технология |
|---|---|---|
| **Через REST-приложение** (`bizproc.activity.add`) | Облако + коробка | REST + обработчик на вашем сервере |
| **Через модуль (коробка)** (`CBPActivity`) | Только коробка | PHP-класс, наследник `CBPActivity` |

Для собеседования важно знать **оба**, но в облаке чаще всего используют REST-активити — именно так публикуются решения в Bitrix24.Market.

---

### REST-активити: как это работает

#### Механизм

1. Приложение регистрирует активити вызовом `bizproc.activity.add` (один раз, при установке).
2. Администратор добавляет активити в робота или в шаблон БП.
3. При срабатывании Битрикс24 делает **HTTP POST-запрос на `HANDLER` URL** вашего приложения, передавая параметры активити и авторизационные данные.
4. Ваш обработчик выполняет логику и **должен вернуть результат** через `bizproc.activity.completed` (для асинхронных действий) или `bizproc.event.send`.
5. Выходные параметры возвращаются в БП и доступны следующим действиям.

**Важно:** обработчик активити обязан ответить Битриксу быстро (лимит ~30 сек). Если работа долгая — сразу вызывайте `bizproc.event.send` с `EVENT_TOKEN` и завершайте активити асинхронно через `bizproc.activity.completed`.

#### Регистрация: bizproc.activity.add

```php
CRest::call('bizproc.activity.add', [
    'CODE' => 'MY_SEND_SMS',            // Уникальный код активити
    'HANDLER' => 'https://myapp.com/handler/activity.php',
    'AUTH_USER_ID' => 1,                // Пользователь, от чьего имени работает
    'NAME' => 'Отправить SMS клиенту',  // Название в конструкторе
    'DESCRIPTION' => 'Отправка SMS через наш шлюз',
    'TYPE' => 'ACTIVITY',               // ACTIVITY | TRIGGER | ROBOT
    'PROPERTIES' => [                   // Входные параметры (задаются в конструкторе)
        'PHONE' => [
            'Name' => 'Номер телефона',
            'Description' => 'Куда отправлять SMS',
            'Type' => 'string',
            'Required' => 'Y',
        ],
        'TEXT' => [
            'Name' => 'Текст сообщения',
            'Type' => 'text',
            'Required' => 'Y',
        ],
    ],
    'RETURN_PROPERTIES' => [            // Выходные параметры
        'SMS_ID' => [
            'Name' => 'ID сообщения',
            'Type' => 'string',
        ],
        'STATUS' => [
            'Name' => 'Статус отправки',
            'Type' => 'string',
        ],
    ],
    'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL'], // Где доступно
    'FILTER' => [
        'INCLUDE' => [                  // В каких конструкторах показывать
            ['crm', 'CCrmDocumentDeal'],
            ['crm', 'CCrmDocumentLead'],
        ],
        // 'EXCLUDE' => [...]           // Либо исключить
    ],
]);
```

**Типы параметров (`Type`):** `string`, `int`, `double`, `bool`, `text`, `select`, `user`, `date`, `datetime`, `file`, `mail`.

Для `select` добавляется `Options` — массив `{'value': 'label'}`.

**DOCUMENT_TYPE** — привязка к типу документа:
- `['crm', 'CCrmDocumentDeal', 'DEAL']` — сделки
- `['crm', 'CCrmDocumentLead', 'LEAD']` — лиды
- `['crm', 'CCrmDocumentContact', 'CONTACT']` — контакты
- `['lists', 'BizProcDocument', ...]` — списки
- `['bizproc', 'CBPVirtualDocument', ...]` — произвольные

#### Удаление и перечисление

```php
CRest::call('bizproc.activity.list');       // Список всех кастомных активити
CRest::call('bizproc.activity.delete', [
    'CODE' => 'MY_SEND_SMS',
]);
```

`bizproc.activity.delete` — активити удаляется вместе с его использованием в шаблонах! Действия, уже стоящие в БП, перестанут работать.

#### Обработчик: что приходит на HANDLER

При срабатывании Битрикс24 шлёт POST на ваш `HANDLER`:

```php
// В $_REQUEST приходят:
$eventToken   = $_REQUEST['event_token'];    // Токен для завершения асинхронно
$workflowId   = $_REQUEST['workflow_id'];    // ID экземпляра БП
$activityId   = $_REQUEST['activity_id'];
$documentId   = $_REQUEST['document_id'];    // ['crm','CCrmDocumentDeal','DEAL_123']
$properties   = $_REQUEST['properties'];     // Значения входных параметров
$auth         = $_REQUEST['auth'];           // access_token, domain, member_id
```

#### Вариант A: синхронное завершение (robot)

Для **робота** можно вернуть результат прямо в ответе на POST — но REST-активити всё равно завершается вызовом `bizproc.activity.completed`. Синхронно робот завершается только если в обработчике сразу вернуть `200` с телом ответа — так работают «мгновенные» роботы; надёжнее универсальный способ:

#### Вариант B: асинхронное завершение (универсальный)

```php
// 1. Подтверждаем получение, чтобы БП ждал
CRest::call('bizproc.event.send', [
    'EVENT_TOKEN' => $eventToken,
    'RETURN_VALUES' => [],
    'LOG_MESSAGE' => 'Начинаю отправку SMS',
]);

// 2. Выполняем работу (можно отложить в очередь)
$smsId = sendSmsViaGateway($properties['PHONE'], $properties['TEXT']);

// 3. Завершаем активити, передавая выходные параметры
CRest::call('bizproc.activity.completed', [
    'EVENT_TOKEN' => $eventToken,
    'OUTPUT' => [
        'SMS_ID' => $smsId,
        'STATUS' => 'sent',
    ],
    'LOG_MESSAGE' => 'SMS отправлено',
]);
```

**EVENT_TOKEN** действует 30 дней, но обработчик обязан ответить Битриксу на исходный POST в течение ~30 секунд — поэтому долгую работу выносят в очередь/крон, а на POST отвечают сразу.

**LOG_MESSAGE** пишется в журнал БП — бесценно при отладке.

---

### Robot vs Activity vs Trigger

- **ROBOT** (`TYPE => 'ROBOT'`) — доступен в роботах на стадиях сделки/лида и в БП. Выполняется мгновенно при переходе на стадию.
- **ACTIVITY** — только в конструкторе бизнес-процессов.
- **TRIGGER** — регистрирует триггер (`bizproc.robot.add` для роботов-триггеров — см. модуль 11.2).

На практике для интеграционных действий почти всегда используют `TYPE => 'ROBOT'` — так его видят и в роботах, и в БП.

---

### Кастомные роботы через приложение (инлайн-роботы)

Альтернатива `bizproc.activity.add` — метод `bizproc.robot.add`:

```php
CRest::call('bizproc.robot.add', [
    'CODE' => 'MY_SEND_SMS',
    'HANDLER' => 'https://myapp.com/handler/robot.php',
    'AUTH_USER_ID' => 1,
    'NAME' => 'Отправить SMS',
    'USE_SUBSCRIPTION' => 'Y',   // Y — ждать bizproc.robot.completed
    'PROPERTIES' => [...],
    'RETURN_PROPERTIES' => [...],
    'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL'],
]);
```

Завершение: `bizproc.robot.completed` (аналогично `activity.completed`). Список — `bizproc.robot.list`, удаление — `bizproc.robot.delete`.

**Разница на практике минимальна:** `robot.*` — только для роботов; `activity.*` — универсальнее (роботы + БП + триггеры).

---

### Коробка: CBPActivity

В коробочной версии активити пишется как PHP-класс в модуле:

```
local/modules/mymodule/install/activities/sendsms/
├── sendsms.php          # Класс CBPSendSmsActivity
├── .description.php     # Описание и параметры
└── icon.gif
```

```php
class CBPSendSmsActivity extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = [
            'Title' => '',
            'Phone' => '',      // Входные параметры
            'Text' => '',
            'SmsId' => null,    // Выходной параметр (внизу класса — arPropertiesMap)
        ];
    }

    public function Execute()
    {
        $smsId = SmsGateway::send($this->Phone, $this->Text);
        $this->SmsId = $smsId;               // Выходной параметр
        $this->WriteToTrackingService("SMS отправлено"); // Лог в журнал БП
        return CBPActivityExecutionStatus::Closed;
    }

    public static function GetPropertiesDialog($docType, $name, $props, $runtime, $root)
    {
        return new CBPActivityPropertiesDialog(...); // Диалог настроек
    }

    public static function GetPropertiesDialogValues($docType, $name, &$props, $runtime, $root, &$errors)
    {
        // Валидация и сохранение параметров диалога
        return true;
    }
}
```

`.description.php`:

```php
$arActivityDescription = [
    'NAME' => 'Отправить SMS',
    'DESCRIPTION' => 'Отправка SMS через шлюз',
    'TYPE' => ['activity'],
    'CLASS' => 'SendSmsActivity',
    'JSCLASS' => 'BizProcActivity',
    'CATEGORY' => ['ID' => 'document', 'OWN_ID' => 'my', 'OWN_NAME' => 'Мой модуль'],
    'FILTER' => ['INCLUDE' => [['crm', 'CCrmDocumentDeal']]],
    'RETURN' => ['SmsId' => ['NAME' => 'ID SMS', 'TYPE' => 'string']],
];
```

Активити появляется в конструкторе после установки модуля.

---

### Вопросы для собеседования

**1. Чем пользовательское активити отличается от робота?**
Робот — действие, привязанное к стадии сущности CRM (срабатывает при переходе на стадию). Активити — действие внутри бизнес-процесса. REST-активити с `TYPE => 'ROBOT'` доступно в обоих местах. Технически у роботов и активити разные методы регистрации (`bizproc.robot.add` vs `bizproc.activity.add`), но механизм один — POST на обработчик + завершение через `*.completed`.

**2. Что такое EVENT_TOKEN и зачем он нужен?**
Токен, который Битрикс24 передаёт на обработчик при запуске активити. Служит для асинхронного завершения: приложение вызывает `bizproc.activity.completed` с этим токеном, и Битрикс связывает результат с конкретным экземпляром БП. Без токена завершить выполнение нельзя.

**3. Почему обработчик активити должен отвечать быстро?**
Битрикс24 ждёт ответа на исходный POST-запрос ~30 секунд. Если обработчик «зависает», активити уходит в ошибку/таймаут. Поэтому долгие операции выносят в фон: на POST отвечают сразу (через `bizproc.event.send`), а работу выполняют асинхронно, затем завершают через `bizproc.activity.completed`.

**4. Как активити получает свои параметры?**
Параметры, описанные в `PROPERTIES` при регистрации, настраиваются пользователем в конструкторе БП/робота (можно подставлять поля документа и результаты предыдущих действий). При запуске значения приходят на обработчик в `$_REQUEST['properties']`.

**5. Чем облачная и коробочная реализации кастомных активити отличаются?**
Облако: регистрация через `bizproc.activity.add` (REST), логика на внешнем сервере. Коробка: класс `CBPActivity` внутри модуля, выполняется на сервере Битрикса. В коробке есть прямой доступ к БД и API, в облаке — только REST.

**6. Что произойдёт, если удалить активити, которое используется в шаблоне БП?**
`bizproc.activity.delete` удалит активити полностью. Экземпляры БП, которые дойдут до этого шага, получат ошибку «активити не найдено». Перед удалением надо убрать его из всех шаблонов.

**7. Как передать результат из активити дальше по бизнес-процессу?**
Через `RETURN_PROPERTIES` при регистрации и поле `OUTPUT` в `bizproc.activity.completed`. Значения появляются в списке «Результаты выполнения» и доступны для подстановки в следующих действиях.

**8. Какие типы документов поддерживаются активити?**
Любые сущности, для которых работают БП: лиды, сделки, контакты, компании, счета, смарт-процессы (SPA), элементы списков, задачи. Привязка задаётся `DOCUMENT_TYPE` и `FILTER` при регистрации.

**9. Можно ли переиспользовать один код активити для разных сущностей?**
Да — в `FILTER['INCLUDE']` перечисляются все нужные `DOCUMENT_TYPE`. В обработчике тип и ID документа приходят в `document_id` — парсите их и ветвите логику.

**10. Как отлаживать кастомное активити?**
- `LOG_MESSAGE` в `bizproc.event.send` / `completed` — пишется в журнал БП (карточка → бизнес-процесс → журнал).
- `bizproc.workflow.instances` — состояние запущенных БП.
- Свой лог на стороне приложения — логируйте входящий `$_REQUEST` и каждый REST-вызов.

---

### Частые ошибки

- ❌ **Долгий обработчик.** Крон-задача внутри POST → таймаут. Нужна очередь.
- ❌ **Нет `bizproc.event.send`.** БП не знает, что активити получено — статус «выполняется» вечно.
- ❌ **`OUTPUT` без `RETURN_PROPERTIES`.** Значения вернулись, но в конструкторе их не видно — параметр надо объявить.
- ❌ **`AUTH_USER_ID` без прав.** Все REST-вызовы обработчика идут от этого пользователя — он должен иметь доступ к CRM.
- ❌ **Одна точка входа для add и выполнения.** Регистрация — одноразовая операция, обработчик — отдельный endpoint.
- ❌ **`HANDLER` на HTTP без SSL.** Битрикс24 требует HTTPS.

---

### Практические задачи

- Задача 1: REST-активити «Отправить сообщение в Telegram» — регистрация, обработчик, выходной параметр `MESSAGE_ID`
- Задача 2: Робот «Проверить ИНН в реестре» — вызов внешнего API, возврат результата
- Задача 3: Логирование и отладка — активити с журналированием каждого шага

**Решения задач находятся в `examples/module-11-1/`**

---

### Ссылки

- [bizproc.activity.add](https://apidocs.bitrix24.ru/api-reference/bizproc/bizproc-activity-add.html)
- [bizproc.activity.completed](https://apidocs.bitrix24.ru/api-reference/bizproc/bizproc-activity-completed.html)
- [bizproc.robot.add](https://apidocs.bitrix24.ru/api-reference/bizproc/bizproc-robot-add.html)
- [Кастомные действия в коробке (CBPActivity)](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=57&CHAPTER_ID=05797)
