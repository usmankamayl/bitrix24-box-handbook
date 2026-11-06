<?php
/**
 * Точка входа модуля складского учета
 *
 * Регистрирует автозагрузку классов модуля
 */

\Bitrix\Main\Loader::registerAutoLoadClasses(
    'acme.warehouse',
    [
        // ORM модели
        'Acme\Warehouse\Model\ProductTable' => 'lib/Model/ProductTable.php',

        // Сервисы
        'Acme\Warehouse\Service\ProductService' => 'lib/Service/ProductService.php',

        // Обработчики событий
        'Acme\Warehouse\EventHandlers' => 'lib/EventHandlers.php',

        // Агенты
        'Acme\Warehouse\Agents\SyncAgent' => 'lib/Agents/SyncAgent.php',

        // Система миграций
        'Acme\Warehouse\Migrator' => 'lib/Migrator.php',
        'Acme\Warehouse\Migrations\Migration001AddCategoryField' => 'lib/Migrations/Migration001AddCategoryField.php',
    ]
);
