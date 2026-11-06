<?php
// examples/module-5-3/payment-handler/lib/AcmePayHandler.php
// Кастомный платёжный обработчик (условный шлюз "AcmePay").
// Реализует: initiation → редирект на платёжную страницу,
//            webhook → capture по подписи,
//            refund → частичный возврат.

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Error;
use Bitrix\Main\Request;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem\BaseServiceHandler;
use Bitrix\Sale\PaySystem\ServiceResult;

class AcmePayHandler extends BaseServiceHandler
{
    private const API_URL        = 'https://api.acme.example/v1';
    private const PAYMENT_TIMEOUT = 10; // секунд — не блокируем оформление

    // Параметры, которые появятся в админке настройки ПС.
    // Хранятся в b_sale_pay_system_action.PARAMS и доступны
    // через $this->getBusinessValue($payment, 'ACME_API_KEY').
    public static function getHandlerParams(): array
    {
        return [
            'ACME_API_KEY'      => ['NAME' => 'API-ключ', 'TYPE' => 'STRING'],
            'ACME_SECRET'       => ['NAME' => 'Секрет для подписи webhook', 'TYPE' => 'STRING'],
            'ACME_SUCCESS_URL'  => ['NAME' => 'URL после успешной оплаты', 'TYPE' => 'STRING'],
            'ACME_FAIL_URL'     => ['NAME' => 'URL после неуспешной оплаты', 'TYPE' => 'STRING'],
        ];
    }

    /**
     * Шаг 1. Пользователь нажал "Оплатить" — создаём сессию у шлюза,
     * возвращаем url для редиректа.
     *
     * ВАЖНО: метод read-only по заказу — заказ ещё сохраняется,
     * изменение его полей здесь создаст race.
     */
    public function initiatePay(Payment $payment, Request $request = null): ServiceResult
    {
        $result = new ServiceResult();

        $order   = $payment->getOrder();
        $apiKey  = $this->getBusinessValue($payment, 'ACME_API_KEY');
        $amount  = (int) round($this->getPrice($payment) * 100); // в копейках
        $payId   = $payment->getId();
        $orderId = (int) ($order->getField('ACCOUNT_NUMBER') ?: $order->getId());

        $payload = [
            'order_id'     => $orderId,
            'payment_id'   => $payId,
            'amount'       => $amount,
            'currency'     => $payment->getField('CURRENCY') ?: 'RUB',
            'success_url'  => $this->getBusinessValue($payment, 'ACME_SUCCESS_URL'),
            'fail_url'     => $this->getBusinessValue($payment, 'ACME_FAIL_URL'),
            // URL, на который шлюз постучится после оплаты.
            // sale_ps_result.php роутит его в processRequest().
            'callback_url' => $this->getServiceUrl($payment, 'ps_result'),
        ];

        $http = new HttpClient(['socketTimeout' => self::PAYMENT_TIMEOUT]);
        $http->setHeader('Authorization', 'Bearer ' . $apiKey);
        $http->setHeader('Content-Type', 'application/json');

        try {
            $response = $http->post(self::API_URL . '/payments', json_encode($payload));
            $data = json_decode($response, true);
        } catch (\Throwable $e) {
            $result->addError(new Error('AcmePay API error: ' . $e->getMessage()));
            return $result;
        }

        if (empty($data['payment_url'])) {
            $result->addError(new Error('Empty payment_url from AcmePay'));
            return $result;
        }

        // Хендлер сам решает, куда отправить пользователя:
        // URL отдаём через setPsData — шаблон получит его из $payment->getParam('PAYMENT_URL').
        // setExtraParams() не существует в BaseServiceHandler.

        // Внутренний ID платежа на стороне шлюза — сохраняем для
        // рефандов и сверки.
        if (!empty($data['id'])) {
            $payment->setField('PS_INVOICE_ID', $data['id']);
            $payment->save();
        }

        $result->setPsData($data);
        return $result;
    }

    /**
     * Шаг 2. Webhook от шлюза.
     * Вызывается отдельным HTTP-запросом — НЕТ доступа к $_SESSION пользователя.
     * Должен быть идемпотентным: шлюз может ретраить.
     */
    public function processRequest(Payment $payment, Request $request): ServiceResult
    {
        $result = new ServiceResult();

        // 1. Проверяем подпись ДО любых действий.
        if (!$this->isSignatureValid($payment, $request)) {
            $result->addError(new Error('Invalid signature'));
            return $result; // вернём 200 + error — шлюз увидит ошибку и ретранит
        }

        $body = json_decode($request->getInput(), true) ?: [];
        $status = $body['status'] ?? '';
        $gatewayTxnId = $body['id'] ?? '';

        // 2. Идемпотентность через таблицу acme_payment_webhooks.
        //    UNIQUE (gateway_txn_id, event_type) — атомарная вставка до бизнес-логики.
        if (!self::recordWebhook($gatewayTxnId, $status, $payment->getId())) {
            // Дубль — уже обработали, вернём успех без действий.
            $result->setOperationType(ServiceResult::MONEY_COMING);
            return $result;
        }

        // 3. Уже оплачено — ничего не делаем (вторая линия защиты после таблицы).
        if ($payment->getField('PAYED') === 'Y') {
            $result->setOperationType(ServiceResult::MONEY_COMING);
            return $result;
        }

        if ($status === 'succeeded') {
            // 4. Помечаем платежку метаданными — ядро само вызовет setPaid('Y')
            //    на основе ServiceResult::MONEY_COMING.
            $payment->setField('PS_STATUS', 'succeeded');
            $payment->setField('PS_STATUS_MESSAGE', $gatewayTxnId);
            $payment->save();

            $result->setOperationType(ServiceResult::MONEY_COMING);
        } elseif ($status === 'canceled') {
            // Пользователь отменил — не оплачиваем, но и не ругаемся:
            // вернём штатный ответ, чтобы шлюз не ретраил.
            $result->setOperationType(ServiceResult::MONEY_LEAVING);
        } else {
            $result->addError(new Error('Unknown status: ' . $status));
        }

        return $result;
    }

