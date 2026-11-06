<?php

/**
 * Контрольное задание: /local/ajax/request-close.php из проекта, принятого на
 * поддержку после инцидента «заявки закрываются, но письма не уходят, а иногда
 * закрываются дважды».
 *
 * В файле пять дефектов обработки ошибок и транзакции. Задание: назвать все
 * пять, объяснить последствие каждого и условия, при которых он проявляется.
 *
 * Разбор и эталон — README.md и fixed.php в этой же папке.
 *
 * Зависимости подключены, классы существуют: дефекты поведенческие, а не
 * «класс не найден».
 */

define('NO_KEEP_STATISTIC', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require_once __DIR__ . '/OrderTable.php';
require_once __DIR__ . '/CloseLogTable.php';

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use MyCompany\Requests\CloseLogTable;
use MyCompany\Requests\OrderTable;

$id = (int)($_POST['id'] ?? 0);
$comment = (string)($_POST['comment'] ?? '');

$connection = Application::getConnection();
$updateResult = null;
$errorMessage = '';

$connection->startTransaction();

try {
    $logResult = CloseLogTable::add([
        'REQUEST_ID' => $id,
        'ACTION'     => 'close',
        'COMMENT'    => $comment,
        'CREATED_AT' => new DateTime(),
    ]);

    $updateResult = OrderTable::update($id, [
        'STATUS'        => 'closed',
        'CLOSED_AT'     => new DateTime(),
        'CLOSE_COMMENT' => $comment,
        'UPDATED_AT'    => new DateTime(),
    ]);

    $connection->commitTransaction();
} catch (\Exception $exception) {
    $errorMessage = $exception->getMessage();
}

header('Content-Type: application/json; charset=UTF-8');

if ($updateResult) {
    echo json_encode([
        'success' => true,
        'id'      => $id,
        'notice'  => $updateResult->getErrors()[0]->getMessage(),
        'message' => $errorMessage,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(500);
echo json_encode([
    'success' => false,
    'message' => $errorMessage,
], JSON_UNESCAPED_UNICODE);
