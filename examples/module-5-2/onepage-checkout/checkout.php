<?php

/**
 * Обработчик сабмита формы одностраничного чекаута.
 * Форма POST'ит сюда: PERSON_TYPE_ID, DELIVERY_ID, PAY_SYSTEM_ID,
 * PROPERTIES[propId] => value.
 */

define('STOP_STATISTICS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once __DIR__ . '/lib/OrderBuilder.php';

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use BitrixCourse\Checkout\OrderBuilder;

global $USER;

if (!Loader::includeModule('sale')) {
    die('Модуль sale не установлен');
}

$request = Context::getCurrent()->getRequest();

if (!$request->isPost() || !check_bitrix_sessid()) {
    LocalRedirect('/cart/');
}

$fuserId = Sale\Fuser::getId();
$basket = Sale\Basket::loadItemsForFUser($fuserId, SITE_ID);

$properties = [];
foreach ((array)$request->getPost('PROPERTIES') as $propId => $value) {
    $properties[(int)$propId] = $value;
}

$formData = [
    'PERSON_TYPE_ID' => (int)$request->getPost('PERSON_TYPE_ID'),
    'DELIVERY_ID'    => (int)$request->getPost('DELIVERY_ID'),
    'PAY_SYSTEM_ID'  => (int)$request->getPost('PAY_SYSTEM_ID'),
    'PROPERTIES'     => $properties,
];

// Заказ привязываем к текущему USER_ID. Для неавторизованных
// Bitrix сам подставит анонимного пользователя (см. CSaleUser::GetAnonymousUserID) —
// в D7 Order::create достаточно передать 0, система обработает это сама.
$builder = new OrderBuilder(SITE_ID, (int)$USER->GetID());
$result = $builder->build($basket, $formData);

if (!$result->isSuccess()) {
    // В реальном проекте — сохранить ошибки в сессию и вернуть пользователя
    // на форму с подсветкой проблемных полей, а не просто редиректить.
    $_SESSION['CHECKOUT_ERRORS'] = $result->getErrorMessages();
    LocalRedirect('/cart/checkout.php');
}

/** @var Sale\Order $order */
$order = $result->getData()['ORDER'];

// Дальше — передача на оплату конкретной платёжной системы
// (см. модуль 3.4 "Платёжные системы и доставка"), здесь не дублируем.
LocalRedirect('/cart/order/' . $order->getId() . '/');
