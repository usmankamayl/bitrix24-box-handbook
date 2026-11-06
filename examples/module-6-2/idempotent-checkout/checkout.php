<?php
/**
 * Кейс 6.2.2 — «Дубли заказов при двойном клике / ретрае платежа»
 *
 * Проблемы:
 * 1. Клиент жмёт «Оформить» дважды → два заказа.
 * 2. Платёжная система шлёт webhook дважды → двойное списание/письма.
 * 3. Retry на фронте после таймаута → дубль.
 *
 * Решение — идемпотентность на трёх уровнях:
 * - REQUEST_ID с фронта (первый уровень защиты)
 * - Уникальный индекс в БД (второй — страховка)
 * - Проверка isPayed() в webhook (третий — для платежей)
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Sale\Order;
use Bitrix\Main\Type\DateTime;

Loader::includeModule('sale');

// ============================================================================
// 1. Таблица для хранения идемпотентных ключей
// ============================================================================
//
// CREATE TABLE acme_idempotency (
//   REQUEST_ID VARCHAR(64) NOT NULL PRIMARY KEY,
//   ORDER_ID   INT NULL,
//   STATUS     ENUM('processing','done','failed') DEFAULT 'processing',
//   CREATED_AT DATETIME NOT NULL,
//   UNIQUE KEY idx_order (ORDER_ID)
// ) ENGINE=InnoDB;
//
// Зачем таблица, а не Option/кеш:
// - Кластерная БД — консистентно на любой ноде.
// - Переживает перезапуск (кеш — нет).
// - Можно смотреть статус "processing" — пока идёт создание заказа.

final class IdempotencyStore
{
    /**
     * Пытаемся зарегистрировать ключ.
     *
     * @return array|null ['order_id'=>..,'status'=>..] если ключ уже был, иначе null
     */
    public static function tryAcquire(string $requestId): ?array
    {
        $conn = \Bitrix\Main\Application::getConnection();

        // INSERT IGNORE — атомарно: если ключ есть — вернётся 0 affected rows
        $conn->queryExecute(
            "INSERT IGNORE INTO acme_idempotency (REQUEST_ID, STATUS, CREATED_AT)
             VALUES (?, 'processing', NOW())",
            [$requestId]
        );

        if ($conn->getAffectedRowsCount() === 0) {
            // Ключ уже существует — возвращаем текущий статус
            $row = $conn->query(
                "SELECT ORDER_ID, STATUS FROM acme_idempotency WHERE REQUEST_ID = ?",
                [$requestId]
            )->fetch();
            return $row ?: ['status' => 'unknown'];
        }

        return null; // ключ наш — можем создавать заказ
    }

    public static function markDone(string $requestId, int $orderId): void
    {
        \Bitrix\Main\Application::getConnection()->queryExecute(
            "UPDATE acme_idempotency SET ORDER_ID = ?, STATUS = 'done'
             WHERE REQUEST_ID = ?",
            [$orderId, $requestId]
        );
    }

    public static function markFailed(string $requestId): void
    {
        \Bitrix\Main\Application::getConnection()->queryExecute(
            "UPDATE acme_idempotency SET STATUS = 'failed'
             WHERE REQUEST_ID = ? AND STATUS = 'processing'",
            [$requestId]
        );
    }
}

// ============================================================================
// 2. Идемпотентное создание заказа
// ============================================================================

final class CheckoutService
{
    /**
     * Создаёт заказ идемпотентно.
     *
     * @param string $requestId UUID v4 с фронта (генерируется при открытии формы)
     * @param int    $userId
     * @param array  $orderData ['person_type_id'=>..,'delivery_id'=>..,'pay_system_id'=>..,'props'=>[..]]
     */
    public function createOrder(string $requestId, int $userId, array $orderData): array
    {
        // Проверяем ключ — может, этот запрос уже обработан?
        $existing = IdempotencyStore::tryAcquire($requestId);

        if ($existing !== null) {
            // Ключ был — либо заказ создался, либо ещё обрабатывается
            if ($existing['STATUS'] === 'done' && $existing['ORDER_ID']) {
                return [
                    'order_id' => (int)$existing['ORDER_ID'],
                    'duplicate' => true, // фронт может показать "заказ уже создан"
                ];
            }
            if ($existing['STATUS'] === 'processing') {
                // Параллельный запрос с тем же ключом ещё работает.
                // Вариант A: вернуть 202 + "повторите через секунду"
                // Вариант B: подождать и проверить снова
                usleep(300_000); // 300ms
                $check = \Bitrix\Main\Application::getConnection()
                    ->query("SELECT ORDER_ID, STATUS FROM acme_idempotency WHERE REQUEST_ID = ?",
                            [$requestId])->fetch();
                if ($check['STATUS'] === 'done') {
                    return [
                        'order_id' => (int)$check['ORDER_ID'],
                        'duplicate' => true,
                    ];
                }
                throw new \RuntimeException('order creation in progress');
            }
        }

        // Ключ наш — создаём заказ
        try {
            $orderId = $this->buildOrder($userId, $orderData);
            IdempotencyStore::markDone($requestId, $orderId);
            return ['order_id' => $orderId, 'duplicate' => false];
        } catch (\Throwable $e) {
            IdempotencyStore::markFailed($requestId);
            throw $e;
        }
    }

