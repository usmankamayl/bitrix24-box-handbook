<?php
/**
 * Code Review #2 — «Плохой» REST-эндпоинт для повторения заказа
 *
 * POST /api/order/repeat.php
 * { "order_id": 123, "sessid": "..." }
 *
 * ЭТОТ КОД — ДЛЯ РАЗБОРА. Содержит 7+ ошибок.
 * Ответы — в review_analysis.md.
 */

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;

Loader::includeModule('sale');

header('Content-Type: application/json');

// "Валидация" входных данных
$orderId = $_REQUEST['order_id'];
$sessid = $_REQUEST['sessid'];

// Проверка sessid — вроде есть
if (!check_bitrix_sessid()) {
    http_response_code(403);
    die('{"error":"sessid"}');
}

// Проверка авторизации
global $USER;
if (!$USER->IsAuthorized()) {
    http_response_code(401);
    die('{"error":"unauthorized"}');
}

// Загрузка заказа
$order = Order::load($orderId);
if (!$order) {
    http_response_code(404);
    die('{"error":"not found"}');
}

// Повтор заказа — копируем корзину
$basket = \Bitrix\Sale\Basket::create(SITE_ID);
$oldBasket = $order->getBasket();

foreach ($oldBasket as $item) {
    // Пропускаем системные позиции
    if ($item->getField('IS_DELAY') === 'Y') {
        continue;
    }

    $newItem = $basket->createItem('catalog', $item->getProductId());
    $newItem->setFields([
        'QUANTITY' => $item->getQuantity(),
        'PRICE' => $item->getPrice(),           // копируем старую цену!
        'CUSTOM_PRICE' => 'Y',                   // говорим "не пересчитывай"
        'CURRENCY' => $item->getCurrency(),
        'NAME' => $item->getField('NAME'),
        'PRODUCT_PROVIDER_CLASS' => $item->getProviderName(),
    ]);
}

// Создаём новый заказ — "клон" старого
$newOrder = Order::create(
    SITE_ID,
    $order->getUserId(),
    $order->getField('PERSON_TYPE_ID')
);

$newOrder->setBasket($basket);

// Копируем свойства
$oldProps = $order->getPropertyCollection();
$newProps = $newOrder->getPropertyCollection();
foreach ($oldProps as $prop) {
    $code = $prop->getField('CODE');
    if ($newProp = $newProps->getItemByOrderPropertyCode($code)) {
        $newProp->setValue($prop->getValue());
    }
}

// Копируем отгрузку и оплату
$oldShipment = $order->getShipmentCollection()->getItemById(0);
$newShipment = $newOrder->getShipmentCollection()->createItem(
    $oldShipment->getDelivery()
);

$oldPayment = $order->getPaymentCollection()->getItemById(0);
$newPayment = $newOrder->getPaymentCollection()->createItem(
    $oldPayment->getPaySystem()
);
$newPayment->setField('SUM', $newOrder->getPrice());

// Сохраняем
$result = $newOrder->save();

if ($result->isSuccess()) {
    // Отправляем письмо — прямо тут, синхронно
    $mailFields = [
        'ORDER_ID' => $newOrder->getId(),
        'EMAIL' => $order->getPropertyCollection()->getUserEmail()->getValue(),
        'PRICE' => $newOrder->getPrice(),
    ];
    \CEvent::Send('SALE_NEW_ORDER', SITE_ID, $mailFields);

    echo json_encode([
        'success' => true,
        'new_order_id' => $newOrder->getId(),
    ]);
} else {
    // Ошибка — но никаких деталей для отладки
    http_response_code(500);
    echo json_encode(['error' => 'save failed']);
}
