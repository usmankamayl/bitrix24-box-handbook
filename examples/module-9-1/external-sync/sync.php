<?php
// Внешнее приложение — синхронизация лидов из Bitrix24 в CSV
// Запускается через cron или вручную

// Конфигурация OAuth
define('CLIENT_ID', 'local.xxxxx.your_client_id');
define('CLIENT_SECRET', 'your_client_secret');
define('REDIRECT_URI', 'https://your-domain.com/sync.php');

// Файл для сохранения токенов
define('TOKENS_FILE', __DIR__ . '/tokens.json');
define('EXPORT_FILE', __DIR__ . '/leads_export.csv');

/**
 * Получение access_token
 */
function getAccessToken() {
    if (!file_exists(TOKENS_FILE)) {
        die('Токены не найдены. Запусти авторизацию сначала.');
    }

    $tokens = json_decode(file_get_contents(TOKENS_FILE), true);

    // Проверяем срок жизни токена
    if (time() > $tokens['expires']) {
        return refreshAccessToken($tokens['refresh_token']);
    }

    return $tokens['access_token'];
}

/**
 * Обновление access_token через refresh_token
 */
function refreshAccessToken($refreshToken) {
    $url = 'https://oauth.bitrix.info/oauth/token/?' . http_build_query([
        'grant_type' => 'refresh_token',
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'refresh_token' => $refreshToken
    ]);

    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        die('Ошибка обновления токена: ' . $response);
    }

    // Сохраняем новые токены
    $tokens = [
        'access_token' => $data['access_token'],
        'refresh_token' => $data['refresh_token'],
        'expires' => time() + $data['expires_in']
    ];
    file_put_contents(TOKENS_FILE, json_encode($tokens));

    return $tokens['access_token'];
}

/**
 * Вызов REST API
 */
function callRest($method, $params = []) {
    $accessToken = getAccessToken();
    $url = 'https://your-domain.bitrix24.ru/rest/' . $method . '?' .
           http_build_query($params) . '&auth=' . $accessToken;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

/**
 * Экспорт лидов в CSV
 */
function exportLeads() {
    echo "Начало экспорта лидов...\n";

    $allLeads = [];
    $start = 0;

    // Загружаем лиды порциями по 50
    do {
        $result = callRest('crm.lead.list', [
            'start' => $start,
            'order' => ['ID' => 'ASC']
        ]);

        if (!isset($result['result'])) {
            die('Ошибка API: ' . json_encode($result));
        }

        $allLeads = array_merge($allLeads, $result['result']);
        $start += 50;

        echo "Загружено: " . count($allLeads) . "\n";

    } while (isset($result['next']));

    // Записываем в CSV
    $fp = fopen(EXPORT_FILE, 'w');

    // Заголовки
    fputcsv($fp, ['ID', 'Название', 'Статус', 'Сумма', 'Дата создания']);

    foreach ($allLeads as $lead) {
        fputcsv($fp, [
            $lead['ID'],
            $lead['TITLE'],
            $lead['STATUS_ID'],
            $lead['OPPORTUNITY'],
            $lead['DATE_CREATE']
        ]);
    }

    fclose($fp);

    echo "Экспортировано " . count($allLeads) . " лидов в " . EXPORT_FILE . "\n";
}

// Запускаем экспорт
exportLeads();
