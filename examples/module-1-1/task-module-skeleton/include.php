<?php
/**
 * Выполняется при каждом Loader::includeModule('mycompany.hello').
 *
 * Здесь регистрируют обработчики событий, константы, проверяют зависимости.
 * Файл подключается один раз на хит — код должен быть быстрым и без вывода.
 */

// Пример: регистрация обработчика (раскомментировать при наличии класса)
// \Bitrix\Main\EventManager::getInstance()->addEventHandler(
//     'main',
//     'OnPageStart',
//     ['Mycompany\\Hello\\EventHandlers', 'onPageStart']
// );
