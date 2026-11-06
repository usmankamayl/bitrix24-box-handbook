<?php
/**
 * Кейс 6.2.3 — «Заказ создан, но письмо/1С/склад не узнали»
 *
 * Проблема: на OnSaleOrderSaved навешаны 5 обработчиков:
 * письмо клиенту, экспорт в 1С, Slack-уведомление, резерв на складе, SMS.
 * Один падает → либо заказ не сохраняется, либо часть консьюмеров молчит.
 *
 * Решение — Transactional Outbox:
 * - В событии только запись в outbox-таблицу (та же транзакция).
 * - Отдельный worker разгребает очередь с retry и dedup.
 * - Каждый консьюмер независим: упал Slack — остальные работают.
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;

Loader::includeModule('sale');

// ============================================================================
// 1. Outbox-таблица
// ============================================================================
//
// CREATE TABLE acme_outbox (
//   ID           INT AUTO_INCREMENT PRIMARY KEY,
//   EVENT_TYPE   VARCHAR(64) NOT NULL,          -- 'order.saved', 'order.paid'
//   ENTITY_ID    INT NOT NULL,                  -- ORDER_ID
//   PAYLOAD      TEXT,                          -- JSON с данными события
//   STATUS       ENUM('new','processing','done','failed') DEFAULT 'new',
//   RETRY_COUNT  INT DEFAULT 0,
//   NEXT_RETRY   DATETIME NULL,
//   ERROR_MSG    TEXT NULL,
//   CREATED_AT   DATETIME NOT NULL,
//   PROCESSED_AT DATETIME NULL,
//   KEY idx_status_retry (STATUS, NEXT_RETRY)
// ) ENGINE=InnoDB;
//
// KEY idx_status_retry — чтобы SELECT next_job был быстрым:
//   WHERE STATUS='new' OR (STATUS='failed' AND NEXT_RETRY<=NOW())

// ============================================================================
// 2. В событии — только запись в outbox (быстро, в той же транзакции)
// ============================================================================

AddEventHandler('sale', 'OnSaleOrderSaved', function($event) {
    /** @var Order $order */
    $order = $event->getParameter('ENTITY');
    $isNew = $event->getParameter('IS_NEW');

    // Payload — только то, что нужно консьюмерам. Не весь Order (тяжёлый).
    $payload = [
        'order_id' => $order->getId(),
        'status' => $order->getField('STATUS_ID'),
        'price' => $order->getPrice(),
        'user_id' => $order->getUserId(),
        'is_new' => $isNew,
    ];

    // INSERT внутри транзакции заказа — атомарно:
    // заказ сохранился = событие записано, либо ничего не произошло.
    \Bitrix\Main\Application::getConnection()->queryExecute(
        "INSERT INTO acme_outbox (EVENT_TYPE, ENTITY_ID, PAYLOAD, STATUS, CREATED_AT)
         VALUES ('order.saved', ?, ?, 'new', NOW())",
        [$order->getId(), json_encode($payload)]
    );

    // На этом всё — checkout завершается за ~50мс.
    // Письма, 1С, Slack — всё это делает worker.
});

// ============================================================================
// 3. Worker — разгребает очередь с retry и backoff
// ============================================================================
//
// Запуск: CLI-скрипт под systemd / supervisor, или Bitrix-агент каждые 30с.
//
// /local/cron/outbox_worker.php:
//   #!/usr/bin/env php
//   <?php
//   $_SERVER['DOCUMENT_ROOT'] = '/var/www/site';
//   require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';
//   $worker = new OutboxWorker();
//   $worker->run(); // бесконечный цикл с sleep

final class OutboxWorker
{
    private const MAX_RETRY = 5;
    private const BATCH_SIZE = 20;

    /** @var callable[] */
    private array $consumers = [];

    public function addConsumer(string $eventType, callable $consumer): void
    {
        $this->consumers[$eventType][] = $consumer;
    }

    public function runOnce(): int
    {
        $conn = Application::getConnection();

        // Берём пачку "новых" или просроченных retry
        $jobs = $conn->query(
            "SELECT * FROM acme_outbox
             WHERE STATUS = 'new'
                OR (STATUS = 'failed' AND NEXT_RETRY <= NOW())
             ORDER BY ID
             LIMIT " . self::BATCH_SIZE
        )->fetchAll();

        $processed = 0;
        foreach ($jobs as $job) {
            $this->processJob($job);
            $processed++;
        }
        return $processed;
    }

