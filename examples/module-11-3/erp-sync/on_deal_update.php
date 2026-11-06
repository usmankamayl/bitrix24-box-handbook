<?php
/**
 * Обработчик события onCrmDealUpdate.
 * Показывает:
 *  - проверку application_token (защита от подделки)
 *  - loop-prevention: пропускаем события, порождённые нашей же синхронизацией
 *  - реакцию на внешние изменения (здесь — лог)
 */

require_once __DIR__ . '/crest.php';
require_once __DIR__ . '/queue.php';

// Ожидаемый токен (задаём при event.bind; для входящего вебхука — из настроек)
const EXPECTED_TOKEN = 'my-secret-token';

$event = $_REQUEST['event'] ?? '';
$data  = $_REQUEST['data'] ?? [];
$auth  = $_REQUEST['auth'] ?? [];

// 1. Проверка подлинности
if (($auth['application_token'] ?? '') !== EXPECTED_TOKEN) {
    http_response_code(403);
    exit('bad token');
}

if ($event !== 'onCrmDealUpdate') {
    http_response_code(200);
    exit('skip');
}

$dealId = (int)($data['FIELDS']['ID'] ?? 0);
if (!$dealId) {
    http_response_code(400);
    exit('no id');
}

// 2. Loop-prevention: если последняя синхронизация шла от нас — пропускаем
$map = file_exists(MAPPING_DB) ? json_decode(file_get_contents(MAPPING_DB), true) : [];
$last = $map[$dealId] ?? null;
if ($last && $last['sync_source'] === 'bitrix' && strtotime($last['updated_at']) > time() - 60) {
    // Мы только что сами обновили — это эхо, не реагируем
    http_response_code(200);
    exit('echo');
}

// 3. Реакция на «внешнее» изменение: лог, можно обновить ERP обратно
file_put_contents(__DIR__ . '/events.log', date('c') . " deal {$dealId} changed externally\n", FILE_APPEND);

http_response_code(200);
echo '{"ok":true}';
