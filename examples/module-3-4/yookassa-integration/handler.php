<?php
/**
 * Обработчик платежной системы ЮKassa
 *
 * Реализует интеграцию с платежной системой ЮKassa через API v3
 */

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\Payment;

\Bitrix\Main\Loader::registerAutoLoadClasses(
    null,
    [
        'Sale\Handlers\PaySystem\YooKassaHandler' => __FILE__,
    ]
);

/**
 * Класс обработчика платежной системы ЮKassa
 */
class YooKassaHandler extends PaySystem\ServiceHandler
{
    /** @var string API endpoint ЮKassa */
    const API_URL = 'https://api.yookassa.ru/v3/payments';

    /**
     * Инициация платежа - показываем форму оплаты
     *
     * @param Payment $payment Объект платежа
     * @param Request|null $request HTTP запрос
     * @param int $type Тип возврата (STRING, REDIRECT, etc)
     * @return mixed
     */
    public function initiatePay(Payment $payment, Request $request = null, $type = PaySystem\ServiceHandler::STRING)
    {
        // Создаем платеж в ЮKassa
        $paymentData = $this->createPayment($payment);

        if (!$paymentData) {
            // Если не удалось создать платеж - показываем ошибку
            return $this->showError('Ошибка при создании платежа');
        }

        // Получаем URL для оплаты
        $confirmationUrl = $paymentData['confirmation']['confirmation_url'] ?? '';

        if (!$confirmationUrl) {
            return $this->showError('Не получен URL для оплаты');
        }

        // Сохраняем ID платежа ЮKassa в нашем платеже
        $payment->setField('PS_INVOICE_ID', $paymentData['id']);
        $payment->save();

        // Логируем создание платежа
        $this->log('Payment created', [
            'payment_id' => $payment->getId(),
            'yookassa_id' => $paymentData['id'],
            'amount' => $paymentData['amount']['value']
        ]);

        // Редирект на страницу оплаты
        if ($type === PaySystem\ServiceHandler::REDIRECT) {
            LocalRedirect($confirmationUrl);
        }

        // Возвращаем HTML с редиректом
        return $this->generateRedirectHtml($confirmationUrl);
    }

    /**
     * Создание платежа через API ЮKassa
     *
     * @param Payment $payment Объект платежа
     * @return array|null Данные созданного платежа или null при ошибке
     */
    protected function createPayment(Payment $payment)
    {
        $order = $payment->getOrder();
        $shopId = $this->getBusinessValue($payment, 'YOOKASSA_SHOP_ID');
        $secretKey = $this->getBusinessValue($payment, 'YOOKASSA_SECRET_KEY');
        $autoCapture = $this->getBusinessValue($payment, 'YOOKASSA_AUTO_CAPTURE') === 'Y';
        $paymentMethod = $this->getBusinessValue($payment, 'YOOKASSA_PAYMENT_METHOD');

        // Формируем данные для создания платежа
        $paymentData = [
            'amount' => [
                'value' => number_format($payment->getSum(), 2, '.', ''),
                'currency' => $payment->getField('CURRENCY')
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $this->getReturnUrl($payment)
            ],
            'capture' => $autoCapture,
            'description' => 'Оплата заказа №' . $order->getId(),
            'metadata' => [
                'order_id' => $order->getId(),
                'payment_id' => $payment->getId()
            ]
        ];

        // Если указан конкретный метод оплаты
        if ($paymentMethod) {
            $paymentData['payment_method_data'] = [
                'type' => $paymentMethod
            ];
        }

        // Генерируем ключ идемпотентности для безопасного повтора запроса
        $idempotenceKey = md5($payment->getId() . $payment->getSum() . time());

        // Выполняем запрос к API
        $httpClient = new HttpClient([
            'socketTimeout' => 30,
            'streamTimeout' => 30,
        ]);

        // Устанавливаем Basic Auth
        $httpClient->setAuthorization($shopId, $secretKey);

        // Устанавливаем заголовки
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Idempotence-Key', $idempotenceKey);

        // Отправляем запрос
        $response = $httpClient->post(
            self::API_URL,
            Json::encode($paymentData)
        );

        // Логируем запрос и ответ
        $this->log('API Request', [
            'url' => self::API_URL,
            'data' => $paymentData,
            'response' => $response
        ]);

        if (!$response) {
            $this->log('API Error', ['error' => $httpClient->getError()]);
            return null;
        }

        // Декодируем ответ
        try {
            $result = Json::decode($response);
        } catch (\Exception $e) {
            $this->log('JSON Decode Error', ['error' => $e->getMessage()]);
            return null;
        }

        // Проверяем наличие ошибки в ответе
        if (isset($result['type']) && $result['type'] === 'error') {
            $this->log('YooKassa Error', ['error' => $result]);
            return null;
        }

        return $result;
    }

