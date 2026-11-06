<?php
/**
 * HANDLER активити ERP_EXPORT_ORDER.
 * Быстро отвечает, ставит задачу в очередь и завершает приём через bizproc.event.send.
 * Реальный экспорт делает worker.php по cron — потом будит активити через bizproc.activity.completed.
 */

require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/queue.php';

$eventToken = $_REQUEST['event_token'] ?? null;
$documentId = $_REQUEST['document_id'] ?? null;
$properties = $_REQUEST['properties'] ?? [];

if (!$eventToken || !$documentId) {
    http_response_code(400);
    exit('bad request');
}

$dealId = (int) preg_replace('/.*_(\d+)$/', '$1', $documentId[2] ?? '');

// 1. Ставим в очередь — это быстро
queue_push([
    'type'         => 'export_order',
    'event_token'  => $eventToken,
    'deal_id'      => $dealId,
    'order_type'   => $properties['ORDER_TYPE'] ?? 'sale',
    'queued_at'    => date('c'),
]);

// 2. Подтверждаем приём — БП ждёт bizproc.activity.completed
CRest::call('bizproc.event.send', [
    'EVENT_TOKEN' => $eventToken,
    'LOG_MESSAGE' => "Заказ {$dealId} поставлен в очередь экспорта в ERP",
]);

// 3. Отвечаем Битриксу мгновенно
http_response_code(200);
echo '{"ok":true}';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
