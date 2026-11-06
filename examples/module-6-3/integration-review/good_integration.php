<?php
/**
 * Code Review #3 — Исправленная интеграция с внешним API доставки
 *
 * Принцип: событие заказа только ставит задачу в очередь,
 * сам HTTP-вызов делает отдельный агент — вне транзакции, с таймаутом,
 * retry и circuit breaker.
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Sale\Order;

final class DeliveryIntegration
{
    // БЫЛО: ключ захардкожен в коде (утечёт при коммите в git)
    // СТАЛО: настройка модуля, меняется без деплоя
    private static function getApiKey(): string
    {
        return Option::get('acme_delivery', 'api_key', '');
    }

    private static function getApiUrl(): string
    {
        return Option::get('acme_delivery', 'api_url', 'https://api.courier-service.com/v2/shipments');
    }

    /**
     * Событие — только постановка в очередь, никакого HTTP здесь.
     * БЫЛО: синхронный curl_exec прямо в обработчике OnSaleOrderSaved —
     * держал транзакцию заказа открытой, пока внешний API не ответит.
     */
    public function onOrderStatusChange($event): void
    {
        $order = $event->getParameter('ENTITY');
        $newStatus = $order->getField('STATUS_ID');

        if ($newStatus !== 'P') {
            return;
        }

        // Быстрый INSERT в outbox — та же транзакция, что и сохранение заказа
        Application::getConnection()->queryExecute(
            "INSERT INTO acme_outbox (EVENT_TYPE, ENTITY_ID, STATUS, CREATED_AT)
             VALUES ('delivery.create_shipment', ?, 'new', NOW())",
            [$order->getId()]
        );
    }

    /**
     * Webhook — статус доставки изменился на стороне курьерской службы.
     */
    public function webhookStatusUpdate(): void
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        // БЫЛО: проверки подписи не было вообще — любой мог прислать
        // поддельный статус и, например, пометить неоплаченный заказ доставленным.
        if (!$this->isSignatureValid($raw, $_SERVER['HTTP_X_SIGNATURE'] ?? '')) {
            http_response_code(403);
            die('invalid signature');
        }

        $orderId = (int)($data['order_id'] ?? 0);
        $status = (string)($data['status'] ?? '');

        if (!$orderId || !$status) {
            http_response_code(400);
            die('bad payload');
        }

        $order = Order::load($orderId);
        if (!$order) {
            // 200, а не 404 — иначе курьерский сервис будет ретраить вечно
            // на заказ, которого у нас никогда не было (например, тестовый).
            http_response_code(200);
            die('order not found, ignored');
        }

        // Идемпотентность — если статус уже такой, не делаем save() повторно
        $current = $order->getField('DELIVERY_STATUS');
        if ($current === $status) {
            http_response_code(200);
            die('already up to date');
        }

        $order->setField('DELIVERY_STATUS', $status);
        $result = $order->save();

        if ($result->isSuccess()) {
            http_response_code(200);
            echo 'ok';
        } else {
            http_response_code(500); // курьерский сервис повторит запрос позже
            echo 'save failed';
        }
    }

    private function isSignatureValid(string $rawBody, string $signature): bool
    {
        $secret = Option::get('acme_delivery', 'webhook_secret', '');
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return $signature !== '' && hash_equals($expected, $signature);
    }
}

/**
 * Worker-консьюмер для outbox — вызывается из OutboxWorker (см. module-6-2).
 * Здесь — HTTP-запрос с таймаутом, retry через backoff outbox-а и circuit breaker.
 */
final class ShipmentCreator
{
    private const CIRCUIT_THRESHOLD = 5;   // сбоев подряд
    private const CIRCUIT_WINDOW = 300;    // 5 минут

    public function __invoke(array $payload, array $job): void
    {
        $orderId = $payload['order_id'] ?? $job['ENTITY_ID'];

        if ($this->isCircuitOpen()) {
            // БЫЛО: при недоступности API вообще не было защиты —
            // каждый заказ пытался достучаться до мёртвого сервиса.
            // СТАЛО: пока "выключатель" разомкнут, не долбим упавший API,
            // outbox retry подхватит job позже через backoff.
            throw new \RuntimeException('circuit open: delivery API is down');
        }

        $order = Order::load($orderId);
        if (!$order) {
            return; // заказ удалён — нечего делать, не ошибка
        }

        $shipment = $order->getShipmentCollection()->current();
        $props = $order->getPropertyCollection();

        $payloadOut = [
            'order_id' => $orderId,
            'address' => $this->getPropValue($props, 'ADDRESS'),
            'city' => $this->getPropValue($props, 'CITY'),
            'phone' => $this->getPropValue($props, 'PHONE'),
            'weight' => $shipment ? $shipment->getField('BASE_WEIGHT') : 0,
        ];

        // БЫЛО: curl без таймаута — повисший запрос держит воркер/транзакцию.
        // СТАЛО: явный connect + total timeout.
        $ch = curl_init(Option::get('acme_delivery', 'api_url', ''));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payloadOut),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . Option::get('acme_delivery', 'api_key', ''),
                'Idempotency-Key: shipment_' . $orderId, // защита от дублей на стороне курьера
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || $httpCode >= 500) {
            $this->recordFailure();
            throw new \RuntimeException("delivery API error: curl=$errno http=$httpCode");
        }
        $this->recordSuccess();

        $data = json_decode($response, true);
        if (!isset($data['tracking_number'])) {
            throw new \RuntimeException('delivery API: no tracking_number in response');
        }

        // БЫЛО: $order->save() внутри OnSaleOrderSaved — рекурсия.
        // СТАЛО: мы уже в отдельном worker-процессе, не в событии —
        // save() здесь безопасен, это не вложенный вызов события.
        $order->setField('DELIVERY_TRACKING', $data['tracking_number']);
        $order->save();
    }

    private function getPropValue($props, string $code): string
    {
        $item = $props->getItemByOrderPropertyCode($code);
        return $item ? (string)$item->getValue() : '';
    }

    private function isCircuitOpen(): bool
    {
        $failures = (int)Option::get('acme_delivery', 'cb_failures', 0);
        $windowStart = (int)Option::get('acme_delivery', 'cb_window_start', 0);

        if (time() - $windowStart > self::CIRCUIT_WINDOW) {
            return false; // окно истекло — пробуем снова
        }
        return $failures >= self::CIRCUIT_THRESHOLD;
    }

    private function recordFailure(): void
    {
        $windowStart = (int)Option::get('acme_delivery', 'cb_window_start', 0);
        if (time() - $windowStart > self::CIRCUIT_WINDOW) {
            Option::set('acme_delivery', 'cb_window_start', time());
            Option::set('acme_delivery', 'cb_failures', 1);
        } else {
            Option::set('acme_delivery', 'cb_failures', (int)Option::get('acme_delivery', 'cb_failures', 0) + 1);
        }
    }

    private function recordSuccess(): void
    {
        Option::set('acme_delivery', 'cb_failures', 0);
    }
}
