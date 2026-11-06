<?php
/**
 * Описание обработчика платежной системы ЮKassa
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$psTitle = Loc::getMessage("YOOKASSA_TITLE");
$psDescription = Loc::getMessage("YOOKASSA_DESCRIPTION");

// Параметры обработчика
$data = [
    'NAME' => $psTitle,
    'DESCRIPTION' => $psDescription,
    'SORT' => 100,
    'CODES' => [
        // ID магазина в ЮKassa
        "YOOKASSA_SHOP_ID" => [
            "NAME" => Loc::getMessage("YOOKASSA_SHOP_ID"),
            "DESCRIPTION" => Loc::getMessage("YOOKASSA_SHOP_ID_DESC"),
            "SORT" => 100,
            "INPUT" => [
                'TYPE' => 'STRING'
            ]
        ],

        // Секретный ключ
        "YOOKASSA_SECRET_KEY" => [
            "NAME" => Loc::getMessage("YOOKASSA_SECRET_KEY"),
            "DESCRIPTION" => Loc::getMessage("YOOKASSA_SECRET_KEY_DESC"),
            "SORT" => 200,
            "INPUT" => [
                'TYPE' => 'STRING'
            ]
        ],

        // Режим работы (тест/боевой)
        "YOOKASSA_MODE" => [
            "NAME" => Loc::getMessage("YOOKASSA_MODE"),
            "SORT" => 300,
            "INPUT" => [
                'TYPE' => 'ENUM',
                'OPTIONS' => [
                    'TEST' => Loc::getMessage("YOOKASSA_MODE_TEST"),
                    'LIVE' => Loc::getMessage("YOOKASSA_MODE_LIVE"),
                ]
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'TEST',
            ]
        ],

        // Автоматическое подтверждение платежа
        "YOOKASSA_AUTO_CAPTURE" => [
            "NAME" => Loc::getMessage("YOOKASSA_AUTO_CAPTURE"),
            "DESCRIPTION" => Loc::getMessage("YOOKASSA_AUTO_CAPTURE_DESC"),
            "SORT" => 400,
            "INPUT" => [
                'TYPE' => 'Y/N'
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => 'Y',
            ]
        ],

        // Метод оплаты по умолчанию
        "YOOKASSA_PAYMENT_METHOD" => [
            "NAME" => Loc::getMessage("YOOKASSA_PAYMENT_METHOD"),
            "SORT" => 500,
            "INPUT" => [
                'TYPE' => 'ENUM',
                'OPTIONS' => [
                    '' => Loc::getMessage("YOOKASSA_PAYMENT_METHOD_ANY"),
                    'bank_card' => Loc::getMessage("YOOKASSA_PAYMENT_METHOD_CARD"),
                    'yoo_money' => Loc::getMessage("YOOKASSA_PAYMENT_METHOD_YOOMONEY"),
                    'qiwi' => Loc::getMessage("YOOKASSA_PAYMENT_METHOD_QIWI"),
                    'sberbank' => Loc::getMessage("YOOKASSA_PAYMENT_METHOD_SBERBANK"),
                ]
            ],
            'DEFAULT' => [
                'PROVIDER_VALUE' => '',
            ]
        ],
    ]
];
