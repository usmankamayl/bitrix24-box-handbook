# Модуль 7.2: Работа с полями CRM (пользовательские поля, множественные)

## Теория

### Типы полей в Bitrix24 CRM

В Bitrix24 CRM существует два основных типа полей:

#### 1. **Стандартные поля** (Standard Fields)
Это встроенные поля, которые есть в каждой сущности по умолчанию:
- `ID` — уникальный идентификатор
- `TITLE` — название (для лидов, сделок, компаний)
- `NAME`, `LAST_NAME` — имя и фамилия (для контактов, лидов)
- `PHONE`, `EMAIL` — контактные данные
- `DATE_CREATE`, `DATE_MODIFY` — даты создания и изменения
- `ASSIGNED_BY_ID` — ответственный пользователь
- `STAGE_ID` — стадия сделки
- `OPPORTUNITY` — сумма сделки

**Особенности стандартных полей:**
- Нельзя удалить или изменить их тип
- Имеют фиксированную структуру
- Поддерживаются всеми методами API

#### 2. **Пользовательские поля** (User Fields / Custom Fields)
Это поля, которые можно создавать для расширения стандартной функциональности.

**Префикс:** `UF_CRM_`

**Примеры:**
- `UF_CRM_1234567890` — пользовательское поле с ID
- `UF_CRM_CUSTOM_SOURCE` — источник (кастомное поле)
- `UF_CRM_DISCOUNT_PERCENT` — процент скидки

### Множественные поля (Multiple Fields)

Множественные поля — это поля, которые могут содержать несколько значений одновременно.

**Примеры:**
- `PHONE` — контакт может иметь несколько телефонов
- `EMAIL` — несколько email адресов
- `WEB` — несколько веб-сайтов
- `IM` — несколько мессенджеров (Skype, Telegram, WhatsApp)

**Структура множественного поля:**
```json
{
    "PHONE": [
        {
            "ID": "123",
            "VALUE_TYPE": "WORK",
            "VALUE": "+79991234567",
            "TYPE_ID": "PHONE"
        },
        {
            "ID": "124",
            "VALUE_TYPE": "MOBILE",
            "VALUE": "+79997654321",
            "TYPE_ID": "PHONE"
        }
    ]
}
```

**Поля структуры:**
- `ID` — уникальный ID значения (автоматически генерируется)
- `VALUE_TYPE` — тип значения (WORK, MOBILE, HOME, OTHER и т.д.)
- `VALUE` — само значение (телефон, email, URL)
- `TYPE_ID` — тип поля (PHONE, EMAIL, WEB, IM)

### Типы пользовательских полей

Bitrix24 поддерживает следующие типы пользовательских полей:

#### **string** — Строка
Однострочное текстовое поле.
```json
{
    "FIELD_NAME": "UF_CRM_CUSTOM_TEXT",
    "USER_TYPE_ID": "string",
    "EDIT_FORM_LABEL": "Дополнительная информация"
}
```

#### **double** — Число
Числовое поле с десятичными знаками.
```json
{
    "FIELD_NAME": "UF_CRM_DISCOUNT",
    "USER_TYPE_ID": "double",
    "EDIT_FORM_LABEL": "Скидка (%)"
}
```

#### **boolean** — Да/Нет
Чекбокс.
```json
{
    "FIELD_NAME": "UF_CRM_IS_VIP",
    "USER_TYPE_ID": "boolean",
    "EDIT_FORM_LABEL": "VIP клиент?"
}
```

#### **datetime** — Дата и время
Поле для выбора даты и времени.
```json
{
    "FIELD_NAME": "UF_CRM_NEXT_CALL",
    "USER_TYPE_ID": "datetime",
    "EDIT_FORM_LABEL": "Дата следующего звонка"
}
```

#### **enumeration** — Список
Выпадающий список с фиксированными значениями.
```json
{
    "FIELD_NAME": "UF_CRM_PRIORITY",
    "USER_TYPE_ID": "enumeration",
    "EDIT_FORM_LABEL": "Приоритет",
    "LIST": [
        {"VALUE": "Низкий"},
        {"VALUE": "Средний"},
        {"VALUE": "Высокий"},
        {"VALUE": "Критический"}
    ]
}
```

