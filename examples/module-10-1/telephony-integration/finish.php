<?php
/**
 * Webhook для завершения звонка
 *
 * АТС вызывает этот URL после окончания звонка:
 *   https://your-domain.com/telephony/finish.php?ats_call_id=ATS12345&duration=120&status=200&record_url=https://...
 *
 * Что делает скрипт:
 *   1. Находит CALL_ID по ID звонка в АТС
 *   2. Вызывает telephony.externalcall.finish — завершает звонок в CRM
 *   3. Вызывает telephony.externalcall.attachrecord — прикрепляет запись разговора
 */

// =============================================================================
// НАСТРОЙКИ — те же что в index.php
// =============================================================================

define('B24_WEBHOOK', 'https://your-domain.bitrix24.ru/rest/1/YOUR_WEBHOOK_KEY/');
define('CALLS_DB',    __DIR__ . '/calls.json');


// =============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =============================================================================

function callB24(string $method, array $params = []): array
{
    $url = B24_WEBHOOK . $method . '.json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 15, // чуть больше таймаут — attachrecord может быть медленным
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'CURL_ERROR', 'error_description' => $error];
    }

    return json_decode($response, true) ?? [];
}

/**
 * Найти CALL_ID в файле-базе по ID звонка из АТС
 */
function findCallData(string $atsCallId): ?array
{
    if (!file_exists(CALLS_DB)) {
        return null;
    }

    $calls = json_decode(file_get_contents(CALLS_DB), true);
    return $calls[$atsCallId] ?? null;
}

/**
 * Удалить звонок из базы после завершения (чистим файл)
 */
function removeCallData(string $atsCallId): void
{
    $calls = json_decode(file_get_contents(CALLS_DB), true) ?? [];
    unset($calls[$atsCallId]);
    file_put_contents(CALLS_DB, json_encode($calls, JSON_PRETTY_PRINT));
}

function log(string $message): void
{
    file_put_contents(
        __DIR__ . '/calls.log',
        date('Y-m-d H:i:s') . ' ' . $message . "\n",
        FILE_APPEND
    );
}


// =============================================================================
// ОБРАБОТКА ЗАПРОСА ОТ АТС
// =============================================================================

$atsCallId  = $_GET['ats_call_id']  ?? $_GET['call_id']     ?? null;
$duration   = (int)($_GET['duration']  ?? 0);             // длительность в секундах
$statusCode = (int)($_GET['status']    ?? 200);           // SIP-код: 200-ok, 304-пропущен
$recordUrl  = $_GET['record_url']   ?? null;              // URL записи разговора
$ext        = $_GET['ext']          ?? $_GET['inner']      ?? null;

if (!$atsCallId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ats_call_id']);
    exit;
}

log("FINISH ats_id={$atsCallId} duration={$duration}s status={$statusCode}");


// =============================================================================
// ШАГ 1: Найти CALL_ID звонка в нашей "базе"
// =============================================================================

$callData = findCallData($atsCallId);

if (!$callData) {
    // Звонок не найден — возможно, index.php не был вызван (перезапуск скрипта и т.д.)
    // В реальном проекте — искать в MySQL или вызвать register с CALL_ID из АТС напрямую
    log("ERROR call not found for ats_id={$atsCallId}");
    http_response_code(404);
    echo json_encode(['error' => 'Call not found', 'ats_call_id' => $atsCallId]);
    exit;
}

$b24CallId = $callData['b24_call_id'];
$userId    = $callData['user_id'];
$userInner = $ext ?? ''; // внутренний номер, если АТС передала


// =============================================================================
// ШАГ 2: Завершить звонок в Bitrix24
// =============================================================================

$finishParams = [
    'CALL_ID'          => $b24CallId,
    'USER_ID'          => $userId,
    'DURATION'         => $duration,        // секунды
    'STATUS_CODE'      => $statusCode,      // 200 = разговор состоялся
    'ADD_TO_CHAT'      => 1,                // добавить сообщение в бизнес-чат
];

// Передаём внутренний номер, если известен
if ($userInner) {
    $finishParams['USER_PHONE_INNER'] = $userInner;
}

$finishResult = callB24('telephony.externalcall.finish', $finishParams);

if (isset($finishResult['error'])) {
    log("ERROR finish failed: " . ($finishResult['error_description'] ?? ''));
    http_response_code(500);
    echo json_encode(['error' => 'finish failed', 'detail' => $finishResult]);
    exit;
}

log("FINISHED b24_call_id={$b24CallId}");


// =============================================================================
// ШАГ 3: Прикрепить запись разговора (если есть URL)
// =============================================================================
// ВАЖНО: attachrecord вызывается СТРОГО после finish — иначе CALL_ID невалиден

if ($recordUrl) {

    // Вариант A: Bitrix24 сам скачает файл по URL
    // (URL должен быть публично доступен без авторизации)
    $attachResult = callB24('telephony.externalcall.attachrecord', [
        'CALL_ID'    => $b24CallId,
        'FILENAME'   => 'call_' . $atsCallId . '.mp3',
        'RECORD_URL' => $recordUrl,
    ]);

    /*
    // Вариант B: скачать файл и передать содержимое в base64
    // (если URL требует авторизации или недоступен извне)
    $fileContent = file_get_contents($recordUrl);
    if ($fileContent !== false) {
        $attachResult = callB24('telephony.externalcall.attachrecord', [
            'CALL_ID'      => $b24CallId,
            'FILENAME'     => 'call_' . $atsCallId . '.mp3',
            'FILE_CONTENT' => base64_encode($fileContent),
        ]);
    }
    */

    if (isset($attachResult['error'])) {
        // Ошибка прикрепления — не критично, звонок уже завершён
        log("WARN attachrecord failed: " . ($attachResult['error_description'] ?? ''));
    } else {
        log("RECORD ATTACHED for b24_call_id={$b24CallId}");
    }
}


// =============================================================================
// ШАГ 4: Очистить базу и ответить АТС
// =============================================================================

removeCallData($atsCallId);

http_response_code(200);
echo json_encode([
    'status'       => 'ok',
    'b24_call_id'  => $b24CallId,
    'duration'     => $duration,
    'record_attached' => isset($recordUrl),
]);
