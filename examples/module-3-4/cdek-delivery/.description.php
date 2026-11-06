<?php
/**
 * Описание службы доставки СДЭК
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$data = [
    'NAME' => Loc::getMessage('CDEK_DELIVERY_NAME'),
    'DESCRIPTION' => Loc::getMessage('CDEK_DELIVERY_DESC'),
    'SORT' => 100,

    'HANDLER' => '\\Sale\\Handlers\\Delivery\\CdekHandler',

    'PROFILES' => [
        // Доставка до двери
        'courier' => [
            'NAME' => Loc::getMessage('CDEK_PROFILE_COURIER'),
            'DESCRIPTION' => Loc::getMessage('CDEK_PROFILE_COURIER_DESC'),
            'TARIFF_CODE' => 136,
        ],

        // Доставка до пункта выдачи
        'pickup' => [
            'NAME' => Loc::getMessage('CDEK_PROFILE_PICKUP'),
            'DESCRIPTION' => Loc::getMessage('CDEK_PROFILE_PICKUP_DESC'),
            'TARIFF_CODE' => 138,
        ],
    ],

    'CODES' => [
        // Аккаунт СДЭК
        'CDEK_ACCOUNT' => [
            'NAME' => Loc::getMessage('CDEK_ACCOUNT'),
            'DESCRIPTION' => Loc::getMessage('CDEK_ACCOUNT_DESC'),
            'SORT' => 100,
            'INPUT' => [
                'TYPE' => 'STRING',
            ]
        ],

        // Пароль СДЭК
        'CDEK_PASSWORD' => [
            'NAME' => Loc::getMessage('CDEK_PASSWORD'),
            'DESCRIPTION' => Loc::getMessage('CDEK_PASSWORD_DESC'),
            'SORT' => 200,
            'INPUT' => [
                'TYPE' => 'STRING',
            ]
        ],

        // Режим работы (тест/боевой)
        'CDEK_MODE' => [
            'NAME' => Loc::getMessage('CDEK_MODE'),
            'SORT' => 300,
            'INPUT' => [
                'TYPE' => 'ENUM',
                'OPTIONS' => [
                    'TEST' => Loc::getMessage('CDEK_MODE_TEST'),
                    'LIVE' => Loc::getMessage('CDEK_MODE_LIVE'),
                ]
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'TEST',
            ]
        ],

        // Город отправки
        'CDEK_FROM_CITY' => [
            'NAME' => Loc::getMessage('CDEK_FROM_CITY'),
            'DESCRIPTION' => Loc::getMessage('CDEK_FROM_CITY_DESC'),
            'SORT' => 400,
            'INPUT' => [
                'TYPE' => 'STRING',
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'Москва',
            ]
        ],

        // Код города отправки СДЭК
        'CDEK_FROM_CODE' => [
            'NAME' => Loc::getMessage('CDEK_FROM_CODE'),
            'DESCRIPTION' => Loc::getMessage('CDEK_FROM_CODE_DESC'),
            'SORT' => 500,
            'INPUT' => [
                'TYPE' => 'STRING',
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => '44', // Москва
            ]
        ],

        // Время кеширования (минуты)
        'CDEK_CACHE_TIME' => [
            'NAME' => Loc::getMessage('CDEK_CACHE_TIME'),
            'DESCRIPTION' => Loc::getMessage('CDEK_CACHE_TIME_DESC'),
            'SORT' => 600,
            'INPUT' => [
                'TYPE' => 'STRING',
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => '60',
            ]
        ],

        // Наценка на доставку (%)
        'CDEK_EXTRA_CHARGE' => [
            'NAME' => Loc::getMessage('CDEK_EXTRA_CHARGE'),
            'DESCRIPTION' => Loc::getMessage('CDEK_EXTRA_CHARGE_DESC'),
            'SORT' => 700,
            'INPUT' => [
                'TYPE' => 'STRING',
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => '0',
            ]
        ],
    ]
];

return $data;
