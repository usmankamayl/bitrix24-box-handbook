<?php
/**
 * /local/php_interface/init.php — версия для проекта с Composer.
 *
 * Порядок в файле важен: autoload подключается ДО первого обращения к классам
 * проекта. Регистрация обработчиков событий ниже autoload-а — не обязательна,
 * но так проще читать: сначала инфраструктура, потом поведение.
 */

use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

// is_file() — не перестраховка: без composer install на сервере безусловный
// require даёт Fatal error до header.php, то есть белый экран без текста
// ошибки в браузере.
$autoload = Loader::getDocumentRoot() . '/local/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
