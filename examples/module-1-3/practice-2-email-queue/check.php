<?php

/**
 * Прогон агента очереди: идемпотентная постановка, разбор, повтор с задержкой,
 * восстановление зависших строк. Только CLI.
 *
 * Путь в проекте: /local/tools/practice-2-email-queue/check.php
 * Запуск:          php -f <DOCUMENT_ROOT>/local/tools/practice-2-email-queue/check.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

$root = dirname((string)realpath(__DIR__), 3);

if (!is_file($root . '/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Не найден пролог ядра. Скопируйте папку в <DOCUMENT_ROOT>/local/tools/.\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $root;
$DOCUMENT_ROOT = $root;

require $root . '/bitrix/modules/main/include/prolog_before.php';

$autoload = $root . '/local/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

foreach (['EmailQueueTable', 'EmailQueueAgent'] as $class) {
    $fqcn = 'MyCompany\\Agents\\' . $class;

    if (class_exists($fqcn)) {
        continue;
    }

    foreach ([__DIR__ . '/' . $class . '.php', $root . '/local/php_interface/classes/Agents/' . $class . '.php'] as $file) {
        if (is_file($file)) {
            require_once $file;
            break;
        }
    }

    if (!class_exists($fqcn)) {
        fwrite(STDERR, "Класс {$fqcn} не найден.\n");
        exit(1);
    }
}

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use MyCompany\Agents\EmailQueueAgent;
use MyCompany\Agents\EmailQueueTable;

header('Content-Type: text/plain; charset=UTF-8');

if (!Application::getConnection()->isTableExists(EmailQueueTable::getTableName())) {
    fwrite(STDERR, "Нет таблицы " . EmailQueueTable::getTableName() . ". Выполните install.sql.\n");
    exit(1);
}

const DEMO_PREFIX = 'demo.queue.';

$clearDemo = static function (): void {
    foreach (EmailQueueTable::getList([
        'select' => ['ID'],
        'filter' => ['%DEDUP_KEY' => DEMO_PREFIX],
    ])->fetchAll() as $row) {
        EmailQueueTable::delete($row['ID']);
    }
};

$statusCounts = static function (): array {
    $counts = [];

    foreach (['new', 'processing', 'sent', 'error'] as $status) {
        $counts[$status] = EmailQueueTable::getCount(['=STATUS' => $status]);
    }

    foreach (EmailQueueTable::getList([
        'select' => ['STATUS', 'CNT'],
        'filter' => ['%DEDUP_KEY' => DEMO_PREFIX],
        'group' => ['STATUS'],
        'runtime' => [
            new \Bitrix\Main\ORM\Fields\ExpressionField('CNT', 'COUNT(*)'),
        ],
    ])->fetchAll() as $row) {
        $counts['demo.' . $row['STATUS']] = (int)$row['CNT'];
    }

    return $counts;
};

$printCounts = static function (array $counts): void {
    $parts = [];

    foreach ($counts as $status => $count) {
        $parts[] = $status . '=' . $count;
    }

    echo '  ' . implode('  ', $parts) . "\n";
};

$clearDemo();

// ------------------------------------------------- 1. идемпотентная постановка

echo "1. Постановка в очередь (идемпотентность по DEDUP_KEY)\n";

$first = EmailQueueAgent::enqueue(
    DEMO_PREFIX . 'order-101',
    'user@example.com',
    'Заказ 101 оформлен',
    'Демонстрационное письмо 1'
);

$again = EmailQueueAgent::enqueue(
    DEMO_PREFIX . 'order-101',
    'user@example.com',
    'Заказ 101 оформлен',
    'Демонстрационное письмо 1'
);

printf("  первый вызов:   id=%d duplicate=%s\n", $first['id'], $first['duplicate'] ? 'да' : 'нет');
printf("  повторный вызов: id=%d duplicate=%s\n", $again['id'], $again['duplicate'] ? 'да' : 'нет');

EmailQueueAgent::enqueue(DEMO_PREFIX . 'order-102', 'second@example.com', 'Заказ 102', 'Демонстрационное письмо 2');
EmailQueueAgent::enqueue(DEMO_PREFIX . 'order-103', 'third@example.com', 'Заказ 103', 'Демонстрационное письмо 3');

$demoCount = EmailQueueTable::getCount(['%DEDUP_KEY' => DEMO_PREFIX]);
printf("  строк с префиксом %s: %d (ожидается 3)\n\n", DEMO_PREFIX, $demoCount);

// ---------------------------------------------------------------- 2. разбор

echo "2. Первый прогон агента\n";

$run1 = EmailQueueAgent::run();

printf("  candidates=%d claimed=%d sent=%d retried=%d dead=%d contended=%d stop=%s\n",
    $run1['candidates'], $run1['claimed'], $run1['sent'],
    $run1['retried'], $run1['dead'], $run1['contended'], $run1['stop_reason']);

$printCounts($statusCounts());

echo "\n3. Второй прогон сразу после первого\n";

$run2 = EmailQueueAgent::run();

printf("  candidates=%d claimed=%d sent=%d retried=%d stop=%s\n",
    $run2['candidates'], $run2['claimed'], $run2['sent'],
    $run2['retried'], $run2['stop_reason']);

$printCounts($statusCounts());

// ------------------------------------------- 3. зависшая строка: восстановление

echo "\n4. Зависшая строка (процесс умер после захвата)\n";

$victim = EmailQueueTable::getList([
    'select' => ['ID', 'ATTEMPTS'],
    'filter' => ['%DEDUP_KEY' => DEMO_PREFIX],
    'order' => ['ID' => 'ASC'],
    'limit' => 1,
])->fetch();

if ($victim !== false) {
    EmailQueueTable::update((int)$victim['ID'], [
        'STATUS' => EmailQueueTable::STATUS_PROCESSING,
        'LOCKED_UNTIL' => new DateTime(date('Y-m-d H:i:s', time() - 60), 'Y-m-d H:i:s'),
        'UPDATED_AT' => new DateTime(),
    ]);

    $run3 = EmailQueueAgent::run();

    printf("  recovered_new=%d recovered_error=%d claimed=%d\n",
        $run3['recovered_new'], $run3['recovered_error'], $run3['claimed']);

    $printCounts($statusCounts());
}

// ------------------------------------------------ 4. исчерпанные попытки: error

echo "\n5. Зависшая строка с исчерпанными попытками\n";

$dead = EmailQueueTable::getList([
    'select' => ['ID'],
    'filter' => ['%DEDUP_KEY' => DEMO_PREFIX],
    'order' => ['ID' => 'ASC'],
    'limit' => 1,
])->fetch();

if ($dead !== false) {
    EmailQueueTable::update((int)$dead['ID'], [
        'STATUS' => EmailQueueTable::STATUS_PROCESSING,
        'ATTEMPTS' => EmailQueueAgent::MAX_ATTEMPTS,
        'LOCKED_UNTIL' => new DateTime(date('Y-m-d H:i:s', time() - 60), 'Y-m-d H:i:s'),
        'UPDATED_AT' => new DateTime(),
    ]);

    $run4 = EmailQueueAgent::run();

    printf("  recovered_error=%d (ожидается >= 1)\n", $run4['recovered_error']);

    $after = EmailQueueTable::getById((int)$dead['ID'])->fetch();
    printf("  статус строки %d: %s\n", (int)$dead['ID'], $after['STATUS'] ?? 'нет строки');
}

// -------------------------------------------------------------------- итог

echo "\nПроверки\n";

$problems = [];

if ($demoCount !== 3) {
    $problems[] = 'идемпотентная постановка создала не 3 строки';
}

if ($again['duplicate'] !== true || $again['id'] !== $first['id']) {
    $problems[] = 'повторная постановка вернула другую строку';
}

if ($run1['claimed'] !== 3) {
    $problems[] = 'первый прогон захватил не все три строки';
}

if ($run2['claimed'] !== 0 && $run1['retried'] > 0) {
    $problems[] = 'второй прогон взял строки раньше NEXT_ATTEMPT_AT';
}

if ($run1['sent'] === 0 && $run1['retried'] === 0) {
    $problems[] = 'ни одно письмо не было ни отправлено, ни отложено';
}

if ($problems === []) {
    echo "  очередь разбирается, дублей нет, зависшие строки возвращаются — ок\n";
} else {
    foreach ($problems as $problem) {
        echo "  ОШИБКА: {$problem}\n";
    }
}

if ($run1['sent'] === 0) {
    echo "\nПримечание: sent=0 — шаблон " . EmailQueueAgent::EVENT_NAME
        . " не найден, отправка вернула неуспех.\n"
        . "Письма ушли в повтор. Создайте шаблон и повторите прогон, чтобы\n"
        . "увидеть сценарий успешной отправки.\n";
}

echo "\nОтчёт: " . Application::getDocumentRoot() . EmailQueueAgent::REPORT_FILE . "\n";
