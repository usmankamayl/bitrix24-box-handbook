# Задача 3: Связь товаров и категорий через Reference

## Описание

Создание связанных таблиц товаров и категорий с использованием ReferenceField для выполнения JOIN-запросов. Демонстрирует работу со связями Many-to-One и древовидными структурами.

## Что демонстрирует

- ✅ ReferenceField для связи таблиц (Many-to-One)
- ✅ JOIN-запросы через ORM
- ✅ Фильтрация по полям связанных таблиц
- ✅ Получение данных из связанных таблиц в одном запросе
- ✅ Древовидная структура (parent-child)
- ✅ Избежание N+1 проблемы
- ✅ Комбинирование связей и агрегатных функций
- ✅ Поиск по нескольким таблицам

## Структура файлов

```
products-categories/
├── CategoryTable.php  # ORM-класс категорий с древовидной структурой
├── ProductTable.php   # ORM-класс товаров со связью к категориям
├── examples.php       # 12 примеров работы со связями
├── install.sql        # SQL для создания таблиц с внешними ключами
└── README.md          # Этот файл
```

## Установка

### 1. Создайте таблицы в БД

```bash
mysql -u root -p your_database < install.sql
```

**Важно:** Таблицы связаны через FOREIGN KEY, поэтому создаются в правильном порядке.

### 2. Подключите классы

```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/Custom/Catalog/CategoryTable.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/Custom/Catalog/ProductTable.php');
```

## Использование

### Создание связи через ReferenceField

```php
// В ProductTable.php
new Entity\ReferenceField(
    'CATEGORY',                     // имя связи для использования в запросах
    CategoryTable::class,           // связанная таблица
    ['=this.CATEGORY_ID' => 'ref.ID'], // условие JOIN
    ['join_type' => 'INNER']        // тип JOIN
)
```

**Типы JOIN:**
- `INNER` — только записи с совпадениями в обеих таблицах
- `LEFT` — все записи из текущей таблицы + совпадения из связанной
- `RIGHT` — редко используется

### Получение данных из связанной таблицы

```php
use Custom\Catalog\ProductTable;

$result = ProductTable::getList([
    'select' => [
        'ID',
        'NAME',
        'PRICE',
        'CATEGORY_NAME' => 'CATEGORY.NAME',  // поле из связанной таблицы
        'CATEGORY_CODE' => 'CATEGORY.CODE',  // еще одно поле
    ],
    'filter' => ['=ACTIVE' => 'Y'],
]);
```

**Префикс `CATEGORY.`** обращается к связи, определенной через ReferenceField.

### Фильтрация по связанной таблице

```php
$result = ProductTable::getList([
    'select' => ['ID', 'NAME', 'PRICE'],
    'filter' => [
        '=ACTIVE' => 'Y',
        '=CATEGORY.CODE' => 'smartphones',    // фильтр по связанной таблице
        '=CATEGORY.ACTIVE' => 'Y',            // еще один фильтр
    ],
]);
```

### Древовидная структура (Parent-Child)

```php
// В CategoryTable.php
new Entity\ReferenceField(
    'PARENT',
    self::class,                    // связь с самой собой
    ['=this.PARENT_ID' => 'ref.ID'],
    ['join_type' => 'LEFT']
)
```

Получение родительской категории:

```php
$result = CategoryTable::getList([
    'select' => [
        'ID',
        'NAME',
        'PARENT_NAME' => 'PARENT.NAME',  // название родителя
    ],
]);
```

## Примеры из examples.php

### 1. Простая выборка с JOIN

```php
$products = ProductTable::getList([
    'select' => [
        'ID',
        'NAME',
        'CATEGORY_NAME' => 'CATEGORY.NAME',
    ],
]);
```

**SQL под капотом:**
```sql
SELECT p.ID, p.NAME, c.NAME as CATEGORY_NAME
FROM custom_products p
INNER JOIN custom_categories c ON p.CATEGORY_ID = c.ID
```

### 2. Фильтр по связанной таблице

```php
ProductTable::getList([
    'filter' => [
        '=CATEGORY.CODE' => 'smartphones',
    ],
]);
```

### 3. Статистика с группировкой по связанной таблице

```php
$result = ProductTable::getList([
    'select' => [
        'CATEGORY_ID',
        'CATEGORY_NAME' => 'CATEGORY.NAME',
        'PRODUCTS_COUNT',
        'AVG_PRICE',
    ],
    'runtime' => [
        new Entity\ExpressionField('PRODUCTS_COUNT', 'COUNT(*)'),
        new Entity\ExpressionField('AVG_PRICE', 'AVG(%s)', ['PRICE']),
    ],
    'group' => ['CATEGORY_ID'],
]);
```

### 4. Поиск по нескольким таблицам

```php
$result = ProductTable::getList([
    'filter' => [
        'LOGIC' => 'OR',
        ['%NAME' => "%{$query}%"],              // в товарах
        ['%CATEGORY.NAME' => "%{$query}%"],     // в категориях
    ],
]);
```

