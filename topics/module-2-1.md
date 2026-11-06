# Модуль 2.1: ORM и работа с базой данных

## Теория

### Что такое ORM в Bitrix (D7)

ORM (Object-Relational Mapping) в Bitrix D7 — это механизм для работы с базой данных через объектно-ориентированный подход. Вместо написания SQL-запросов вручную, вы работаете с классами-сущностями (Entity), которые представляют таблицы БД.

**Основные преимущества:**
- Типобезопасность и автодополнение в IDE
- Автоматическая валидация данных
- Защита от SQL-инъекций
- Кеширование запросов
- Удобная работа со связями между таблицами
- Единый API для разных СУБД

### Структура ORM-класса

Каждая таблица описывается классом, наследующим `Bitrix\Main\Entity\DataManager`:

```php
use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

class OrderTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'custom_orders'; // имя таблицы в БД
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\StringField('TITLE', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                }
            ]),
            new Entity\IntegerField('USER_ID'),
            new Entity\FloatField('AMOUNT'),
            new Entity\DatetimeField('DATE_CREATE', [
                'default_value' => new DateTime(),
            ]),
            new Entity\BooleanField('IS_PAID', [
                'values' => ['N', 'Y'],
                'default_value' => 'N',
            ]),
        ];
    }
}
```

### Типы полей

| Тип | Класс | Описание |
|-----|-------|----------|
| **IntegerField** | `Entity\IntegerField` | Целые числа (INT) |
| **StringField** | `Entity\StringField` | Строки (VARCHAR, CHAR) |
| **TextField** | `Entity\TextField` | Длинный текст (TEXT) |
| **FloatField** | `Entity\FloatField` | Числа с плавающей точкой |
| **DatetimeField** | `Entity\DatetimeField` | Дата и время |
| **DateField** | `Entity\DateField` | Только дата |
| **BooleanField** | `Entity\BooleanField` | Булевы значения (Y/N) |
| **EnumField** | `Entity\EnumField` | Перечисление значений |
| **ExpressionField** | `Entity\ExpressionField` | Вычисляемые поля (SQL) |

### CRUD операции

#### CREATE - Создание записи

```php
$result = OrderTable::add([
    'TITLE' => 'Заказ #123',
    'USER_ID' => 1,
    'AMOUNT' => 5000.50,
    'IS_PAID' => 'N',
]);

if ($result->isSuccess()) {
    $newId = $result->getId();
} else {
    $errors = $result->getErrorMessages();
}
```

#### READ - Чтение данных

**Получить одну запись:**
```php
$order = OrderTable::getById(5)->fetch();
```

**Получить список с условиями:**
```php
$result = OrderTable::getList([
    'select' => ['ID', 'TITLE', 'AMOUNT', 'USER_ID'],
    'filter' => [
        '=IS_PAID' => 'Y',
        '>AMOUNT' => 1000,
    ],
    'order' => ['DATE_CREATE' => 'DESC'],
    'limit' => 10,
    'offset' => 0,
]);

while ($order = $result->fetch()) {
    // обработка каждого заказа
}
```

#### UPDATE - Обновление записи

```php
$result = OrderTable::update(5, [
    'IS_PAID' => 'Y',
    'AMOUNT' => 6000,
]);

if (!$result->isSuccess()) {
    $errors = $result->getErrorMessages();
}
```

#### DELETE - Удаление записи

```php
$result = OrderTable::delete(5);

if (!$result->isSuccess()) {
    $errors = $result->getErrorMessages();
}
```

### Фильтры и операторы

```php
'filter' => [
    '=ID' => 5,                    // равно
    '!=STATUS' => 'CANCEL',        // не равно
    '>AMOUNT' => 1000,             // больше
    '>=AMOUNT' => 1000,            // больше или равно
    '<AMOUNT' => 5000,             // меньше
    '<=AMOUNT' => 5000,            // меньше или равно
    '%TITLE' => 'Заказ%',         // LIKE
    '!%TITLE' => 'Тест%',         // NOT LIKE
    '@ID' => [1, 2, 3],           // IN (массив значений)
    '!@ID' => [4, 5, 6],          // NOT IN
    '><AMOUNT' => [100, 500],     // BETWEEN
    '!><AMOUNT' => [100, 500],    // NOT BETWEEN
]
```

### Логические операторы

