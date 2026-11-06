<?php
/**
 * Регистрация обработчика в init.php
 *
 * Добавьте этот код в /local/php_interface/init.php
 */

use Local\Handlers\UserRegistrationHandler;

// Регистрируем автозагрузку класса
spl_autoload_register(function ($class) {
    if (strpos($class, 'Local\\Handlers\\') === 0) {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/handlers/'
            . str_replace('\\', '/', substr($class, 15)) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Регистрируем обработчик события
UserRegistrationHandler::register();
