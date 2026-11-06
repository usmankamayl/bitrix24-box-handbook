<?php
/**
 * Практика оптимизации запросов: поиск медленных,
 * создание индексов, EXPLAIN-анализ.
 *
 * Senior-контекст: на реальном проекте ты не можешь
 * просто "добавить индекс" — нужно понять, какие запросы
 * медленные, почему, и не сломает ли индекс другие запросы.
 */

namespace Vendor\Shop\Optimization;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;

class IndexOptimization
{
    private Connection $db;

    public function __construct()
    {
        $this->db = Application::getConnection();
    }

    // ============================================================
    // ШАГ 1: Собираем медленные запросы
    // ============================================================

    /**
     * В реальном проекте — включаем slow query log MySQL:
     *
     * [mysqld]
     * slow_query_log = 1
     * slow_query_log_file = /var/log/mysql/slow.log
     * long_query_time = 0.5    -- все запросы >500мс
     * log_queries_not_using_indexes = 1
     *
     * Или используем perfmon в Bitrix.
     */
    public function getSlowQueries(): array
    {
        // Читаем slow log MySQL (если доступен)
        $slowLog = '/var/log/mysql/slow.log';
        $queries = [];

        if (file_exists($slowLog)) {
            $content = file_get_contents($slowLog);
            // Парсим типичный формат:
            // # Time: 2024-01-15T10:00:00
            // # Query_time: 2.5  Lock_time: 0.0  Rows_sent: 100  Rows_examined: 50000
            // SELECT * FROM ...
            preg_match_all(
                '/# Query_time: ([\d.]+).*?\n(SELECT.*?);/s',
                $content,
                $matches
            );

            foreach ($matches[1] as $i => $time) {
                $queries[] = [
                    'time' => (float)$time,
                    'sql'  => trim($matches[2][$i]),
                ];
            }
        }

        return $queries;
    }

    // ============================================================
    // ШАГ 2: Анализируем через EXPLAIN
    // ============================================================

    /**
     * Запускаем EXPLAIN для подозрительного запроса.
     */
    public function explain(string $sql): array
    {
        // Убираем LIMIT для чистого анализа (опционально)
        $explainSql = 'EXPLAIN ' . $sql;
        $result = $this->db->query($explainSql);

        $explain = [];
        while ($row = $result->fetch()) {
            $explain[] = $row;
        }
        return $explain;
    }

    /**
     * Анализируем результат EXPLAIN и даём рекомендации.
     */
    public function analyzeExplain(array $explain): array
    {
        $recommendations = [];

        foreach ($explain as $row) {
            $table = $row['table'] ?? 'unknown';
            $type  = $row['type'] ?? '';
            $key   = $row['key'] ?? null;
            $rows  = (int)($row['rows'] ?? 0);
            $extra = $row['Extra'] ?? '';

            // 1. Полное сканирование (ALL) — нет индекса
            if ($type === 'ALL') {
                $recommendations[] = "❌ Full table scan on {$table}: no index used. " .
                    "Consider adding an index on WHERE/JOIN columns.";
            }

            // 2. Индекс не используется
            if (!$key && $type !== 'system' && $type !== 'const') {
                $recommendations[] = "⚠️ {$table}: no index used (type={$type}). " .
                    "Check WHERE columns for index coverage.";
            }

            // 3. Filesort — нет индекса для сортировки
            if (str_contains($extra, 'Using filesort')) {
                $recommendations[] = "⚠️ {$table}: filesort needed. " .
                    "Consider adding index for ORDER BY columns.";
            }

            // 4. Temporary table — GROUP BY/ORDER BY создают временную таблицу
            if (str_contains($extra, 'Using temporary')) {
                $recommendations[] = "⚠️ {$table}: temporary table created. " .
                    "Simplify GROUP BY/ORDER BY or add covering index.";
            }

            // 5. Много строк сканируется
            if ($rows > 10000 && $type !== 'index') {
                $recommendations[] = "⚠️ {$table}: scanning {$rows} rows. " .
                    "Add index to reduce examined rows.";
            }

            // 6. Index merge — может быть медленнее, чем один хороший индекс
            if ($type === 'index_merge') {
                $recommendations[] = "ℹ️ {$table}: index_merge used. " .
                    "A composite index might be faster.";
            }
        }

        return $recommendations;
    }

    // ============================================================
    // ШАГ 3: Предлагаем индексы
    // ============================================================

