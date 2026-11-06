<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/InstallationRepository.php';

$code = $_REQUEST['code'] ?? null;
$domain = $_REQUEST['DOMAIN'] ?? $_REQUEST['domain'] ?? null;
$event = $_REQUEST['event'] ?? null; // ONAPPINSTALL при повторной установке/обновлении прав
$authPayload = $_REQUEST['auth'] ?? [];

if (!$domain) {
    http_response_code(400);
    die('Missing domain');
}

try {
    Security::assertValidDomain($domain);
} catch (RuntimeException $e) {
    http_response_code(403);
    die($e->getMessage());
}

if (!$code && !$event) {
    http_response_code(400);
    die('Missing authorization code');
}

try {
    // Первая установка — обмениваем code на токены.
    // Повторная установка/апдейт прав — Bitrix24 присылает event=ONAPPINSTALL
    // вместе с уже готовым auth, обмен не нужен.
    $tokens = $code
        ? exchangeCodeForTokens($code, $domain)
        : $authPayload;

    if (empty($tokens['member_id']) || empty($tokens['access_token'])) {
        throw new RuntimeException('Incomplete token payload');
    }

    $applicationToken = $authPayload['application_token'] ?? bin2hex(random_bytes(32));

    InstallationRepository::create()->save($tokens, $domain, $applicationToken);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[install.php] ' . $e->getMessage());
    die('Installation failed');
}

// Обязательно: ответ в формате, который ожидает iframe Bitrix24 при установке.
// Без installFinish() экран установки у пользователя "зависнет" на загрузке,
// даже если токены на самом деле уже сохранены в БД.
echo "<script src=\"https://api.bitrix24.com/api/v1/\"></script>
<script>
    BX24.init(function () {
        BX24.installFinish();
    });
</script>";

function exchangeCodeForTokens(string $code, string $domain): array
{
    $ch = curl_init(APP_OAUTH_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => APP_CLIENT_ID,
            'client_secret' => APP_CLIENT_SECRET,
            'code' => $code,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);

    if ($httpCode !== 200 || empty($data['access_token'])) {
        throw new RuntimeException('Code exchange failed: ' . ($raw ?: "HTTP {$httpCode}"));
    }

    return $data;
}
