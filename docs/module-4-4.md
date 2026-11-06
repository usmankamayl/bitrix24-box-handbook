# Модуль 4.4: Оптимизация запросов в Bitrix

## Теория

### Проблемы производительности БД в Bitrix

Bitrix — высоконагруженная CMS. Типичные проблемы:

1. **N+1 запросы** — в ORM при связях и в `CIBlockElement::GetList` при свойствах
2. **Отсутствие индексов** — кастомные таблицы без индексов, неправильные индексы
3. **`SELECT *`** — выбираем все поля, когда нужны 3
4. **Неэффективные фильтры** — LIKE, OR, функции в WHERE
5. **Тяжёлые джойны** — соединяем большие таблицы без индексов
6. **Пагинация через OFFSET** — `LIMIT 50000, 20` сканирует 50к строк

### 1. ORM — оптимизация запросов

#### Выбирай только нужные поля

```php
// ❌ ПЛОХО — тащим все поля, включая большие TEXT
$items = ProductTable::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['*'],
]);

// ✅ ХОРОШО — только нужное
$items = ProductTable::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['ID', 'NAME', 'PRICE'],
]);
```

#### Избегай N+1 через `->with()` и `select`

```php
// ❌ ПЛОХО — N+1
$orders = OrderTable::getList([
    'filter' => ['STATUS' => 'N'],
    'select' => ['ID', 'USER_ID'],
]);
foreach ($orders as $order) {
    $user = UserTable::getById($order['USER_ID'])->fetch();
    // 100 заказов = 100 запросов!
}

// ✅ ХОРОШО — один запрос с join
$orders = OrderTable::getList([
    'filter' => ['STATUS' => 'N'],
    'select' => ['ID', 'USER_NAME' => 'USER.NAME', 'USER_EMAIL' => 'USER.EMAIL'],
    'runtime' => [
        'USER' => [
            'data_type' => UserTable::class,
            'reference' => ['=this.USER_ID' => 'ref.ID'],
        ],
    ],
]);
```

#### Для коллекций — `->with()`

```php
// Загружаем заказы С пользователями одним запросом
$orders = OrderTable::getList([
    'filter' => ['STATUS' => 'N'],
    'select' => ['*', 'USER'],
    // или в новом API:
    // 'with' => ['USER'],
]);
```

### 2. CIBlockElement::GetList — оптимизация

```php
// ❌ ПЛОХО
$res = CIBlockElement::GetList(
    [],                                    // без сортировки — непредсказуемый порядок
    ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y'],
    false,
    false,                                 // без пагинации — все 50к элементов в память!
    ['*']                                  // все поля
);

// ✅ ХОРОШО
$res = CIBlockElement::GetList(
    ['SORT' => 'ASC', 'ID' => 'ASC'],      // явная сортировка
    ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y'],
    false,
    ['nPageSize' => 1000, 'iNumPage' => 1], // пагинация
    ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE'] // только нужное
);
```

#### Оптимизация свойств

```php
// ❌ ПЛОХО — для каждого элемента дополнительный запрос на свойства
$res = CIBlockElement::GetList(..., ['ID', 'NAME']);
while ($item = $res->GetNext()) {
    $props = CIBlockElement::GetProperties($item['IBLOCK_ID'], $item['ID']);
    // N запросов!
}

// ✅ ХОРОШО — GetList с PROPERTY_*
$res = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    ['IBLOCK_ID' => 5],
    false, false,
    ['ID', 'NAME', 'PROPERTY_PRICE', 'PROPERTY_ARTICLE']
);
```

### 3. Индексы — что нужно знать

#### Составные индексы и порядок колонок

```sql
-- Запрос:
SELECT * FROM orders WHERE status='N' AND date_create > '2024-01-01' ORDER BY id

-- ❌ Плохой индекс — только status, без date_create и id
ADD INDEX idx_status (status);

-- ✅ Хороший индекс — покрывает WHERE + ORDER BY
ADD INDEX idx_status_date (status, date_create, id);
-- MySQL использует индекс для status + date_create, сортировка по id — filesort
-- Но это уже лучше чем полное сканирование

-- ✅ Идеальный — если часто фильтруем по дате внутри статуса:
ADD INDEX idx_status_date_id (status, date_create, id);
```

#### Правило левого префикса

```sql
-- Индекс (status, date_create, id):

-- ✅ Использует индекс
WHERE status='N' AND date_create > '...'
WHERE status='N'

-- ❌ Не использует (или использует частично)
WHERE date_create > '...' -- без status в начале!
```

#### Индексы для сортировки

```sql
-- ORDER BY без индекса = filesort (медленно на больших данных)

-- Индекс для сортировки:
ADD INDEX idx_sort (status, date_create);
-- Запрос:
WHERE status='N' ORDER BY date_create -- использует индекс для сортировки

-- Но:
WHERE status='N' ORDER BY price -- filesort! индекс не помогает
```

#### EXPLAIN — диагностика

```sql
EXPLAIN SELECT * FROM orders WHERE status='N' ORDER BY date_create;

-- Смотрим:
-- key: idx_status_date → какой индекс используется (NULL = нет индекса)
-- type: ALL → полное сканирование (плохо)
--       ref → поиск по индексу (хорошо)
--       range → диапазон (нормально)
-- rows: 50000 → сколько строк придётся прочитать (меньше = лучше)
-- Extra: Using filesort → нет индекса для сортировки
--        Using where → дополнительная фильтрация после индекса
```

### 4. Пагинация — OFFSET vs Cursor

