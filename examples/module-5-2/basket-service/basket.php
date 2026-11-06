<?php

/**
 * AJAX-эндпоинт корзины: /local/ajax/basket.php (или роут компонента).
 *
 * Лёгкий пролог вместо полного header.php — не тянем шаблон сайта и меню,
 * но модули, сессия и авторизация доступны в полном объёме. Это важно
 * для частого экшена "+1 в корзине": незачем рендерить весь сайт ради
 * JSON-ответа на 200 байт.
 */

define('STOP_STATISTICS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once __DIR__ . '/lib/BasketService.php';

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use BitrixCourse\Basket\BasketService;

header('Content-Type: application/json; charset=UTF-8');

if (!Loader::includeModule('sale') || !Loader::includeModule('catalog')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Модуль sale недоступен'], JSON_UNESCAPED_UNICODE);
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
    exit;
}

$request = Context::getCurrent()->getRequest();

// CSRF: любая мутация корзины — только POST с валидным sessid.
// "summary" — чтение, его можно разрешить и на GET, но не усложняем пример.
if (!$request->isPost() || !check_bitrix_sessid()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректный запрос или истекла сессия'], JSON_UNESCAPED_UNICODE);
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
    exit;
}

$action = (string)$request->getPost('action');
$service = new BasketService();

// Цену и количество принимаем из запроса только как "намерение";
// итоговая цена всегда считается внутри BasketService/Sale\Basket,
// клиентским данным не доверяем.
$result = match ($action) {
    'add' => $service->addProduct(
        (int)$request->getPost('productId'),
        (float)($request->getPost('quantity') ?: 1)
    ),
    'update' => $service->updateQuantity(
        (int)$request->getPost('basketItemId'),
        (float)$request->getPost('quantity')
    ),
    'remove' => $service->removeItem((int)$request->getPost('basketItemId')),
    'coupon' => $service->applyCoupon((string)$request->getPost('couponCode')),
    'summary' => ['success' => true, 'basket' => $service->getBasketSummary()],
    default => ['success' => false, 'error' => 'Неизвестное действие: ' . $action],
};

if (($result['success'] ?? false) === false) {
    http_response_code(422);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
