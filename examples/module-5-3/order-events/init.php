<?php
// examples/module-5-3/order-events/init.php
// Точка подключения — include из /local/php_interface/init.php.

use Bitrix\Main\EventManager;

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderPaid',
    ['\\Acme\\Sale\\OrderEvents', 'onOrderPaid']
);

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleStatusOrderChange',
    ['\\Acme\\Sale\\OrderEvents', 'onStatusChange']
);

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderCanceled',
    ['\\Acme\\Sale\\OrderEvents', 'onOrderCanceled']
);

EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderBeforeSaved',
    ['\\Acme\\Sale\\OrderEvents', 'onBeforeSaved']
);
