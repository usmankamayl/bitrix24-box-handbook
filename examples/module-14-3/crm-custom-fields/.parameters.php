<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentParameters = [
    "GROUPS" => [
        "SETTINGS" => [
            "NAME" => "Настройки"
        ],
    ],

    "PARAMETERS" => [
        // Тип сущности CRM
        "ENTITY_TYPE" => [
            "PARENT" => "BASE",
            "NAME" => "Тип сущности",
            "TYPE" => "LIST",
            "VALUES" => [
                "DEAL" => "Сделка",
                "LEAD" => "Лид",
                "CONTACT" => "Контакт",
                "COMPANY" => "Компания",
            ],
            "DEFAULT" => "DEAL"
        ],

        // ID сущности
        "ENTITY_ID" => [
            "PARENT" => "BASE",
            "NAME" => "ID сущности (0 для создания)",
            "TYPE" => "STRING",
            "DEFAULT" => "0"
        ],

        // Включить кастомные поля
        "ENABLE_CUSTOM_FIELDS" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Включить кастомные поля",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y"
        ],

        // Включить модификацию стандартных полей
        "MODIFY_STANDARD_FIELDS" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Модифицировать стандартные поля",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y"
        ],

        // URL успешного сохранения
        "SUCCESS_URL" => [
            "PARENT" => "BASE",
            "NAME" => "URL после успешного сохранения",
            "TYPE" => "STRING",
            "DEFAULT" => ""
        ],
    ],
];
