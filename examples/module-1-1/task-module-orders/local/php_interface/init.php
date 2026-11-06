<?php

/**
 * Точка подключения собственного кода.
 *
 * Путь в проекте: /local/php_interface/init.php
 *
 * Здесь два независимых механизма:
 *   1. автозагрузка классов Analytics (namespace -> каталог);
 *   2. подписка на событие OnPageStart.
 *
 * Автозагрузку модуля mycompany.orders регистрировать не нужно:
 * Loader::includeModule('mycompany.orders') сам ставит namespace
 * Mycompany\Orders на каталог lib/ модуля.
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

\Bitrix\Main\Loader::registerNamespace('MyCompany\Analytics', __DIR__ . '/classes/Analytics');

$eventManager = EventManager::getInstance();

// [класс, метод] вместо имени функции: не засоряем глобальное пространство
// и не ловим "Cannot redeclare" при повторном подключении init.php.
$eventManager->addEventHandler(
    'main',
    'OnPageStart',
    [\MyCompany\Analytics\VisitorTracker::class, 'track']
);
