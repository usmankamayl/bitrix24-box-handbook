<?php
/**
 * Обработчик события onCrmDealAdd: как только сделка создана,
 * планируем в календаре ответственного ознакомительную встречу
 * на ближайший рабочий день, 10:00–10:30.
 */

require_once __DIR__ . '/crest.php';

const APPLICATION_TOKEN = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'; // токен приложения/вебхука — см. раздел "Вебхуки" портала

function logLine(string $message): void
{
    file_put_contents(__DIR__ . '/handler.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

// 1. Проверяем подлинность запроса — без этого кто угодно, зная URL,
//    может подделать событие "сделка создана"
$receivedToken = $_REQUEST['auth']['application_token'] ?? '';
if (!hash_equals(APPLICATION_TOKEN, $receivedToken)) {
    http_response_code(403);
    logLine('Отклонён запрос с неверным application_token');
    exit;
}

$dealId = (int)($_REQUEST['data']['FIELDS']['ID'] ?? 0);
if ($dealId <= 0) {
    http_response_code(400);
    exit;
}

// Отвечаем Bitrix24 сразу, дальше работаем в фоне — событийные хуки
// ждут ответ ограниченное время, а звонить в calendar.event.add можно
// уже после того, как соединение с Bitrix24 закрыто
http_response_code(200);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$deal = CRest::call('crm.deal.get', ['id' => $dealId]);
if (empty($deal['result'])) {
    logLine("Сделка {$dealId} не найдена при обработке onCrmDealAdd");
    exit;
}

$responsibleId = (int)$deal['result']['ASSIGNED_BY_ID'];
if ($responsibleId <= 0) {
    logLine("У сделки {$dealId} не назначен ответственный — встреча не запланирована");
    exit;
}

// Ближайший рабочий день (пропускаем субботу/воскресенье)
$meetingDate = new DateTime('tomorrow');
while ((int)$meetingDate->format('N') >= 6) {
    $meetingDate->modify('+1 day');
}

$from = $meetingDate->format('d.m.Y') . ' 10:00:00';
$to   = $meetingDate->format('d.m.Y') . ' 10:30:00';

$eventResult = CRest::call('calendar.event.add', [
    'type'        => 'user',
    'ownerId'     => $responsibleId,
    'name'        => 'Ознакомительная встреча по сделке №' . $dealId,
    'description' => 'Сделка: ' . $deal['result']['TITLE'] . "\nАвтоматически запланировано при создании сделки.",
    'from'        => $from,
    'to'          => $to,
    'is_meeting'  => 'Y',
    'attendees'   => [$responsibleId],
    'meeting'     => ['notify' => 'Y', 'reinvite' => 'N'],
    'remind'      => [
        ['type' => 'min', 'count' => 15],
    ],
]);

if (!empty($eventResult['error'])) {
    logLine("Ошибка создания события для сделки {$dealId}: " . ($eventResult['error_description'] ?? $eventResult['error']));
    exit;
}

logLine("Для сделки {$dealId} создано событие #{$eventResult['result']} на {$from}");
