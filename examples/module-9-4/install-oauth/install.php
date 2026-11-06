<?php
/**
 * install.php — точка входа при установке приложения из Bitrix24.Market
 *
 * Flow:
 * 1. Bitrix24 вызывает этот URL с параметрами установки (mode=install)
 * 2. Мы сохраняем токены портала в "базу" (файл для примера)
 * 3. Регистрируем нужные виджеты/события
 * 4. Отвечаем страницей "Приложение установлено"
 *
 * ВАЖНО: при установке Bitrix24 передаёт POST с member_id, access_token,
 * refresh_token — это НЕ OAuth code flow, токены выдаются сразу!
 */

// --- Конфигурация ---
define('APP_CLIENT_ID', 'local.xxxxx.your_client_id');
define('APP_CLIENT_SECRET', 'your_client_secret');
define('DATA_DIR', __DIR__ . '/data');

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// --- Читаем параметры установки ---
// Bitrix24 шлёт application/x-www-form-urlencoded POST
$request = $_REQUEST;

$memberId      = $request['member_id']      ?? null;
$accessToken   = $request['AUTH_ID']        ?? null;
$refreshToken  = $request['REFRESH_ID']     ?? null;
$domain        = $request['DOMAIN']         ?? null;
$lang          = $request['LANG']           ?? 'ru';
$installMode   = $request['PLACEMENT']      ?? 'DEFAULT';

if (!$memberId || !$accessToken) {
    http_response_code(400);
    die('Некорректные параметры установки');
}

// --- Сохраняем токены портала ---
$portalData = [
    'member_id'     => $memberId,
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
    'domain'        => $domain,
    'lang'          => $lang,
    'installed_at'  => date('c'),
    'expires'       => time() + 3600   // AUTH_ID живёт ~1 час
];

file_put_contents(
    DATA_DIR . '/portal_' . preg_replace('/[^a-z0-9]/i', '', $memberId) . '.json',
    json_encode($portalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// --- Вызываем REST API от имени приложения ---
function appRest($method, $params, $token, $domain) {
    $url = "https://{$domain}/rest/{$method}?auth={$token}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// --- Регистрируем виджет-вкладку в сделке ---
appRest('placement.bind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
    'HANDLER'   => 'https://your-domain.com/widgets/deal-tab.php',
    'TITLE'     => 'Мой виджет',
    'OPTIONS'   => ['height' => 400]
], $accessToken, $domain);

// --- Регистрируем обработчик события создания сделки ---
appRest('event.bind', [
    'event'      => 'ONCRMDEALADD',
    'handler'    => 'https://your-domain.com/handlers/deal-created.php',
    'event_type' => 'online'
], $accessToken, $domain);

// --- Ответ пользователю ---
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <title>Установка завершена</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 60px 20px; }
        .ok { color: #22c55e; font-size: 48px; }
        h1 { color: #333; }
        p { color: #666; }
        a { color: #2e5eff; }
    </style>
</head>
<body>
    <div class="ok">✓</div>
    <h1>Приложение установлено</h1>
    <p>Портал: <strong><?= htmlspecialchars($domain) ?></strong></p>
    <p>member_id: <strong><?= htmlspecialchars($memberId) ?></strong></p>
    <p><a href="https://<?= htmlspecialchars($domain) ?>/">Вернуться в Bitrix24</a></p>
</body>
</html>
