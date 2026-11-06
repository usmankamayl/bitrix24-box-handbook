# Модуль 2.2: Highload-блоки

## Теория

### Что такое Highload-блоки?

**Highload-блоки (HL-блоки)** — это специальный механизм в Bitrix для хранения больших объемов данных с высокой скоростью доступа. Они представляют собой обычные таблицы в базе данных, но с удобным API для работы.

### Когда использовать HL-блоки?

✅ **Используйте HL-блоки когда:**
- Нужно хранить большие объемы структурированных данных (логи, статистика, справочники)
- Требуется высокая скорость записи/чтения
- Не нужен функционал инфоблоков (SEO, версионность, привязка к разделам)
- Нужна простая таблица с произвольной структурой

❌ **НЕ используйте HL-блоки для:**
- Контентных данных, требующих SEO
- Данных с деревом разделов
- Когда нужны стандартные возможности инфоблоков

### Преимущества HL-блоков

1. **Скорость** — прямая работа с таблицей БД без лишних оберток
2. **Гибкость** — любая структура полей
3. **Масштабируемость** — эффективная работа с миллионами записей
4. **Простота** — минимум кода для CRUD операций

### Создание Highload-блока

#### Через административный интерфейс

```
Настройки → Настройки продукта → Настройки модулей → 
Highload-блоки → Добавить Highload-блок
```

#### Программное создание

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$result = HighloadBlockTable::add([
    'NAME' => 'RequestLog',              // Системное имя
    'TABLE_NAME' => 'b_hlbd_request_log', // Имя таблицы в БД
]);

if ($result->isSuccess()) {
    $hlblockId = $result->getId();
}
```

### Добавление полей

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$hlblock = HighloadBlockTable::getById($hlblockId)->fetch();
$entity = HighloadBlockTable::compileEntity($hlblock);
$entity_data_class = $entity->getDataClass();

// Добавление полей через UserFieldTable
$fields = [
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_USER_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_REQUEST_URL',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'Y',
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_REQUEST_DATE',
        'USER_TYPE_ID' => 'datetime',
        'MANDATORY' => 'Y',
    ],
];
```

### Получение класса для работы с HL-блоком

```php
use Bitrix\Highloadblock\HighloadBlockTable;

// По ID
$hlblock = HighloadBlockTable::getById($hlblockId)->fetch();

// По имени таблицы
$hlblock = HighloadBlockTable::getList([
    'filter' => ['=TABLE_NAME' => 'b_hlbd_request_log']
])->fetch();

// Получение Entity
$entity = HighloadBlockTable::compileEntity($hlblock);

// Получение DataClass для работы с данными
$dataClass = $entity->getDataClass();
```

### CRUD операции

#### Добавление записи

```php
$result = $dataClass::add([
    'UF_USER_ID' => 1,
    'UF_REQUEST_URL' => '/catalog/item/123/',
    'UF_REQUEST_DATE' => new \Bitrix\Main\Type\DateTime(),
]);

if ($result->isSuccess()) {
    $id = $result->getId();
}
```

#### Получение записи

```php
// Одна запись
$item = $dataClass::getById($id)->fetch();

// Список с фильтром
$result = $dataClass::getList([
    'select' => ['*'],
    'filter' => ['=UF_USER_ID' => 1],
    'order' => ['UF_REQUEST_DATE' => 'DESC'],
    'limit' => 10,
]);

while ($item = $result->fetch()) {
    // Обработка
}
```

#### Обновление записи

```php
$result = $dataClass::update($id, [
    'UF_REQUEST_URL' => '/new-url/',
]);

if (!$result->isSuccess()) {
    $errors = $result->getErrorMessages();
}
```

#### Удаление записи

```php
$result = $dataClass::delete($id);

if (!$result->isSuccess()) {
    $errors = $result->getErrorMessages();
}
```

### Типы полей HL-блоков

| Тип | USER_TYPE_ID | Описание |
|-----|--------------|----------|
| Строка | string | Текстовое поле |
| Число | integer, double | Числовые значения |
| Дата/время | date, datetime | Временные метки |
| Логический | boolean | Да/Нет |
| Файл | file | Загрузка файлов |
| Список | enumeration | Справочник значений |
| Привязка к элементу | iblock_element | Связь с инфоблоком |
| Привязка к разделу | iblock_section | Связь с разделом |
| Привязка к пользователю | user | Связь с пользователем |

### Индексы для оптимизации

```php
// Создание индекса на поле для ускорения выборки
$connection = \Bitrix\Main\Application::getConnection();
$connection->query("
    CREATE INDEX ix_user_id 
    ON b_hlbd_request_log(UF_USER_ID)
");

// Составной индекс
$connection->query("
    CREATE INDEX ix_user_date 
    ON b_hlbd_request_log(UF_USER_ID, UF_REQUEST_DATE)
");
```

### Кеширование

```php
$cache = \Bitrix\Main\Data\Cache::createInstance();
$cacheTime = 3600;
$cacheId = 'hlblock_stats_' . $userId;
$cacheDir = '/hlblock/stats/';

if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
    $stats = $cache->getVars();
} else {
    $cache->startDataCache();
    
    $stats = $dataClass::getList([
        'select' => ['UF_USER_ID', 'CNT'],
        'filter' => ['=UF_USER_ID' => $userId],
        'runtime' => [
            new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)')
        ],
    ])->fetch();
    
    $cache->endDataCache($stats);
}
```

### Агрегатные функции

