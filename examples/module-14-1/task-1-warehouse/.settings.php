<?php

/**
 * Конфигурация модуля
 * Регистрация сервисов через Service Locator
 * Регистрация контроллеров
 */

return [
    'services' => [
        'value' => [
            // Регистрация ProductService через Service Locator
            'acme.warehouse.product.service' => [
                'className' => \Acme\Warehouse\Service\ProductService::class,
            ],

            // Пример регистрации API-клиента с параметрами
            'acme.warehouse.api.client' => [
                'className' => \Acme\Warehouse\Integration\ApiClient::class,
                'constructorParams' => static fn() => [
                    \Bitrix\Main\Config\Option::get('acme.warehouse', 'api_url', ''),
                    \Bitrix\Main\Config\Option::get('acme.warehouse', 'api_key', ''),
                ],
            ],

            // Пример регистрации через фабрику
            'acme.warehouse.cache.manager' => [
                'constructor' => static function() {
                    return new \Acme\Warehouse\Cache\CacheManager(
                        3600 // TTL кеша
                    );
                },
            ],
        ],
    ],

    'controllers' => [
        'value' => [
            // Регистрация пространства имён для контроллеров модуля
            'defaultNamespace' => '\\Acme\\Warehouse\\Controller',
        ],
    ],
];