```php
'filter' => [
    'LOGIC' => 'OR',
    ['=STATUS' => 'NEW'],
    ['=STATUS' => 'PROCESSING'],
]
```

### Связи между таблицами (Reference)

**One-to-One и Many-to-One:**
```php
new Entity\ReferenceField(
    'USER',
    UserTable::class,
    ['=this.USER_ID' => 'ref.ID']
)
```

**Использование связи:**
```php
$result = OrderTable::getList([
    'select' => ['ID', 'TITLE', 'USER.NAME', 'USER.EMAIL'],
    'filter' => ['=USER.ACTIVE' => 'Y'],
]);
```

### Runtime-поля

Добавление полей "на лету" в запрос:

```php
$result = OrderTable::getList([
    'select' => ['ID', 'TITLE', 'TOTAL_COUNT'],
    'runtime' => [
        new Entity\ExpressionField(
            'TOTAL_COUNT',
            'COUNT(*)'
        ),
    ],
    'group' => ['USER_ID'],
]);
```

### Транзакции

```php
$connection = \Bitrix\Main\Application::getConnection();
$connection->startTransaction();

try {
    OrderTable::add([...]);
    OrderItemTable::add([...]);
    
    $connection->commitTransaction();
} catch (\Exception $e) {
    $connection->rollbackTransaction();
    throw $e;
}
```

### Частые ошибки

❌ **Не использовать валидацию:**
```php
// Плохо
OrderTable::add(['TITLE' => '']); // пустое обязательное поле
```

✅ **Правильно:**
```php
$result = OrderTable::add(['TITLE' => $title]);
if (!$result->isSuccess()) {
    foreach ($result->getErrorMessages() as $error) {
        echo $error;
    }
}
```

❌ **N+1 проблема:**
```php
// Плохо - для каждого заказа отдельный запрос к users
$orders = OrderTable::getList()->fetchAll();
foreach ($orders as $order) {
    $user = UserTable::getById($order['USER_ID'])->fetch();
}
```

✅ **Правильно - один запрос с JOIN:**
```php
$result = OrderTable::getList([
    'select' => ['*', 'USER.NAME', 'USER.EMAIL'],
]);
```

❌ **Забыть про лимиты:**
```php
// Плохо - может вернуть миллионы записей
$orders = OrderTable::getList()->fetchAll();
```

✅ **Правильно:**
```php
$orders = OrderTable::getList([
    'limit' => 100,
])->fetchAll();
```

---

## Вопросы для собеседования

### 1. Что такое ORM в Bitrix D7 и зачем он нужен?

**Ответ:**
ORM (Object-Relational Mapping) — это механизм сопоставления объектно-ориентированного кода с реляционными таблицами БД. В Bitrix D7 каждая таблица представлена классом-наследником `DataManager`.

**Преимущества:**
- Защита от SQL-инъекций
- Автоматическая валидация данных
- Типобезопасность
- Удобная работа со связями
- Кеширование запросов
- Независимость от СУБД

### 2. Как создать новую запись в таблице через ORM?

**Ответ:**
```php
$result = MyTable::add([
    'FIELD1' => 'value1',
    'FIELD2' => 'value2',
]);

if ($result->isSuccess()) {
    $newId = $result->getId();
} else {
    $errors = $result->getErrorMessages();
}
```

Метод `add()` возвращает объект `Result`, у которого есть методы:
- `isSuccess()` — успешность операции
- `getId()` — ID новой записи
- `getErrorMessages()` — массив ошибок

### 3. Как получить список записей с фильтром и сортировкой?

**Ответ:**
```php
$result = MyTable::getList([
    'select' => ['ID', 'NAME', 'PRICE'],
    'filter' => [
        '=ACTIVE' => 'Y',
        '>PRICE' => 1000,
    ],
    'order' => ['PRICE' => 'DESC'],
    'limit' => 20,
    'offset' => 0,
]);

while ($item = $result->fetch()) {
    // обработка
}
```

**Параметры:**
- `select` — список полей для выборки
- `filter` — условия фильтрации
- `order` — сортировка
- `limit` — ограничение количества записей
- `offset` — смещение (для пагинации)

### 4. Какие операторы фильтрации доступны в ORM?