    /**
     * Генерируем рекомендации по индексам на основе запроса.
     */
    public function suggestIndex(string $sql, string $table): array
    {
        $suggestions = [];

        // Простой парсинг WHERE и ORDER BY
        preg_match('/WHERE\s+(.+?)(?:ORDER BY|GROUP BY|LIMIT|$)/is', $sql, $whereMatch);
        preg_match('/ORDER BY\s+(.+?)(?:LIMIT|$)/is', $sql, $orderMatch);
        preg_match('/GROUP BY\s+(.+?)(?:ORDER BY|LIMIT|$)/is', $sql, $groupMatch);

        $whereCols  = $this->extractColumns($whereMatch[1] ?? '');
        $orderCols  = $this->extractColumns($orderMatch[1] ?? '');
        $groupCols  = $this->extractColumns($groupMatch[1] ?? '');

        // Правило: WHERE-колонки в начале индекса, ORDER BY в конце
        if (!empty($whereCols) || !empty($orderCols) || !empty($groupCols)) {
            $indexCols = array_merge($whereCols, $groupCols, $orderCols);
            $indexCols = array_unique($indexCols);

            if (!empty($indexCols)) {
                $indexName = 'idx_' . implode('_', array_slice($indexCols, 0, 3));
                $suggestions[] = sprintf(
                    "ALTER TABLE %s ADD INDEX %s (%s);",
                    $table,
                    $indexName,
                    implode(', ', array_slice($indexCols, 0, 4)) // max 4 колонки
                );
            }
        }

        return $suggestions;
    }

    private function extractColumns(string $clause): array
    {
        // Упрощённый парсинг — ищем column = value или column > value
        preg_match_all(
            '/(?:`?(\w+)`?\s*(?:=|>|<|>=|<=|!=|LIKE|IN)|`?(\w+)`?\s+ASC|`?(\w+)`?\s+DESC)/i',
            $clause,
            $matches
        );

        $cols = [];
        foreach ([1, 2, 3] as $i) {
            if (!empty($matches[$i])) {
                $cols = array_merge($cols, array_filter($matches[$i]));
            }
        }
        return array_unique(array_filter($cols, fn($c) => !is_numeric($c)));
    }

    // ============================================================
    // ШАГ 4: Применяем индекс и замеряем
    // ============================================================

    /**
     * Проверяем, помог ли индекс.
     */
    public function testIndex(string $sql, string $indexSql, string $dropSql): array
    {
        $result = ['before' => null, 'after' => null, 'index_created' => false];

        // Замер ДО
        $start = microtime(true);
        $this->db->query($sql)->fetch();
        $result['before'] = microtime(true) - $start;

        // Создаём индекс
        try {
            $this->db->queryExecute($indexSql);
            $result['index_created'] = true;

            // ANALYZE TABLE — обновляем статистику
            $this->db->queryExecute("ANALYZE TABLE " . $this->getTableName($sql));

            // Замер ПОСЛЕ
            $start = microtime(true);
            $this->db->query($sql)->fetch();
            $result['after'] = microtime(true) - $start;

        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        // Откатываем — оставляем только если помогло
        if ($result['index_created'] && $result['after'] >= $result['before']) {
            // Не помогло — удаляем
            $this->db->queryExecute($dropSql);
            $result['index_created'] = false;
            $result['note'] = 'Index did not help, dropped';
        }

        return $result;
    }

    private function getTableName(string $sql): string
    {
        preg_match('/FROM\s+`?(\w+)`?/i', $sql, $m);
        return $m[1] ?? '';
    }
}


// ============================================================
// Готовые рецепты для типичных Bitrix-запросов
// ============================================================

$bitrixIndexes = [
    // Список элементов инфоблока с фильтрацией и сортировкой
    'iblock_elements' => "
        ALTER TABLE b_iblock_element
        ADD INDEX idx_iblock_active_sort (IBLOCK_ID, ACTIVE, SORT),
        ADD INDEX idx_iblock_section_active (IBLOCK_SECTION_ID, ACTIVE, ID);
        -- Покрывает: WHERE IBLOCK_ID=5 AND ACTIVE='Y' ORDER BY SORT
        --           WHERE IBLOCK_SECTION_ID=12 AND ACTIVE='Y'
    ",

    // Заказы по пользователю и статусу
    'sale_orders' => "
        ALTER TABLE b_sale_order
        ADD INDEX idx_user_status_date (USER_ID, STATUS_ID, DATE_INSERT),
        ADD INDEX idx_status_date (STATUS_ID, DATE_INSERT);
        -- Покрывает: WHERE USER_ID=123 AND STATUS_ID='N' ORDER BY DATE_INSERT
    ",

    // Поиск по свойствам инфоблока (b_iblock_element_property)
    'element_properties' => "
        ALTER TABLE b_iblock_element_property
        ADD INDEX idx_element_property_value (IBLOCK_ELEMENT_ID, IBLOCK_PROPERTY_ID, VALUE);
        -- Покрывает: WHERE IBLOCK_ELEMENT_ID=5 AND IBLOCK_PROPERTY_ID=10 AND VALUE='...'
    ",

    // Highload-блоки — свои таблицы, свои индексы
    'hlblock_example' => "
        -- Для HL-блока 'my_table':
        ALTER TABLE my_table
        ADD INDEX idx_uf_product_uf_size (UF_PRODUCT_ID, UF_SIZE),
        ADD INDEX idx_uf_active_date (UF_ACTIVE, UF_DATE_CREATE);
    ",
];
