<?php

/**
 * Прогон транзакции: пять сценариев, состояние остатков и журнала после каждого.
 *
 * Путь в проекте: /local/tools/stock-transfer-check.php
 *
 * Что показывает:
 *   1. успешный перенос — обе записи и журнал применились;
 *   2. повтор с тем же ключом операции — идемпотентность, без новых записей;
 *   3. недостаток остатка — отказ до транзакции, БД не тронута;
 *   4. целевой склад не найден после списания — откат вернул остаток;
 *   5. инфраструктурный сбой после списания — исключение наверх, откат.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$autoload = $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

foreach (['StockTable', 'StockLogTable', 'TransferFailed', 'TransferService'] as $class) {
    $fqcn = 'MyCompany\\Stock\\' . $class;
    if (!class_exists($fqcn)) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/Stock/' . $class . '.php';
    }
}

use MyCompany\Stock\StockLogTable;
use MyCompany\Stock\StockTable;
use MyCompany\Stock\TransferService;

header('Content-Type: text/plain; charset=UTF-8');

const PRODUCT_ID = 7777;
const WH_A = 'WH-A';
const WH_B = 'WH-B';

// ---------------------------------------------------------------- подготовка

foreach (StockLogTable::getList(['select' => ['ID'], 'filter' => ['=PRODUCT_ID' => PRODUCT_ID]])->fetchAll() as $log) {
    StockLogTable::delete($log['ID']);
}

foreach (StockTable::getList(['select' => ['ID'], 'filter' => ['=PRODUCT_ID' => PRODUCT_ID]])->fetchAll() as $stock) {
    StockTable::delete($stock['ID']);
}

$seed = static function (string $warehouse, int $quantity): void {
    StockTable::add([
        'PRODUCT_ID'     => PRODUCT_ID,
        'WAREHOUSE_CODE' => $warehouse,
        'QUANTITY'       => $quantity,
    ]);
};

$seed(WH_A, 10);
$seed(WH_B, 0);

$balance = static function (string $warehouse): string {
    $row = StockTable::getList([
        'select' => ['QUANTITY'],
        'filter' => ['=PRODUCT_ID' => PRODUCT_ID, '=WAREHOUSE_CODE' => $warehouse],
        'limit'  => 1,
    ])->fetch();

    return $row === false ? 'нет склада' : (string)(int)$row['QUANTITY'];
};

$logCount = static fn(): int => StockLogTable::getCount(['=PRODUCT_ID' => PRODUCT_ID]);

$printState = static function (string $label) use ($balance, $logCount): void {
    printf(
        "  %-46s %s=%s  %s=%s  журнал=%d\n",
        $label,
        WH_A,
        $balance(WH_A),
        WH_B,
        $balance(WH_B),
        $logCount()
    );
};

echo "До сценариев:\n";
$printState('исходное состояние');

$service = new TransferService();

// ---------------------------------------------------------------- 1. успех

echo "\n1. Перенос 3 единиц " . WH_A . " -> " . WH_B . "\n";
$result = $service->transfer(PRODUCT_ID, WH_A, WH_B, 3, 'op-ok-1');
printf(
    "  isSuccess=%s data=%s\n",
    $result->isSuccess() ? 'да' : 'нет',
    json_encode($result->getData(), JSON_UNESCAPED_UNICODE)
);
$printState('после успешного переноса');

// ------------------------------------------------------- 2. повтор ключа

echo "\n2. Тот же ключ операции (retry после таймаута клиента)\n";
$result = $service->transfer(PRODUCT_ID, WH_A, WH_B, 3, 'op-ok-1');
printf(
    "  isSuccess=%s data=%s\n",
    $result->isSuccess() ? 'да' : 'нет',
    json_encode($result->getData(), JSON_UNESCAPED_UNICODE)
);
$printState('после повтора (должно не измениться)');

// --------------------------------------------------- 3. недостаток остатка

echo "\n3. Перенос 100 единиц при остатке 7\n";
$result = $service->transfer(PRODUCT_ID, WH_A, WH_B, 100, 'op-big');
printf(
    "  isSuccess=%s codes=%s\n",
    $result->isSuccess() ? 'да' : 'нет',
    implode(', ', array_map(
        static fn($error) => (string)$error->getCode(),
        $result->getErrors()
    ))
);
$firstError = $result->getErrors()[0] ?? null;
if ($firstError !== null) {
    printf("  customData=%s\n", json_encode($firstError->getCustomData(), JSON_UNESCAPED_UNICODE));
}
$printState('после отказа (транзакция не начиналась)');

// ---------------------------------------------- 4. откат после списания

echo "\n4. Целевой склад WH-GHOST не существует\n";
$result = $service->transfer(PRODUCT_ID, WH_A, 'WH-GHOST', 2, 'op-ghost');
printf(
    "  isSuccess=%s codes=%s\n",
    $result->isSuccess() ? 'да' : 'нет',
    implode(', ', array_map(
        static fn($error) => (string)$error->getCode(),
        $result->getErrors()
    ))
);
$printState('после отката (остаток A должен быть 7)');

// ------------------------------------- 5. инфраструктурный сбой

echo "\n5. Сбой после списания (падение внешнего вызова)\n";
$failingService = new TransferService(
    null,
    static function (): void {
        throw new RuntimeException('Внешний сервис недоступен');
    }
);

try {
    $failingService->transfer(PRODUCT_ID, WH_A, WH_B, 1, 'op-boom');
    echo "  исключения не было — это дефект примера\n";
} catch (\Throwable $exception) {
    printf("  исключение наверх: %s: %s\n", get_class($exception), $exception->getMessage());
}

$printState('после отката (остаток A должен быть 7)');

echo "\nготово\n";