**Ответ:**
- `=` — равно
- `!=` — не равно
- `>`, `>=`, `<`, `<=` — сравнения
- `%` — LIKE (поиск по подстроке)
- `@` — IN (вхождение в массив)
- `><` — BETWEEN (диапазон)
- `!` — отрицание (перед любым оператором)

**Пример:**
```php
'filter' => [
    '=ACTIVE' => 'Y',
    '>PRICE' => 100,
    '%NAME' => '%товар%',
    '@STATUS' => ['NEW', 'PROCESSING'],
    '><DATE' => [$dateFrom, $dateTo],
]
```

### 5. Как работать со связями между таблицами?

**Ответ:**
Связи описываются через `ReferenceField` в методе `getMap()`:

```php
new Entity\ReferenceField(
    'USER',                      // имя связи
    UserTable::class,            // связанная таблица
    ['=this.USER_ID' => 'ref.ID'] // условие связи
)
```

**Использование:**
```php
$result = OrderTable::getList([
    'select' => ['ID', 'AMOUNT', 'USER.NAME', 'USER.EMAIL'],
    'filter' => ['=USER.ACTIVE' => 'Y'],
]);
```

Префиксы:
- `this.` — поле текущей таблицы
- `ref.` — поле связанной таблицы

### 6. Что такое Runtime-поля и когда их использовать?

**Ответ:**
Runtime-поля — это поля, добавляемые "на лету" в запрос, не описанные в `getMap()`. Используются для:
- Вычисляемых значений (COUNT, SUM, AVG)
- Сложных SQL-выражений
- Временных связей

**Пример:**
```php
'runtime' => [
    new Entity\ExpressionField(
        'TOTAL_PRICE',
        'SUM(%s * %s)',
        ['PRICE', 'QUANTITY']
    ),
]
```

### 7. Как обновить или удалить запись?

**Ответ:**

**Обновление:**
```php
$result = MyTable::update($id, [
    'FIELD1' => 'new_value',
]);
```

**Удаление:**
```php
$result = MyTable::delete($id);
```

Оба метода возвращают объект `Result` с методами `isSuccess()` и `getErrorMessages()`.

### 8. Как использовать транзакции?

**Ответ:**
```php
$connection = \Bitrix\Main\Application::getConnection();
$connection->startTransaction();

try {
    OrderTable::add([...]);
    OrderItemTable::add([...]);
    
    $connection->commitTransaction();
} catch (\Exception $e) {
    $connection->rollbackTransaction();
    throw $e;
}
```

Транзакции нужны когда несколько операций должны выполниться атомарно (все или ничего).

### 9. В чем разница между fetch(), fetchAll() и fetchCollection()?

**Ответ:**
- **`fetch()`** — возвращает одну запись как массив, при повторном вызове — следующую
- **`fetchAll()`** — возвращает все записи сразу как массив массивов
- **`fetchCollection()`** — возвращает коллекцию объектов-сущностей (для работы через OOP)

```php
// fetch() - построчно
while ($row = $result->fetch()) {
    echo $row['NAME'];
}

// fetchAll() - все сразу
$rows = $result->fetchAll();

// fetchCollection() - объекты
$collection = $result->fetchCollection();
foreach ($collection as $entity) {
    echo $entity->getName(); // через геттеры
}
```

### 10. Как избежать N+1 проблемы?

**Ответ:**
N+1 проблема возникает когда для каждой записи делается отдельный запрос к связанной таблице.

❌ **Плохо:**
```php
$orders = OrderTable::getList()->fetchAll();
foreach ($orders as $order) {
    $user = UserTable::getById($order['USER_ID'])->fetch(); // N запросов
}
```

✅ **Правильно:**
```php
$result = OrderTable::getList([
    'select' => ['*', 'USER.NAME', 'USER.EMAIL'], // JOIN в одном запросе
]);
```

---

## Практические задачи

В этом модуле вы создадите три задачи:

1. **Таблица отзывов с CRUD** — создание ORM-класса и базовые операции
2. **Статистика заказов** — агрегирующие запросы с группировкой
3. **Связь товаров и категорий** — работа с Reference и JOIN

**Решения задач находятся в `examples/module-2-1/`**

---

## Ссылки на документацию

- [ORM в Bitrix D7](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5753)
- [DataManager API](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php)
- [Типы полей](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/field/index.php)
- [Фильтры и операторы](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5753&LESSON_PATH=3913.4776.5753)
