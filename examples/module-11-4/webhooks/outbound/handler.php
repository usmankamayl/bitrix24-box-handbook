<?php
/**
 * ИСХОДЯЩИЙ webhook (обработчик события onCrmDealAdd).
 * Bitrix24 шлёт сюда POST при создании сделки.
 *
 * Привязка события выполняется однократно:
 *   event.bind(event=onCrmDealAdd, handler=https://.../handler.php, auth=my-secret-token)
 */

const EXPECTED_TOKEN   = 'my-secret-token';
const INBOUND_WEBHOOK  = 'https://your-domain.bitrix24.ru/rest/1/XXXXXXXXXXXX/';

// ---- 1. Проверка подлинности: application_token ----
$token = $_REQUEST['auth']['application_token'] ?? '';
if ($token !== EXPECTED_TOKEN) {
    http_response_code(403);
    exit('bad token');
}

// ---- 2. Разбор события ----
$event   = $_REQUEST['event'] ?? '';
$fields  = $_REQUEST['data']['FIELDS'] ?? [];
$dealId  = (int)($fields['ID'] ?? 0);

if ($event !== 'onCrmDealAdd' || !$dealId) {
    http_response_code(200); // отвечаем OK, чтобы Bitrix не ретраил
    exit('skip');
}

// ---- 3. Быстро отвечаем — дальше работаем в фоне ----
http_response_code(200);
echo '{"ok":true}';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ---- 4. Логирование (идемпотентность: пропускаем повторы по ID+ts) ----
$logFile = __DIR__ . '/events.log';
$ts      = $_REQUEST['ts'] ?? '';
$dedupKey = "{$event}:{$dealId}:{$ts}";
$seen = file_exists($logFile) ? file_get_contents($logFile) : '';
if (strpos($seen, $dedupKey) !== false) {
    exit('duplicate');
}
file_put_contents($logFile, date('c') . " {$dedupKey}\n", FILE_APPEND);

// ---- 5. Получаем данные сделки и ставим задачу менеджеру ----
function bx_call(string $method, array $params): array
{
    $ch = curl_init(INBOUND_WEBHOOK . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true) ?: [];
}

$deal = bx_call('crm.deal.get', ['ID' => $dealId])['result'] ?? [];

bx_call('tasks.task.add', [
    'fields' => [
        'TITLE'       => 'Новая сделка: ' . ($deal['TITLE'] ?? "#{$dealId}"),
        'DESCRIPTION' => "Сумма: {$deal['OPPORTUNITY']} {$deal['CURRENCY_ID']}\n"
                       . "Ответственный: {$deal['ASSIGNED_BY_ID']}",
        'RESPONSIBLE_ID' => (int)($deal['ASSIGNED_BY_ID'] ?? 1),
        'UF_CRM_TASK'    => ["D_{$dealId}"], // привязка к сделке
    ],
]);
