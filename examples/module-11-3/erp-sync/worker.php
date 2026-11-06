<?php
/**
 * Worker — обрабатывает очередь. Запускается по cron каждую минуту:
 *   * * * * * php /path/to/worker.php
 *
 * Для каждой задачи:
 *   1) забирает данные сделки из Bitrix24
 *   2) отправляет в ERP API (upsert по маппингу)
 *   3) завершает активити через bizproc.activity.completed
 */

require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/queue.php';

const ERP_API_URL = 'https://erp.example.com/api';
const ERP_API_KEY = 'secret-erp-key';

function erp_request(string $method, string $path, array $body = []): array
{
    $ch = curl_init(ERP_API_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . ERP_API_KEY,
            'Content-Type: application/json',
            'Idempotency-Key: ' . ($body['external_id'] ?? uniqid()),
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $r  = curl_exec($ch);
    $e  = curl_error($ch);
    $rc = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $rc, 'body' => json_decode($r, true), 'error' => $e];
}

foreach (queue_fetch_all() as $job) {
    $data       = $job['data'];
    $dealId     = (int)$data['deal_id'];
    $eventToken = $data['event_token'];

    // 1. Получаем сделку из Bitrix
    $deal = CRest::call('crm.deal.get', ['ID' => $dealId]);
    if (empty($deal['result'])) {
        CRest::call('bizproc.activity.completed', [
            'EVENT_TOKEN' => $eventToken,
            'OUTPUT'      => ['ERP_ORDER_ID' => '', 'STATUS' => 'deal_not_found'],
            'LOG_MESSAGE' => "Сделка {$dealId} не найдена",
        ]);
        queue_remove($job['file']);
        continue;
    }
    $d = $deal['result'];

    // 2. Upsert в ERP — по маппингу решаем create/update
    $externalId = "b24_deal_{$dealId}";
    $existingErpId = mapping_get($dealId);

    $payload = [
        'external_id' => $externalId,
        'title'       => $d['TITLE'] ?? '',
        'amount'      => (float)($d['OPPORTUNITY'] ?? 0),
        'currency'    => $d['CURRENCY_ID'] ?? 'RUB',
        'customer_id' => (int)($d['CONTACT_ID'] ?? 0),
        'order_type'  => $data['order_type'],
    ];

    if ($existingErpId) {
        $resp = erp_request('PUT', "/orders/{$existingErpId}", $payload);
    } else {
        $resp = erp_request('POST', '/orders', $payload);
    }

    // 3. Завершаем активити
    if ($resp['status'] === 200 && !empty($resp['body']['id'])) {
        $erpId = (string)$resp['body']['id'];
        mapping_set($dealId, $erpId, 'bitrix');

        CRest::call('bizproc.activity.completed', [
            'EVENT_TOKEN' => $eventToken,
            'OUTPUT'      => ['ERP_ORDER_ID' => $erpId, 'STATUS' => 'exported'],
            'LOG_MESSAGE' => "Заказ {$dealId} экспортирован в ERP, id={$erpId}",
        ]);
    } else {
        CRest::call('bizproc.activity.completed', [
            'EVENT_TOKEN' => $eventToken,
            'OUTPUT'      => ['ERP_ORDER_ID' => '', 'STATUS' => 'error'],
            'LOG_MESSAGE' => "Ошибка ERP: HTTP {$resp['status']} {$resp['error']}",
        ]);
        // В продакшене: повторная попытка / dead-letter
    }

    queue_remove($job['file']);
}
