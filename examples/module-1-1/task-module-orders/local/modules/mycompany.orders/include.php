<?php

/**
 * include.php модуля mycompany.orders.
 *
 * Путь в проекте: /local/modules/mycompany.orders/include.php
 *
 * Подключается один раз при Loader::includeModule('mycompany.orders').
 * Здесь — только регистрация namespace и то, что нужно модулю на каждом хите.
 * Кода с побочными эффектами (запросы, HTTP-вызовы) здесь быть не должно:
 * include.php выполняется при каждом подключении модуля.
 */

use Bitrix\Main\Loader;

// Автозагрузка классов модуля: namespace -> каталог lib/.
// Регистрация явная, чтобы не зависеть от того, как конкретная версия ядра
// преобразует идентификатор модуля в namespace. Работает и для /local/modules,
// и для /bitrix/modules.
Loader::registerNamespace('Mycompany\\Orders', __DIR__ . '/lib');