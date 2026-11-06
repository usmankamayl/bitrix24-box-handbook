<?php

/**
 * Копировать в /local/php_interface/init.php (или добавить строку, если файл
 * уже есть).
 *
 * init.php выполняется в прологе на КАЖДОМ запросе, поэтому здесь только
 * регистрация автозагрузки — без подключения модулей и без логики.
 *
 * Дальше классы MyCompany\DealStage\* находятся как
 * /local/php_interface/classes/DealStage/Journal.php и т.д.
 * (autoload D7 подробно — в 1.6).
 */

use Bitrix\Main\Loader;

Loader::registerNamespace('MyCompany\\', '/local/php_interface/classes/');
