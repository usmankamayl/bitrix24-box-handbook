<?php
/**
 * Установка агента для проверки брошенных корзин
 *
 * Запустите этот файл один раз для установки агента
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Local\Agents\AbandonedCartAgent;

// Регистрируем автозагрузку
spl_autoload_register(function ($class) {
    if (strpos($class, 'Local\\Agents\\') === 0) {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/agents/'
            . str_replace('\\', '/', substr($class, 13)) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
});

echo "<h1>Установка агента проверки брошенных корзин</h1>";
echo "<pre>";

AbandonedCartAgent::install();

echo "</pre>";
echo "<p><a href='/bitrix/admin/agent_list.php'>Перейти к списку агентов</a></p>";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
