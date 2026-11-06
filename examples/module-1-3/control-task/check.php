<?php

/**
 * Прогон контрольного задания: одна и та же очередь, две реализации.
 *
 * Путь в проекте: /local/tools/practice-1.3-control.php
 * Запуск:
 *   php -f <DOCUMENT_ROOT>/local/tools/practice-1.3-control.php broken
 *   php -f <DOCUMENT_ROOT>/local/tools/practice-1.3-control.php fixed
 *
 * Файлы broken.php / fixed.php подключаются по одному: в них один и тот же
 * класс, чтобы разница была только в дефектах.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$mode = $argv[1] ?? '';

if (!in_array($mode, ['broken', 'fixed'], true)) {
    fwrite(STDERR, "Укажите режим: broken или fixed\n");
    exit(1);
}

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

$root = dirname((string)realpath(__DIR__), 2);

if (!is_file($root . '/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Не найден пролог ядра. Положите файлы в <DOCUMENT_ROOT>/local/tools/.\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $root;
$DOCUMENT_ROOT = $root;

require $root . '/bitrix/modules/main/include/prolog_before.php';

require_once __DIR__ . '/ControlQueueTable.php';
require_once __DIR__ . '/' . $mode . '.php';

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use MyCompany\Agents\ControlQueueTable;
use MyCompany\Agents\QueueControlAgent;

header('Content-Type: text/plain; charset=UTF-8');

$connection = Application::getConnection();

if (!$connection->isTableExists(ControlQueueTable::getTableName())) {
    fwrite(STDERR, "Нет таблицы " . ControlQueueTable::getTableName() . ". Выполните install.sql.\n");
    exit(1);
}

// ---------------------------------------------------------------- подготовка

foreach (ControlQueueTable::getList(['select' => ['ID']])->fetchAll() as $row) {
    ControlQueueTable::delete($row['ID']);
}

for ($i = 1; $i <= 3; $i++) {
    ControlQueueTable::add([
        'EMAIL' => 'user' . $i . '@example.com',
        'STATUS' => ControlQueueTable::STATUS_NEW,
        'ATTEMPTS' => 0,
        'UPDATED_AT' => new DateTime(),
    ]);
}

echo "Режим: {$mode}\n";
echo "До прогона: " . describe() . "\n\n";

// ---------------------------------------------------------------- прогон

$startedAt = microtime(true);
$returned = QueueControlAgent::execute();
$elapsed = round(microtime(true) - $startedAt, 2);

echo "execute() вернул: " . var_export($returned, true) . "\n";
echo "Время прогона: {$elapsed} с\n\n";
echo "После прогона: " . describe() . "\n\n";

// ---------------------------------------------------------------- разбор

$rows = ControlQueueTable::getList([
    'select' => ['ID', 'EMAIL', 'STATUS', 'ATTEMPTS', 'LAST_ERROR'],
    'order' => ['ID' => 'ASC'],
])->fetchAll();

echo "Строки очереди:\n";
printf("  %-4s %-22s %-10s %-9s %s\n", 'ID', 'EMAIL', 'STATUS', 'ATTEMPTS', 'LAST_ERROR');

foreach ($rows as $row) {
    printf(
        "  %-4d %-22s %-10s %-9d %s\n",
        (int)$row['ID'],
        (string)$row['EMAIL'],
        (string)$row['STATUS'],
        (int)$row['ATTEMPTS'],
        (string)($row['LAST_ERROR'] ?? '')
    );
}

echo "\nНа что смотреть:\n";

$expectedName = '\\MyCompany\\Agents\\QueueControlAgent::execute();';

if ($returned !== $expectedName) {
    echo "  - execute() вернул не имя агента: следующий запуск вызовет не ту точку входа\n";
}

$allSent = true;
$attemptsZero = true;

foreach ($rows as $row) {
    $allSent = $allSent && $row['STATUS'] === 'sent';
    $attemptsZero = $attemptsZero && (int)$row['ATTEMPTS'] === 0;
}

if ($allSent) {
    echo "  - все строки стали sent, хотя успешность отправки не проверялась\n";
}

if ($attemptsZero) {
    echo "  - ATTEMPTS остался 0: число попыток не считается\n";
}

echo "\n";

/**
 * Сводка по статусам.
 */
function describe(): string
{
    $counts = [];

    foreach (ControlQueueTable::getList([
        'select' => ['STATUS', 'CNT'],
        'group' => ['STATUS'],
        'runtime' => [new \Bitrix\Main\ORM\Fields\ExpressionField('CNT', 'COUNT(*)')],
    ])->fetchAll() as $row) {
        $counts[] = $row['STATUS'] . '=' . $row['CNT'];
    }

    return $counts === [] ? '(пусто)' : implode(' ', $counts);
}
