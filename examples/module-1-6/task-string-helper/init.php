<?php
/**
 * Регистрация автозагрузки для StringHelper.
 * Этот код добавляется в /local/php_interface/init.php
 */

// Вариант 1: один класс — registerAutoLoadClasses
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'MyCompany\\Helpers\\StringHelper' => '/local/php_interface/classes/Helpers/StringHelper.php',
]);

// Вариант 2: несколько классов сразу
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'MyCompany\\Helpers\\StringHelper' => '/local/php_interface/classes/Helpers/StringHelper.php',
    'MyCompany\\Helpers\\ArrayHelper'  => '/local/php_interface/classes/Helpers/ArrayHelper.php',
    'MyCompany\\Services\\UserService' => '/local/php_interface/classes/Services/UserService.php',
]);

// Вариант 3: Composer PSR-4 (современный подход, масштабируется)
/*
// composer.json в корне проекта:
{
    "autoload": {
        "psr-4": {
            "MyCompany\\": "local/php_interface/classes/"
        }
    }
}

// затем в init.php:
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
*/
