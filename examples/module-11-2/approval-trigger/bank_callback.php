<?php
/**
 * Callback от банковского API.
 * Банк дёргает этот URL, когда по заявке принято решение.
 * Мы находим сохранённый event_token и "будим" триггер.
 *
 * Пример вызова банком:
 *   POST bank_callback.php
 *   { "deal_id": 123, "decision": "approved", "sum": 500000, "comment": "OK" }
 */

require_once __DIR__ . '/crest.php';

$logFile = __DIR__ . '/trigger.log';
file_put_contents($logFile, date('c') . ' BANK ' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

$dealId   = (int)($_REQUEST['deal_id'] ?? 0);
$decision = $_REQUEST['decision'] ?? 'pending';
$sum      = (float)($_REQUEST['sum'] ?? 0);
$comment  = $_REQUEST['comment'] ?? '';

if (!$dealId) {
    http_response_code(400);
    exit('no deal_id');
}

// Достаём сохранённый event_token
$waiting = file_exists(__DIR__ . '/waiting.json')
    ? json_decode(file_get_contents(__DIR__ . '/waiting.json'), true)
    : [];

if (empty($waiting[$dealId])) {
    http_response_code(404);
    exit('no waiting trigger for deal ' . $dealId);
}

$eventToken = $waiting[$dealId]['event_token'];

// "Будим" триггер — передаём выходные параметры в RETURN_VALUES
$result = CRest::call('bizproc.event.send', [
    'EVENT_TOKEN'   => $eventToken,
    'RETURN_VALUES' => [
        'DECISION'     => $decision,
        'APPROVED_SUM' => $sum,
        'COMMENT'      => $comment,
    ],
    'LOG_MESSAGE'   => "Банк ответил: {$decision}, сумма {$sum}",
]);

// Удаляем из ожидания
unset($waiting[$dealId]);
file_put_contents(__DIR__ . '/waiting.json', json_encode($waiting, JSON_UNESCAPED_UNICODE));

http_response_code(200);
echo json_encode(['ok' => true, 'result' => $result]);
