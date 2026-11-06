<?php

define('AMOCRM_DOMAIN', 'yourdomain.amocrm.ru');
define('AMOCRM_ACCESS_TOKEN', 'your_long_lived_or_oauth_token');

// ID кастомного поля в AmoCRM (тип "Текст"), в которое пишется ID сделки
// Bitrix24 — служит "якорем" для идемпотентного поиска при повторной отправке
define('AMOCRM_BITRIX_ID_FIELD_ID', 123456);

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=sync_app;charset=utf8mb4',
            'app_user',
            'app_password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    return $pdo;
}
