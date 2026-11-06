<?php
/**
 * Инструмент для анализа SQL-запросов на странице.
 *
 * Запуск:
 *   php sql-analyzer.php --url=/catalog/123/
 *   php sql-analyzer.php --code=MyService::getOrders
 *
 * В компоненте — обёртка для измерения конкретного блока.
 */

namespace Vendor\Debug;

use Bitrix\Main\Diag\SqlTracker;
use Bitrix\Main\Loader;

class SqlAnalyzer
{
    private SqlTracker $tracker;
    private float $startTime;
    private float $startMemory;

    public function __construct()
    {
        $this->tracker = new SqlTracker();
    }

    public function start(): void
    {
        $this->tracker->start();
        $this->startTime   = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Останавливаем и возвращаем отчёт.
     */
    public function stop(): array
    {
        $this->tracker->stop();

        $queries = $this->tracker->getQueries();
        $totalTime = $this->tracker->getTime();
        $wallTime  = microtime(true) - $this->startTime;
        $memoryUsed = memory_get_usage(true) - $this->startMemory;

        // Группируем похожие запросы (N+1 detector)
        $grouped = [];
        foreach ($queries as $query) {
            // Нормализуем SQL: подставляем ? вместо значений
            $normalized = preg_replace(
                '/\s+/', ' ',
                preg_replace(
                    '/(\'[^\']*\'|\d+)/',
                    '?',
                    $query->getSql()
                )
            );

            if (!isset($grouped[$normalized])) {
                $grouped[$normalized] = [
                    'sql'       => $query->getSql(),
                    'normalized'=> $normalized,
                    'count'     => 0,
                    'total_time'=> 0,
                    'min_time'  => PHP_FLOAT_MAX,
                    'max_time'  => 0,
                    'nodes'     => $query->getNodes(),
                ];
            }
            $grouped[$normalized]['count']++;
            $grouped[$normalized]['total_time'] += $query->getTime();
            $grouped[$normalized]['min_time'] = min(
                $grouped[$normalized]['min_time'], $query->getTime()
            );
            $grouped[$normalized]['max_time'] = max(
                $grouped[$normalized]['max_time'], $query->getTime()
            );
        }

        // Сортируем по общему времени
        usort($grouped, fn($a, $b) => $b['total_time'] <=> $a['total_time']);

        return [
            'summary' => [
                'total_queries' => count($queries),
                'unique_queries'=> count($grouped),
                'total_sql_time'=> round($totalTime, 4),
                'wall_time'     => round($wallTime, 4),
                'memory_used'   => $this->formatBytes($memoryUsed),
                'php_time'      => round($wallTime - $totalTime, 4),
            ],
            'slow_queries'   => array_filter($grouped, fn($q) => $q['total_time'] > 0.05),
            'repeated_queries' => array_filter($grouped, fn($q) => $q['count'] > 1),
            'all_queries'    => $grouped,
        ];
    }

    /**
     * Печатаем красивый отчёт в консоль.
     */
    public function printReport(array $report): void
    {
        $s = $report['summary'];
        echo str_repeat('=', 70) . "\n";
        echo "SQL ANALYSIS REPORT\n";
        echo str_repeat('=', 70) . "\n";
        printf(
            "Queries: %d total | %d unique | SQL time: %.3fs | PHP time: %.3fs\n",
            $s['total_queries'], $s['unique_queries'],
            $s['total_sql_time'], $s['php_time']
        );
        printf("Wall time: %.3fs | Memory: %s\n", $s['wall_time'], $s['memory_used']);
        echo str_repeat('-', 70) . "\n";

        // N+1 детектор
        if (!empty($report['repeated_queries'])) {
            echo "🔄 REPEATED QUERIES (possible N+1):\n";
            foreach ($report['repeated_queries'] as $q) {
                printf(
                    "  [%dx] %.3fs total | %.3fs avg | %.3fs max\n",
                    $q['count'], $q['total_time'],
                    $q['total_time'] / $q['count'], $q['max_time']
                );
                echo "  SQL: " . substr($q['sql'], 0, 120) . "...\n\n";
            }
        }

        // Медленные запросы
        if (!empty($report['slow_queries'])) {
            echo "🐌 SLOW QUERIES (>50ms):\n";
            foreach ($report['slow_queries'] as $q) {
                printf(
                    "  [%.3fs] %s\n",
                    $q['total_time'],
                    substr($q['sql'], 0, 150)
                );
                if ($q['count'] > 1) {
                    echo "  (repeated {$q['count']} times)\n";
                }
                echo "\n";
            }
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}


// ============================================================
// Пример использования в компоненте или сервисе
// ============================================================

class DebuggableService
{
    /**
     * Обёртка для анализа SQL в сервисе.
     * Используй только для отладки — на проде убирай!
     */
    public function analyzeMethod(callable $callback): array
    {
        $analyzer = new SqlAnalyzer();
        $analyzer->start();

        $result = $callback();

        $report = $analyzer->stop();
        $analyzer->printReport($report);

        return $result;
    }
}

// Пример:
// $service = new DebuggableService();
// $orders = $service->analyzeMethod(function () {
//     return OrderTable::getList([...])->fetchAll();
// });
