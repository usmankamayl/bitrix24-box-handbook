<?php
/**
 * Выполняется при каждом Loader::includeModule('mycompany.hello').
 *
 * Здесь регистрируют namespace модуля, обработчики событий, константы,
 * проверяют зависимости. Файл подключается один раз на хит — код должен быть
 * быстрым и без вывода.
 */

use Bitrix\Main\Loader;

// Автозагрузка классов модуля: namespace -> каталог lib/.
// Явная регистрация в include.php не зависит от того, как конкретная версия
// ядра разбирает имя модуля в namespace, и работает одинаково для
// /local/modules и /bitrix/modules.
Loader::registerNamespace('Mycompany\\Hello', __DIR__ . '/lib');

// Пример: регистрация обработчика (раскомментировать при наличии класса)
// \Bitrix\Main\EventManager::getInstance()->addEventHandler(
//     'main',
//     'OnPageStart',
//     ['Mycompany\\Hello\\EventHandlers', 'onPageStart']
// );
