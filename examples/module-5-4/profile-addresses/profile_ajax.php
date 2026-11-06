<?php
// examples/module-5-4/profile-addresses/profile_ajax.php
// AJAX-эндпоинт управления профилями.
// Используется из ЛК (список профилей) и оформления заказа (выбор "Профиль №2").

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Acme\Sale\Profile\UserProfileService;

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    die();
}

global $USER;
if (!$USER->IsAuthorized()) {
    respond(['error' => 'Unauthorized'], 401);
}
if (!check_bitrix_sessid()) {
    respond(['error' => 'Invalid sessid'], 403);
}

$userId       = (int)$USER->GetID();
$action       = $_REQUEST['action']       ?? '';
$personTypeId = (int)($_REQUEST['person_type_id'] ?? 1); // физлицо по умолчанию
$profileId    = (int)($_REQUEST['profile_id'] ?? 0);

switch ($action) {

    // Список профилей пользователя.
    case 'list':
        $profiles = UserProfileService::getList($userId, $personTypeId);
        respond(['profiles' => $profiles]);

    // Один профиль для формы — автоподстановка.
    case 'get':
        if (!$profileId) respond(['error' => 'profile_id required'], 400);
        $data = UserProfileService::getAsFormData($userId, $profileId);
        if (!$data) respond(['error' => 'Profile not found'], 404);
        respond(['profile' => $data]);

    // Сохранение (create или update).
    case 'save':
        $data = [
            'name'           => trim((string)($_REQUEST['name'] ?? '')),
            'person_type_id' => $personTypeId,
            'props'          => [
                'CITY'    => trim((string)($_REQUEST['city']    ?? '')),
                'ADDRESS' => trim((string)($_REQUEST['address'] ?? '')),
                'ZIP'     => trim((string)($_REQUEST['zip']     ?? '')),
                'PHONE'   => trim((string)($_REQUEST['phone']   ?? '')),
                'EMAIL'   => trim((string)($_REQUEST['email']   ?? '')),
            ],
        ];
        $result = UserProfileService::save($userId, $data, $profileId ?: null);
        if (!$result->isSuccess()) {
            respond(['error' => implode('; ', $result->getErrorMessages())], 400);
        }
        respond($result->getData());

    // Удаление.
    case 'delete':
        if (!$profileId) respond(['error' => 'profile_id required'], 400);
        $result = UserProfileService::delete($userId, $profileId);
        if (!$result->isSuccess()) {
            respond(['error' => implode('; ', $result->getErrorMessages())], 400);
        }
        respond(['ok' => true]);

    // Применение профиля к текущему заказу.
    // Используется на странице оформления: пользователь выбрал "Дом"
    // — свойства заказа автозаполнились из профиля.
    case 'apply':
        if (!$profileId) respond(['error' => 'profile_id required'], 400);

        // Загружаем текущий заказ пользователя — из корзины в оформлении.
        // Здесь упрощённо: предполагаем, что order_id пришёл из сессии/checkout.
        $orderId = (int)($_REQUEST['order_id'] ?? 0);
        if (!$orderId) respond(['error' => 'order_id required'], 400);

        $order = \Bitrix\Sale\Order::load($orderId);
        if (!$order || (int)$order->getUserId() !== $userId) {
            respond(['error' => 'Order not found'], 404);
        }

        $result = UserProfileService::applyToOrder($userId, $profileId, $order);
        if (!$result->isSuccess()) {
            respond(['error' => implode('; ', $result->getErrorMessages())], 400);
        }

        // Сохраняем заказ с новыми свойствами.
        $saveResult = $order->save();
        if (!$saveResult->isSuccess()) {
            respond(['error' => 'Order save failed'], 500);
        }

        respond(['ok' => true, 'applied' => $result->getData()['applied'] ?? []]);

    default:
        respond(['error' => 'Unknown action'], 400);
}
