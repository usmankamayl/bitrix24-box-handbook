<?php

/**
 * Прогон агента очистки журнала: подготовка данных, два запуска, проверка
 * границы срока. Только CLI — скрипт удаляет строки, веб-запуск здесь не нужен
 * и опасен (GET, который меняет данные).
 *
 * Путь в проекте: /local/tools/practice-1-log-cleanup/check.php
 * Запуск:          php -f <DOCUMENT_ROOT>/local/tools/practice-1-log-cleanup/check.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

$root = dirname((string)realpath(__DIR__), 3);

if (!is_file($root . '/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Не найден пролог ядра. Скопируйте папку в <DOCUMENT_ROOT>/local/tools/ и запускайте оттуда.\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $root;
$DOCUMENT_ROOT = $root;

require $root . '/bitrix/modules/main/include/prolog_before.php';

// Автозагрузка проекта (PSR-4 из /local/composer.json) — как в 1.6.
$autoload = $root . '/local/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

// Резерв, чтобы папка запускалась в том виде, в каком лежит.
foreach (['LogTable', 'LogCleanupAgent'] as $class) {
    $fqcn = 'MyCompany\\Agents\\' . $class;

    if (class_exists($fqcn)) {
        continue;
    }

    $candidates = [
        __DIR__ . '/' . $class . '.php',
        $root . '/local/php_interface/classes/Agents/' . $class . '.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            break;
        }
    }

    if (!class_exists($fqcn)) {
        fwrite(STDERR, "Класс {$fqcn} не найден. Положите файлы в /local/php_interface/classes/Agents/.\n");
        exit(1);
    }
}

use Bitrix\Main\Type\DateTime;
use MyCompany\Agents\LogCleanupAgent;
use MyCompany\Agents\LogTable;

header('Content-Type: text/plain; charset=UTF-8');

// ------------------------------------------------------------------ подготовка

$connection = \Bitrix\Main\Application::getConnection();

if (!$connection->isTableExists(LogTable::getTableName())) {
    fwrite(STDERR, "Нет таблицы " . LogTable::getTableName() . ". Выполните install.sql.\n");
    exit(1);
}

$seedCode = 'demo.cleanup';

/*
 * Чистим только свои тестовые строки: журнал живой инсталляции не трогаем.
 */
foreach (LogTable::getList([
    'select' => ['ID'],
    'filter' => ['=CODE' => $seedCode],
])->fetchAll() as $old) {
    LogTable::delete($old['ID']);
}

$now = time();

$makeRow = static function (int $ageSeconds, string $code) use ($now): void {
    $result = LogTable::add([
        'CODE' => $code,
        'LEVEL' => LogTable::LEVEL_INFO,
        'MESSAGE' => 'Строка возрастом ' . $ageSeconds . ' с',
        'CREATED_AT' => new DateTime(date('Y-m-d H:i:s', $now - $ageSeconds), 'Y-m-d H:i:s'),
    ]);

    if (!$result->isSuccess()) {
        throw new RuntimeException('Не удалось подготовить данные: '
            . implode('; ', $result->getErrorMessages()));
    }
};

// 3 строки старше срока и 3 не старше: две свежие и одна за 60 секунд до границы.
$makeRow(45 * 86400, $seedCode);
$makeRow(31 * 86400, $seedCode);
$makeRow(30 * 86400 + 86400, $seedCode);
$makeRow(86400, $seedCode);
$makeRow(60, $seedCode);
$makeRow(30 * 86400 - 60, $seedCode);

$countByAge = static function (): array {
    $cutoff = new DateTime(
        date('Y-m-d H:i:s', time() - LogCleanupAgent::RETENTION_DAYS * 86400),
        'Y-m-d H:i:s'
    );

    $old = LogTable::getCount(['<CREATED_AT' => $cutoff]);
    $fresh = LogTable::getCount(['>=CREATED_AT' => $cutoff]);

    return [$old, $fresh];
};

[$oldBefore, $freshBefore] = $countByAge();

echo "Подготовка\n";
echo "  старше срока: {$oldBefore}   не старше срока: {$freshBefore}\n\n";

// ------------------------------------------------------------------- прогон 1

$first = LogCleanupAgent::run();

echo "1. Первый запуск\n";
printf("  deleted=%d failed=%d batches=%d stop=%s\n",
    $first['deleted'], $first['failed'], $first['batches'], $first['stop_reason']);

[$oldAfterFirst, $freshAfterFirst] = $countByAge();

printf("  после запуска: старше срока: %d   не старше: %d\n", $oldAfterFirst, $freshAfterFirst);

// ------------------------------------------------------------------- прогон 2

$second = LogCleanupAgent::run();

echo "\n2. Повторный запуск (идемпотентность)\n";
printf("  deleted=%d failed=%d stop=%s\n",
    $second['deleted'], $second['failed'], $second['stop_reason']);

[$oldAfterSecond, $freshAfterSecond] = $countByAge();

printf("  после запуска: старше срока: %d   не старше: %d\n", $oldAfterSecond, $freshAfterSecond);

// ------------------------------------------------------------------ что важно

$failed = [];

if ($first['deleted'] !== $oldBefore) {
    $failed[] = 'первый запуск удалил не все строки старше срока';
}

if ($freshAfterFirst !== $freshBefore) {
    $failed[] = 'пострадали строки не старше срока (включая границу)';
}

if ($second['deleted'] !== 0) {
    $failed[] = 'повторный запуск что-то удалил: фильтр или выборка работают неверно';
}

if ($first['failed'] !== 0) {
    $failed[] = 'в отчёте есть ошибки удаления';
}

echo "\nПроверки\n";

if ($failed === []) {
    echo "  старые удалены, свежие и граница сохранены, повтор идемпотентен — ок\n";
} else {
    foreach ($failed as $line) {
        echo "  ОШИБКА: {$line}\n";
    }
}

echo "\nОтчёт по последнему запуску лежит в "
    . \Bitrix\Main\Application::getDocumentRoot() . LogCleanupAgent::REPORT_FILE . "\n";
