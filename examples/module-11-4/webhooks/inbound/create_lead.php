<?php
/**
 * Пример работы с ВХОДЯЩИМ вебхуком Bitrix24.
 *
 * Входящий вебхук — это URL-ключ вида
 *   https://{portal}.bitrix24.ru/rest/{USER_ID}/{TOKEN}/{method}
 * Он позволяет вызывать REST-методы БЕЗ OAuth — просто POST'ом.
 *
 * Создание вебхука в портале:
 *   Разработчикам → Другое → Входящий вебхук
 *   Права: crm (для crm.lead.add)
 */

const WEBHOOK = 'https://your-domain.bitrix24.ru/rest/1/XXXXXXXXXXXX/';

function webhook_call(string $method, array $params): array
{
    $url = WEBHOOK . $method; // метод уже без .json — так тоже работает
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => 'curl', 'error_description' => $err];
    }
    return json_decode($body, true);
}

// 1. Создать лид
$lead = webhook_call('crm.lead.add', [
    'fields' => [
        'TITLE'         => 'Заявка с формы обратного звона',
        'NAME'          => 'Иван',
        'LAST_NAME'     => 'Петров',
        'PHONE'         => [['VALUE' => '+79991234567', 'VALUE_TYPE' => 'WORK']],
        'EMAIL'         => [['VALUE' => 'ivan@example.com', 'VALUE_TYPE' => 'WORK']],
        'SOURCE_ID'     => 'WEB',
        'COMMENTS'      => 'Создан через входящий вебхук',
        'ASSIGNED_BY_ID'=> 1,
    ],
    'params' => ['REGISTER_SONET_EVENT' => 'Y'], // уведомление в ленту
]);
print_r($lead); // ['result' => 456]

// 2. Получить лид
if (!empty($lead['result'])) {
    print_r(webhook_call('crm.lead.get', ['ID' => $lead['result']]));
}

// 3. Список лидов с фильтром
print_r(webhook_call('crm.lead.list', [
    'filter' => ['>DATE_CREATE' => '2026-01-01'],
    'select' => ['ID', 'TITLE', 'STATUS_ID'],
    'order'  => ['DATE_CREATE' => 'DESC'],
]));
