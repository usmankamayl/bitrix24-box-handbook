<?php
// examples/module-5-3/order-events/lib/OrderEvents.php
// Обработчики жизненного цикла заказа.
// Принцип: тяжёлые операции (внешние API, почта, 1С) — НЕ внутри события,
// а через очередь на CAgent. Иначе сохранение заказа тормозит/падает.

namespace Acme\Sale;

use Bitrix\Main\Event;
use Bitrix\Sale\Order;

class OrderEvents
{
    /**
     * Заказ оплачен (все платежи PAYED=Y).
     * Вызывается из Payment::setPaid('Y') после save().
     *
     * Здесь: очередь на отправку в ERP, письмо, метрика.
     * САМО событие — только ставит задачу, реальную работу делает агент.
     */
    public static function onOrderPaid(Event $event): void
    {
        /** @var Order $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof Order) {
            return;
        }

        $orderId   = $order->getId();
        $accountNo = $order->getField('ACCOUNT_NUMBER');

        // 1. Ставим задачу в очередь — CAgent выполнит её асинхронно.
        //    Order::save() не ждёт внешний API.
        self::enqueueJob('exportToErp', [
            'order_id'    => $orderId,
            'account_num' => $accountNo,
            'event'       => 'paid',
        ]);

        // 2. Лёгкие локальные операции можно делать прямо здесь:
        //    - сменить свойство заказа
        //    - записать в HL-блок
        self::markOrderExported($orderId, 'queued');
    }

    /**
     * Смена статуса заказа (P → F, F → DE и т.д.).
     * Также ловит переход в 'P' — для совместимости проверяем оба события.
     */
    public static function onStatusChange(Event $event): void
    {
        /** @var Order $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof Order) {
            return;
        }

        $orderId = $order->getId();
        $status  = $order->getField('STATUS_ID');

        // Отправляем в ERP только "интересные" переходы.
        // Список бизнес-статусов — из настроек модуля, не хардкод.
        $notifyStatuses = \Bitrix\Main\Config\Option::get('acme.sale', 'erp_notify_statuses', 'P,F,DE');
        $notifyStatuses = array_filter(explode(',', $notifyStatuses));

        if (in_array($status, $notifyStatuses, true)) {
            self::enqueueJob('exportToErp', [
                'order_id' => $orderId,
                'status'   => $status,
                'event'    => 'status_change',
            ]);
        }
    }

    /**
     * Отмена заказа. Тоже уходит в ERP.
     */
    public static function onOrderCanceled(Event $event): void
    {
        /** @var Order $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof Order) {
            return;
        }

        self::enqueueJob('exportToErp', [
            'order_id' => $order->getId(),
            'event'    => 'canceled',
        ]);
    }

    /**
     * ДО сохранения заказа — здесь можно модифицировать объект
     * и бросить ошибку, чтобы откатить сохранение.
     *
     * Пример: проверка бизнес-правила, блокировка изменений
     * если заказ уже экспортирован в ERP.
     */
    public static function onBeforeSaved(Event $event): void
    {
        /** @var Order $order */
        $order = $event->getParameter('ENTITY');
        if (!$order instanceof Order) {
            return;
        }

        // Только для существующих заказов.
        if (!$order->getId()) {
            return;
        }

        // Если заказ экспортирован и статус меняется на "в работе" —
        // блокируем: ERP уже знает другую версию.
        $newStatus = $order->getField('STATUS_ID');
        $oldStatus = self::getCurrentStatus($order->getId());

        if ($oldStatus === 'DE' && $newStatus === 'P') {
            // Нельзя вернуть "выполненный" заказ обратно в "оплачен".
            // Result::addError() в событии Before → Bitrix откатит save().
            $result = $event->getParameter('RESULT');
            if ($result instanceof \Bitrix\Main\Result) {
                $result->addError(new \Bitrix\Main\Error(
                    'Заказ уже выполнен — нельзя вернуть в статус "Оплачен"'
                ));
            }
        }
    }

    /**
     * Ставим задачу в очередь — через CAgent.
     * Агент выполнится в фоне при следующем хите или по cron.
     *
     * ВАЖНО: параметры сериализуем простыми типами — агент хранит их в
     * b_agent и вызывает метод как строку. Объекты не переживают сериализацию.
     */
    private static function enqueueJob(string $action, array $params): void
    {
        $job = serialize(['action' => $action, 'params' => $params, 'retry_count' => 0]);

        \CAgent::AddAgent(
            '\\Acme\\Sale\\OrderEvents::processQueue("' . addslashes($job) . '");',
            'acme_sale',       // модуль — для группировки агентов
            'N',               // не периодический
            30,                // задержка, сек — отпускаем save() сразу
            '',                // дата следующего запуска
            'Y',               // активен
            '',                // период
            30                 // interval
        );
    }

    /**
     * Выполняется агентом — уже вне транзакции заказа.
     * Здесь можно долго ходить в ERP, слать письма, писать логи.
     *
     * Ограничение ретраев: max 5 попыток с exponential backoff.
     */
    public static function processQueue(string $jobData): string
    {
        $data = unserialize($jobData);
        if (!is_array($data)) {
            return ''; // пустой return — агент завершается
        }

        $orderId = (int) ($data['params']['order_id'] ?? 0);
        if (!$orderId) {
            return '';
        }

        $retryCount = (int) ($data['retry_count'] ?? 0);

        try {
            switch ($data['action']) {
                case 'exportToErp':
                    self::exportToErp($data['params']);
                    break;
            }
        } catch (\Throwable $e) {
            // Логируем — но не роняем агента (иначе задача "зависнет" в очереди).
            self::log('OrderEvents queue error (attempt ' . ($retryCount + 1) . '): ' . $e->getMessage(), $orderId);

            // Ограничение ретраев: max 5 попыток
            if ($retryCount >= 5) {
                self::log('OrderEvents max retries reached, dropping task', $orderId);
                return ''; // отказываемся от задачи
            }

            // Exponential backoff: 30s, 60s, 120s, 240s, 300s
            $data['retry_count'] = $retryCount + 1;
            $delay = min(300, 30 * pow(2, $retryCount)); // секунды

            return '\\Acme\\Sale\\OrderEvents::processQueue("' . addslashes(serialize($data)) . '");';
        }

        return ''; // успех — агент не повторяется
    }

    /**
     * Реальная отправка в ERP.
     * Здесь же идемпотентность через HL-блок с атомарной вставкой.
     *
     * HL-блок: OrderExportLog
     * Поля:
     * - UF_ORDER_ID (int, обязательное)
     * - UF_EVENT_KEY (string, обязательное)
     * - UF_TS (datetime)
     * UNIQUE KEY: (UF_ORDER_ID, UF_EVENT_KEY)
     */
    private static function exportToErp(array $params): void
    {
        $orderId = (int) $params['order_id'];
        $event   = $params['event'] ?? 'unknown';

        // 1. Идемпотентность: атомарная вставка в HL-блок.
        //    UNIQUE (UF_ORDER_ID, UF_EVENT_KEY) — дубли отклоняются на уровне БД.
        $dedupKey = "erp_{$orderId}_{$event}_" . ($params['status'] ?? '');

        if (!self::recordExportAttempt($orderId, $dedupKey)) {
            // Дубль — уже отправляли, выходим без ошибки
            self::log('Export skipped: duplicate event ' . $dedupKey, $orderId);
            return;
        }

        // 2. Загружаем заказ ПОЛНОСТЬЮ — события дали только Order объект,
        //    но нам нужны товары, адрес, покупатель.
        $order = Order::load($orderId);
        if (!$order) {
            throw new \RuntimeException("Order {$orderId} not found");
        }

        // 3. Формируем payload для ERP.
        $payload = self::buildErpPayload($order, $params);

        // 4. Отправляем.
        $http = new \Bitrix\Main\Web\HttpClient([
            'socketTimeout' => 30,  // уже в фоне — можно подольше
            'streamTimeout' => 30,
        ]);
        $response = $http->post(
            \Bitrix\Main\Config\Option::get('acme.sale', 'erp_url', '') . '/order',
            json_encode($payload),
            ['Content-Type' => 'application/json']
        );

        if ($http->getStatus() !== 200) {
            throw new \RuntimeException("ERP HTTP {$http->getStatus()}: {$response}");
        }

        // 5. Записываем ID экспорта в свойство заказа — полезно для
        //    отладки и двусторонней интеграции.
        self::markOrderExported($orderId, 'sent');
    }

    /**
     * Формируем payload — в реале это сложная маппинговая логика.
     * Здесь — минимальный пример.
     */
    private static function buildErpPayload(Order $order, array $params): array
    {
        $basket = $order->getBasket();
        $items  = [];

        foreach ($basket->getBasketItems() as $item) {
            $items[] = [
                'product_id' => $item->getProductId(),
                'name'       => $item->getField('NAME'),
                'price'      => $item->getPrice(),
                'quantity'   => $item->getQuantity(),
                'xml_id'     => $item->getField('PRODUCT_XML_ID'),
            ];
        }

        return [
            'order_id'    => $order->getId(),
            'account_num' => $order->getField('ACCOUNT_NUMBER'),
            'status'      => $order->getField('STATUS_ID'),
            'event'       => $params['event'],
            'event_status'=> $params['status'] ?? null,
            'price'       => $order->getPrice(),
            'currency'    => $order->getCurrency(),
            'items'       => $items,
            'created_at'  => $order->getField('DATE_INSERT')->toString(),
        ];
    }

    private static function markOrderExported(int $orderId, string $state): void
    {
        // В реале — запись в HL-блок `order_export_log` с (order_id, state, ts).
        // Для примера — просто лог.
        self::log("Export state: {$state}", $orderId);
    }

    private static function getCurrentStatus(int $orderId): ?string
    {
        // Текущий статус в базе — до изменения.
        $current = \Bitrix\Sale\Internals\OrderTable::getRow([
            'select' => ['STATUS_ID'],
            'filter' => ['=ID' => $orderId],
        ]);
        return $current['STATUS_ID'] ?? null;
    }

    private static function log(string $message, int $orderId = 0): void
    {
        // Лог — в /local/logs/order_events.log или HL-блок.
        // Не в Exception письмо админу — это спам.
        $file = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/order_events.log';
        $dir = dirname($file);

        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            error_log("Failed to create log directory: {$dir}");
            return;
        }

        @file_put_contents(
            $file,
            date('Y-m-d H:i:s') . " [order #{$orderId}] {$message}\n",
            FILE_APPEND
        );
    }

    /**
     * Атомарная запись попытки экспорта в HL-блок для идемпотентности.
     *
     * Предполагается, что HL-блок OrderExportLog создан с полями:
     * - UF_ORDER_ID (int)
     * - UF_EVENT_KEY (string)
     * - UF_TS (datetime)
     * И уникальным ключом (UF_ORDER_ID, UF_EVENT_KEY).
     *
     * Если HL-блок не создан, метод вернёт true (разрешит экспорт) и запишет ошибку в лог.
     *
     * @return bool true — первый вызов, false — дубль
     */
    private static function recordExportAttempt(int $orderId, string $eventKey): bool
    {
        try {
            // Получаем класс HL-блока по символьному коду
            $hlBlock = \Bitrix\Highloadblock\HighloadBlockTable::getList([
                'filter' => ['=NAME' => 'OrderExportLog'],
                'limit' => 1,
            ])->fetch();

            if (!$hlBlock) {
                // HL-блок не создан — разрешаем экспорт, логируем предупреждение
                self::log('HL OrderExportLog not found, idempotency disabled', $orderId);
                return true;
            }

            $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlBlock);
            $hlClass = $entity->getDataClass();

            // Пытаемся вставить запись — дубли отклонятся на уровне UNIQUE KEY
            $hlClass::add([
                'UF_ORDER_ID' => $orderId,
                'UF_EVENT_KEY' => $eventKey,
                'UF_TS' => new \Bitrix\Main\Type\DateTime(),
            ]);

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