#### **file** — Файл
Поле для загрузки файлов.
```json
{
    "FIELD_NAME": "UF_CRM_CONTRACT",
    "USER_TYPE_ID": "file",
    "EDIT_FORM_LABEL": "Договор"
}
```

#### **url** — Ссылка
Поле для хранения URL.
```json
{
    "FIELD_NAME": "UF_CRM_WEBSITE",
    "USER_TYPE_ID": "url",
    "EDIT_FORM_LABEL": "Личный сайт"
}
```

#### **crm_status** — Статус из справочника
Привязка к справочнику статусов CRM.
```json
{
    "FIELD_NAME": "UF_CRM_INDUSTRY",
    "USER_TYPE_ID": "crm_status",
    "SETTINGS": {
        "ENTITY_TYPE": "STATUS"
    }
}
```

#### **crm** — Привязка к сущности CRM
Связь с лидом, сделкой, контактом или компанией.
```json
{
    "FIELD_NAME": "UF_CRM_PARENT_COMPANY",
    "USER_TYPE_ID": "crm",
    "SETTINGS": {
        "LEAD": "Y",
        "CONTACT": "Y",
        "COMPANY": "Y",
        "DEAL": "Y"
    }
}
```

### REST API для работы с пользовательскими полями

#### Получить список всех полей сущности
```php
GET /crm.contact.fields
GET /crm.deal.fields
GET /crm.company.fields
GET /crm.lead.fields
```

**Результат:**
```json
{
    "result": {
        "ID": {"type": "integer", "isReadOnly": true},
        "NAME": {"type": "string", "isRequired": false},
        "UF_CRM_CUSTOM_FIELD": {
            "type": "string",
            "isRequired": false,
            "isReadOnly": false,
            "isImmutable": false,
            "isMultiple": false,
            "isDynamic": false,
            "title": "Пользовательское поле"
        }
    }
}
```

#### Создать пользовательское поле
```php
POST /crm.contact.userfield.add
{
    "fields": {
        "FIELD_NAME": "UF_CRM_1234567890",
        "USER_TYPE_ID": "string",
        "EDIT_FORM_LABEL": "Дополнительная информация",
        "LIST_COLUMN_LABEL": "Доп. инфо",
        "MULTIPLE": "N",  // Y - множественное, N - одиночное
        "MANDATORY": "N"  // Y - обязательное, N - необязательное
    }
}
```

#### Обновить пользовательское поле
```php
POST /crm.contact.userfield.update
{
    "id": 123,
    "fields": {
        "EDIT_FORM_LABEL": "Новое название"
    }
}
```

#### Удалить пользовательское поле
```php
POST /crm.contact.userfield.delete
{
    "id": 123
}
```

### Работа с множественными полями

#### Добавление нескольких телефонов при создании контакта
```php
POST /crm.contact.add
{
    "fields": {
        "NAME": "Иван",
        "LAST_NAME": "Петров",
        "PHONE": [
            {"VALUE": "+79991234567", "VALUE_TYPE": "WORK"},
            {"VALUE": "+79997654321", "VALUE_TYPE": "MOBILE"}
        ],
        "EMAIL": [
            {"VALUE": "ivan@work.com", "VALUE_TYPE": "WORK"},
            {"VALUE": "ivan@personal.com", "VALUE_TYPE": "HOME"}
        ]
    }
}
```

#### Обновление множественного поля (добавление нового телефона)
```php
POST /crm.contact.update
{
    "id": 123,
    "fields": {
        "PHONE": [
            {"ID": "456", "VALUE": "+79991234567", "VALUE_TYPE": "WORK"},  // Существующий
            {"VALUE": "+79993334455", "VALUE_TYPE": "OTHER"}  // Новый (без ID)
        ]
    }
}
```

**Важно:**
- Если указать `ID` существующего значения — оно обновится
- Если НЕ указать `ID` — добавится новое значение
- Если не включить существующее значение в массив — оно удалится

#### Удаление одного значения из множественного поля
```php
POST /crm.contact.update
{
    "id": 123,
    "fields": {
        "PHONE": [
            {"ID": "456", "VALUE": "+79991234567", "VALUE_TYPE": "WORK"}
            // Телефон с ID "457" был убран из массива → удалится
        ]
    }
}
```

### Работа с пользовательскими полями типа "список" (enumeration)