    /**
     * Идёт по тело webhook'а — извлекаем PAYMENT_ID.
     * По нему sale_ps_result.php найдёт нашу Payment.
     * ВАЖНО: это ID платежа, не заказа — у заказа может быть много платежей.
     */
    public function getPaymentIdFromRequest(Request $request): ?int
    {
        $body = json_decode($request->getInput(), true) ?: [];
        return isset($body['payment_id']) ? (int) $body['payment_id'] : null;
    }

    /**
     * Частичный возврат — админ жмёт "Возврат" в заказе.
     */
    public function refund(Payment $payment, $refundableSum): ServiceResult
    {
        $result = new ServiceResult();

        $invoiceId = $payment->getField('PS_INVOICE_ID');
        if (!$invoiceId) {
            $result->addError(new Error('No gateway invoice id'));
            return $result;
        }

        $http = new HttpClient();
        $http->setHeader('Authorization', 'Bearer ' . $this->getBusinessValue($payment, 'ACME_API_KEY'));
        $http->setHeader('Content-Type', 'application/json');

        try {
            $response = $http->post(
                self::API_URL . '/payments/' . urlencode($invoiceId) . '/refund',
                json_encode(['amount' => (int) round($refundableSum * 100)])
            );
            $data = json_decode($response, true);
        } catch (\Throwable $e) {
            $result->addError(new Error($e->getMessage()));
            return $result;
        }

        if (!empty($data['status']) && $data['status'] === 'refunded') {
            $result->setOperationType(ServiceResult::MONEY_LEAVING);
            $payment->setField('PS_STATUS_MESSAGE', 'refunded ' . $data['id']);
            $payment->save();
        } else {
            $result->addError(new Error('Refund rejected'));
        }

        return $result;
    }

    /**
     * Проверка подписи webhook'а.
     * Схема шлюза: X-Acme-Signature = HMAC_SHA256(rawBody, secret).
     */
    private function isSignatureValid(Payment $payment, Request $request): bool
    {
        $secret   = $this->getBusinessValue($payment, 'ACME_SECRET');
        $received = $request->getHeader('X-Acme-Signature');
        if (!$received || !$secret) {
            return false;
        }
        $raw = $request->getInput();
        $expected = hash_hmac('sha256', $raw, $secret);
        return hash_equals($expected, $received);
    }

    /**
     * Шлюз может спросить "оплачено ли?" — например, при сверке.
     */
    public function isPayed(Payment $payment): bool
    {
        return $payment->getField('PAYED') === 'Y';
    }

    /**
     * Сумма в мин. единицах — реализация зависит от PS.
     */
    public function getPrice(Payment $payment): float
    {
        return (float) $payment->getField('SUM');
    }

    /**
     * Атомарная запись webhook'а в таблицу для идемпотентности.
     * UNIQUE (gateway_txn_id, event_type) — дубли отклоняются на уровне БД.
     *
     * Таблица acme_payment_webhooks:
     * CREATE TABLE acme_payment_webhooks (
     *   id INT AUTO_INCREMENT PRIMARY KEY,
     *   gateway_txn_id VARCHAR(255) NOT NULL,
     *   event_type VARCHAR(50) NOT NULL,
     *   payment_id INT NOT NULL,
     *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     *   UNIQUE KEY (gateway_txn_id, event_type)
     * );
     *
     * @return bool true — первый вызов, false — дубль
     */
    private static function recordWebhook(string $gatewayTxnId, string $eventType, int $paymentId): bool
    {
        if (!$gatewayTxnId) {
            return true; // нет ID транзакции — разрешаем (не блокируем обработку)
        }

        $conn = \Bitrix\Main\Application::getConnection();
        try {
            $conn->query("
                INSERT INTO acme_payment_webhooks (gateway_txn_id, event_type, payment_id, created_at)
                VALUES ('" . $conn->getSqlHelper()->forSql($gatewayTxnId) . "',
                        '" . $conn->getSqlHelper()->forSql($eventType) . "',
                        " . (int) $paymentId . ",
                        NOW())
            ");
            return true; // успешно вставили — первый вызов
        } catch (\Bitrix\Main\DB\SqlException $e) {
            // Ошибка дубликата ключа — это нормально, второй вызов
            if (stripos($e->getMessage(), 'Duplicate entry') !== false) {
                return false;
            }
            // Другая ошибка БД — прокидываем выше
            throw $e;
        }
    }
}
