<?php
/**
 * refresh.php — обновление access_token через refresh_token
 *
 * access_token живёт 1 час. Для фоновых задач (cron, события)
 * нужно обновлять его через refresh_token.
 */

define('APP_CLIENT_ID', 'local.xxxxx.your_client_id');
define('APP_CLIENT_SECRET', 'your_client_secret');
define('DATA_DIR', __DIR__ . '/data');

function loadPortal($memberId) {
    $file = DATA_DIR . '/portal_' . preg_replace('/[^a-z0-9]/i', '', $memberId) . '.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
}

function savePortal($data) {
    file_put_contents(
        DATA_DIR . '/portal_' . preg_replace('/[^a-z0-9]/i', '', $data['member_id']) . '.json',
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function getValidToken($memberId) {
    $portal = loadPortal($memberId);
    if (!$portal) {
        throw new Exception('Портал не найден: ' . $memberId);
    }

    // Токен ещё жив — возвращаем как есть
    if (time() < $portal['expires'] - 60) {
        return $portal['access_token'];
    }

    // Обновляем через oauth.bitrix.info (единый endpoint для всех порталов)
    $url = 'https://oauth.bitrix.info/oauth/token/?' . http_build_query([
        'grant_type'    => 'refresh_token',
        'client_id'     => APP_CLIENT_ID,
        'client_secret' => APP_CLIENT_SECRET,
        'refresh_token' => $portal['refresh_token']
    ]);

    $response = json_decode(file_get_contents($url), true);

    if (empty($response['access_token'])) {
        throw new Exception('Ошибка обновления токена: ' . json_encode($response));
    }

    // Важно: refresh_token тоже меняется! Сохраняем оба.
    $portal['access_token']  = $response['access_token'];
    $portal['refresh_token'] = $response['refresh_token'];
    $portal['expires']       = time() + (int)$response['expires_in'];
    savePortal($portal);

    return $portal['access_token'];
}

// --- Пример использования ---
// php refresh.php member_id_xxx
$memberId = $argv[1] ?? $_GET['member_id'] ?? null;
if ($memberId) {
    try {
        $token = getValidToken($memberId);
        echo "Валидный токен: {$token}\n";
    } catch (Exception $e) {
        echo "Ошибка: " . $e->getMessage() . "\n";
    }
}
