<?php
/**
 * Скрипт для просмотра зарегистрированных вебхуков
 *
 * Использование:
 * php list-webhooks.php
 */

require_once __DIR__ . '/config.php';

$bitrixUrl = BITRIX24_WEBHOOK_URL;

if (!$bitrixUrl) {
    die("Ошибка: BITRIX24_WEBHOOK_URL не настроен в config.php\n");
}

echo "Получение списка зарегистрированных вебхуков...\n\n";

$webhooks = getWebhooks($bitrixUrl);

if (empty($webhooks)) {
    echo "❌ Вебхуки не найдены или произошла ошибка\n";
    exit(1);
}

echo "Найдено вебхуков: " . count($webhooks) . "\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($webhooks as $index => $webhook) {
    $event = $webhook['event'] ?? 'N/A';
    $handler = $webhook['handler'] ?? 'N/A';
    $userId = $webhook['user_id'] ?? 'N/A';

    echo ($index + 1) . ". Событие: $event\n";
    echo "   Handler: $handler\n";
    echo "   User ID: $userId\n";
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "\nДля удаления вебхука выполните:\n";
echo "php unregister-webhooks.php [EVENT_NAME]\n";

/**
 * Получить список зарегистрированных вебхуков
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

    if (isset($result['error'])) {
        echo "Ошибка API: " . ($result['error_description'] ?? $result['error']) . "\n";
        return [];
    }

    return $result['result'] ?? [];
}
