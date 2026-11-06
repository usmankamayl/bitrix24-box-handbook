<?php
/**
 * Привязка исходящего вебхука (события) к нашему handler.
 *
 * Запускается один раз. После этого каждая новая сделка
 * будет порождать POST на handler.php.
 */

const INBOUND_WEBHOOK = 'https://your-domain.bitrix24.ru/rest/1/XXXXXXXXXXXX/';

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

// Подписываемся на создание сделки
print_r(bx_call('event.bind', [
    'event'      => 'onCrmDealAdd',
    'handler'    => 'https://your-domain.com/bitrix-app/outbound/handler.php',
    'event_type' => 'online',
    'auth'       => 'my-secret-token', // вернётся в auth.application_token
]));

// Проверка: список привязанных событий
// print_r(bx_call('events', []));

// Отписка:
// print_r(bx_call('event.unbind', [
//     'event'   => 'onCrmDealAdd',
//     'handler' => 'https://your-domain.com/bitrix-app/outbound/handler.php',
// ]));
