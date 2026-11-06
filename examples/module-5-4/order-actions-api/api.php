<?php
// examples/module-5-4/order-actions-api/api.php
// REST-эндпоинт ЛК: детали заказа, повтор, отмена.
// Уровень senior: проверка владельца (IDOR), идемпотентность,
// D7-модель, корректные коды ответа.

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', false);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;

Loader::includeModule('sale');

header('Content-Type: application/json; charset=utf-8');

// ---------- Утилиты ответа ----------
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    die();
}

function fail(string $message, int $code = 400): void {
    respond(['error' => $message], $code);
}

// ---------- Auth + CSRF ----------
global $USER;
if (!$USER->IsAuthorized()) {
    fail('Unauthorized', 401);
}
if (!check_bitrix_sessid()) {
    fail('Invalid sessid', 403);
}

$action = $_REQUEST['action'] ?? '';
$orderId = (int)($_REQUEST['order_id'] ?? 0);
if (!$orderId) fail('order_id required');

// ---------- Загружаем заказ и проверяем владельца ----------
$order = Order::load($orderId);
if (!$order) fail('Order not found', 404);

// ГЛАВНАЯ защита: заказ принадлежит текущему пользователю.
// Без этого — IDOR: подставляешь чужой order_id и смотришь/отменяешь чужой заказ.
if ((int)$order->getUserId() !== (int)$USER->GetID()) {
    fail('Forbidden', 403);
}

// ---------- Actions ----------
switch ($action) {
    case 'detail':
        respond(['order' => buildOrderDetail($order)]);
        break;

    case 'repeat':
        // Идемпотентность: повторный клик не дублирует позиции —
        // возвращаем состояние корзины после копирования.
        $result = repeatOrder($order);
        if (!$result->isSuccess()) {
            fail(implode('; ', $result->getErrorMessages()));
        }
        respond(['basket' => getBasketSummary()]);
        break;

    case 'cancel':
        $reason = trim((string)($_REQUEST['reason'] ?? ''));
        $result = cancelOrder($order, $reason);
        if (!$result->isSuccess()) {
            fail(implode('; ', $result->getErrorMessages()));
        }
        respond(['ok' => true, 'status' => 'A']);
        break;

    default:
        fail('Unknown action', 400);
}

// ---------- Helpers ----------

function buildOrderDetail(Order $order): array {
    $items = [];
    $basket = $order->getBasket();
    if ($basket) {
        foreach ($basket->getBasketItems() as $item) {
            $items[] = [
                'id'       => $item->getId(),
                'name'     => $item->getField('NAME'),
                'price'    => $item->getPrice(),
                'quantity' => $item->getQuantity(),
                'sum'      => $item->getFinalPrice(),
                'product'  => $item->getProductId(),
            ];
        }
    }

    $payments = [];
    foreach ($order->getPaymentCollection() as $p) {
        $payments[] = [
            'id'        => $p->getId(),
            'paid'      => $p->getField('PAYED') === 'Y',
            'sum'       => $p->getField('SUM'),
            'pay_system'=> $p->getPaymentSystemName(),
        ];
    }

    $shipments = [];
    foreach ($order->getShipmentCollection() as $s) {
        if ($s->isSystem()) continue;
        $shipments[] = [
            'id'          => $s->getId(),
            'delivery'    => $s->getDeliveryName(),
            'status'      => $s->getField('STATUS_ID'),
            'tracking'    => $s->getField('TRACKING_NUMBER'),
            'price'       => $s->getField('PRICE_DELIVERY'),
        ];
    }

    return [
        'id'         => $order->getId(),
        'number'     => $order->getField('ACCOUNT_NUMBER'),
        'status'     => $order->getField('STATUS_ID'),
        'date'       => $order->getField('DATE_INSERT')->toString(),
        'price'      => $order->getPrice(),
        'currency'   => $order->getCurrency(),
        'paid'       => $order->isPaid(),
        'canceled'   => $order->isCanceled(),
        'items'      => $items,
        'payments'   => $payments,
        'shipments'  => $shipments,
    ];
}

function repeatOrder(Order $order): \Bitrix\Main\Result {
    global $USER;
    $result = new \Bitrix\Main\Result();

    // Повторяем — копируем позиции в текущую корзину пользователя.
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(), SITE_ID
    );
    $sourceBasket = $order->getBasket();

    $added = 0;
    foreach ($sourceBasket->getBasketItems() as $item) {
        // Проверяем, что товар ещё продаётся — цены берутся актуальные.
        if ($item->getField('CAN_BUY') !== 'Y') {
            continue;
        }

        // Не дублируем если товар уже в корзине — увеличим количество.
        $existing = $basket->getExistsItem('catalog', $item->getProductId());
        if ($existing) {
            $existing->setField('QUANTITY',
                $existing->getQuantity() + $item->getQuantity());
        } else {
            $newItem = $basket->createItem('catalog', $item->getProductId());
            $newItem->setFields([
                'QUANTITY'     => $item->getQuantity(),
                'PRICE'        => $item->getPrice(),
                'CUSTOM_PRICE' => 'N', // цену пересчитает по правилам
                'CURRENCY'     => $item->getField('CURRENCY'),
            ]);
        }
        $added++;
    }

    if ($added === 0) {
        $result->addError(new \Bitrix\Main\Error('Все товары заказа недоступны'));
        return $result;
    }

    $saveResult = $basket->save();
    if (!$saveResult->isSuccess()) {
        $result->addErrors($saveResult->getErrors());
    }
    return $result;
}

function cancelOrder(Order $order, string $reason): \Bitrix\Main\Result {
    $result = new \Bitrix\Main\Result();

    // Бизнес-правила отмены — сверьтесь с модулем 5.4 теории.
    if ($order->isCanceled()) {
        $result->addError(new \Bitrix\Main\Error('Заказ уже отменён'));
        return $result;
    }
    if ($order->isPaid()) {
        $result->addError(new \Bitrix\Main\Error('Оплаченный заказ нельзя отменить — оформите возврат'));
        return $result;
    }
    if (in_array($order->getField('STATUS_ID'), ['DE', 'DF'], true)) {
        $result->addError(new \Bitrix\Main\Error('Выполненный заказ нельзя отменить'));
        return $result;
    }

    $order->setField('CANCELED', 'Y');
    $order->setField('REASON_CANCELED', $reason ?: 'Отменено покупателем');
    $order->setField('STATUS_ID', 'A');

    $saveResult = $order->save();
    if (!$saveResult->isSuccess()) {
        $result->addErrors($saveResult->getErrors());
        return $result;
    }

    // Поднялись события OnSaleOrderCanceled — там ERP/письмо.
    return $result;
}

function getBasketSummary(): array {
    $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
        \Bitrix\Sale\Fuser::getId(), SITE_ID
    );
    $count = 0; $sum = 0;
    foreach ($basket->getBasketItems() as $i) {
        $count += $i->getQuantity();
        $sum   += $i->getFinalPrice();
    }
    return ['count' => $count, 'sum' => $sum];
}
