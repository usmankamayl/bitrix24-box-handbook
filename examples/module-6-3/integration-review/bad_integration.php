<?php
/**
 * Code Review #3 — «Плохая» интеграция с внешним API доставки
 *
 * Синхронизация статуса доставки: при изменении статуса заказа
 * дёргаем API курьерской службы, чтобы создать/обновить заявку на доставку.
 *
 * ЭТОТ КОД — ДЛЯ РАЗБОРА. Содержит 7+ ошибок.
 */

class DeliveryIntegration
{
    // Захардкоженные credentials прямо в коде
    private const API_KEY = 'sk_live_a8f7d6e5c4b3a291';
    private const API_URL = 'https://api.courier-service.com/v2/shipments';

    public function onOrderStatusChange($event)
    {
        $order = $event->getParameter('ENTITY');
        $orderId = $order->getId();
        $newStatus = $order->getField('STATUS_ID');

        if ($newStatus !== 'P') {
            return; // реагируем только на "оплачен"
        }

        // Собираем данные для отправки
        $shipment = $order->getShipmentCollection()->getItemById(0);
        $props = $order->getPropertyCollection();

        $payload = [
            'order_id' => $orderId,
            'address' => $props->getItemByOrderPropertyCode('ADDRESS')->getValue(),
            'city' => $props->getItemByOrderPropertyCode('CITY')->getValue(),
            'phone' => $props->getItemByOrderPropertyCode('PHONE')->getValue(),
            'weight' => $shipment->getField('BASE_WEIGHT'),
        ];

        // Синхронный HTTP-запрос прямо в обработчике события
        // (который внутри транзакции сохранения заказа!)
        $ch = curl_init(self::API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . self::API_KEY,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Нет таймаута — курьерский API повис — вся система ждёт

        $response = curl_exec($ch);
        curl_close($ch);

        // Никакой обработки ошибок
        $data = json_decode($response, true);

        // Сохраняем трек-номер
        if (isset($data['tracking_number'])) {
            $order->setField('COMMENTS', 'Трек: ' . $data['tracking_number']);
            $order->save(); // save() внутри события OnSaleOrderSaved → рекурсия!
        }
        // Если запрос упал — заявка на доставку просто не создалась,
        // никто об этом не узнает, заказ "висит" без доставки
    }

    public function webhookStatusUpdate()
    {
        // Webhook от курьерской службы — статус доставки изменился
        $data = json_decode(file_get_contents('php://input'), true);

        // Нет проверки подписи — любой может прислать поддельный статус!
        $orderId = $data['order_id'];
        $status = $data['status'];

        $order = \Bitrix\Sale\Order::load($orderId);
        if ($order) {
            $order->setField('COMMENTS', 'Статус доставки: ' . $status);
            $order->save();
        }

        echo 'ok';
    }
}