    /**
     * Обработка уведомления от ЮKassa (webhook)
     *
     * @param Payment $payment Объект платежа
     * @param Request $request HTTP запрос
     * @return PaySystem\ServiceResult
     */
    public function processRequest(Payment $payment, Request $request)
    {
        $result = new PaySystem\ServiceResult();

        // Получаем тело запроса
        $requestBody = file_get_contents('php://input');

        // Логируем входящий запрос
        $this->log('Webhook received', [
            'payment_id' => $payment ? $payment->getId() : 'unknown',
            'body' => $requestBody
        ]);

        try {
            $data = Json::decode($requestBody);
        } catch (\Exception $e) {
            $this->log('Webhook JSON error', ['error' => $e->getMessage()]);
            return $result;
        }

        // Проверяем, что это уведомление об оплате
        if (!isset($data['event']) || $data['event'] !== 'payment.succeeded') {
            $this->log('Webhook event skipped', ['event' => $data['event'] ?? 'unknown']);
            return $result;
        }

        // Получаем объект платежа из уведомления
        $paymentObject = $data['object'] ?? [];

        if (empty($paymentObject)) {
            $this->log('Webhook error', ['error' => 'Empty payment object']);
            return $result;
        }

        // Проверяем статус платежа
        $status = $paymentObject['status'] ?? '';

        if ($status === 'succeeded') {
            // Проверяем, что сумма совпадает
            $amount = (float)($paymentObject['amount']['value'] ?? 0);
            $expectedAmount = (float)$payment->getSum();

            if (abs($amount - $expectedAmount) > 0.01) {
                $this->log('Amount mismatch', [
                    'expected' => $expectedAmount,
                    'received' => $amount
                ]);
                return $result;
            }

            // Устанавливаем платеж как оплаченный
            $setResult = $payment->setPaid('Y');

            if ($setResult->isSuccess()) {
                // Сохраняем дату оплаты
                $payment->setField('PS_RESPONSE_DATE', new DateTime());
                $payment->setField('PS_STATUS', 'succeeded');
                $payment->setField('PS_STATUS_CODE', $status);
                $payment->setField('PS_STATUS_DESCRIPTION', 'Платеж успешно завершен');

                $saveResult = $payment->save();

                if ($saveResult->isSuccess()) {
                    $this->log('Payment marked as paid', [
                        'payment_id' => $payment->getId(),
                        'yookassa_id' => $paymentObject['id']
                    ]);
                    $result->setOperationType(PaySystem\ServiceResult::MONEY_COMING);
                } else {
                    $this->log('Payment save error', [
                        'errors' => $saveResult->getErrorMessages()
                    ]);
                }
            } else {
                $this->log('Set paid error', [
                    'errors' => $setResult->getErrorMessages()
                ]);
            }
        } elseif ($status === 'canceled') {
            // Платеж отменен
            $payment->setField('PS_STATUS', 'canceled');
            $payment->setField('PS_STATUS_DESCRIPTION', 'Платеж отменен');
            $payment->save();

            $this->log('Payment canceled', [
                'payment_id' => $payment->getId(),
                'yookassa_id' => $paymentObject['id']
            ]);
        }

        return $result;
    }

