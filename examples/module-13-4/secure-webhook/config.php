<?php

// Подключение к БД, где хранятся установки приложения (см. модуль 13.1)
define('DB_DSN', 'mysql:host=localhost;dbname=app;charset=utf8mb4');
define('DB_USER', 'app_user');
define('DB_PASS', 'app_password');

function getPdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
    return $pdo;
}