### 5. Древовидная структура

```php
$tree = CategoryTable::getCategoriesTree();

// Результат:
[
    [
        'ID' => 1,
        'NAME' => 'Электроника',
        'CHILDREN' => [
            ['ID' => 2, 'NAME' => 'Смартфоны'],
            ['ID' => 3, 'NAME' => 'Ноутбуки'],
        ],
    ],
    ...
]
```

## Ключевые концепции

### Избежание N+1 проблемы

❌ **Плохо — N+1 проблема:**
```php
// 1 запрос для получения товаров
$products = ProductTable::getList()->fetchAll();

// N запросов для категорий (по одному на каждый товар)
foreach ($products as $product) {
    $category = CategoryTable::getById($product['CATEGORY_ID'])->fetch();
}
```

✅ **Правильно — один запрос с JOIN:**
```php
$products = ProductTable::getList([
    'select' => ['*', 'CATEGORY.NAME', 'CATEGORY.CODE'],
]);

while ($product = $products->fetch()) {
    // Все данные уже загружены в одном запросе
    echo $product['CATEGORY_NAME'];
}
```

### Типы связей

| Тип | Описание | Пример |
|-----|----------|--------|
| **One-to-One** | Одна запись → одна связанная | Пользователь → Профиль |
| **One-to-Many** | Одна запись → много связанных | Категория → Товары |
| **Many-to-One** | Много записей → одна связанная | Товары → Категория |
| **Many-to-Many** | Много → много (через pivot) | Товары ↔ Теги |

В этом примере используется **Many-to-One**: много товаров относятся к одной категории.

### Runtime ReferenceField

Можно добавить связь "на лету" без изменения класса:

```php
'runtime' => [
    new Entity\ReferenceField(
        'REVIEWS',
        ReviewTable::class,
        ['=this.ID' => 'ref.PRODUCT_ID'],
        ['join_type' => 'LEFT']
    ),
    new Entity\ExpressionField('REVIEWS_COUNT', 'COUNT(%s)', ['REVIEWS.ID']),
],
```

### Множественные связи

Можно иметь несколько связей с одной таблицей:

```php
// Автор товара
new Entity\ReferenceField(
    'AUTHOR',
    UserTable::class,
    ['=this.CREATED_BY' => 'ref.ID']
),

// Модератор товара
new Entity\ReferenceField(
    'MODERATOR',
    UserTable::class,
    ['=this.MODERATED_BY' => 'ref.ID']
),
```

## Оптимизация

### Индексы для внешних ключей

```sql
-- Всегда создавайте индексы на поля-связи
INDEX `IX_CATEGORY_ID` (`CATEGORY_ID`)
```

### Выбирайте только нужные поля

❌ **Плохо:**
```php
'select' => ['*', 'CATEGORY.*'],  // загружает все поля
```

✅ **Правильно:**
```php
'select' => ['ID', 'NAME', 'CATEGORY.NAME', 'CATEGORY.CODE'],
```

### Ограничивайте выборки

```php
'filter' => ['=CATEGORY.ACTIVE' => 'Y'],  // только активные категории
'limit' => 50,                             // не более 50 записей
```

## Частые ошибки

❌ **Забыли определить ReferenceField:**
```php
'select' => ['CATEGORY.NAME'],  // Error: Unknown field
```

✅ **Правильно:**
```php
// Сначала в getMap() класса:
new Entity\ReferenceField('CATEGORY', CategoryTable::class, ...),

// Потом в запросе:
'select' => ['CATEGORY.NAME'],
```

❌ **Неправильное условие связи:**
```php
['=CATEGORY_ID' => 'ID']  // неправильно!
```

✅ **Правильно:**
```php
['=this.CATEGORY_ID' => 'ref.ID']
```

❌ **Фильтр по несуществующему полю:**
```php
'filter' => ['=CATEGORY_NAME' => 'Test'],  // нет такого поля!
```

✅ **Правильно:**
```php
'filter' => ['=CATEGORY.NAME' => 'Test'],  // через связь
```

## Задания для самопроверки

1. Добавьте связь товаров с производителями (BrandTable)
2. Реализуйте Many-to-Many связь товаров и тегов через промежуточную таблицу
3. Добавьте связь категорий с изображениями
4. Создайте метод для получения всех товаров категории и ее подкатегорий
5. Реализуйте "хлебные крошки" для категории (путь от корня до текущей)
6. Добавьте связь товаров с отзывами и выведите средний рейтинг

## Что дальше?

Модуль 2.1 завершен! Теперь вы умеете:
- Создавать ORM-классы
- Выполнять CRUD операции
- Работать с агрегатными функциями
- Связывать таблицы через ReferenceField

**Следующий модуль:** 2.2 Highload-блоки

## Полезные ссылки

- [ReferenceField документация](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/referencefield/index.php)
- [JOIN в ORM](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5753)
- [Связи между таблицами](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=11815)
