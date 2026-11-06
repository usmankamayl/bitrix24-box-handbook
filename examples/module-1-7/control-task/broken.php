<?php

/**
 * Контрольное задание: /local/ajax/request-status.php из проекта, принятого
 * на поддержку. Эндпоинт меняет статус заявки в инфоблоке.
 *
 * В файле пять дефектов. Задание: назвать все пять, объяснить последствие
 * каждого и условия, при которых дефект проявляется.
 *
 * Разбор и эталон — README.md и fixed.php в этой же папке.
 */

define('NO_KEEP_STATISTIC', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');

$iblockId = 12;
$allowedStatuses = ['new', 'in_work', 'done'];

$id = (int)$_REQUEST['id'];
$status = $_REQUEST['status'];

if (!\Bitrix\Main\Context::getCurrent()->getRequest()->isAjaxRequest()) {
    die('AJAX only');
}

if (!in_array($status, $allowedStatuses, true)) {
    echo 'Неверный статус';
    die();
}

$comment = htmlspecialchars(trim((string)($_POST['comment'] ?? '')));

$element = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $iblockId, 'ID' => $id],
    false,
    false,
    ['ID', 'NAME', 'CREATED_BY']
)->Fetch();

if (!$element) {
    echo 'Заявка не найдена';
    die();
}

$ok = (new CIBlockElement())->Update($id, [
    'PROPERTY_STATUS' => $status,
    'PREVIEW_TEXT' => $comment,
]);

header('Content-Type: application/json');

echo json_encode([
    'success' => (bool)$ok,
    'id' => $id,
    'status' => $status,
]);
