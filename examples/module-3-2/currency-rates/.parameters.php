<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "GROUPS" => [
        "SETTINGS" => [
            "NAME" => "Настройки",
        ],
    ],
    "PARAMETERS" => [
        "CURRENCIES" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Валюты для отображения (через запятую)",
            "TYPE" => "STRING",
            "DEFAULT" => "USD,EUR,CNY,GBP",
        ],
        "BASE_CURRENCY" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Базовая валюта",
            "TYPE" => "LIST",
            "VALUES" => [
                "RUB" => "Российский рубль",
                "USD" => "Доллар США",
                "EUR" => "Евро",
            ],
            "DEFAULT" => "RUB",
        ],
        "SHOW_DYNAMICS" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Показывать изменение курса",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],
        "CACHE_TIME" => [
            "DEFAULT" => 86400,  // 24 часа
        ],
    ],
];
