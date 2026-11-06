<?php

/**
 * Проба: что реально умеют Result, Error, ErrorCollection и исключения D7
 * в твоей версии ядра.
 *
 * Путь в проекте: /local/tools/result-probe.php
 * Запуск: открыть в браузере или curl-ом. Вывод — text/plain.
 *
 * Зачем: в теории 1.8 часть утверждений помечена [проверить] (наличие методов,
 * поведение getError() при нескольких ошибках, что делает Loader::includeModule
 * с опечаткой, что возвращает update() по несуществующему ID). Проба печатает
 * фактическое состояние, а не пересказ документации. Вызовы обёрнуты в try/catch:
 * на другой версии ядра скрипт не должен падать фаталом — он должен показать,
 * чего нет.
 *
 * Ничего не пишет в БД: единственная запись — update() по заведомо
 * несуществующему ID группы (0 строк).
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;

header('Content-Type: text/plain; charset=UTF-8');

function section(string $title): void
{
    echo "\n=== " . $title . " ===\n";
}

function line(string $key, $value): void
{
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $value = 'null';
    } elseif (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    } elseif (is_object($value)) {
        $value = get_class($value);
    }

    echo str_pad($key, 46, '.') . ' ' . $value . "\n";
}

// ---------------------------------------------------------------- 1. версия

section('1. Окружение');
line('SM_VERSION', defined('SM_VERSION') ? SM_VERSION : 'не определена');
line('PHP_VERSION', PHP_VERSION);
line('class Result', class_exists(Result::class) ? 'есть' : 'НЕТ');
line('class Error', class_exists(Error::class) ? 'есть' : 'НЕТ');
line('class ErrorCollection', class_exists(ErrorCollection::class) ? 'есть' : 'НЕТ');

// ------------------------------------------------------- 2. матрица методов

section('2. Матрица методов (method_exists)');

$matrix = [
    Result::class => [
        'isSuccess', 'addError', 'addErrors', 'getErrors', 'getError',
        'getErrorMessages', 'getErrorCollection', 'getData', 'setData', 'addData',
    ],
    Error::class => [
        'getMessage', 'getCode', 'getCustomData', 'createFromThrowable',
        'jsonSerialize', '__toString', 'getLocalizableMessage',
    ],
    ErrorCollection::class => [
        'add', 'setError', 'getErrorByCode', 'isEmpty', 'toArray', 'getValues', 'clear',
    ],
];

foreach ($matrix as $class => $methods) {
    echo "\n" . $class . "\n";
    foreach ($methods as $method) {
        printf("  %-22s %s\n", $method . '()', method_exists($class, $method) ? 'да' : 'нет');
    }
}

// ------------------------------------------------- 3. addErrors и типы

section('3. addErrors: только объекты Error');

try {
    $r = (new Result())->addErrors(['просто строка']);
    line('addErrors([строка])', 'не бросил, isSuccess=' . var_export($r->isSuccess(), true));
} catch (\Throwable $e) {
    line('addErrors([строка])', get_class($e) . ': ' . $e->getMessage());
}

$r = (new Result())->addErrors([new Error('ошибка 1', 'E_ONE'), new Error('ошибка 2', 'E_TWO')]);
line('addErrors([Error, Error]) -> isSuccess', $r->isSuccess());
line('  getErrors(): count', count($r->getErrors()));
line('  getErrorMessages()', $r->getErrorMessages());
line('  getError() -> message', $r->getError() ? $r->getError()->getMessage() : 'null');
line('  getErrorCollection() -> count', method_exists($r, 'getErrorCollection')
    ? count($r->getErrorCollection())
    : 'метода нет');

// ------------------------------------------------- 4. truthiness результата

section('4. Truthiness: объект всегда true');

$failed = (new Result())->addError(new Error('не удалось', 'FAIL'));
line('(bool)$failed', (bool)$failed);
line('$failed->isSuccess()', $failed->isSuccess());
line('if ($failed) ветка', $failed ? 'УСПЕХ (неверно)' : 'ошибка');

// ------------------------------------------------------ 5. getErrors()[0]

section('5. getErrors()[0] без проверки успеха');

$ok = new Result();
try {
    $message = $ok->getErrors()[0]->getMessage();
    line('getErrors()[0]->getMessage()', $message);
} catch (\Throwable $e) {
    line('getErrors()[0] на успешном', get_class($e) . ': ' . $e->getMessage());
}

// --------------------------------------------------------- 6. setData

section('6. setData заменяет, а не дополняет');

$data = new Result();
$data->setData(['a' => 1]);
$data->setData(['b' => 2]);
line('после двух setData', $data->getData());

$copy = $data->getData();
$copy['c'] = 3;
line('правка копии getData()', $data->getData());

$withError = (new Result())->addError(new Error('плохо', 'BAD'));
$withError->setData(['stale' => true]);
line('ошибка + setData: isSuccess', $withError->isSuccess());
line('  getData()', $withError->getData());

// ------------------------------------------------------------- 7. Error

section('7. Error: код, customData, JSON');

$error = new Error('Товара не хватает', 'INSUFFICIENT_STOCK', ['available' => 5, 'requested' => 10]);
line('getCode()', var_export($error->getCode(), true));
line('getCustomData()', $error->getCustomData());
line('(string)$error', (string)$error);
line('json_encode($error)', json_encode($error, JSON_UNESCAPED_UNICODE));

$stringCode = new Error('текст', 'CODE_AS_STRING');
line('код-строка: getCode() === 0', $stringCode->getCode() === 0);
line('код-строка: getCode() === "CODE_AS_STRING"', $stringCode->getCode() === 'CODE_AS_STRING');

if (method_exists(Error::class, 'createFromThrowable')) {
    $fromException = Error::createFromThrowable(new \RuntimeException('бум', 17));
    echo "\ncreateFromThrowable(RuntimeException):\n";
    line('  class', get_class($fromException));
    line('  getMessage()', $fromException->getMessage());
    line('  getCode()', var_export($fromException->getCode(), true));
}

// ------------------------------------------------------- 8. ErrorCollection

section('8. ErrorCollection: getErrorByCode, isEmpty, счёт');

$collection = new ErrorCollection();
line('пустая isEmpty()', $collection->isEmpty());

$collection->add([new Error('плохой email', 'EMAIL_INVALID'), new Error('плохой телефон', 'PHONE_INVALID')]);
line('после add([2 ошибки]) isEmpty()', $collection->isEmpty());
line('count($collection)', count($collection));
line('getErrorByCode("PHONE_INVALID")', $collection->getErrorByCode('PHONE_INVALID')
    ? $collection->getErrorByCode('PHONE_INVALID')->getMessage()
    : 'null');
line('getErrorByCode("NOPE")', var_export($collection->getErrorByCode('NOPE'), true));

// ------------------------------------------------- 9. иерархия исключений

section('9. Иерархия исключений D7 (is_a)');

$hierarchy = [
    'Bitrix\\Main\\SystemException' => 'Bitrix\\Main\\SystemException',
    'Bitrix\\Main\\ArgumentException' => 'Bitrix\\Main\\SystemException',
    'Bitrix\\Main\\ArgumentTypeException' => 'Bitrix\\Main\\ArgumentException',
    'Bitrix\\Main\\DB\\SqlException' => 'Bitrix\\Main\\DB\\Exception',
    'Bitrix\\Main\\DB\\Exception' => 'Bitrix\\Main\\SystemException',
    'Bitrix\\Main\\LoaderException' => 'Bitrix\\Main\\SystemException',
    'Bitrix\\Main\\ObjectNotFoundException' => 'Bitrix\\Main\\ObjectException',
    'Bitrix\\Main\\AccessDeniedException' => 'Bitrix\\Main\\SystemException',
    'Bitrix\\Main\\IO\\FileNotFoundException' => 'Bitrix\\Main\\IO\\IoException',
];

foreach ($hierarchy as $child => $parent) {
    $exists = class_exists($child);
    printf(
        "  %-40s %s\n",
        $child,
        !$exists ? 'класса нет' : (is_a($child, $parent, true) ? 'наследует ' . $parent : 'НЕ наследует ' . $parent)
    );
}

// ------------------------------------------------- 10. Loader::includeModule

section('10. Loader::includeModule: false или исключение');

line("includeModule('iblock')", var_export(Loader::includeModule('iblock'), true));

try {
    $unknown = Loader::includeModule('iblok');
    line("includeModule('iblok')", var_export($unknown, true));
} catch (\Throwable $e) {
    line("includeModule('iblok')", get_class($e) . ': ' . $e->getMessage());
}

try {
    $empty = Loader::includeModule('');
    line("includeModule('')", var_export($empty, true));
} catch (\Throwable $e) {
    line("includeModule('')", get_class($e) . ': ' . $e->getMessage());
}

// ------------------------------------------------- 11. update() без строки

section('11. ORM: update() по несуществующему ID');

try {
    Loader::includeModule('main');
    $updateResult = \Bitrix\Main\GroupTable::update(2147483647, ['NAME' => 'probe_' . time()]);
    line('isSuccess()', $updateResult->isSuccess());
    line('getErrorMessages()', $updateResult->getErrorMessages());
    line('вывод', 'ORM не считает 0 затронутых строк ошибкой — нужен read-back');
} catch (\Throwable $e) {
    line('исключение', get_class($e) . ': ' . $e->getMessage());
}

// --------------------------------------------------------- 12. логирование

section('12. Логирование: что доступно');

line('function AddMessage2Log', function_exists('AddMessage2Log') ? 'есть' : 'НЕТ');
line('константа LOG_FILENAME', defined('LOG_FILENAME') ? LOG_FILENAME : 'не определена');
line('class Bitrix\\Main\\Diag\\Logger', class_exists('Bitrix\\Main\\Diag\\Logger') ? 'есть' : 'нет');
line('class Bitrix\\Main\\Diag\\ExceptionHandler', class_exists('Bitrix\\Main\\Diag\\ExceptionHandler') ? 'есть' : 'нет');

echo "\nготово\n";
