<?php
/**
 * Обработчик входящих webhook-запросов
 *
 * Принимает данные от внешних систем и обрабатывает их
 *
 * Использование:
 * Создайте файл /local/webhook.php:
 *
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
 * $handler = new \Acme\Integration\WebhookHandler();
 * $handler->process();
 */

namespace Acme\Integration;

use Bitrix\Main\Application;
use Acme\Integration\Service\LogService;

class WebhookHandler
{
    /** @var LogService */
    protected $logger;

    /** @var array Входящие данные */
    protected $payload;

    /** @var string Тип события */
    protected $event;

    public function __construct()
    {
        $this->logger = new LogService();
    }

    /**
     * Обработать входящий webhook
     */
    public function process(): void
    {
        try {
            // Получаем данные
            $this->payload = $this->getPayload();

            // Логируем входящий запрос
            $this->logger->logRequest(
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['REQUEST_URI'],
                $this->payload
            );

            // Определяем тип события
            $this->event = $this->payload['event'] ?? 'unknown';

            // Обрабатываем событие
            $result = $this->handleEvent();

            // Отправляем ответ
            $this->sendResponse(200, $result);

        } catch (\Exception $e) {
            $this->logger->logError($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->sendResponse(500, [
                'error' => 'Internal server error',
            ]);
        }
    }

    /**
     * Получить входящие данные
     *
     * @return array
     */
    protected function getPayload(): array
    {
        $input = file_get_contents('php://input');

        if (empty($input)) {
            return [];
        }

        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Обработать событие
     *
     * @return array
     */
    protected function handleEvent(): array
    {
        switch ($this->event) {
            case 'order.created':
                return $this->handleOrderCreated();

            case 'order.updated':
                return $this->handleOrderUpdated();

            case 'payment.received':
                return $this->handlePaymentReceived();

            case 'sms.status':
                return $this->handleSmsStatus();

            case 'crm.contact.created':
                return $this->handleCrmContactCreated();

            default:
                $this->logger->logError("Unknown event type: {$this->event}");
                return ['status' => 'ignored'];
        }
    }

    /**
     * Обработать создание заказа
     *
     * @return array
     */
    protected function handleOrderCreated(): array
    {
        $orderId = $this->payload['order_id'] ?? null;

        if (!$orderId) {
            throw new \Exception('Missing order_id');
        }

        // Здесь может быть логика создания заказа в Bitrix
        // на основе данных из внешней системы

        AddMessage2Log(sprintf(
            'Webhook: Создан заказ #%s',
            $orderId
        ), 'acme.integration');

        return [
            'status' => 'success',
            'order_id' => $orderId,
        ];
    }

    /**
     * Обработать обновление заказа
     *
     * @return array
     */
    protected function handleOrderUpdated(): array
    {
        $orderId = $this->payload['order_id'] ?? null;
        $status = $this->payload['status'] ?? null;

        if (!$orderId || !$status) {
            throw new \Exception('Missing order_id or status');
        }

        // Обновляем статус заказа в Bitrix
        if (\Bitrix\Main\Loader::includeModule('sale')) {
            $order = \Bitrix\Sale\Order::load($orderId);

            if ($order) {
                $order->setField('STATUS_ID', $status);
                $order->save();

                AddMessage2Log(sprintf(
                    'Webhook: Обновлен статус заказа #%s -> %s',
                    $orderId,
                    $status
                ), 'acme.integration');
            }
        }

        return [
            'status' => 'success',
            'order_id' => $orderId,
            'new_status' => $status,
        ];
    }

    /**
     * Обработать получение платежа
     *
     * @return array
     */
    protected function handlePaymentReceived(): array
    {
        $paymentId = $this->payload['payment_id'] ?? null;
        $amount = $this->payload['amount'] ?? null;

        if (!$paymentId || !$amount) {
            throw new \Exception('Missing payment_id or amount');
        }

        // Логика обработки платежа

        AddMessage2Log(sprintf(
            'Webhook: Получен платеж #%s на сумму %s',
            $paymentId,
            $amount
        ), 'acme.integration');

        return [
            'status' => 'success',
            'payment_id' => $paymentId,
        ];
    }

    /**
     * Обработать статус SMS
     *
     * @return array
     */
    protected function handleSmsStatus(): array
    {
        $messageId = $this->payload['message_id'] ?? null;
        $status = $this->payload['status'] ?? null;

        if (!$messageId || !$status) {
            throw new \Exception('Missing message_id or status');
        }

        AddMessage2Log(sprintf(
            'Webhook: Статус SMS %s -> %s',
            $messageId,
            $status
        ), 'acme.integration');

        return [
            'status' => 'success',
            'message_id' => $messageId,
        ];
    }

    /**
     * Обработать создание контакта в CRM
     *
     * @return array
     */
    protected function handleCrmContactCreated(): array
    {
        $contactId = $this->payload['contact_id'] ?? null;
        $name = $this->payload['name'] ?? null;

        if (!$contactId) {
            throw new \Exception('Missing contact_id');
        }

        // Создаем контакт в Bitrix CRM
        if (\Bitrix\Main\Loader::includeModule('crm')) {
            $contact = new \CCrmContact();

            $fields = [
                'NAME' => $name,
                'SOURCE_ID' => 'WEB',
                'UF_EXTERNAL_ID' => $contactId,
            ];

            if (!empty($this->payload['email'])) {
                $fields['EMAIL'] = [
                    ['VALUE' => $this->payload['email'], 'VALUE_TYPE' => 'WORK']
                ];
            }

            if (!empty($this->payload['phone'])) {
                $fields['PHONE'] = [
                    ['VALUE' => $this->payload['phone'], 'VALUE_TYPE' => 'WORK']
                ];
            }

            $bitrixContactId = $contact->Add($fields);

            if ($bitrixContactId) {
                AddMessage2Log(sprintf(
                    'Webhook: Создан контакт #%s (external: %s)',
                    $bitrixContactId,
                    $contactId
                ), 'acme.integration');

                return [
                    'status' => 'success',
                    'contact_id' => $bitrixContactId,
                ];
            }
        }

        return ['status' => 'failed'];
    }

    /**
     * Отправить HTTP-ответ
     *
     * @param int $statusCode HTTP статус
     * @param array $data Данные ответа
     */
    protected function sendResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->logger->logResponse($statusCode, json_encode($data));

        exit;
    }

    /**
     * Валидация подписи webhook (для безопасности)
     *
     * @param string $signature Подпись из заголовка
     * @param string $secret Секретный ключ
     * @return bool
     */
    protected function validateSignature(string $signature, string $secret): bool
    {
        $payload = file_get_contents('php://input');
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
