<?php

/**
 * Прогон агента синхронизации на демонстрационном провайдере.
 * /local/tools/practice-3-api-sync/check.php — открывать в браузере под
 * администратором: сценариям нужен HTTP-доступ к demo-api.php на этом же сайте.
 *
 * Сценарии:
 *   1. полная синхронизация двух страниц;
 *   2. повторный прогон — ни одного дубля;
 *   3. ошибка провайдера (HTTP 503) — отчёт с ошибкой, исключение не уходит;
 *   4. ошибка на второй странице — контрольная точка остаётся на первой.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Нужен HTTP: запускайте из браузера под администратором.\n");
    exit(1);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$classDir = is_file(__DIR__ . '/ApiSyncAgent.php')
    ? __DIR__
    : $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/Agents';

require_once $classDir . '/SyncStateTable.php';
require_once $classDir . '/SyncItemTable.php';
require_once $classDir . '/ApiSyncAgent.php';

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Type\DateTime;
use MyCompany\Agents\ApiSyncAgent;
use MyCompany\Agents\SyncItemTable;
use MyCompany\Agents\SyncStateTable;

header('Content-Type: text/plain; charset=UTF-8');

if (!is_object($GLOBALS['USER']) || !$GLOBALS['USER']->IsAdmin()) {
    http_response_code(403);
    echo '403';
    exit;
}

if (!Application::getConnection()->isTableExists(SyncItemTable::getTableName())) {
    echo "Нет таблиц синхронизации. Выполните install.sql.\n";
    exit;
}

$scheme = \CMain::IsHTTPS() ? 'https' : 'http';
$selfDir = rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/');
$baseUrl = $scheme . '://' . (string)$_SERVER['HTTP_HOST'] . $selfDir . '/demo-api.php';

$setUrl = static function (string $url): void {
    Option::set(ApiSyncAgent::OPTION_MODULE, ApiSyncAgent::OPTION_URL, $url);
};

$resetAll = static function (): void {
    foreach (SyncItemTable::getList(['select' => ['ID']])->fetchAll() as $row) {
        SyncItemTable::delete($row['ID']);
    }

    $state = SyncStateTable::getById(ApiSyncAgent::PROVIDER)->fetch();

    if ($state === false) {
        SyncStateTable::add([
            'CODE' => ApiSyncAgent::PROVIDER,
            'CURSOR' => null,
            'PAGES_DONE' => 0,
            'ITEMS_DONE' => 0,
            'UPDATED_AT' => new DateTime(),
        ]);
    } else {
        SyncStateTable::update(ApiSyncAgent::PROVIDER, [
            'CURSOR' => null,
            'PAGES_DONE' => 0,
            'ITEMS_DONE' => 0,
            'UPDATED_AT' => new DateTime(),
        ]);
    }
};

$itemCount = static fn(): int => SyncItemTable::getCount();
$state = static fn(): array => (array)SyncStateTable::getById(ApiSyncAgent::PROVIDER)->fetch();

$printReport = static function (array $report): void {
    printf(
        "  pages=%d fetched=%d created=%d updated=%d skipped=%d completed=%s stop=%s\n",
        $report['pages'],
        $report['fetched'],
        $report['created'],
        $report['updated'],
        $report['skipped'],
        $report['completed'] ? 'да' : 'нет',
        $report['stop_reason']
    );

    printf("  курсор: было %s -> стало %s\n",
        $report['cursor_before'] === '' ? '(пусто)' : $report['cursor_before'],
        $report['cursor_after'] === '' ? '(пусто)' : $report['cursor_after']);

    if ($report['errors'] !== []) {
        printf("  ошибки: %s\n", $report['errors'][0]);
    }
};

$problems = [];

// ------------------------------------------------------------ 1. полный проход

$setUrl($baseUrl);
$resetAll();

echo "1. Полная синхронизация (две страницы)\n";

$run1 = ApiSyncAgent::run();
$printReport($run1);
printf("  строк в локальной таблице: %d\n", $itemCount());

if ($run1['created'] !== 3 || $itemCount() !== 3) {
    $problems[] = 'первый прогон создал не 3 строки';
}

if ($run1['skipped'] !== 1) {
    $problems[] = 'запись без id не попала в skipped';
}

if ($run1['completed'] !== true) {
    $problems[] = 'первый прогон не дошёл до конца пагинации';
}

// ---------------------------------------------------------------- 2. повтор

echo "\n2. Повторный прогон (идемпотентность)\n";

$run2 = ApiSyncAgent::run();
$printReport($run2);
printf("  строк в локальной таблице: %d\n", $itemCount());

if ($itemCount() !== 3) {
    $problems[] = 'повторный прогон создал дубли';
}

if ($run2['created'] !== 0) {
    $problems[] = 'повторный прогон что-то создал вместо обновления';
}

// ------------------------------------------------------ 3. провайдер недоступен

echo "\n3. Ошибка провайдера (HTTP 503 на первой странице)\n";

$setUrl($baseUrl . '?fail=1');
$before = $state();

$run3 = ApiSyncAgent::run();
$printReport($run3);

$after = $state();

printf("  контрольная точка: pages_done %s -> %s\n",
    (string)$before['PAGES_DONE'], (string)$after['PAGES_DONE']);

if ($run3['stop_reason'] !== 'error') {
    $problems[] = 'ошибка провайдера не попала в отчёт как error';
}

if ((string)$before['PAGES_DONE'] !== (string)$after['PAGES_DONE']) {
    $problems[] = 'контрольная точка сдвинулась на неуспешном прогоне';
}

// --------------------------------------- 4. ошибка на второй странице

echo "\n4. Прогресс сохраняется: точка после первой страницы, вторая падает\n";

$resetAll();
Option::set(ApiSyncAgent::OPTION_MODULE, ApiSyncAgent::OPTION_URL, $baseUrl);

/*
 * Ставим курсор на вторую страницу и ломаем провайдера: первая страница
 * якобы уже обработана, вторая недоступна.
 */
SyncStateTable::update(ApiSyncAgent::PROVIDER, [
    'CURSOR' => 'p2',
    'PAGES_DONE' => 1,
    'ITEMS_DONE' => 2,
    'UPDATED_AT' => new DateTime(),
]);

$setUrl($baseUrl . '?fail=1');
$run4 = ApiSyncAgent::run();
$printReport($run4);

$stateAfterFail = $state();

printf("  курсор после сбоя: %s (ожидается p2)\n", (string)$stateAfterFail['CURSOR']);

if ((string)$stateAfterFail['CURSOR'] !== 'p2') {
    $problems[] = 'при сбое курсор потерян';
}

echo "\n5. Продолжение с сохранённой точки\n";

$setUrl($baseUrl);
$run5 = ApiSyncAgent::run();
$printReport($run5);
printf("  строк в локальной таблице: %d (ожидается 1: вторая страница)\n", $itemCount());

if ($run5['created'] !== 1 || $run5['skipped'] !== 1) {
    $problems[] = 'продолжение с точки p2 обработало не ту страницу';
}

// ---------------------------------------------------------------- 6. уборка

$setUrl($baseUrl);
$resetAll();

echo "\nПроверки\n";

if ($problems === []) {
    echo "  пагинация, идемпотентность и контрольная точка работают — ок\n";
} else {
    foreach ($problems as $problem) {
        echo "  ОШИБКА: {$problem}\n";
    }
}

echo "\nОтчёт: " . Application::getDocumentRoot() . ApiSyncAgent::REPORT_FILE . "\n";
