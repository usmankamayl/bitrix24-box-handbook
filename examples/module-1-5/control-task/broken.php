<?php
/**
 * Контрольное задание. Фрагмент с пятью дефектами.
 *
 * Задача: найти все пять, объяснить последствие каждого и условия, при которых
 * дефект проявляется. Эталон — fixed.php, разбор — README.md.
 *
 * Копировать НЕ надо: это учебный дефектный фрагмент.
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);

header('Content-Type: application/json; charset=UTF-8');

global $USER;

$dealId = (int)$_POST['deal_id'];
$stage  = $_POST['stage_id'];

\Bitrix\Main\Loader::includeModule('crm');

$deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();

if (!$deal) {
    echo json_encode(['error' => 'not_found']);
    die();
}

if (!\CCrmDeal::Update($dealId, ['STAGE_ID' => $stage])) {
    echo json_encode(['error' => 'update_failed']);
    die();
}

echo json_encode([
    'ok'    => true,
    'id'    => $dealId,
    'stage' => $stage,
]);

die();
