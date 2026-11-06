<?php

/**
 * Подключить в /local/php_interface/init.php:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/local/basket-discount/events.php';
 */

namespace BitrixCourse\BasketDiscount;

use Bitrix\Main\Loader;
use Bitrix\Sale;

require_once __DIR__ . '/lib/DiscountAuditLog.php';

if (Loader::includeModule('sale')) {
    \AddEventHandler('sale', 'OnSaleOrderSaved', __NAMESPACE__ . '\\onSaleOrderSavedHandler');
}

/**
 * isNew() — чтобы не писать в аудит повторно на каждое обновление заказа
 * (смена статуса, правка в админке), только на момент создания.
 */
function onSaleOrderSavedHandler(Sale\Order $order): void
{
    if (!$order->isNew()) {
        return;
    }

    $discountedItems = [];
    foreach ($order->getBasket() as $item) {
        /** @var Sale\BasketItem $item */
        if ($item->getField('CUSTOM_PRICE') === 'Y' && (float)$item->getField('DISCOUNT_PRICE') > 0) {
            $discountedItems[] = [
                'PRODUCT_ID' => $item->getProductId(),
                'QUANTITY'   => $item->getQuantity(),
                'DISCOUNT'   => $item->getField('DISCOUNT_PRICE'),
            ];
        }
    }

    if ($discountedItems === []) {
        return;
    }

    DiscountAuditLog::write($order->getId(), $discountedItems);
}
