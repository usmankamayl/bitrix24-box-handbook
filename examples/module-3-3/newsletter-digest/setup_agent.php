<?php
/**
 * Установка агента для еженедельного дайджеста
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Local\Agents\WeeklyDigestAgent;

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

echo "<h1>Установка агента еженедельного дайджеста</h1>";
echo "<pre>";

WeeklyDigestAgent::install();

echo "</pre>";
echo "<p><a href='/bitrix/admin/agent_list.php'>Перейти к списку агентов</a></p>";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