```php
// ❌ ПЛОХО — OFFSET на больших данных
// LIMIT 50000, 20 — читает и отбрасывает 50000 строк!
$res = ProductTable::getList([
    'filter' => ['IBLOCK_ID' => 5],
    'select' => ['ID', 'NAME'],
    'offset' => 50000,
    'limit'  => 20,
    'order'  => ['ID' => 'ASC'],
]);

// ✅ ХОРОШО — курсорная пагинация (keyset pagination)
// "После ID=50000" — не сканирует предыдущие строки
$res = ProductTable::getList([
    'filter' => ['IBLOCK_ID' => 5, '>ID' => $lastSeenId],
    'select' => ['ID', 'NAME'],
    'limit'  => 20,
    'order'  => ['ID' => 'ASC'],
]);
// Последний ID из предыдущей выборки — "курсор"
```

### 5. Агрегация — COUNT и SUM

```php
// ❌ ПЛОХО — выбираем все, считаем в PHP
$items = ProductTable::getList([...]);
$count = count($items); // 50к строк в память!

// ✅ ХОРОШО — COUNT в SQL
$count = ProductTable::getCount(['IBLOCK_ID' => 5]);

// Сумма с группировкой:
$stats = OrderTable::getList([
    'filter' => ['STATUS' => 'PAYED'],
    'select' => ['USER_ID', 'TOTAL' => new \Bitrix\Main\DB\SqlExpression('SUM(PRICE)')],
    'group'  => ['USER_ID'],
]);
```

### 6. Subquery и JOIN — что быстрее

```php
// Вариант 1: JOIN (обычно быстрее, чем подзапрос)
$res = OrderTable::getList([
    'filter' => ['USER.ACTIVE' => 'Y'],
    'runtime' => [
        'USER' => [
            'data_type' => UserTable::class,
            'reference' => ['=this.USER_ID' => 'ref.ID'],
        ],
    ],
]);

// Вариант 2: подзапрос (когда JOIN не нужен — только проверка существования)
$activeUserIds = UserTable::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['ID'],
])->fetchAll();

$res = OrderTable::getList([
    'filter' => ['USER_ID' => array_column($activeUserIds, 'ID')],
]);
```

### 7. Batch-операции

```php
// ❌ ПЛОХО — обновление в цикле
foreach ($productIds as $id) {
    ProductTable::update($id, ['STOCK' => 0]);
    // 1000 запросов!
}

// ✅ ХОРОШО — один запрос
ProductTable::updateMulti($productIds, ['STOCK' => 0]);
// или через query:
// UPDATE product SET STOCK=0 WHERE ID IN (...)
```

### 8. MySQL-специфичные оптимизации

#### Полнотекстовый поиск вместо LIKE

```sql
-- ❌ ПЛОХО — LIKE %text% не использует индекс
SELECT * FROM product WHERE name LIKE '%iphone%';

-- ✅ ХОРОШО — FULLTEXT индекс
ALTER TABLE product ADD FULLTEXT(name);
SELECT * FROM product WHERE MATCH(name) AGAINST('iphone' IN NATURAL LANGUAGE MODE);
```

#### LIMIT для существования

```php
// ❌ ПЛОХО — получаем все, проверяем count
$items = ProductTable::getList(['filter' => ['NAME' => 'test']]);
$exists = count($items) > 0;

// ✅ ХОРОШО — LIMIT 1
$item = ProductTable::getList([
    'filter' => ['NAME' => 'test'],
    'select' => ['ID'],
    'limit'  => 1,
])->fetch();
$exists = !empty($item);
```

---

## Вопросы для собеседования

### Q1: Что такое N+1 и как его решить в ORM?

**Ответ:** N+1 — когда для каждой строки основного запроса делается дополнительный запрос. Решение: `select` с полями связанной таблицы через runtime reference, или `->with('RELATION')` в новом API.

### Q2: Как найти медленный запрос?

**Ответ:** `EXPLAIN` — смотрим `type` (ALL=плохо, ref/range=хорошо), `rows` (сколько сканируем), `Extra` (Using filesort, Using where). Плюс `SqlTracker` в Bitrix для сбора всех запросов страницы.

### Q3: Почему `SELECT *` плохо?

**Ответ:** 1) Тащим ненужные данные (большие TEXT/BLOB поля) — больше IO и память; 2) индекс не может быть covering (не содержит всех полей) → нужен lookup в данные; 3) меньше помещается в буфер.

### Q4: Как работает составной индекс и правило левого префикса?

**Ответ:** Индекс `(a, b, c)` работает для `WHERE a=`, `WHERE a= AND b=`, `WHERE a= AND b= AND c=`. НЕ работает для `WHERE b=` без `a`. Порядок колонок критичен.

### Q5: Чем `LIMIT 50000, 20` плох?

**Ответ:** Читает 50k строк, сортирует, отбрасывает 50k, отдаёт 20. На больших таблицах — сканирование всей таблицы. Решение: курсорная пагинация (`WHERE id > last_id LIMIT 20`).

### Q6: Когда нужен FULLTEXT вместо LIKE?

**Ответ:** `LIKE '%text%'` не использует индекс — полное сканирование. FULLTEXT с MATCH...AGAINST использует специальный индекс и быстрее на больших текстах. Для поиска по части слова (`%phone%`) FULLTEXT не поможет — нужен ngram или Elasticsearch.

---

## Практические задачи

- **Задача 1:** Оптимизация N+1 в списке заказов с пользователями
- **Задача 2:** Курсорная пагинация vs OFFSET на больших данных
- **Задача 3:** Поиск медленных запросов и создание индексов

**Решения задач находятся в `examples/module-4-4/`**
