<?php
/**
 * Эталон контрольного задания.
 *
 * Каждое исправление помечено номером дефекта из README.md.
 */

use Bitrix\Main\Loader;

// Дефект 4: init.php выполняется до создания $USER, поэтому проверка прав в
// теле файла бессмысленна. Единственная проверка, уместная здесь, — защита от
// прямого вызова файла по HTTP.
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

// Дефект 3: require без проверки существования убивает весь сайт, если на
// сервере не выполнен composer install (Fatal error до header.php — белый
// экран без текста ошибки).
$autoload = Loader::getDocumentRoot() . '/local/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// Дефект 1: собственный spl_autoload_register не нужен — PSR-4 из composer.json
// покрывает MyCompany\*. Свой загрузчик дублировал его с другим поведением,
// а `include` без проверки существования файла обрывал цепочку загрузчиков:
// после него Loader не вызывался, и любой класс ядра, запрошенный впервые,
// падал с Fatal error.
//
// Дефект 2: registerNamespace приводит путь к нижнему регистру, поэтому каталог
// Legacy/ и файлы Legacy класса на Linux не находятся. Классы MyCompany\Legacy
// после исправления тоже переезжают под PSR-4.
//
// Дефект 5: класс MyCompany\Pricing\Calculator объявлен дважды — в карте ниже и
// в autoload.files composer.json. Запись в карте удалена: источник истины один,
// PSR-4 в composer.json.

// Регистрация обработчиков в init.php — законна: они выполнятся позже, когда
// $USER уже существует. Проверка прав — внутри обработчика.
// \Bitrix\Main\EventManager::getInstance()->addEventHandler(
//     'main',
//     'OnAfterUserAuthorize',
//     [\MyCompany\Handlers\User::class, 'onAuthorize']
// );
