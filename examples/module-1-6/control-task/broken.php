<?php
/**
 * Контрольное задание: /local/php_interface/init.php из проекта, принятого
 * на поддержку. Рядом лежит composer.json того же проекта.
 *
 * В файле пять дефектов. Задание: назвать все пять, объяснить последствие
 * каждого и условия, при которых дефект проявляется.
 */

spl_autoload_register(function ($class) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/'
        . str_replace('\\', '/', $class) . '.php';
    include $path;
});

\Bitrix\Main\Loader::registerNamespace(
    'MyCompany\\Legacy',
    $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/Legacy'
);

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

if (!$USER->IsAuthorized()) {
    return;
}

\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'MyCompany\\Pricing\\Calculator' => '/local/php_interface/classes/Pricing/Calculator.php',
]);
