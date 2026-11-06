<?php

/**
 * Задача 3: уведомление о новом крупном заказе.
 *
 * Подключение в /local/php_interface/init.php:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/order-notification/init.php';
 *
 * Почему addEventHandler (без Compatible): OnSaleOrderSaved — D7-событие,
 * отправляется из \Bitrix\Sale\Order::save() объектом Main\Event, параметры
 * ENTITY, VALUES, IS_NEW.
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Sale\Order;

if (!Loader::includeModule('sale')) {
    return;
}

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler(
    'sale',
    'OnSaleOrderSaved',
    [OrderNotification::class, 'onOrderSaved']
);

/**
 * Что здесь важно понять:
 * - «только создание» определяется параметром IS_NEW, а не эвристикой внутри
 *   обработчика: флаг выставляет сам sale;
 * - письмо уходит одним каналом (почтовое событие + шаблон), без дублирующего
 *   mail(): иначе администратор получает два письма;
 * - обработчик не бросает исключений: заказ уже сохранён, ломать ответ
 *   покупателю из-за недоступного SMTP нельзя — только лог.
 */
final class OrderNotification
{
    private const THRESHOLD = 100000.0;
    private const MAIL_EVENT = 'LARGE_ORDER_NOTIFICATION';

    public static function onOrderSaved(\Bitrix\Main\Event $event): void
    {
        if (!$event->getParameter('IS_NEW')) {
            return;
        }

        $order = $event->getParameter('ENTITY');
        if (!$order instanceof Order) {
            return;
        }

        $price = (float)$order->getPrice();
        if ($price < self::THRESHOLD) {
            return;
        }

        try {
            self::sendMail($order);
        } catch (\Throwable $e) {
            \Bitrix\Main\Diag\Debug::writeToFile(
                ['orderId' => $order->getId(), 'error' => $e->getMessage()],
                'large order notification failed',
                '/local/logs/order_notification.log'
            );
        }
    }

    private static function sendMail(Order $order): void
    {
        $orderId = (int)$order->getId();

        $fields = [
            'ORDER_ID' => $orderId,
            'ORDER_PRICE' => \CurrencyFormat($order->getPrice(), $order->getCurrency()),
            'ORDER_DATE' => (string)$order->getDateInsert(),
            'USER_ID' => (int)$order->getUserId(),
            'CUSTOMER' => self::getCustomerName($order),
            'CONTACT' => self::getContact($order),
            'BASKET' => self::getBasketText($order),
            'ORDER_LINK' => self::getAdminLink($orderId),
        ];

        $result = \Bitrix\Main\Mail\Event::send([
            'EVENT_NAME' => self::MAIL_EVENT,
            'LID' => SITE_ID,
            'C_FIELDS' => $fields,
        ]);

        if (!$result->isSuccess()) {
            \Bitrix\Main\Diag\Debug::writeToFile(
                ['orderId' => $orderId, 'errors' => $result->getErrorMessages()],
                'large order mail failed',
                '/local/logs/order_notification.log'
            );
        }
    }

    private static function getCustomerName(Order $order): string
    {
        $name = trim((string)$order->getPropertyCollection()->getPayerName());
        if ($name !== '') {
            return $name;
        }

        $userId = (int)$order->getUserId();
        if ($userId <= 0) {
            return 'Гость';
        }

        $user = \Bitrix\Main\UserTable::getById($userId)->fetch();
        if (!$user) {
            return 'Гость';
        }

        return trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? '')) ?: 'Гость';
    }

    /**
     * Почта и телефон — только для внутреннего уведомления администратору.
     * В шаблоне письма их выводить сырыми нельзя: значения приходят от покупателя.
     */
    private static function getContact(Order $order): string
    {
        $collection = $order->getPropertyCollection();

        $parts = array_filter(
            [
                trim((string)$collection->getUserEmail()),
                trim((string)$collection->getPhone()),
            ],
            static fn(string $value): bool => $value !== ''
        );

        return implode(', ', $parts);
    }

    private static function getBasketText(Order $order): string
    {
        $lines = [];

        /** @var \Bitrix\Sale\BasketItem $item */
        foreach ($order->getBasket() as $item) {
            $lines[] = sprintf(
                '%s x %s = %s',
                (string)$item->getField('NAME'),
                (float)$item->getQuantity(),
                \CurrencyFormat((float)$item->getFinalPrice(), $order->getCurrency())
            );
        }

        return implode("\n", $lines);
    }

    private static function getAdminLink(int $orderId): string
    {
        $host = \Bitrix\Main\Context::getCurrent()->getServer()->getHttpHost();
        $scheme = \Bitrix\Main\Context::getCurrent()->getRequest()->isHttps() ? 'https' : 'http';

        return sprintf('%s://%s/bitrix/admin/sale_order_view.php?ID=%d&lang=%s', $scheme, $host, $orderId, LANGUAGE_ID);
    }
}
