# Задача 2: Статистика заказов с агрегацией

## Описание

Создание системы аналитики заказов с использованием агрегирующих функций и группировки данных. Демонстрирует построение сложных аналитических запросов через ORM.

## Что демонстрирует

- ✅ Агрегатные функции (COUNT, SUM, AVG, MIN, MAX)
- ✅ Группировка данных (GROUP BY)
- ✅ Runtime-поля с ExpressionField
- ✅ Сложные SQL-выражения через ORM
- ✅ Условная логика в запросах (IF, CASE)
- ✅ Вычисления с датами (DATEDIFF, DATE_FORMAT)
- ✅ Статистические методы в классе ORM
- ✅ Построение аналитических отчетов

## Структура файлов

```
orders-statistics/
├── OrderTable.php     # ORM-класс с методами статистики
├── examples.php       # Примеры аналитических запросов
├── install.sql        # SQL для создания таблицы
└── README.md          # Этот файл
```

## Установка

### 1. Создайте таблицу в БД

Выполните SQL из файла `install.sql`:

```bash
mysql -u root -p your_database < install.sql
```

### 2. Подключите класс

```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/Custom/Orders/OrderTable.php');
```

Или через composer autoload (PSR-4).

## Использование

### Общая статистика продаж

```php
use Custom\Orders\OrderTable;
use Bitrix\Main\Type\DateTime;

$dateFrom = new DateTime('2024-01-01');
$dateTo = new DateTime('2024-12-31');

$stats = OrderTable::getSalesStatistics($dateFrom, $dateTo);

echo "Всего заказов: {$stats['TOTAL_COUNT']}";
echo "Выручка: {$stats['TOTAL_REVENUE']} ₽";
echo "Средний чек: {$stats['AVG_ORDER']} ₽";
```

### Статистика по статусам

```php
$statusStats = OrderTable::getStatusStatistics();

foreach ($statusStats as $stat) {
    echo "{$stat['STATUS']}: {$stat['COUNT']} заказов на {$stat['TOTAL_AMOUNT']} ₽";
}
```

### Топ покупателей

```php
$topBuyers = OrderTable::getTopBuyers(10);

foreach ($topBuyers as $buyer) {
    echo "User #{$buyer['USER_ID']}: {$buyer['ORDERS_COUNT']} заказов, ";
    echo "потрачено {$buyer['TOTAL_SPENT']} ₽";
}
```

### Статистика по городам

```php
$cityStats = OrderTable::getCityStatistics();

foreach ($cityStats as $city) {
    echo "{$city['DELIVERY_CITY']}: {$city['ORDERS_COUNT']} заказов, ";
    echo "выручка {$city['TOTAL_REVENUE']} ₽";
}
```

### Конверсия по источникам

```php
$conversion = OrderTable::getPaymentConversionBySource();

foreach ($conversion as $source) {
    echo "{$source['SOURCE']}: конверсия {$source['CONVERSION_RATE']}%";
}
```

## Ключевые концепции

### Runtime-поля с ExpressionField

ExpressionField позволяет добавлять вычисляемые поля "на лету":

```php
'runtime' => [
    // Простой COUNT
    new Entity\ExpressionField('TOTAL_COUNT', 'COUNT(*)'),
    
    // SUM по полю
    new Entity\ExpressionField('TOTAL_REVENUE', 'SUM(%s)', ['FINAL_AMOUNT']),
    
    // Среднее значение
    new Entity\ExpressionField('AVG_ORDER', 'AVG(%s)', ['FINAL_AMOUNT']),
    
    // Условная логика
    new Entity\ExpressionField(
        'PAID_COUNT',
        'SUM(IF(%s = "Y", 1, 0))',
        ['IS_PAID']
    ),
    
    // Вычисления с датами
    new Entity\ExpressionField(
        'DELIVERY_TIME',
        'DATEDIFF(%s, %s)',
        ['DATE_DELIVERED', 'DATE_CREATE']
    ),
]
```

### Группировка данных

```php
'group' => ['STATUS']              // по одному полю
'group' => ['USER_ID', 'STATUS']   // по нескольким полям
```

При группировке в `select` указывайте:
- Поля из `group`
- Агрегатные функции (COUNT, SUM, AVG, etc.)

### Агрегатные функции

| Функция | Описание | Пример |
|---------|----------|--------|
| **COUNT(*)** | Количество записей | Всего заказов |
| **SUM(field)** | Сумма значений | Общая выручка |
| **AVG(field)** | Среднее значение | Средний чек |
| **MIN(field)** | Минимум | Самый маленький заказ |
| **MAX(field)** | Максимум | Самый большой заказ |

