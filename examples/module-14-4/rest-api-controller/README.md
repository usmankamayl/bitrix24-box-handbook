# Задача 3: REST API для динамического типа

## Описание

Создание собственного REST API контроллера для работы с динамическими типами CRM через Factory API. Полноценный CRUD API с фильтрацией, сортировкой и пагинацией.

## Что демонстрирует

- ✅ Создание REST API контроллера на основе `Engine\Controller`
- ✅ Настройка фильтров и прав доступа для методов
- ✅ CRUD операции через Factory API
- ✅ Сериализация Item в JSON
- ✅ Обработка ошибок и валидация
- ✅ Фильтрация и пагинация
- ✅ Получение метаданных (поля, стадии)
- ✅ Интеграция с Operation API для проверки прав

## Файлы

### 1. `Dynamic.php`

**Назначение:** REST API контроллер для работы с динамическими типами

**Namespace:** `MyCompany\RestApi\Controller`

**Базовый класс:** `Bitrix\Main\Engine\Controller`

**Доступные методы:**

#### 1. `list` - Получить список элементов

**Endpoint:** `/rest/mycompany.restapi.dynamic.list`

**Параметры:**
- `entityTypeId` (int) - ID типа сущности
- `filter` (array) - Фильтр (опционально)
- `select` (array) - Выбираемые поля (опционально)
- `order` (array) - Сортировка (по умолчанию `['ID' => 'DESC']`)
- `limit` (int) - Лимит (по умолчанию 50)
- `offset` (int) - Смещение (по умолчанию 0)

**Возвращает:**
```json
{
  "result": {
    "items": [...],
    "total": 100,
    "limit": 50,
    "offset": 0
  }
}
```

**Пример:**
```bash
GET /rest/mycompany.restapi.dynamic.list?entityTypeId=128&limit=10
```

#### 2. `get` - Получить элемент по ID

**Endpoint:** `/rest/mycompany.restapi.dynamic.get`

**Параметры:**
- `entityTypeId` (int) - ID типа сущности
- `id` (int) - ID элемента

**Возвращает:**
```json
{
  "result": {
    "item": {
      "ID": 1,
      "TITLE": "Название",
      "STAGE_ID": "NEW",
      ...
    }
  }
}
```

**Пример:**
```bash
GET /rest/mycompany.restapi.dynamic.get?entityTypeId=128&id=1
```

#### 3. `add` - Добавить элемент

**Endpoint:** `/rest/mycompany.restapi.dynamic.add`

**Параметры:**
- `entityTypeId` (int) - ID типа сущности
- `fields` (array) - Поля элемента

**Возвращает:**
```json
{
  "result": {
    "item": {
      "ID": 10,
      ...
    }
  }
}
```

**Пример:**
```bash
POST /rest/mycompany.restapi.dynamic.add
{
  "entityTypeId": 128,
  "fields": {
    "TITLE": "Новый элемент",
    "STAGE_ID": "NEW",
    "UF_CUSTOM_FIELD": "значение"
  }
}
```

#### 4. `update` - Обновить элемент

**Endpoint:** `/rest/mycompany.restapi.dynamic.update`

**Параметры:**
- `entityTypeId` (int) - ID типа сущности
- `id` (int) - ID элемента
- `fields` (array) - Поля для обновления

**Возвращает:**
```json
{
  "result": {
    "item": {
      "ID": 1,
      ...
    }
  }
}
```

**Пример:**
```bash
POST /rest/mycompany.restapi.dynamic.update
{
  "entityTypeId": 128,
  "id": 1,
  "fields": {
    "TITLE": "Обновлённое название",
    "STAGE_ID": "IN_WORK"
  }
}
```

#### 5. `delete` - Удалить элемент

**Endpoint:** `/rest/mycompany.restapi.dynamic.delete`

**Параметры:**
- `entityTypeId` (int) - ID типа сущности
- `id` (int) - ID элемента

**Возвращает:**
```json
{
  "result": {
    "success": true,
    "id": 1
  }
}
```

**Пример:**
```bash
POST /rest/mycompany.restapi.dynamic.delete
{
  "entityTypeId": 128,
  "id": 1
}
```

#### 6. `fields` - Получить описание полей

