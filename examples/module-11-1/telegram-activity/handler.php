<?php
/**
 * Обработчик пользовательского активити TG_SEND_MESSAGE.
 *
 * Битрикс24 делает POST сюда каждый раз, когда робот/БП доходит до нашего шага.
 * Мы ОБЯЗАНЫ:
 *   1) быстро ответить (лимит ~30 сек)
 *   2) подтвердить приём через bizproc.event.send (для activity) — БП перейдёт в режим ожидания
 *   3) завершить работу через bizproc.activity.completed, вернув OUTPUT-параметры
 */

require_once __DIR__ . '/crest.php';

// ---- Логирование входящего запроса (для отладки) ----
$logFile = __DIR__ . '/activity.log';
file_put_contents($logFile, date('c') . ' ' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

// ---- Данные, которые шлёт Битрикс ----
$eventToken  = $_REQUEST['event_token']   ?? null;
$workflowId  = $_REQUEST['workflow_id']   ?? null;
$documentId  = $_REQUEST['document_id']   ?? null; // ['crm','CCrmDocumentDeal','DEAL_123']
$properties  = $_REQUEST['properties']    ?? [];
$auth        = $_REQUEST['auth']          ?? [];

if (!$eventToken) {
    http_response_code(400);
    exit('no event_token');
}

// Немедленно отвечаем Битриксу, чтобы не было таймаута,
// а работу делаем после закрытия соединения.
http_response_code(200);
header('Content-Type: application/json');
echo '{"ok":true}';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ---- В реальном приложении CRest должен использовать $auth['access_token'] ----
// Здесь для простоты используем входящий вебхук (crest.php).

// 1. Подтверждаем получение — переводит БП в асинхронный режим
CRest::call('bizproc.event.send', [
    'EVENT_TOKEN'   => $eventToken,
    'LOG_MESSAGE'   => 'Активити TG_SEND_MESSAGE запущено',
]);

// 2. Бизнес-логика: отправка в Telegram Bot API
$tgToken   = '123456:ABC-DEF...';                       // токен бота из BotFather
$chatId    = $properties['CHAT_ID']    ?? '';
$text      = $properties['TEXT']       ?? '';
$parseMode = $properties['PARSE_MODE'] ?? 'HTML';

$output = [
    'MESSAGE_ID' => 0,
    'SENT_AT'    => date('Y-m-d H:i:s'),
    'STATUS'     => 'error',
];
$logMessage = 'Ошибка отправки';

try {
    $response = file_get_contents(
        'https://api.telegram.org/bot' . $tgToken . '/sendMessage?' . http_build_query([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ]),
        false,
        stream_context_create(['http' => ['timeout' => 10]])
    );
    $data = json_decode($response, true);

    if (!empty($data['ok'])) {
        $output['MESSAGE_ID'] = $data['result']['message_id'];
        $output['STATUS']     = 'sent';
        $logMessage = 'Telegram-сообщение отправлено, id=' . $data['result']['message_id'];
    } else {
        $logMessage = 'Telegram API error: ' . ($data['description'] ?? 'unknown');
    }
} catch (Throwable $e) {
    $logMessage = 'Exception: ' . $e->getMessage();
}

// 3. Завершаем активити и возвращаем выходные параметры
CRest::call('bizproc.activity.completed', [
    'EVENT_TOKEN' => $eventToken,
    'OUTPUT'      => $output,
    'LOG_MESSAGE' => $logMessage,
]);

file_put_contents($logFile, date('c') . " done: {$logMessage}\n", FILE_APPEND);
