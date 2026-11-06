<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentParameters = [
    "GROUPS" => [
        "SETTINGS" => [
            "NAME" => "Настройки формы"
        ],
    ],

    "PARAMETERS" => [
        // ID редактируемой сущности
        "ENTITY_ID" => [
            "PARENT" => "BASE",
            "NAME" => "ID сущности (0 для создания новой)",
            "TYPE" => "STRING",
            "DEFAULT" => "0"
        ],

        // Включить автосохранение
        "ENABLE_AUTOSAVE" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Включить автосохранение",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y"
        ],

        // Интервал автосохранения
        "AUTOSAVE_INTERVAL" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Интервал автосохранения (секунды)",
            "TYPE" => "STRING",
            "DEFAULT" => "30"
        ],

        // URL успешного сохранения
        "SUCCESS_URL" => [
            "PARENT" => "BASE",
            "NAME" => "URL после успешного сохранения",
            "TYPE" => "STRING",
            "DEFAULT" => ""
        ],

        // Кеширование
        "CACHE_TIME" => [
            "DEFAULT" => 3600
        ],
    ],
];