### Условная логика в SQL

```php
// IF(условие, значение_если_true, значение_если_false)
'SUM(IF(%s = "Y", 1, 0))'  // подсчет оплаченных

// CASE WHEN для сложных условий
new Entity\ExpressionField(
    'ORDER_SIZE',
    'CASE 
        WHEN %s < 1000 THEN "small"
        WHEN %s < 5000 THEN "medium"
        ELSE "large"
    END',
    ['FINAL_AMOUNT', 'FINAL_AMOUNT']
)
```

## Примеры из examples.php

Файл содержит готовые примеры для:

1. ✅ Создание тестовых данных (10 заказов)
2. ✅ Общая статистика продаж (выручка, средний чек, min/max)
3. ✅ Распределение по статусам
4. ✅ Топ-5 покупателей
5. ✅ Статистика по городам доставки
6. ✅ Конверсия оплаты по источникам
7. ✅ Средняя скидка
8. ✅ Статистика по способам оплаты
9. ✅ Топ-3 региона
10. ✅ Процент отмененных заказов
11. ✅ Средний срок доставки
12. ✅ Динамика по месяцам

## Частые паттерны

### Конверсия (процент от общего числа)

```php
new Entity\ExpressionField(
    'CONVERSION_RATE',
    'ROUND(SUM(IF(%s = "Y", 1, 0)) / COUNT(*) * 100, 2)',
    ['IS_PAID']
)
```

### Средний чек по группе

```php
'select' => ['USER_ID', 'AVG_ORDER'],
'runtime' => [
    new Entity\ExpressionField('AVG_ORDER', 'AVG(%s)', ['FINAL_AMOUNT']),
],
'group' => ['USER_ID'],
```

### Количество уникальных значений

```php
new Entity\ExpressionField(
    'UNIQUE_CITIES',
    'COUNT(DISTINCT %s)',
    ['DELIVERY_CITY']
)
```

### Распределение по диапазонам

```php
'runtime' => [
    new Entity\ExpressionField(
        'RANGE_0_1000',
        'SUM(IF(%s < 1000, 1, 0))',
        ['FINAL_AMOUNT']
    ),
    new Entity\ExpressionField(
        'RANGE_1000_5000',
        'SUM(IF(%s BETWEEN 1000 AND 5000, 1, 0))',
        ['FINAL_AMOUNT']
    ),
]
```

## Оптимизация запросов

### ✅ Правильно — используем индексы

```php
'filter' => [
    '=STATUS' => 'DELIVERED',  // есть INDEX IX_STATUS
    '>DATE_CREATE' => $date,   // есть INDEX IX_DATE_CREATE
]
```

### ❌ Неправильно — вычисления в фильтре

```php
'filter' => [
    // Индекс не используется из-за вычислений!
    'YEAR(DATE_CREATE)' => 2024,
]
```

### ✅ Правильно — диапазон дат

```php
$yearStart = new DateTime('2024-01-01');
$yearEnd = new DateTime('2024-12-31 23:59:59');

'filter' => [
    '><DATE_CREATE' => [$yearStart, $yearEnd],  // использует индекс
]
```

## Отладка запросов

Чтобы увидеть SQL, который генерирует ORM:

```php
$connection = \Bitrix\Main\Application::getConnection();
$connection->startTracker();

// Ваш запрос
$result = OrderTable::getList([...]);

$tracker = $connection->getTracker();
foreach ($tracker->getQueries() as $query) {
    echo $query->getSql() . "\n";
}

$connection->stopTracker();
```

## Задания для самопроверки

1. Добавьте метод для расчета LTV (Lifetime Value) клиентов
2. Создайте отчет "Когорты по месяцам регистрации"
3. Реализуйте RFM-анализ (Recency, Frequency, Monetary)
4. Добавьте расчет retention rate (возвращаемость клиентов)
5. Постройте воронку продаж по статусам
6. Создайте метод для ABC-анализа товаров/клиентов
7. Добавьте расчет сезонности продаж

## Что дальше?

После изучения этой задачи переходите к:
- **Задача 3**: Связь товаров и категорий через Reference

## Полезные ссылки

- [ExpressionField документация](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/expressionfield/index.php)
- [Агрегатные функции MySQL](https://dev.mysql.com/doc/refman/8.0/en/aggregate-functions.html)
- [Группировка и Having](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5753)
