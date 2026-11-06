<?php
/**
 * Пошаговая диагностика "медленной страницы" — senior-подход.
 *
 * Сценарий: пользователи жалуются, что страница каталога грузится 8 секунд.
 * Показываем полный процесс диагностики от симптома до решения.
 */

// ============================================================
// ШАГ 1: Воспроизводим проблему и замеряем
// ============================================================

// CLI-замер — быстрее чем браузер, нет кеша
$_SERVER['DOCUMENT_ROOT'] = '/var/www/site';
$_SERVER['REQUEST_URI']   = '/catalog/phones/123/';
$_SERVER['HTTP_HOST']     = 'site.com';

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Diag\SqlTracker;

$start = microtime(true);

// Выполняем страницу
ob_start();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/urlrewrite.php';
$html = ob_get_clean();

$totalTime = microtime(true) - $start;

echo "=== PAGE LOAD: {$_SERVER['REQUEST_URI']} ===\n";
echo "Total: {$totalTime}s\n\n";

// ============================================================
// ШАГ 2: Разделяем время PHP / MySQL / внешние
// ============================================================

$tracker = new SqlTracker();
$tracker->start();
$startDb = microtime(true);

// Повторяем запрос к БД с трекером
// (в реальном проекте — оборачиваем конкретный компонент/метод)

// ...

$tracker->stop();
$dbTime = $tracker->getTime();
$phpTime = $totalTime - $dbTime;

printf("PHP: %.3fs | MySQL: %.3fs\n", $phpTime, $dbTime);
// Если PHP >> MySQL — проблема в коде (циклы, регулярки, PHP-функции)
// Если MySQL >> PHP — проблема в запросах

// ============================================================
// ШАГ 3: Находим N+1
// ============================================================

$queries = $tracker->getQueries();
$grouped = [];

foreach ($queries as $q) {
    // Нормализуем: убираем конкретные значения
    $normalized = preg_replace('/\'[^\']*\'|\d+/', '?', $q->getSql());
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $grouped[$normalized]['count'] = ($grouped[$normalized]['count'] ?? 0) + 1;
    $grouped[$normalized]['sql']   = $q->getSql();
    $grouped[$normalized]['time']  = ($grouped[$normalized]['time'] ?? 0) + $q->getTime();
}

// Сортируем по количеству повторений
uasort($grouped, fn($a, $b) => $b['count'] <=> $a['count']);

echo "=== TOP REPEATED (possible N+1) ===\n";
foreach (array_slice($grouped, 0, 10) as $item) {
    if ($item['count'] > 1) {
        printf("[%dx] %.3fs: %s\n", $item['count'], $item['time'],
            substr($item['sql'], 0, 120));
    }
}

// ============================================================
// ШАГ 4: Находим медленные запросы
// ============================================================

usort($queries, fn($a, $b) => $b->getTime() <=> $a->getTime());

echo "\n=== SLOWEST QUERIES ===\n";
foreach (array_slice($queries, 0, 10) as $q) {
    printf("%.3fs: %s\n", $q->getTime(), substr($q->getSql(), 0, 150));
}

// ============================================================
// ШАГ 5: EXPLAIN для подозрительного запроса
// ============================================================

/*
// Найдён медленный запрос:
SELECT * FROM b_iblock_element
WHERE IBLOCK_ID = 5 AND ACTIVE = 'Y'
ORDER BY SORT ASC
LIMIT 100000, 20

// Добавляем EXPLAIN:
EXPLAIN SELECT * FROM b_iblock_element
WHERE IBLOCK_ID = 5 AND ACTIVE = 'Y'
ORDER BY SORT ASC LIMIT 100000, 20

// Смотрим:
// type: ALL → полное сканирование, нет индекса!
// rows: 50000 → сканирует 50к строк для 20 результатов
// Extra: Using filesort → нет индекса для сортировки

// Решение — составной индекс:
ALTER TABLE b_iblock_element
ADD INDEX idx_iblock_active_sort (IBLOCK_ID, ACTIVE, SORT);
*/

// ============================================================
// ШАГ 6: Проверяем кеш
// ============================================================

// Время с повторного запроса (кеш должен быть тёплым)
$start2 = microtime(true);
ob_start();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/urlrewrite.php';
ob_get_clean();
$cachedTime = microtime(true) - $start2;

echo "\n=== CACHE CHECK ===\n";
echo "Cold: {$totalTime}s | Warm: {$cachedTime}s\n";
// Если warm == cold — кеш не работает
// Если warm << cold — кеш работает

// ============================================================
// ШАГ 7: Компонентный профайлинг
// ============================================================

// В debug panel видно время каждого компонента.
// Или вручную — оборачиваем подозрительный компонент:

$startComp = microtime(true);
$APPLICATION->IncludeComponent('my:catalog.section', '', [...]);
$compTime = microtime(true) - $startComp;
echo "Component time: {$compTime}s\n";

// ============================================================
// ШАГ 8: Проверяем внешние запросы
// ============================================================

// curl в коде — частая причина медленных страниц
// Если есть REST-запросы — логируем их время:

/*
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$startCurl = microtime(true);
$response = curl_exec($ch);
$curlTime = microtime(true) - $startCurl;
$logger->info('External API call', [
    'url' => $url,
    'time' => $curlTime,
    'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
]);
*/

// ============================================================
// Типичные находки и решения
// ============================================================

echo "\n=== DIAGNOSIS CHECKLIST ===\n";
echo "□ PHP time >> SQL time → проблема в PHP (циклы, файлы, регулярки)\n";
echo "□ SQL time >> PHP time → проблема в БД (запросы, индексы)\n";
echo "□ [Nx] запросы в отчёте → N+1, нужен ->with() или JOIN\n";
echo "□ Один запрос > 1000ms → EXPLAIN, индексы\n";
echo "□ Warm == Cold → кеш не работает (проверь CACHE_TYPE, composite)\n";
echo "□ Много 'SELECT *' → выбирай только нужные поля\n";
echo "□ Curl в логе > 500ms → внешнее API, кешируй или делай асинхронно\n";
