# Модуль 3.1: REST API Bitrix24

## Теория

### Что такое REST API Bitrix24?

REST API Bitrix24 — это интерфейс для взаимодействия с данными портала Bitrix24 через HTTP-запросы. Позволяет создавать интеграции, приложения и автоматизировать бизнес-процессы.

### Основные концепции

#### 1. Webhook vs OAuth

**Webhook (входящий вебхук)**
- Простой способ авторизации для серверных приложений
- URL с токеном: `https://ваш-портал.bitrix24.ru/rest/1/webhook_code/`
- Права настраиваются при создании
- Подходит для внутренних интеграций

**OAuth 2.0**
- Для приложений в Bitrix24.Маркет
- Требует CLIENT_ID и CLIENT_SECRET
- Пользователь явно дает разрешения
- Более безопасный, но сложнее в настройке

#### 2. Структура запросов

**Формат URL:**
```
https://ваш-портал.bitrix24.ru/rest/METHOD_NAME
```

**С webhook:**
```
https://ваш-портал.bitrix24.ru/rest/1/webhook_code/METHOD_NAME
```

**Методы запросов:**
- `GET` — получение данных
- `POST` — создание/изменение данных (основной метод)

#### 3. Пакетные запросы (batch)

Позволяют выполнить до 50 запросов за один HTTP-вызов:

```php
$result = CRest::call('batch', [
    'halt' => 0, // продолжать при ошибках
    'cmd' => [
        'get_user' => 'user.current',
        'get_deals' => 'crm.deal.list?filter[STAGE_ID]=NEW',
        'add_contact' => 'crm.contact.add?fields[NAME]=John&fields[LAST_NAME]=Doe'
    ]
]);
```

#### 4. Pagination (постраничная навигация)

Bitrix24 возвращает максимум 50 элементов за запрос:

```php
$result = CRest::call('crm.deal.list', [
    'start' => 0,  // начальная позиция
    'limit' => 50  // не более 50
]);

// Получить все элементы
$allDeals = [];
$start = 0;
do {
    $result = CRest::call('crm.deal.list', [
        'start' => $start
    ]);
    $allDeals = array_merge($allDeals, $result['result']);
    $start += 50;
} while (!empty($result['next']));
```

### Основные группы методов

#### 1. CRM методы

**Лиды:**
- `crm.lead.add` — создать лид
- `crm.lead.get` — получить лид
- `crm.lead.list` — список лидов
- `crm.lead.update` — обновить лид
- `crm.lead.delete` — удалить лид

**Сделки:**
- `crm.deal.add`
- `crm.deal.get`
- `crm.deal.list`
- `crm.deal.update`
- `crm.deal.delete`

**Контакты:**
- `crm.contact.add`
- `crm.contact.get`
- `crm.contact.list`
- `crm.contact.update`

**Компании:**
- `crm.company.add`
- `crm.company.get`
- `crm.company.list`
- `crm.company.update`

#### 2. Задачи (tasks)

- `tasks.task.add` — создать задачу
- `tasks.task.get` — получить задачу
- `tasks.task.list` — список задач
- `tasks.task.update` — обновить задачу
- `tasks.task.complete` — завершить задачу

#### 3. Пользователи

- `user.current` — текущий пользователь
- `user.get` — получить пользователя
- `user.search` — поиск пользователей

#### 4. Диск (файлы)

- `disk.storage.getlist` — список хранилищ
- `disk.folder.getchildren` — содержимое папки
- `disk.file.uploadfile` — загрузить файл

### Работа с пользовательскими полями (UF)

**Получить список полей:**
```php
$fields = CRest::call('crm.deal.fields');
```

**Создать пользовательское поле:**
```php
CRest::call('crm.deal.userfield.add', [
    'fields' => [
        'FIELD_NAME' => 'UF_CRM_CUSTOM_FIELD',
        'USER_TYPE_ID' => 'string',
        'EDIT_FORM_LABEL' => ['ru' => 'Мое поле'],
        'LIST_COLUMN_LABEL' => ['ru' => 'Мое поле']
    ]
]);
```

### Обработка ошибок

```php
$result = CRest::call('crm.deal.get', ['ID' => 123]);

if (isset($result['error'])) {
    // Ошибка
    echo "Код: " . $result['error'];
    echo "Описание: " . $result['error_description'];
} else {
    // Успех
    $deal = $result['result'];
}
```

### События (Event Handlers)

Bitrix24 может отправлять уведомления на ваш сервер при изменениях:

**Регистрация обработчика:**
```php
CRest::call('event.bind', [
    'event' => 'ONCRMDEALADD',
    'handler' => 'https://your-domain.com/handler.php'
]);
```

**Типы событий:**
- `ONCRMDEALADD` — создана сделка
- `ONCRMDEALUPDATE` — обновлена сделка
- `ONTASKADД` — создана задача
- `ONTASKUPDATE` — обновлена задача

## Вопросы для собеседования

### 1. В чем разница между webhook и OAuth авторизацией?

**Ответ:**
- **Webhook** — упрощенная авторизация с токеном в URL, подходит для внутренних интеграций, права настраиваются заранее
- **OAuth** — полноценная авторизация через протокол OAuth 2.0, требуется для приложений в Маркете, пользователь явно дает права

### 2. Как получить больше 50 элементов через API?