**Endpoint:** `/rest/mycompany.restapi.dynamic.fields`

**Параметры:**
- `entityTypeId` (int) - ID типа сущности

**Возвращает:**
```json
{
  "result": {
    "fields": {
      "ID": {
        "title": "ID",
        "type": "integer",
        "required": false,
        "multiple": false
      },
      "TITLE": {
        "title": "Название",
        "type": "string",
        "required": true,
        "multiple": false
      },
      ...
    }
  }
}
```

**Пример:**
```bash
GET /rest/mycompany.restapi.dynamic.fields?entityTypeId=128
```

#### 7. `stages` - Получить список стадий

**Endpoint:** `/rest/mycompany.restapi.dynamic.stages`

**Параметры:**
- `entityTypeId` (int) - ID типа сущности
- `categoryId` (int) - ID категории (по умолчанию 0)

**Возвращает:**
```json
{
  "result": {
    "stages": [
      {
        "STATUS_ID": "NEW",
        "NAME": "Новый",
        "SORT": 100,
        "COLOR": "#39A8EF",
        "SEMANTICS": "P"
      },
      ...
    ]
  }
}
```

**Пример:**
```bash
GET /rest/mycompany.restapi.dynamic.stages?entityTypeId=128
```

### 2. `usage_examples.php`

**Назначение:** Примеры использования REST API

**Содержит:**
- 8 примеров использования всех методов API
- Примеры cURL запросов
- Примеры JavaScript (fetch)
- Демонстрация фильтрации и пагинации

## Установка

### Шаг 1: Размещение контроллера

Разместите файл контроллера в модуле:

```
/local/modules/mycompany.restapi/
└── lib/
    └── Controller/
        └── Dynamic.php
```

Или в директории проекта с подключением через autoload:

```
/local/php_interface/
└── classes/
    └── MyCompany/
        └── RestApi/
            └── Controller/
                └── Dynamic.php
```

### Шаг 2: Регистрация namespace

Добавьте в `/local/php_interface/init.php`:

```php
<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    'MyCompany\\RestApi\\Controller\\Dynamic' => '/local/php_interface/classes/MyCompany/RestApi/Controller/Dynamic.php',
]);
```

### Шаг 3: Проверка доступности

Откройте в браузере:

```
https://your-domain.com/rest/mycompany.restapi.dynamic.fields?entityTypeId=128
```

Должен вернуться JSON с описанием полей.

## Использование

### Авторизация

Контроллер использует фильтр `Authentication`, поэтому требуется авторизация.

**Способы авторизации:**

1. **Cookie-based (для браузера):**
```javascript
fetch('/rest/mycompany.restapi.dynamic.list?entityTypeId=128')
  .then(response => response.json())
  .then(data => console.log(data));
```

2. **Basic Auth:**
```bash
curl -u "admin:password" "https://your-domain.com/rest/mycompany.restapi.dynamic.list?entityTypeId=128"
```

3. **OAuth 2.0** (для Bitrix24 облака):
```bash
curl "https://your-domain.bitrix24.ru/rest/mycompany.restapi.dynamic.list?entityTypeId=128&auth=ACCESS_TOKEN"
```

### Примеры использования

#### JavaScript (fetch)

```javascript
// Получить список
async function getItems() {
  const response = await fetch('/rest/mycompany.restapi.dynamic.list?entityTypeId=128&limit=10');
  const data = await response.json();
  
  console.log('Total:', data.result.total);
  console.log('Items:', data.result.items);
}

// Добавить элемент
async function addItem() {
  const formData = new FormData();
  formData.append('entityTypeId', 128);
  formData.append('fields[TITLE]', 'Новый элемент');
  formData.append('fields[STAGE_ID]', 'NEW');
  
  const response = await fetch('/rest/mycompany.restapi.dynamic.add', {
    method: 'POST',
    body: formData
  });
  
  const data = await response.json();
  console.log('Created:', data.result.item);
}

// Обновить элемент
async function updateItem(id) {
  const formData = new FormData();
  formData.append('entityTypeId', 128);
  formData.append('id', id);
  formData.append('fields[TITLE]', 'Обновлённый элемент');
  
  const response = await fetch('/rest/mycompany.restapi.dynamic.update', {
    method: 'POST',
    body: formData
  });
  
  const data = await response.json();
  console.log('Updated:', data.result.item);
}

// Удалить элемент
async function deleteItem(id) {
  const formData = new FormData();
  formData.append('entityTypeId', 128);
  formData.append('id', id);
  
  const response = await fetch('/rest/mycompany.restapi.dynamic.delete', {
    method: 'POST',
    body: formData
  });
  
  const data = await response.json();
  console.log('Deleted:', data.result.success);
}
```