#### Создание поля со списком
```php
POST /crm.deal.userfield.add
{
    "fields": {
        "FIELD_NAME": "UF_CRM_PRIORITY",
        "USER_TYPE_ID": "enumeration",
        "EDIT_FORM_LABEL": "Приоритет сделки",
        "LIST": [
            {"VALUE": "Низкий", "SORT": 100},
            {"VALUE": "Средний", "SORT": 200},
            {"VALUE": "Высокий", "SORT": 300},
            {"VALUE": "Критический", "SORT": 400}
        ]
    }
}
```

#### Установка значения списка
```php
POST /crm.deal.update
{
    "id": 789,
    "fields": {
        "UF_CRM_PRIORITY": 3  // ID значения "Высокий" из списка
    }
}
```

**Получение списка значений:**
```php
GET /crm.deal.userfield.get?id=123
```

### Работа с файлами в пользовательских полях

#### Загрузка файла в пользовательское поле
```php
POST /crm.contact.add
{
    "fields": {
        "NAME": "Иван",
        "UF_CRM_CONTRACT": [
            "contract.pdf",  // Имя файла
            base64_encode(file_get_contents('contract.pdf'))  // Base64 содержимое
        ]
    }
}
```

#### Множественная загрузка файлов
```php
POST /crm.contact.update
{
    "id": 123,
    "fields": {
        "UF_CRM_DOCUMENTS": [
            ["doc1.pdf", base64_encode(file_get_contents('doc1.pdf'))],
            ["doc2.pdf", base64_encode(file_get_contents('doc2.pdf'))]
        ]
    }
}
```

### Фильтрация по пользовательским полям

#### Фильтр по текстовому полю
```php
GET /crm.deal.list?filter[UF_CRM_CUSTOM_SOURCE]=Google Ads
```

#### Фильтр по числовому полю (больше/меньше)
```php
GET /crm.deal.list?filter[>=UF_CRM_DISCOUNT]=10
GET /crm.deal.list?filter[<UF_CRM_DISCOUNT]=50
```

#### Фильтр по дате
```php
GET /crm.deal.list?filter[>=UF_CRM_NEXT_CALL]=2024-01-01T00:00:00
```

#### Фильтр по булевому полю
```php
GET /crm.contact.list?filter[UF_CRM_IS_VIP]=1  // Y или 1
```

### Частые ошибки и best practices

**❌ Ошибка 1: Неправильная структура множественного поля**
```php
// ❌ ПЛОХО
"PHONE": "+79991234567"  // Строка вместо массива

// ✅ ХОРОШО
"PHONE": [
    {"VALUE": "+79991234567", "VALUE_TYPE": "WORK"}
]
```

**❌ Ошибка 2: Потеря существующих значений при обновлении**
При обновлении множественного поля нужно передавать ВСЕ значения (и старые, и новые), иначе старые удалятся.

```php
// ❌ ПЛОХО: добавляем второй телефон, забыв первый
POST /crm.contact.update
{
    "id": 123,
    "fields": {
        "PHONE": [
            {"VALUE": "+79993334455", "VALUE_TYPE": "MOBILE"}
            // Первый телефон удалится!
        ]
    }
}

// ✅ ХОРОШО: сначала читаем, потом добавляем
// 1. GET /crm.contact.get?id=123
// 2. Забираем существующие PHONE
// 3. Добавляем новый
// 4. Отправляем обновление
POST /crm.contact.update
{
    "id": 123,
    "fields": {
        "PHONE": [
            {"ID": "456", "VALUE": "+79991234567", "VALUE_TYPE": "WORK"},  // Старый
            {"VALUE": "+79993334455", "VALUE_TYPE": "MOBILE"}  // Новый
        ]
    }
}
```

**❌ Ошибка 3: Некорректное имя пользовательского поля**
Пользовательские поля должны иметь префикс `UF_CRM_` и уникальное имя.

```php
// ❌ ПЛОХО
"FIELD_NAME": "CUSTOM_FIELD"  // Без префикса

// ✅ ХОРОШО
"FIELD_NAME": "UF_CRM_CUSTOM_FIELD"
```

**❌ Ошибка 4: Неправильный тип данных**
```php
// ❌ ПЛОХО: передаем строку в числовое поле
"UF_CRM_DISCOUNT": "15"  // Строка вместо числа

// ✅ ХОРОШО
"UF_CRM_DISCOUNT": 15  // Число
```

