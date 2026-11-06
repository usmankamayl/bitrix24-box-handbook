<?php

/**
 * Эталон контрольного задания: тот же эндпоинт, обработка ошибок и транзакция
 * сделаны правильно.
 *
 * Путь в проекте: /local/ajax/request-close.php
 *
 * Что изменено относительно broken.php — в README.md, дефект за дефектом.
 * Кратко: проверка метода и CSRF, типизация входа, проверка каждого Result,
 * read-back, транзакция с rollback, исключение наружу не выходит, ответ без
 * внутренностей.
 */

define('NO_KEEP_STATISTIC', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require_once __DIR__ . '/OrderTable.php';
require_once __DIR__ . '/CloseLogTable.php';

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use MyCompany\Requests\CloseLogTable;
use MyCompany\Requests\OrderTable;

/** @var CUser $USER */
global $USER;

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

// Вне темы задания, но без этого точка входа неверна: метод и CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(405, ['success' => false, 'code' => 'METHOD_NOT_ALLOWED']);
}

if (!check_bitrix_sessid()) {
    $respond(403, ['success' => false, 'code' => 'CSRF_FAILED']);
}

// Тип проверяется, а не приводится: (int)'12abc' === 12, (int)[] === 1.
$rawId = $_POST['id'] ?? null;
$id = is_string($rawId) || is_int($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($id === false) {
    $respond(400, ['success' => false, 'code' => 'REQUEST_ID_INVALID']);
}

$comment = $_POST['comment'] ?? '';
if (!is_string($comment)) {
    $respond(400, ['success' => false, 'code' => 'COMMENT_INVALID']);
}
$comment = trim($comment);

$connection = Application::getConnection();
$connection->startTransaction();

try {
    $logResult = CloseLogTable::add([
        'REQUEST_ID' => $id,
        'ACTION'     => 'close',
        'COMMENT'    => $comment,
        'CREATED_AT' => new DateTime(),
    ]);

    // Result внутри транзакции не возвращают — прерывают выполнение.
    if (!$logResult->isSuccess()) {
        throw new RuntimeException('Журнал: ' . implode('; ', $logResult->getErrorMessages()));
    }

    $updateResult = OrderTable::update($id, [
        'STATUS'        => 'closed',
        'CLOSED_AT'     => new DateTime(),
        'CLOSE_COMMENT' => $comment,
        'UPDATED_AT'    => new DateTime(),
    ]);

    if (!$updateResult->isSuccess()) {
        throw new RuntimeException('Заявка: ' . implode('; ', $updateResult->getErrorMessages()));
    }

    // update() мог не затронуть ни одной строки и всё равно быть успешным.
    $row = OrderTable::getById($id)->fetch();
    if ($row === false || (string)$row['STATUS'] !== 'closed') {
        throw new RuntimeException('Read-back не подтвердил закрытие заявки ' . $id);
    }

    $connection->commitTransaction();
} catch (\Throwable $exception) {
    // Throwable, а не Exception: TypeError в PHP 8 тоже обязан откатывать.
    $connection->rollbackTransaction();

    $errorId = bin2hex(random_bytes(8));
    AddMessage2Log(
        '[' . $errorId . '] ' . get_class($exception) . ': ' . $exception->getMessage()
        . "\n" . $exception->getTraceAsString(),
        'mycompany.requests'
    );

    $respond(500, [
        'success' => false,
        'code'    => 'INTERNAL',
        'message' => 'Внутренняя ошибка. Код для поддержки: ' . $errorId,
    ]);
}

$respond(200, [
    'success' => true,
    'id'      => $id,
    'status'  => 'closed',
]);