#### PHP (curl)

```php
function restApiRequest($method, $params = [])
{
    $url = 'https://your-domain.com/rest/' . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, 'admin:password'); // Basic Auth
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Получить список
$result = restApiRequest('mycompany.restapi.dynamic.list', [
    'entityTypeId' => 128,
    'filter' => ['=STAGE_ID' => 'NEW'],
    'limit' => 10,
]);

// Добавить элемент
$result = restApiRequest('mycompany.restapi.dynamic.add', [
    'entityTypeId' => 128,
    'fields' => [
        'TITLE' => 'Новый элемент',
        'STAGE_ID' => 'NEW',
    ],
]);

// Обновить элемент
$result = restApiRequest('mycompany.restapi.dynamic.update', [
    'entityTypeId' => 128,
    'id' => 1,
    'fields' => [
        'TITLE' => 'Обновлённый элемент',
    ],
]);
```

#### Python (requests)

```python
import requests

BASE_URL = 'https://your-domain.com/rest'
AUTH = ('admin', 'password')

# Получить список
response = requests.get(
    f'{BASE_URL}/mycompany.restapi.dynamic.list',
    params={'entityTypeId': 128, 'limit': 10},
    auth=AUTH
)
data = response.json()
print('Total:', data['result']['total'])

# Добавить элемент
response = requests.post(
    f'{BASE_URL}/mycompany.restapi.dynamic.add',
    data={
        'entityTypeId': 128,
        'fields[TITLE]': 'Новый элемент',
        'fields[STAGE_ID]': 'NEW'
    },
    auth=AUTH
)
data = response.json()
print('Created:', data['result']['item']['ID'])
```

## Фильтрация

Контроллер поддерживает стандартную фильтрацию Bitrix:

```javascript
fetch('/rest/mycompany.restapi.dynamic.list?' + new URLSearchParams({
  entityTypeId: 128,
  'filter[=STAGE_ID]': 'NEW',
  'filter[>ID]': 10,
  'filter[%TITLE]': 'поиск',
  limit: 20
}))
```

**Операторы фильтрации:**
- `=` - равно
- `!=` - не равно
- `>` - больше
- `<` - меньше
- `>=` - больше или равно
- `<=` - меньше или равно
- `%` - LIKE (содержит)
- `@` - IN (в списке)
- `!@` - NOT IN (не в списке)

**Примеры:**

```php
// Все новые элементы
$filter = ['=STAGE_ID' => 'NEW'];

// ID больше 10
$filter = ['>ID' => 10];

// Название содержит "тест"
$filter = ['%TITLE' => 'тест'];

// Стадии NEW или IN_WORK
$filter = ['@STAGE_ID' => ['NEW', 'IN_WORK']];

// Созданы за последние 7 дней
$filter = ['>CREATED_TIME' => date('Y-m-d', strtotime('-7 days'))];

// Комбинированный фильтр
$filter = [
    '=STAGE_ID' => 'NEW',
    '>ID' => 10,
    '%TITLE' => 'важно',
];
```

## Сортировка

```javascript
fetch('/rest/mycompany.restapi.dynamic.list?' + new URLSearchParams({
  entityTypeId: 128,
  'order[ID]': 'DESC',
  'order[CREATED_TIME]': 'ASC'
}))
```

## Пагинация

```javascript
// Страница 1 (элементы 1-50)
fetch('/rest/mycompany.restapi.dynamic.list?entityTypeId=128&limit=50&offset=0')

// Страница 2 (элементы 51-100)
fetch('/rest/mycompany.restapi.dynamic.list?entityTypeId=128&limit=50&offset=50')

// Страница 3 (элементы 101-150)
fetch('/rest/mycompany.restapi.dynamic.list?entityTypeId=128&limit=50&offset=100')
```