**Ответ:**
Использовать постраничную навигацию с параметром `start`:
```php
$start = 0;
do {
    $result = CRest::call('crm.deal.list', ['start' => $start]);
    // обработка результатов
    $start += 50;
} while (!empty($result['next']));
```

### 3. Что такое batch-запросы и зачем они нужны?

**Ответ:**
Batch-запросы позволяют выполнить до 50 API-методов за один HTTP-запрос. Это снижает количество обращений к API и ускоряет работу:
```php
CRest::call('batch', [
    'cmd' => [
        'cmd1' => 'crm.deal.get?ID=1',
        'cmd2' => 'crm.contact.get?ID=2'
    ]
]);
```

### 4. Как обработать ошибки REST API?

**Ответ:**
Проверять наличие ключа `error` в ответе:
```php
if (isset($result['error'])) {
    // обработка ошибки
    $errorCode = $result['error'];
    $errorDescription = $result['error_description'];
}
```

### 5. Что такое события (webhooks) в Bitrix24?

**Ответ:**
События позволяют Bitrix24 отправлять HTTP-запросы на ваш сервер при изменениях данных (создание сделки, задачи и т.д.). Регистрируются через `event.bind`.

### 6. Какие основные группы методов есть в REST API?

**Ответ:**
- CRM (лиды, сделки, контакты, компании)
- Задачи (tasks)
- Пользователи (user)
- Диск (файлы)
- Бизнес-процессы
- Календарь
- Телефония

### 7. Как создать пользовательское поле через API?

**Ответ:**
Использовать метод `*.userfield.add`:
```php
CRest::call('crm.deal.userfield.add', [
    'fields' => [
        'FIELD_NAME' => 'UF_CRM_CUSTOM',
        'USER_TYPE_ID' => 'string',
        'EDIT_FORM_LABEL' => ['ru' => 'Название']
    ]
]);
```

### 8. Как фильтровать и сортировать данные?

**Ответ:**
Использовать параметры `filter`, `order`, `select`:
```php
CRest::call('crm.deal.list', [
    'filter' => ['STAGE_ID' => 'NEW', '>OPPORTUNITY' => 1000],
    'order' => ['DATE_CREATE' => 'DESC'],
    'select' => ['ID', 'TITLE', 'OPPORTUNITY']
]);
```

### 9. Какие ограничения есть у REST API?

**Ответ:**
- Не более 50 элементов за запрос
- Не более 50 команд в batch
- Лимиты на количество запросов (зависит от тарифа)
- Таймаут выполнения запроса

### 10. Как загрузить файл через REST API?

**Ответ:**
Использовать метод `disk.file.uploadfile` или передать файл в base64:
```php
CRest::call('disk.folder.uploadfile', [
    'id' => $folderId,
    'fileContent' => base64_encode(file_get_contents($filePath)),
    'data' => ['NAME' => 'document.pdf']
]);
```

## Разбор частых ошибок

### 1. "ERROR_CORE: Недостаточно прав"
**Причина:** Webhook или приложение не имеет нужных прав  
**Решение:** Проверить права webhook или запросить дополнительные scope при OAuth

### 2. "Превышен лимит запросов"
**Причина:** Слишком много обращений к API  
**Решение:** Использовать batch-запросы, кешировать данные, добавить паузы

### 3. "Invalid or expired token"
**Причина:** Токен доступа устарел (для OAuth)  
**Решение:** Обновить токен через `refresh_token`

### 4. Получение пустого результата при большом количестве данных
**Причина:** Не используется pagination  
**Решение:** Реализовать цикл с параметром `start`

### 5. "METHOD_NOT_FOUND"
**Причина:** Неправильное имя метода или метод недоступен на вашем тарифе  
**Решение:** Проверить документацию и доступность метода

## Практические задачи

1. **Интеграция с CRM** — получение и создание сделок
2. **Синхронизация задач** — двусторонняя синхронизация задач
3. **Webhook обработчик** — обработка событий от Bitrix24

**Решения задач находятся в `examples/module-3-1/`**

## Ссылки на документацию

- [REST API Bitrix24 — Официальная документация](https://dev.1c-bitrix.ru/rest_help/)
- [CRM методы](https://dev.1c-bitrix.ru/rest_help/crm/index.php)
- [Задачи API](https://dev.1c-bitrix.ru/rest_help/tasks/index.php)
- [OAuth авторизация](https://dev.1c-bitrix.ru/rest_help/general/oauth.php)
- [Batch запросы](https://dev.1c-bitrix.ru/rest_help/general/batch.php)

## Полезные советы

1. **Используйте batch** для множественных операций
2. **Кешируйте справочники** (пользователи, статусы) — они редко меняются
3. **Логируйте запросы** для отладки
4. **Обрабатывайте ошибки** — API может быть недоступен
5. **Следите за лимитами** — добавляйте задержки между запросами
6. **Используйте select** — запрашивайте только нужные поля
7. **Pagination обязательна** для работы с большими объемами

## Вопросы для самопроверки

1. Как создать webhook в Bitrix24?
2. В чем преимущество batch-запросов?
3. Как получить все сделки, если их больше 1000?
4. Какие параметры доступны для фильтрации?
5. Как отловить событие создания лида?
6. Что делать при ошибке "Недостаточно прав"?
7. Как передать файл через REST API?
8. В чем разница между `crm.deal.get` и `crm.deal.list`?
9. Как обновить несколько сделок одним запросом?
10. Какие есть лимиты на запросы к API?