    /**
     * Получение ID платежа из запроса
     *
     * @param Request $request HTTP запрос
     * @return int|null
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        // Читаем тело запроса
        $requestBody = file_get_contents('php://input');

        try {
            $data = Json::decode($requestBody);
        } catch (\Exception $e) {
            return null;
        }

        // Получаем payment_id из метаданных
        return (int)($data['object']['metadata']['payment_id'] ?? 0) ?: null;
    }

    /**
     * Возврат платежа (refund)
     *
     * @param Payment $payment Объект платежа
     * @param int|null $refundableSum Сумма возврата (null = полный возврат)
     * @return PaySystem\ServiceResult
     */
    public function refund(Payment $payment, $refundableSum = null)
    {
        $result = new PaySystem\ServiceResult();

        $shopId = $this->getBusinessValue($payment, 'YOOKASSA_SHOP_ID');
        $secretKey = $this->getBusinessValue($payment, 'YOOKASSA_SECRET_KEY');
        $yookassaPaymentId = $payment->getField('PS_INVOICE_ID');

        if (!$yookassaPaymentId) {
            $result->addError(new \Bitrix\Main\Error('Не найден ID платежа в ЮKassa'));
            return $result;
        }

        // Сумма возврата
        $refundSum = $refundableSum ?? $payment->getSum();

        // Формируем данные для возврата
        $refundData = [
            'payment_id' => $yookassaPaymentId,
            'amount' => [
                'value' => number_format($refundSum, 2, '.', ''),
                'currency' => $payment->getField('CURRENCY')
            ]
        ];

        // Генерируем ключ идемпотентности
        $idempotenceKey = md5($payment->getId() . $refundSum . time());

        // Выполняем запрос к API
        $httpClient = new HttpClient();
        $httpClient->setAuthorization($shopId, $secretKey);
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Idempotence-Key', $idempotenceKey);

        $response = $httpClient->post(
            self::API_URL . '/refunds',
            Json::encode($refundData)
        );

        // Логируем запрос и ответ
        $this->log('Refund request', [
            'payment_id' => $payment->getId(),
            'yookassa_id' => $yookassaPaymentId,
            'amount' => $refundSum,
            'response' => $response
        ]);

        if (!$response) {
            $result->addError(new \Bitrix\Main\Error('Ошибка при выполнении запроса: ' . $httpClient->getError()));
            return $result;
        }

        try {
            $refundResult = Json::decode($response);
        } catch (\Exception $e) {
            $result->addError(new \Bitrix\Main\Error('Ошибка декодирования ответа'));
            return $result;
        }

        if (isset($refundResult['type']) && $refundResult['type'] === 'error') {
            $result->addError(new \Bitrix\Main\Error($refundResult['description'] ?? 'Ошибка возврата'));
            return $result;
        }

        // Успешный возврат
        $result->setOperationType(PaySystem\ServiceResult::MONEY_LEAVING);
        $this->log('Refund completed', [
            'payment_id' => $payment->getId(),
            'refund_id' => $refundResult['id']
        ]);

        return $result;
    }

    /**
     * Проверка, поддерживает ли система возвраты
     *
     * @return bool
     */
    public function isRefundable()
    {
        return true;
    }

    /**
     * URL для возврата пользователя после оплаты
     *
     * @param Payment $payment Объект платежа
     * @return string
     */
    protected function getReturnUrl(Payment $payment)
    {
        $order = $payment->getOrder();

        return $this->getContext()->getServer()->getHttpHost() .
            '/personal/orders/detail/' . $order->getId() . '/?success=Y';
    }

    /**
     * Генерация HTML для редиректа на страницу оплаты
     *
     * @param string $url URL страницы оплаты
     * @return string
     */
    protected function generateRedirectHtml($url)
    {
        return '
            <html>
            <head>
                <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">
            </head>
            <body>
                <p>Переадресация на страницу оплаты...</p>
                <p><a href="' . htmlspecialchars($url) . '">Нажмите здесь, если автоматический переход не сработал</a></p>
            </body>
            </html>
        ';
    }

    /**
     * Отображение ошибки
     *
     * @param string $message Текст ошибки
     * @return string
     */
    protected function showError($message)
    {
        return '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>';
    }

    /**
     * Логирование событий
     *
     * @param string $title Заголовок записи
     * @param array $data Данные для логирования
     */
    protected function log($title, $data = [])
    {
        \Bitrix\Main\Diag\Debug::writeToFile(
            $data,
            $title,
            'yookassa_payment.log'
        );
    }

    /**
     * Получение URL для webhook
     *
     * @return string
     */
    public static function getWebhookUrl()
    {
        return \Bitrix\Main\Engine\UrlManager::getInstance()->create(
            'sale.paysystem.handler',
            ['handler' => 'yookassa']
        );
    }
}