## Обработка ошибок

API возвращает ошибки в стандартном формате:

```json
{
  "error": "ERROR_CODE",
  "error_description": "Описание ошибки"
}
```

**Типичные ошибки:**

- `ACCESS_DENIED` - нет прав доступа
- `NOT_FOUND` - элемент не найден
- `VALIDATION_ERROR` - ошибка валидации полей

**Пример обработки:**

```javascript
fetch('/rest/mycompany.restapi.dynamic.add', {
  method: 'POST',
  body: formData
})
  .then(response => response.json())
  .then(data => {
    if (data.error) {
      console.error('Error:', data.error_description);
    } else {
      console.log('Success:', data.result);
    }
  });
```

## Расширение контроллера

### Добавление своего метода

```php
/**
 * Получить статистику по элементам
 *
 * @param int $entityTypeId
 * @return array
 */
public function statsAction(int $entityTypeId): array
{
    $factory = $this->getFactory($entityTypeId);
    
    if ($factory === null) {
        $this->addError(new Error('Factory not found'));
        return [];
    }
    
    $stages = $factory->getStages();
    $stats = [];
    
    foreach ($stages as $stage) {
        $count = $factory->getItemsCount([
            '=STAGE_ID' => $stage->getStatusId(),
        ]);
        
        $stats[] = [
            'STAGE_ID' => $stage->getStatusId(),
            'NAME' => $stage->getName(),
            'COUNT' => $count,
        ];
    }
    
    return ['stats' => $stats];
}
```

### Добавление валидации

```php
public function addAction(int $entityTypeId, array $fields): ?array
{
    // Валидация перед сохранением
    if (empty($fields['TITLE'])) {
        $this->addError(new Error('Название обязательно'));
        return null;
    }
    
    if (mb_strlen($fields['TITLE']) < 3) {
        $this->addError(new Error('Название должно быть не менее 3 символов'));
        return null;
    }
    
    // ... остальной код
}
```

## Тестирование

### Postman Collection

Создайте коллекцию в Postman:

1. **List Items**
   - Method: GET
   - URL: `{{base_url}}/rest/mycompany.restapi.dynamic.list?entityTypeId=128`

2. **Get Item**
   - Method: GET
   - URL: `{{base_url}}/rest/mycompany.restapi.dynamic.get?entityTypeId=128&id=1`

3. **Add Item**
   - Method: POST
   - URL: `{{base_url}}/rest/mycompany.restapi.dynamic.add`
   - Body (form-data):
     - `entityTypeId`: 128
     - `fields[TITLE]`: "Test Item"

4. **Update Item**
   - Method: POST
   - URL: `{{base_url}}/rest/mycompany.restapi.dynamic.update`
   - Body (form-data):
     - `entityTypeId`: 128
     - `id`: 1
     - `fields[TITLE]`: "Updated"

5. **Delete Item**
   - Method: POST
   - URL: `{{base_url}}/rest/mycompany.restapi.dynamic.delete`
   - Body (form-data):
     - `entityTypeId`: 128
     - `id`: 1

## Безопасность

**Контроллер автоматически:**
- ✅ Проверяет авторизацию (ActionFilter\Authentication)
- ✅ Проверяет права через Operation API
- ✅ Валидирует HTTP методы (GET/POST)
- ✅ Защищён от SQL инъекций (использует ORM)

**Рекомендации:**
- Используйте HTTPS в продакшене
- Ограничьте доступ к API по IP (при необходимости)
- Логируйте все запросы к API
- Установите rate limiting

## Полезные ссылки

- [Engine\Controller](https://dev.1c-bitrix.ru/api_d7/bitrix/main/engine/controller.php)
- [ActionFilter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/engine/actionfilter/)
- [REST API Bitrix](https://dev.1c-bitrix.ru/rest_help/)
- [Factory API](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/service/factory.php)

## Что дальше?

После изучения этой задачи:
1. Установите контроллер и протестируйте все методы
2. Добавьте свои методы (например, `statsAction`, `searchAction`)
3. Интегрируйте API с фронтенд-приложением
4. Добавьте версионирование API
5. Создайте документацию в формате OpenAPI/Swagger