**✅ Best Practice 1: Проверяйте существование поля перед использованием**
```php
// Получаем все поля
GET /crm.contact.fields

// Проверяем, есть ли UF_CRM_CUSTOM_FIELD
if (isset($fields['UF_CRM_CUSTOM_FIELD'])) {
    // Работаем с полем
}
```

**✅ Best Practice 2: Используйте понятные названия**
```php
// ❌ ПЛОХО
"UF_CRM_123"

// ✅ ХОРОШО
"UF_CRM_NEXT_CALL_DATE"
"UF_CRM_IS_VIP_CLIENT"
"UF_CRM_DISCOUNT_PERCENT"
```

**✅ Best Practice 3: Валидируйте данные перед записью**
```php
// Проверяем формат телефона
if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
    throw new Exception("Некорректный формат телефона");
}

// Проверяем email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception("Некорректный email");
}
```

**✅ Best Practice 4: Кешируйте метаданные полей**
Метаданные полей (типы, названия, списки значений) меняются редко, их можно кешировать.

```php
// Кешируем на 1 час
$cacheKey = 'crm_contact_fields';
$fields = $cache->get($cacheKey);

if (!$fields) {
    $fields = $api->call('crm.contact.fields');
    $cache->set($cacheKey, $fields, 3600);
}
```

### Схема работы с множественными полями

```
┌─────────────────────────────────────────────────────┐
│   1. Чтение сущности                                │
│      GET /crm.contact.get?id=123                    │
│                                                     │
│   2. Получение текущих значений PHONE               │
│      [                                              │
│        {"ID": "456", "VALUE": "+7999...", ...}     │
│      ]                                              │
│                                                     │
│   3. Добавление нового значения                     │
│      [                                              │
│        {"ID": "456", ...},  // Существующий        │
│        {"VALUE": "+7888...", ...}  // Новый (без ID)│
│      ]                                              │
│                                                     │
│   4. Обновление сущности                            │
│      POST /crm.contact.update                       │
│      { "id": 123, "fields": { "PHONE": [...] } }   │
└─────────────────────────────────────────────────────┘
```

---

## Вопросы для собеседования

### Q1: Что такое пользовательские поля в Bitrix24 CRM?
**A:**
Пользовательские поля (User Fields) — это дополнительные поля, которые можно создавать для расширения стандартной функциональности CRM.
- Имеют префикс `UF_CRM_`
- Могут быть разных типов (строка, число, дата, файл, список и т.д.)
- Создаются через REST API методы `*.userfield.add`
- Доступны для фильтрации, сортировки и отображения в списках

### Q2: Какие типы пользовательских полей поддерживает Bitrix24?
**A:**
- `string` — однострочный текст
- `double` — число с десятичными знаками
- `boolean` — да/нет (чекбокс)
- `datetime` — дата и время
- `enumeration` — выпадающий список
- `file` — файл
- `url` — ссылка
- `crm_status` — статус из справочника CRM
- `crm` — привязка к другой сущности CRM (лид, сделка, контакт, компания)

### Q3: Что такое множественные поля и как они работают?
**A:**
Множественные поля (Multiple Fields) — это поля, которые могут содержать несколько значений одновременно.

**Примеры:** PHONE, EMAIL, WEB, IM

**Структура:**
```json
{
    "PHONE": [
        {"ID": "123", "VALUE": "+7999...", "VALUE_TYPE": "WORK"},
        {"ID": "124", "VALUE": "+7888...", "VALUE_TYPE": "MOBILE"}
    ]
}
```

Каждое значение имеет:
- `ID` — уникальный идентификатор (для обновления)
- `VALUE` — само значение
- `VALUE_TYPE` — тип (WORK, MOBILE, HOME и т.д.)

### Q4: Как добавить новое значение в множественное поле без удаления существующих?
**A:**
Нужно:
1. Получить текущие значения через `GET /crm.contact.get`
2. Добавить новое значение БЕЗ указания `ID`
3. Отправить обновление, включив ВСЕ значения (и старые с ID, и новое без ID)

