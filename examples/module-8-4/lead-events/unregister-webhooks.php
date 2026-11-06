<?php
/**
 * Скрипт для удаления вебхуков из Bitrix24
 *
 * Использование:
 * php unregister-webhooks.php [EVENT_NAME]
 *
 * Примеры:
 * php unregister-webhooks.php ONCRMLEADADD
 * php unregister-webhooks.php all  # Удалить все вебхуки
 */

require_once __DIR__ . '/config.php';

$bitrixUrl = BITRIX24_WEBHOOK_URL;
$handlerUrl = 'https://ваш-сайт.com/webhook-handler.php?token=' . WEBHOOK_SECRET_TOKEN;

if (!$bitrixUrl) {
    die("Ошибка: BITRIX24_WEBHOOK_URL не настроен в config.php\n");
}

// Получаем событие из аргументов командной строки
$eventName = $argv[1] ?? null;

if (!$eventName) {
    echo "Использование: php unregister-webhooks.php [EVENT_NAME|all]\n";
    echo "\nПримеры:\n";
    echo "  php unregister-webhooks.php ONCRMLEADADD\n";
    echo "  php unregister-webhooks.php all\n";
    exit(1);
}

if ($eventName === 'all') {
    echo "Удаление ВСЕХ зарегистрированных вебхуков...\n\n";

    // Получаем список всех вебхуков
    $webhooks = getWebhooks($bitrixUrl);

    if (empty($webhooks)) {
        echo "❌ Вебхуки не найдены\n";
        exit(1);
    }

    foreach ($webhooks as $webhook) {
        $event = $webhook['event'] ?? null;
        $handler = $webhook['handler'] ?? null;

        if ($event && $handler) {
            echo "Удаление: $event... ";
            $result = unregisterWebhook($bitrixUrl, $event, $handler);

            if ($result['success']) {
                echo "✅ Успешно\n";
            } else {
                echo "❌ Ошибка: " . $result['error'] . "\n";
            }
        }
    }
} else {
    echo "Удаление вебхука: $eventName... ";

    $result = unregisterWebhook($bitrixUrl, $eventName, $handlerUrl);

    if ($result['success']) {
        echo "✅ Успешно\n";
    } else {
        echo "❌ Ошибка: " . $result['error'] . "\n";
    }
}

echo "\n✅ Готово!\n";

/**
 * Удаление вебхука
 */
function unregisterWebhook($bitrixUrl, $event, $handlerUrl)
{
    $url = $bitrixUrl . 'event.unbind.json';

    $data = [
        'event' => $event,
        'handler' => $handlerUrl,
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
    curl_close($ch);

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $httpCode"
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

/**
 * Получить список вебхуков
 */
function getWebhooks($bitrixUrl)
{
    $url = $bitrixUrl . 'event.get.json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [];
    }

    $result = json_decode($response, true);
    return $result['result'] ?? [];
}
