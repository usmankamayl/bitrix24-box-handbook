# Задача 1: SqlAnalyzer — анализ SQL-запросов

## Что делает

Инструмент для поиска проблем производительности:

1. **N+1 детектор** — группирует похожие запросы. Если видишь `[50x] SELECT * FROM b_user WHERE ID=?` — это N+1.

2. **Медленные запросы** — все запросы >50мс.

3. **Сводная статистика** — время PHP vs время SQL. Если PHP доминирует — проблема не в БД.

## Использование

```php
// В сервисе или компоненте
$analyzer = new SqlAnalyzer();
$analyzer->start();

$items = MyService::getHeavyData();

$report = $analyzer->stop();
$analyzer->printReport($report);
```

```bash
# CLI — анализ страницы
php sql-analyze.php /catalog/123/

# Сохранить отчёт
php sql-analyze.php /catalog/ --save-report=/tmp/catalog.json
```

## Что искать в отчёте

| Находка | Диагноз |
|---------|---------|
| `[50x] SELECT ... WHERE ID=?` | N+1 — добавь `->with()` или JOIN |
| `total_time=2.5s` в одном запросе | Медленный запрос — нужен EXPLAIN, индекс |
| `total_queries=500` на странице | Слишком много запросов — кеширование, объединение |
| `php_time=1.5s, sql_time=0.1s` | Проблема в PHP-коде, не в БД |

## Файлы

- `SqlAnalyzer.php` — класс-анализатор
- `sql-analyze.php` — CLI-скрипт
