<?php
/**
 * Уведомление администратора о крупных заказах
 *
 * Отправляет email администратору при создании заказа на сумму больше заданного порога.
 *
 * Этот файл нужно подключить в /local/php_interface/init.php:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/order-notification/init.php';
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Mail\Event;
use Bitrix\Sale\Order;

// Проверяем наличие модуля sale
if (!Loader::includeModule('sale')) {
    return;
}

$eventManager = EventManager::getInstance();

// Регистрируем обработчик сохранения заказа
$eventManager->addEventHandler(
    'sale',
    'OnSaleOrderSaved',
    ['OrderNotification', 'onOrderSaved']
);

/**
 * Класс для уведомлений о крупных заказах
 */
class OrderNotification
{
    /**
     * Порог суммы заказа для отправки уведомления (в рублях)
     */
    const THRESHOLD_AMOUNT = 100000;

    /**
     * Email администратора для уведомлений
     * Можно переопределить в настройках сайта
     */
    const ADMIN_EMAIL = 'admin@example.com';

    /**
     * Обработчик сохранения заказа
     *
     * @param \Bitrix\Main\Event $event
     * @return void
     */
    public static function onOrderSaved(\Bitrix\Main\Event $event)
    {
        /** @var Order $order */
        $order = $event->getParameter('ENTITY');

        // Проверяем, что это новый заказ (только что созданный)
        if (!$order->isNew()) {
            return;
        }

        // Получаем сумму заказа
        $orderPrice = $order->getPrice();

        // Проверяем порог суммы
        if ($orderPrice < self::THRESHOLD_AMOUNT) {
            return;
        }

        // Отправляем уведомление
        self::sendNotification($order);
    }

    /**
     * Отправляет уведомление администратору
     *
     * @param Order $order Объект заказа
     * @return void
     */
    private static function sendNotification(Order $order)
    {
        // Получаем данные заказа
        $orderId = $order->getId();
        $orderPrice = $order->getPrice();
        $currency = $order->getCurrency();

        // Получаем данные пользователя
        $userId = $order->getUserId();
        $propertyCollection = $order->getPropertyCollection();

        // Собираем email получателя заказа
        $customerEmail = '';
        $customerPhone = '';
        $customerName = '';

        foreach ($propertyCollection as $property) {
            $code = $property->getField('CODE');
            $value = $property->getValue();

            if ($code === 'EMAIL') {
                $customerEmail = $value;
            } elseif ($code === 'PHONE') {
                $customerPhone = $value;
            } elseif ($code === 'FIO' || $code === 'NAME') {
                $customerName = $value;
            }
        }

        // Если имя не нашли в свойствах, берём из пользователя
        if (empty($customerName) && $userId) {
            $user = \CUser::GetByID($userId)->Fetch();
            if ($user) {
                $customerName = trim($user['NAME'] . ' ' . $user['LAST_NAME']);
            }
        }

        // Получаем состав заказа
        $basket = $order->getBasket();
        $basketItems = [];

        /** @var \Bitrix\Sale\BasketItem $basketItem */
        foreach ($basket as $basketItem) {
            $basketItems[] = [
                'NAME' => $basketItem->getField('NAME'),
                'QUANTITY' => $basketItem->getQuantity(),
                'PRICE' => $basketItem->getPrice(),
                'SUM' => $basketItem->getFinalPrice(),
            ];
        }

        // Формируем данные для почтового шаблона
        $arFields = [
            'ORDER_ID' => $orderId,
            'ORDER_PRICE' => \CurrencyFormat($orderPrice, $currency),
            'ORDER_PRICE_RAW' => $orderPrice,
            'CURRENCY' => $currency,
            'CUSTOMER_NAME' => $customerName ?: 'Не указано',
            'CUSTOMER_EMAIL' => $customerEmail ?: 'Не указано',
            'CUSTOMER_PHONE' => $customerPhone ?: 'Не указано',
            'USER_ID' => $userId ?: 'Гость',
            'BASKET_ITEMS' => $basketItems,
            'DATE_INSERT' => $order->getDateInsert()->toString(),
            'SITE_NAME' => self::getSiteName(),
            'ORDER_URL' => self::getOrderUrl($orderId),
        ];

        // Формируем список товаров для email
        $arFields['BASKET_HTML'] = self::formatBasketItems($basketItems, $currency);

        // Отправляем через стандартное почтовое событие
        Event::send([
            'EVENT_NAME' => 'LARGE_ORDER_NOTIFICATION',
            'LID' => SITE_ID,
            'C_FIELDS' => $arFields,
        ]);

        // Дополнительно: отправляем простое уведомление напрямую
        // (на случай, если почтовое событие не настроено)
        self::sendSimpleEmail($arFields);

        // Логируем отправку
        AddMessage2Log(sprintf(
            'Large order notification sent: Order #%d, Amount: %s, Customer: %s',
            $orderId,
            $arFields['ORDER_PRICE'],
            $customerName
        ), 'order_notification');
    }

