<?php
/**
 * HANDLER триггера — вызывается Битриксом, когда БП дошёл до триггера.
 * Здесь мы НЕ "срабатываем" — мы сохраняем event_token, чтобы потом
 * внешняя система могла разбудить триггер через bizproc.event.send.
 */

require_once __DIR__ . '/crest.php';

$logFile = __DIR__ . '/trigger.log';
file_put_contents($logFile, date('c') . ' WAIT ' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

$eventToken = $_REQUEST['event_token'] ?? null;
$documentId = $_REQUEST['document_id'] ?? null; // ['crm','CCrmDocumentDeal','DEAL_123']
$workflowId = $_REQUEST['workflow_id'] ?? null;

if (!$eventToken || !$documentId) {
    http_response_code(400);
    exit('bad request');
}

// Парсим DEAL_123 → 123
$dealId = (int) preg_replace('/.*_(\d+)$/', '$1', $documentId[2] ?? '');

// Сохраняем ожидание в БД (здесь — в файл для примера)
$waiting = file_exists(__DIR__ . '/waiting.json')
    ? json_decode(file_get_contents(__DIR__ . '/waiting.json'), true)
    : [];
$waiting[$dealId] = [
    'event_token' => $eventToken,
    'workflow_id' => $workflowId,
    'document'    => $documentId,
    'created_at'  => date('c'),
];
file_put_contents(__DIR__ . '/waiting.json', json_encode($waiting, JSON_UNESCAPED_UNICODE));

// Отправляем заявку в банк (условно)
// sendToBank($dealId, ...);

http_response_code(200);
echo '{"ok":true}';