```php
use Bitrix\Main\Entity\ExpressionField;

$result = $dataClass::getList([
    'select' => [
        'UF_USER_ID',
        'TOTAL_REQUESTS',
        'LAST_DATE',
    ],
    'filter' => [
        '>UF_REQUEST_DATE' => new \Bitrix\Main\Type\DateTime('01.01.2024', 'd.m.Y'),
    ],
    'group' => ['UF_USER_ID'],
    'runtime' => [
        new ExpressionField('TOTAL_REQUESTS', 'COUNT(*)'),
        new ExpressionField('LAST_DATE', 'MAX(%s)', ['UF_REQUEST_DATE']),
    ],
]);
```

## Вопросы для собеседования

### 1. Что такое Highload-блоки и для чего они используются?

**Ответ:** Highload-блоки — это механизм для хранения больших объемов данных с высокой скоростью доступа. Это обычные таблицы БД с удобным ORM API. Используются для логов, статистики, справочников, когда не нужен функционал инфоблоков.

### 2. В чем отличие HL-блоков от инфоблоков?

**Ответ:**
- **Скорость**: HL-блоки быстрее (нет лишних оберток)
- **Функционал**: У инфоблоков больше возможностей (SEO, разделы, версионность)
- **Структура**: HL-блоки — простые таблицы, инфоблоки — сложная структура
- **Назначение**: HL-блоки для служебных данных, инфоблоки для контента

### 3. Как получить класс для работы с HL-блоком?

**Ответ:**
```php
use Bitrix\Highloadblock\HighloadBlockTable;

$hlblock = HighloadBlockTable::getById($id)->fetch();
$entity = HighloadBlockTable::compileEntity($hlblock);
$dataClass = $entity->getDataClass();
```

### 4. Какие типы полей поддерживают HL-блоки?

**Ответ:** string, integer, double, date, datetime, boolean, file, enumeration, iblock_element, iblock_section, user и другие пользовательские типы.

### 5. Как оптимизировать выборку из HL-блока с миллионами записей?

**Ответ:**
1. Создать индексы на часто используемые поля
2. Использовать кеширование
3. Ограничивать выборку через `limit`
4. Использовать `select` только для нужных полей
5. Архивировать старые данные

### 6. Можно ли делать JOIN с другими таблицами в HL-блоках?

**Ответ:** Да, через `runtime` можно добавлять связи с другими таблицами и делать JOIN.

```php
'runtime' => [
    new \Bitrix\Main\Entity\ReferenceField(
        'USER',
        \Bitrix\Main\UserTable::class,
        ['=this.UF_USER_ID' => 'ref.ID']
    ),
],
```

### 7. Как массово добавить данные в HL-блок?

**Ответ:**
```php
$connection = \Bitrix\Main\Application::getConnection();
$sqlHelper = $connection->getSqlHelper();

// Начало транзакции
$connection->startTransaction();

try {
    foreach ($data as $item) {
        $dataClass::add($item);
    }
    $connection->commitTransaction();
} catch (\Exception $e) {
    $connection->rollbackTransaction();
}
```

### 8. Как удалить HL-блок программно?

**Ответ:**
```php
use Bitrix\Highloadblock\HighloadBlockTable;

$result = HighloadBlockTable::delete($hlblockId);

if (!$result->isSuccess()) {
    $errors = $result->getErrorMessages();
}
```

### 9. Можно ли использовать HL-блоки в компонентах?

**Ответ:** Да, HL-блоки можно использовать в компонентах для вывода данных, как и любой другой источник данных.

### 10. Как сделать выборку с подсчетом количества записей?

**Ответ:**
```php
// Получить общее количество
$count = $dataClass::getCount([
    'filter' => ['=UF_USER_ID' => 1]
]);

// Или через runtime
$result = $dataClass::getList([
    'select' => ['CNT'],
    'runtime' => [
        new ExpressionField('CNT', 'COUNT(*)')
    ],
]);
```

## Частые ошибки

### 1. Забывают компилировать Entity

```php
// ❌ Неправильно
$dataClass = HighloadBlockTable::getDataClass();

// ✅ Правильно
$hlblock = HighloadBlockTable::getById($id)->fetch();
$entity = HighloadBlockTable::compileEntity($hlblock);
$dataClass = $entity->getDataClass();
```

### 2. Не обрабатывают ошибки

```php
// ❌ Неправильно
$result = $dataClass::add($data);
$id = $result->getId(); // Может вызвать ошибку

// ✅ Правильно
$result = $dataClass::add($data);
if ($result->isSuccess()) {
    $id = $result->getId();
} else {
    $errors = $result->getErrorMessages();
}
```

### 3. Не используют индексы

```php
// ❌ Медленно без индекса
$result = $dataClass::getList([
    'filter' => ['=UF_USER_ID' => 1],
    'order' => ['UF_REQUEST_DATE' => 'DESC'],
]);

// ✅ Создайте индекс:
// CREATE INDEX ix_user_date ON table(UF_USER_ID, UF_REQUEST_DATE)
```

### 4. Загружают все данные в память

```php
// ❌ Неправильно для больших объемов
$items = $dataClass::getList()->fetchAll();

// ✅ Правильно - обрабатываем построчно
$result = $dataClass::getList(['limit' => 1000]);
while ($item = $result->fetch()) {
    // Обработка
}
```

## Практические задачи

1. **Лог запросов** — Система логирования запросов пользователей
2. **Счетчик просмотров** — Хранение счетчиков с агрегацией
3. **История изменений** — Логирование изменений в заказах

**Решения задач находятся в `examples/module-2-2/`**

## Полезные ссылки

- [Официальная документация Bitrix: Highload-блоки](https://dev.1c-bitrix.ru/api_d7/bitrix/highloadblock/index.php)
- [Примеры работы с HL-блоками](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2830)