    /**
     * Отправляет простое email-уведомление напрямую
     *
     * @param array $arFields Данные заказа
     * @return void
     */
    private static function sendSimpleEmail($arFields)
    {
        $adminEmail = self::getAdminEmail();

        $subject = sprintf(
            '[%s] Крупный заказ #%d на сумму %s',
            $arFields['SITE_NAME'],
            $arFields['ORDER_ID'],
            $arFields['ORDER_PRICE']
        );

        $message = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <h2 style='color: #ff6b00;'>⚠️ Создан крупный заказ</h2>

    <table style='border-collapse: collapse; margin: 20px 0;'>
        <tr>
            <td style='padding: 8px; font-weight: bold;'>Номер заказа:</td>
            <td style='padding: 8px;'><a href='{$arFields['ORDER_URL']}'>#{$arFields['ORDER_ID']}</a></td>
        </tr>
        <tr>
            <td style='padding: 8px; font-weight: bold;'>Сумма заказа:</td>
            <td style='padding: 8px; color: #ff6b00; font-size: 18px; font-weight: bold;'>{$arFields['ORDER_PRICE']}</td>
        </tr>
        <tr>
            <td style='padding: 8px; font-weight: bold;'>Дата создания:</td>
            <td style='padding: 8px;'>{$arFields['DATE_INSERT']}</td>
        </tr>
        <tr>
            <td style='padding: 8px; font-weight: bold;'>Покупатель:</td>
            <td style='padding: 8px;'>{$arFields['CUSTOMER_NAME']}</td>
        </tr>
        <tr>
            <td style='padding: 8px; font-weight: bold;'>Email:</td>
            <td style='padding: 8px;'>{$arFields['CUSTOMER_EMAIL']}</td>
        </tr>
        <tr>
            <td style='padding: 8px; font-weight: bold;'>Телефон:</td>
            <td style='padding: 8px;'>{$arFields['CUSTOMER_PHONE']}</td>
        </tr>
    </table>

    <h3>Состав заказа:</h3>
    {$arFields['BASKET_HTML']}

    <p style='margin-top: 30px;'>
        <a href='{$arFields['ORDER_URL']}' style='background: #ff6b00; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
            Открыть заказ в админке
        </a>
    </p>

    <hr style='margin: 30px 0; border: none; border-top: 1px solid #ccc;'>
    <p style='color: #999; font-size: 12px;'>
        Это автоматическое уведомление о создании заказа на сумму более " . number_format(self::THRESHOLD_AMOUNT, 0, ',', ' ') . " руб.
    </p>
</body>
</html>
        ";

        // Отправляем email
        mail(
            $adminEmail,
            $subject,
            $message,
            "Content-type: text/html; charset=utf-8\r\n" .
            "From: noreply@{$_SERVER['HTTP_HOST']}\r\n"
        );
    }

    /**
     * Форматирует список товаров для HTML-письма
     *
     * @param array $basketItems Массив товаров
     * @param string $currency Валюта
     * @return string HTML-код таблицы
     */
    private static function formatBasketItems($basketItems, $currency)
    {
        if (empty($basketItems)) {
            return '<p>Корзина пуста</p>';
        }

        $html = "
        <table style='border-collapse: collapse; width: 100%; border: 1px solid #ddd;'>
            <thead>
                <tr style='background: #f5f5f5;'>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: left;'>Товар</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: center;'>Кол-во</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: right;'>Цена</th>
                    <th style='padding: 10px; border: 1px solid #ddd; text-align: right;'>Сумма</th>
                </tr>
            </thead>
            <tbody>
        ";

        foreach ($basketItems as $item) {
            $html .= sprintf(
                "<tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'>%s</td>
                    <td style='padding: 10px; border: 1px solid #ddd; text-align: center;'>%s</td>
                    <td style='padding: 10px; border: 1px solid #ddd; text-align: right;'>%s</td>
                    <td style='padding: 10px; border: 1px solid #ddd; text-align: right;'>%s</td>
                </tr>",
                htmlspecialchars($item['NAME']),
                $item['QUANTITY'],
                \CurrencyFormat($item['PRICE'], $currency),
                \CurrencyFormat($item['SUM'], $currency)
            );
        }

        $html .= "
            </tbody>
        </table>
        ";

        return $html;
    }

    /**
     * Получает email администратора из настроек
     *
     * @return string
     */
    private static function getAdminEmail()
    {
        // Пробуем получить из настроек сайта
        $siteEmail = \COption::GetOptionString('main', 'email_from', '');

        if (!empty($siteEmail) && filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
            return $siteEmail;
        }

        // Возвращаем константу по умолчанию
        return self::ADMIN_EMAIL;
    }

    /**
     * Получает название сайта
     *
     * @return string
     */
    private static function getSiteName()
    {
        $site = \CSite::GetByID(SITE_ID)->Fetch();
        return $site['NAME'] ?? $_SERVER['HTTP_HOST'];
    }

    /**
     * Формирует URL для просмотра заказа в админке
     *
     * @param int $orderId ID заказа
     * @return string
     */
    private static function getOrderUrl($orderId)
    {
        return sprintf(
            'https://%s/bitrix/admin/sale_order_view.php?ID=%d&lang=ru',
            $_SERVER['HTTP_HOST'],
            $orderId
        );
    }
}
