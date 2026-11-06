<?php
/**
 * Скрипт для регистрации вебхуков в Bitrix24
 *
 * Использование:
 * php register-webhooks.php
 */

require_once __DIR__ . '/config.php';

// URL обработчика на вашем сервере
$handlerUrl = 'https://ваш-сайт.com/webhook-handler.php?token=' . WEBHOOK_SECRET_TOKEN;

// События для регистрации
$events = [
    'ONCRMLEADADD',      // Создание лида
    'ONCRMLEADUPDATE',   // Обновление лида
    'ONCRMLEADDELETE',   // Удаление лида
];

// Bitrix24 REST API URL
$bitrixUrl = BITRIX24_WEBHOOK_URL;

if (!$bitrixUrl) {
    die("Ошибка: BITRIX24_WEBHOOK_URL не настроен в config.php\n");
}

echo "Регистрация вебхуков в Bitrix24...\n";
echo "Handler URL: $handlerUrl\n\n";

foreach ($events as $event) {
    echo "Регистрация события: $event... ";

    $result = registerWebhook($bitrixUrl, $event, $handlerUrl);

    if ($result['success']) {
        echo "✅ Успешно\n";
    } else {
        echo "❌ Ошибка: " . $result['error'] . "\n";
    }
}

echo "\n✅ Регистрация завершена!\n";
echo "\nДля проверки списка зарегистрированных вебхуков выполните:\n";
echo "php list-webhooks.php\n";

/**
 * Регистрация вебхука в Bitrix24
 */
function registerWebhook($bitrixUrl, $event, $handlerUrl)
{
    $url = $bitrixUrl . 'event.bind.json';

    $data = [
        'event' => $event,
        'handler' => $handlerUrl,
        'auth_type' => 0, // 0 - без авторизации, 1 - с авторизацией
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $httpCode: $error"
        ];
    }

    $result = json_decode($response, true);

    if (isset($result['error'])) {
        return [
            'success' => false,
            'error' => $result['error_description'] ?? $result['error']
        ];
    }

    return [
        'success' => true,
        'result' => $result['result'] ?? true
    ];
}