    private function buildOrder(int $userId, array $data): int
    {
        $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
            \Bitrix\Sale\Fuser::getId(),
            SITE_ID
        );

        $order = Order::create(SITE_ID, $userId, \Bitrix\Main\Context::getCurrent()->getLanguage());
        $order->setPersonTypeId($data['person_type_id']);
        $order->setBasket($basket);

        // Свойства
        $props = $order->getPropertyCollection();
        foreach ($data['props'] as $code => $value) {
            if ($item = $props->getItemByOrderPropertyCode($code)) {
                $item->setValue($value);
            }
        }

        // Отгрузка
        $shipments = $order->getShipmentCollection();
        $shipment = $shipments->createItem(
            \Bitrix\Sale\Delivery\Services\Manager::getObjectById($data['delivery_id'])
        );

        // Оплата
        $payments = $order->getPaymentCollection();
        $payment = $payments->createItem(
            \Bitrix\Sale\PaySystem\Manager::getObjectById($data['pay_system_id'])
        );
        $payment->setField('SUM', $order->getPrice());

        $result = $order->save();
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
        return (int)$order->getId();
    }
}

// ============================================================================
// 3. Идемпотентный webhook — платёжная система шлёт уведомление дважды
// ============================================================================
//
// Senior-пойнт: webhook должен быть IDEMPOTENT BY DESIGN.
// 200 OK даже если уже обработано — иначе ПС будет ретраить до таймаута.

final class WebhookHandler
{
    public function handle(array $post): void
    {
        // 1. Подпись — до любых действий
        if (!$this->verifySignature($post)) {
            http_response_code(403);
            die('bad signature');
        }

        $invoiceId = $post['invoice_id']; // = PS_INVOICE_ID
        $paymentStatus = $post['status']; // 'succeeded' | 'canceled'

        // 2. Загружаем payment по invoice_id
        $payment = \Bitrix\Sale\Payment::getList([
            'select' => ['ID', 'ORDER_ID', 'PAYED'],
            'filter' => ['PS_INVOICE_ID' => $invoiceId],
        ])->fetchObject();

        if (!$payment) {
            // Платёж не найден — но webhook должен ответить 200,
            // иначе ПС будет ретраить бесконечно.
            http_response_code(200);
            die('payment not found'); // логируем, но 200
        }

        // 3. ИДЕМПОТЕНТНОСТЬ — уже оплачен? Возвращаем 200, ничего не делаем.
        if ($payment->isPaid()) {
            http_response_code(200);
            die('already paid'); // это НОРМАЛЬНЫЙ ответ, не ошибка
        }

        if ($paymentStatus === 'succeeded') {
            // 4. Атомарно меняем статус — внутри транзакции
            $order = Order::load($payment->getOrderId());
            $order->getPaymentCollection()
                ->getItemById($payment->getId())
                ->setPaid('Y');
            $result = $order->save();

            if ($result->isSuccess()) {
                http_response_code(200);
                echo 'ok';
            } else {
                http_response_code(500); // ПС ретраит — и это ОК
                echo 'save failed';
            }
        }
    }

    private function verifySignature(array $post): bool
    {
        // HMAC-SHA256 по сырому телу запроса — НЕ по распарсенным полям
        $raw = file_get_contents('php://input');
        $secret = Option::get('acme_pay', 'webhook_secret', '');
        $expected = hash_hmac('sha256', $raw, $secret);
        return hash_equals($expected, $post['signature'] ?? '');
    }
}
