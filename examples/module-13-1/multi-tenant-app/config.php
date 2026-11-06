<?php

// Клиентские данные приложения, выданные при регистрации на dev.bitrix24.ru/marketplace/
define('APP_CLIENT_ID', 'local.xxxxxxxxxxxxx.xxxxxxxx');
define('APP_CLIENT_SECRET', 'your_client_secret_here');
define('APP_OAUTH_TOKEN_URL', 'https://oauth.bitrix.info/oauth/token/');

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=market_app;charset=utf8mb4',
            'app_user',
            'app_password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    return $pdo;
}
