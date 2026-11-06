<?php
/**
 * Обработчик привилегированного действия «Экспортировать полную карточку
 * сделки» (вызывается кнопкой из размещения в интерфейсе CRM, см. модуль 9.2).
 * Экспорт включает контактные данные и сумму сделки, поэтому действие
 * доступно не всем, а только группам доступа "Руководители" (G5) и
 * "Администраторы" (G2).
 *
 * Запрос проходит ДВЕ независимых проверки:
 *   1) application_token — запрос действительно пришёл от Bitrix24;
 *   2) роль пользователя — у ЭТОГО пользователя есть право на экспорт.
 */

require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/access_control.php';

const APPLICATION_TOKEN   = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // токен приложения — из настроек вебхука/приложения
const ALLOWED_GROUP_CODES = ['G2', 'G5']; // администраторы и руководители

header('Content-Type: application/json; charset=utf-8');

// --- Уровень 1: подлинность запроса ---
$receivedToken = $_REQUEST['auth']['application_token'] ?? '';
if (!verifyApplicationToken($receivedToken, APPLICATION_TOKEN)) {
    http_response_code(403);
    logSecurityEvent('Отклонён запрос с неверным application_token, IP=' . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['error' => 'invalid_token']);
    exit;
}

$currentUserId = (int)($_REQUEST['auth']['user_id'] ?? 0);
$dealId        = (int)($_REQUEST['PLACEMENT_OPTIONS']['ID'] ?? $_REQUEST['deal_id'] ?? 0);

if ($currentUserId <= 0 || $dealId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request']);
    exit;
}

// --- Уровень 2: права конкретного пользователя на это действие ---
if (!userHasRole($currentUserId, ALLOWED_GROUP_CODES)) {
    http_response_code(403);
    logSecurityEvent("Пользователь {$currentUserId} попытался выполнить экспорт сделки {$dealId} без прав");
    echo json_encode(['error' => 'access_denied', 'message' => 'Недостаточно прав для экспорта']);
    exit;
}

// Запрос подлинный и пользователь уполномочен — выполняем экспорт
$deal = CRest::call('crm.deal.get', ['id' => $dealId]);
if (empty($deal['result'])) {
    http_response_code(404);
    echo json_encode(['error' => 'deal_not_found']);
    exit;
}

$contactId = (int)$deal['result']['CONTACT_ID'];
$contact   = $contactId > 0 ? CRest::call('crm.contact.get', ['id' => $contactId]) : ['result' => null];

$exportData = [
    'deal' => [
        'id'       => $deal['result']['ID'],
        'title'    => $deal['result']['TITLE'],
        'amount'   => $deal['result']['OPPORTUNITY'],
        'currency' => $deal['result']['CURRENCY_ID'],
        'stage'    => $deal['result']['STAGE_ID'],
    ],
    'contact' => $contact['result'] ? [
        'name'  => $contact['result']['NAME'] . ' ' . $contact['result']['LAST_NAME'],
        'email' => $contact['result']['EMAIL'][0]['VALUE'] ?? null,
        'phone' => $contact['result']['PHONE'][0]['VALUE'] ?? null,
    ] : null,
];

logSecurityEvent("Пользователь {$currentUserId} выполнил экспорт сделки {$dealId}");

echo json_encode(['result' => $exportData], JSON_UNESCAPED_UNICODE);