```php
POST /crm.contact.update
{
    "id": 123,
    "fields": {
        "PHONE": [
            {"ID": "456", "VALUE": "+7999...", "VALUE_TYPE": "WORK"},  // Старый
            {"VALUE": "+7888...", "VALUE_TYPE": "MOBILE"}  // Новый
        ]
    }
}
```

Если не включить старые значения — они удалятся!

### Q5: Как создать пользовательское поле типа "список"?
**A:**
```php
POST /crm.deal.userfield.add
{
    "fields": {
        "FIELD_NAME": "UF_CRM_PRIORITY",
        "USER_TYPE_ID": "enumeration",
        "EDIT_FORM_LABEL": "Приоритет",
        "LIST": [
            {"VALUE": "Низкий", "SORT": 100},
            {"VALUE": "Средний", "SORT": 200},
            {"VALUE": "Высокий", "SORT": 300}
        ]
    }
}
```

При установке значения нужно передавать ID элемента списка, а не текст:
```php
"UF_CRM_PRIORITY": 2  // ID значения "Средний"
```

### Q6: Какие ошибки часто допускают при работе с множественными полями?
**A:**
1. **Потеря данных:** не включают существующие значения при обновлении → они удаляются
2. **Неправильный формат:** передают строку вместо массива объектов
3. **Дублирование:** создают новое значение вместо обновления существующего (не указывают ID)
4. **Невалидные данные:** не проверяют формат телефонов, email адресов

**Правило:** всегда читай → модифицируй → записывай (не перезаписывай вслепую)

### Q7: Как загрузить файл в пользовательское поле?
**A:**
```php
POST /crm.contact.add
{
    "fields": {
        "NAME": "Иван",
        "UF_CRM_CONTRACT": [
            "contract.pdf",  // Имя файла
            base64_encode(file_get_contents('contract.pdf'))  // Base64 содержимое
        ]
    }
}
```

Для множественной загрузки файлов:
```php
"UF_CRM_DOCUMENTS": [
    ["doc1.pdf", base64_encode($content1)],
    ["doc2.pdf", base64_encode($content2)]
]
```

### Q8: Как фильтровать по пользовательским полям?
**A:**
```php
// По текстовому полю
GET /crm.deal.list?filter[UF_CRM_SOURCE]=Google

// По числовому полю (больше/меньше)
GET /crm.deal.list?filter[>=UF_CRM_DISCOUNT]=10
GET /crm.deal.list?filter[<UF_CRM_DISCOUNT]=50

// По дате
GET /crm.deal.list?filter[>=UF_CRM_NEXT_CALL]=2024-01-01

// По булевому полю
GET /crm.contact.list?filter[UF_CRM_IS_VIP]=1
```

### Q9: Зачем кешировать метаданные полей?
**A:**
Метаданные полей (названия, типы, списки значений) меняются редко, но запрашиваются часто.

**Без кеша:** каждый раз делаем запрос `crm.contact.fields` → медленно, расходуем квоту API

**С кешем:** делаем запрос раз в час, остальное берем из кеша → быстро, экономим запросы

```php
$cacheKey = 'crm_contact_fields';
$fields = $cache->get($cacheKey);

if (!$fields) {
    $fields = $api->call('crm.contact.fields');
    $cache->set($cacheKey, $fields, 3600);  // 1 час
}
```

### Q10: Как правильно обновить один телефон, не затронув остальные?
**A:**
```php
// 1. Получаем контакт
$contact = $api->call('crm.contact.get', ['id' => 123]);
$phones = $contact['PHONE'];

// 2. Находим нужный телефон и обновляем
foreach ($phones as &$phone) {
    if ($phone['ID'] == '456') {
        $phone['VALUE'] = '+79999999999';  // Новый номер
    }
}

// 3. Отправляем обновление со ВСЕМИ телефонами
$api->call('crm.contact.update', [
    'id' => 123,
    'fields' => [
        'PHONE' => $phones  // Все телефоны (измененные и неизмененные)
    ]
]);
```

---

## Практические задачи

- **Задача 1:** Добавление пользовательского поля "Источник лида" с выпадающим списком
- **Задача 2:** Управление множественными телефонами контакта (добавление, обновление, удаление)
- **Задача 3:** Загрузка и привязка файлов к сделке через пользовательское поле

**Решения задач находятся в `examples/module-7-2/`**
