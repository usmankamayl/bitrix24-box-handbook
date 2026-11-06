<?php
/**
 * CLI-скрипт для анализа SQL-запросов на конкретной странице.
 *
 * Запуск:
 *   php sql-analyze.php /catalog/123/
 *   php sql-analyze.php /catalog/ --save-report=/tmp/report.json
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
$_SERVER['REQUEST_URI']   = $argv[1] ?? '/';
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['REQUEST_METHOD']= 'GET';

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Vendor\Debug\SqlAnalyzer;
use Bitrix\Main\Diag\SqlTracker;

// Глобальный трекер — включаем с самого начала
$tracker = new SqlTracker();
$tracker->start();
$startTime = microtime(true);

// Выполняем страницу
ob_start();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/urlrewrite.php';
$content = ob_get_clean();

$tracker->stop();
$totalTime = microtime(true) - $startTime;

// Анализируем
$analyzer = new SqlAnalyzer();
$report = [
    'url' => $_SERVER['REQUEST_URI'],
    'time' => date('c'),
    'total_time' => round($totalTime, 4),
    'queries' => array_map(function ($q) {
        return [
            'sql'  => $q->getSql(),
            'time' => $q->getTime(),
            'node' => $q->getNodes(),
        ];
    }, $tracker->getQueries()),
];

// Группировка для отчёта
$grouped = [];
foreach ($report['queries'] as $q) {
    $normalized = preg_replace('/\'[^\']*\'|\d+/', '?', $q['sql']);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $grouped[$normalized][] = $q;
}

echo "=== SQL Analysis: {$_SERVER['REQUEST_URI']} ===\n";
echo "Total time: {$report['total_time']}s\n";
echo "Total queries: " . count($report['queries']) . "\n";
echo "Unique queries: " . count($grouped) . "\n\n";

// N+1
echo "=== Possible N+1 ===\n";
foreach ($grouped as $norm => $queries) {
    if (count($queries) > 1) {
        $total = array_sum(array_column($queries, 'time'));
        printf("[%dx] %.3fs total: %s\n", count($queries), $total, substr($norm, 0, 100));
    }
}

echo "\n=== Slowest queries ===\n";
$allQueries = $report['queries'];
usort($allQueries, fn($a, $b) => $b['time'] <=> $a['time']);
foreach (array_slice($allQueries, 0, 10) as $q) {
    printf("%.3fs: %s\n", $q['time'], substr($q['sql'], 0, 120));
}

// Сохраняем отчёт
if (!empty($argv[2]) && str_starts_with($argv[2], '--save-report=')) {
    $path = substr($argv[2], strlen('--save-report='));
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));
    echo "\nReport saved to {$path}\n";
}
