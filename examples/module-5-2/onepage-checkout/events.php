<?php

/**
 * Подключить в /local/php_interface/init.php:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/local/checkout/events.php';
 */

namespace BitrixCourse\Checkout;

use Bitrix\Main\Loader;
use Bitrix\Sale;
use CAgent;

require_once __DIR__ . '/lib/OrderMailAgent.php';

if (Loader::includeModule('sale')) {
    \AddEventHandler('sale', 'OnSaleOrderSaved', __NAMESPACE__ . '\\onSaleOrderSavedHandler');
}

/**
 * OnSaleOrderSaved срабатывает и на создание заказа, и на любое его
 * изменение (смена статуса, пересчёт, правка в админке) — isNew()
 * отличает именно создание. Без этой проверки письмо "заказ оформлен"
 * будет уходить повторно на каждый save() заказа.
 *
 * Письмо шлём не синхронно (в самом чекауте), а через агента: SMTP может
 * ответить за секунды, и клиент не должен ждать этого после оплаты.
 */
function onSaleOrderSavedHandler(Sale\Order $order): void
{
    if (!$order->isNew()) {
        return;
    }

    CAgent::AddAgent(
        'BitrixCourse\\Checkout\\OrderMailAgent::send(' . $order->getId() . ');',
        'main',
        'N',
        0,
        '',
        'Y',
        date('d.m.Y H:i:s', time() + 5),
        50
    );
}
