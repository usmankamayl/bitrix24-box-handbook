# Задача 3: Оптимизация индексов

## Что в файле

Полный пайплайн оптимизации запросов:

1. **Сбор медленных запросов** — парсинг MySQL slow log
2. **EXPLAIN-анализ** — автоматические рекомендации
3. **Генерация индексов** — парсинг WHERE/ORDER BY/GROUP BY
4. **Тестирование** — замер ДО/ПОСЛЕ, откат если не помогло
5. **Готовые рецепты** — индексы для типичных Bitrix-запросов

## Senior-подход

Не просто "добавь индекс", а:

```php
// 1. Нашли медленный запрос
$sql = "SELECT * FROM b_iblock_element WHERE IBLOCK_ID=5 ORDER BY SORT LIMIT 50000,20";

// 2. EXPLAIN → видим ALL + filesort
$explain = $optimizer->explain($sql);
$recommendations = $optimizer->analyzeExplain($explain);

// 3. Предлагаем индекс
$suggestions = $optimizer->suggestIndex($sql, 'b_iblock_element');

// 4. Тестируем — помогло? Оставляем. Не помогло? Удаляем.
$result = $optimizer->testIndex($sql, $suggestions[0], "DROP INDEX idx_...");
```

## Чеклист индексов

- [ ] `type: ALL` → нужен индекс
- [ ] `rows: 50000` при `LIMIT 20` → неэффективный индекс
- [ ] `Using filesort` → ORDER BY не покрыт индексом
- [ ] `Using temporary` → GROUP BY/ORDER BY создают временную таблицу
- [ ] Составной индекс: WHERE-колонки слева, ORDER BY справа
- [ ] `ANALYZE TABLE` после создания индекса

## Файлы

- `IndexOptimization.php` — класс + готовые SQL-индексы
