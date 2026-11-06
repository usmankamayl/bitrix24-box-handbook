<?php

namespace BitrixCourse\Checkout;

use Bitrix\Main\Loader;
use Bitrix\Sale;
use CEvent;

/**
 * Асинхронная отправка письма "заказ оформлен".
 *
 * Берём заказ заново по ID — агент выполняется в отдельном хите,
 * объект $order из обработчика события до него не доживает.
 */
class OrderMailAgent
{
    /**
     * Вызывается планировщиком агентов. Возвращаемая пустая строка
     * означает "одноразовый агент, не перерегистрировать".
     */
    public static function send(int $orderId): string
    {
        if (!Loader::includeModule('sale')) {
            return '';
        }

        $order = Sale\Order::load($orderId);
        if (!$order) {
            return '';
        }

        $email = self::resolveEmail($order);
        if ($email === '') {
            return '';
        }

        CEvent::Send('SALE_NEW_ORDER', 's1', [
            'ORDER_ID'    => $order->getId(),
            'ORDER_PRICE' => $order->getPrice(),
            'ORDER_DATE'  => $order->getField('DATE_INSERT'),
            'EMAIL'       => $email,
        ]);

        return '';
    }

    private static function resolveEmail(Sale\Order $order): string
    {
        $propertyCollection = $order->getPropertyCollection();
        $emailProperty = $propertyCollection->getUserEmail();

        return $emailProperty ? (string)$emailProperty->getValue() : '';
    }
}