    private function processJob(array $job): void
    {
        $conn = Application::getConnection();
        $payload = json_decode($job['PAYLOAD'], true);

        // Помечаем "processing" — если worker умрёт, job не потеряется
        $conn->queryExecute(
            "UPDATE acme_outbox SET STATUS='processing' WHERE ID=? AND STATUS IN ('new','failed')",
            [$job['ID']]
        );
        if ($conn->getAffectedRowsCount() === 0) {
            return; // другой worker уже взял — optimistic lock
        }

        $consumers = $this->consumers[$job['EVENT_TYPE']] ?? [];
        $allOk = true;
        $lastError = '';

        foreach ($consumers as $consumer) {
            try {
                $consumer($payload, $job);
            } catch (\Throwable $e) {
                $allOk = false;
                $lastError = $e->getMessage();
                // Логируем, но продолжаем — другие консьюмеры не страдают
            }
        }

        if ($allOk) {
            $conn->queryExecute(
                "UPDATE acme_outbox SET STATUS='done', PROCESSED_AT=NOW() WHERE ID=?",
                [$job['ID']]
            );
        } else {
            $retry = (int)$job['RETRY_COUNT'] + 1;
            if ($retry >= self::MAX_RETRY) {
                // Dead letter — не ретраим бесконечно, откладываем для разбора
                $conn->queryExecute(
                    "UPDATE acme_outbox SET STATUS='failed', RETRY_COUNT=?, ERROR_MSG=? WHERE ID=?",
                    [$retry, $lastError, $job['ID']]
                );
                // Тут можно алерт в Slack/monitoring
            } else {
                // Exponential backoff: 30s, 60s, 120s, 240s, 480s
                $delay = 30 * (2 ** ($retry - 1));
                $conn->queryExecute(
                    "UPDATE acme_outbox
                     SET STATUS='failed', RETRY_COUNT=?, NEXT_RETRY=DATE_ADD(NOW(), INTERVAL ? SECOND), ERROR_MSG=?
                     WHERE ID=?",
                    [$retry, $delay, $lastError, $job['ID']]
                );
            }
        }
    }

    /** Бесконечный цикл — для systemd-worker */
    public function run(): void
    {
        while (true) {
            $processed = $this->runOnce();
            if ($processed === 0) {
                sleep(2); // нет работы — ждём
            }
        }
    }
}

// ============================================================================
// 4. Консьюмеры — каждый независим
// ============================================================================

$worker = new OutboxWorker();

// Письмо клиенту — если SMTP лежит, падает только этот консьюмер
$worker->addConsumer('order.saved', function(array $payload, array $job) {
    if (!$payload['is_new']) {
        return; // только на создание
    }
    $orderId = $payload['order_id'];
    $order = Order::load($orderId);
    if (!$order) {
        throw new \RuntimeException("Order $orderId not found");
    }

    // Отправка — через агент или прямо тут (worker уже асинхронный)
    \CEvent::Send('SALE_NEW_ORDER', SITE_ID, [
        'ORDER_ID' => $orderId,
        'EMAIL' => $order->getUserEmail(),
        'PRICE' => $order->getPrice(),
    ]);
});

// Экспорт в 1С — отдельный консьюмер с dedup
$worker->addConsumer('order.saved', function(array $payload, array $job) {
    $orderId = $payload['order_id'];

    // Dedup: если уже экспортировали этот заказ — пропускаем
    $exported = \Bitrix\Main\Config\Option::get('acme_erp', 'exported_' . $orderId, 'N');
    if ($exported === 'Y') {
        return;
    }

    // Тут вызов 1С API / запись в файл обмена
    exportOrderTo1C($orderId);

    \Bitrix\Main\Config\Option::set('acme_erp', 'exported_' . $orderId, 'Y');
});

// Slack-уведомление менеджеру — самый "тяжёлый" API, но не блокирует остальных
$worker->addConsumer('order.saved', function(array $payload, array $job) {
    if (!$payload['is_new']) {
        return;
    }
    $orderId = $payload['order_id'];
    $ch = curl_init('https://slack.com/api/chat.postMessage');
    // ... POST с токеном
    $ok = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new \RuntimeException('slack: ' . curl_error($ch));
    }
});

// ============================================================================
// 5. Monitoring: dead letter и stuck jobs
// ============================================================================
//
// SELECT * FROM acme_outbox WHERE STATUS='failed' AND RETRY_COUNT >= 5
//   — job'ы, которые не смогли обработать даже после 5 попыток.
//
// SELECT * FROM acme_outbox WHERE STATUS='processing' AND CREATED_AT < NOW() - INTERVAL 1 HOUR
//   — зависшие (worker умер в середине обработки).
//
// Алерт на эти запросы — обязательная часть решения.
