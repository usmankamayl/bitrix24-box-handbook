<?php

/**
 * Прогон доменного сервиса: девять сценариев, вывод — таблицей.
 *
 * Путь в проекте: /local/tools/requests-check.php
 * (сам файл — диагностический, рядом с сервисом ему делать нечего)
 *
 * Что показывает: Result на каждый ожидаемый отказ, коды вместо текстов,
 * «чужая заявка = не найдена», идемпотентность через отдельный код, отсутствие
 * исключений на доменных ошибках.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Автозагрузка проекта (PSR-4 из /local/composer.json), как в 1.6.
$autoload = $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// Резерв на случай, если composer в демо-инсталляции не настроен: пример должен
// запускаться в том виде, в каком лежит. В проекте этот блок не нужен.
foreach (['OrderTable', 'OrderStatusService'] as $class) {
    $fqcn = 'MyCompany\\Requests\\' . $class;
    if (!class_exists($fqcn)) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/Requests/' . $class . '.php';
    }
}

use Bitrix\Main\Type\DateTime;
use MyCompany\Requests\OrderStatusService;
use MyCompany\Requests\OrderTable;

header('Content-Type: text/plain; charset=UTF-8');

const SEED_AUTHOR_OWN = 9001;
const SEED_AUTHOR_FOREIGN = 9002;

// ---------------------------------------------------------------- подготовка

foreach (OrderTable::getList([
    'select' => ['ID'],
    'filter' => ['@AUTHOR_ID' => [SEED_AUTHOR_OWN, SEED_AUTHOR_FOREIGN]],
])->fetchAll() as $old) {
    OrderTable::delete($old['ID']);
}

$makeRequest = static function (string $title, string $status, int $authorId): int {
    $add = OrderTable::add([
        'TITLE'     => $title,
        'STATUS'    => $status,
        'AUTHOR_ID' => $authorId,
        'UPDATED_AT' => new DateTime(),
    ]);

    if (!$add->isSuccess()) {
        throw new RuntimeException('Не удалось подготовить данные: ' . implode(', ', $add->getErrorMessages()));
    }

    return (int)$add->getId();
};

$ownNew = $makeRequest('Не работает принтер', OrderTable::STATUS_NEW, SEED_AUTHOR_OWN);
$foreignNew = $makeRequest('Заявка другого автора', OrderTable::STATUS_NEW, SEED_AUTHOR_FOREIGN);
$ownInWork = $makeRequest('Нужен доступ к 1С', OrderTable::STATUS_IN_WORK, SEED_AUTHOR_OWN);
$legacyStatus = $makeRequest('Заявка со старым статусом', 'archived', SEED_AUTHOR_OWN);

// ---------------------------------------------------------------- сценарии

$service = new OrderStatusService();
$rows = [];

$run = static function (string $label, callable $call) use (&$rows): void {
    $result = $call();

    $codes = [];
    foreach ($result->getErrors() as $error) {
        $codes[] = (string)$error->getCode();
    }

    $rows[] = [
        'scenario' => $label,
        'success'  => $result->isSuccess() ? 'да' : 'нет',
        'codes'    => $codes === [] ? '—' : implode(', ', $codes),
        'data'     => $result->isSuccess()
            ? json_encode($result->getData(), JSON_UNESCAPED_UNICODE)
            : '—',
    ];
};

$run('1. своя заявка, new', static fn() => $service->closeRequest($ownNew, SEED_AUTHOR_OWN, 'Заменили картридж'));
$run('2. повторно та же заявка', static fn() => $service->closeRequest($ownNew, SEED_AUTHOR_OWN, 'Ещё раз'));
$run('3. чужая заявка', static fn() => $service->closeRequest($foreignNew, SEED_AUTHOR_OWN, 'Пытаюсь закрыть'));
$run('4. несуществующий ID', static fn() => $service->closeRequest(999999, SEED_AUTHOR_OWN, 'Текст'));
$run('5. пустой комментарий', static fn() => $service->closeRequest($ownInWork, SEED_AUTHOR_OWN, '   '));
$run('6. комментарий 501 символ', static fn() => $service->closeRequest($ownInWork, SEED_AUTHOR_OWN, str_repeat('x', 501)));
$run('7. своя заявка, in_work', static fn() => $service->closeRequest($ownInWork, SEED_AUTHOR_OWN, 'Вопрос решён'));
$run('8. заявка со статусом archived', static fn() => $service->closeRequest($legacyStatus, SEED_AUTHOR_OWN, 'Текст'));
$run('9. ID = 0', static fn() => $service->closeRequest(0, SEED_AUTHOR_OWN, 'Текст'));

// ------------------------------------------------------------------- вывод

$widths = ['scenario' => 30, 'success' => 7, 'codes' => 24, 'data' => 0];
$header = ['scenario' => 'Сценарий', 'success' => 'Успех', 'codes' => 'Коды ошибок', 'data' => 'data'];

$printRow = static function (array $row) use ($widths): void {
    $line = '';
    foreach ($widths as $key => $width) {
        $value = (string)$row[$key];
        $line .= $width > 0 ? str_pad($value, $width) . '| ' : $value;
    }
    echo rtrim($line) . "\n";
};

$printRow($header);
echo str_repeat('-', 30 + 7 + 24 + 40 + 6) . "\n";
foreach ($rows as $row) {
    $printRow($row);
}

echo "\nСостояние таблицы:\n";
foreach (OrderTable::getList([
    'select' => ['ID', 'TITLE', 'STATUS', 'AUTHOR_ID', 'CLOSED_AT'],
    'filter' => ['@AUTHOR_ID' => [SEED_AUTHOR_OWN, SEED_AUTHOR_FOREIGN]],
    'order'  => ['ID' => 'ASC'],
])->fetchAll() as $request) {
    printf(
        "  #%d %-32s %-8s author=%-5d closed_at=%s\n",
        $request['ID'],
        $request['TITLE'],
        $request['STATUS'],
        $request['AUTHOR_ID'],
        $request['CLOSED_AT'] ? (string)$request['CLOSED_AT'] : '—'
    );
}

echo "\nготово\n";
