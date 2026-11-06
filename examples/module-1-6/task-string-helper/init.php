<?php
/**
 * Регистрация автозагрузки для StringHelper.
 * Этот код добавляется в /local/php_interface/init.php
 */

use Bitrix\Main\Loader;

// --- Вариант 1 (используется в этой задаче): карта класс → файл -------------
// $module = null: класс не принадлежит модулю.
// Ключ — полное имя класса, регистр не важен (Loader сам приводит к нижнему).
// Значение — путь от корня документа. Именно путь, а не ключ, зависит от
// регистра: на Linux /Helpers/StringHelper.php и /helpers/stringhelper.php —
// разные файлы.
Loader::registerAutoLoadClasses(null, [
    'MyCompany\\Helpers\\StringHelper' => '/local/php_interface/classes/Helpers/StringHelper.php',
]);

// --- Вариант 2: несколько классов одной картой ------------------------------
// Дешевле по времени старта, чем N отдельных вызовов, и все пути видны в одном
// месте. Держите карту короткой: это «горячий» список, а не реестр проекта.
//
// Loader::registerAutoLoadClasses(null, [
//     'MyCompany\\Helpers\\StringHelper' => '/local/php_interface/classes/Helpers/StringHelper.php',
//     'MyCompany\\Helpers\\ArrayHelper'  => '/local/php_interface/classes/Helpers/ArrayHelper.php',
//     'MyCompany\\Services\\UserService' => '/local/php_interface/classes/Services/UserService.php',
// ]);

// --- Вариант 3: Composer PSR-4 (см. task-composer/) -------------------------
// Для группы классов в CamelCase-файлах это единственный корректный вариант:
// Loader::registerNamespace() приводит путь к нижнему регистру, и на Linux
// класс не найдётся.
//
// require_once Loader::getDocumentRoot() . '/local/vendor/autoload.php';
//
// composer.json (в /local/):
// {
//     "autoload": { "psr-4": { "MyCompany\\": "php_interface/classes/" } }
// }
