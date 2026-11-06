<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "GROUPS" => [
        "SETTINGS" => [
            "NAME" => "Настройки",
        ],
    ],
    "PARAMETERS" => [
        // ID Highload-блока
        "HLBLOCK_ID" => [
            "PARENT" => "SETTINGS",
            "NAME" => "ID Highload-блока для счетчиков",
            "TYPE" => "STRING",
            "DEFAULT" => "2",
        ],

        // Тип сущности (product, article, page и т.д.)
        "ENTITY_TYPE" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Тип сущности",
            "TYPE" => "LIST",
            "VALUES" => [
                "product" => "Товар",
                "article" => "Статья",
                "page" => "Страница",
                "video" => "Видео",
            ],
            "DEFAULT" => "article",
        ],

        // ID элемента
        "ENTITY_ID" => [
            "PARENT" => "SETTINGS",
            "NAME" => "ID элемента (0 - текущая страница)",
            "TYPE" => "STRING",
            "DEFAULT" => "0",
        ],

        // Учитывать уникальные просмотры
        "UNIQUE_VIEWS" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Учитывать только уникальные просмотры",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // Период уникальности (в секундах)
        "UNIQUE_PERIOD" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Период уникальности просмотра (секунд)",
            "TYPE" => "STRING",
            "DEFAULT" => "3600",
        ],

        // Режим работы
        "MODE" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Режим работы",
            "TYPE" => "LIST",
            "VALUES" => [
                "show" => "Только показывать счетчик",
                "count" => "Только считать просмотры",
                "both" => "И считать, и показывать",
            ],
            "DEFAULT" => "both",
        ],

        // Кеширование
        "CACHE_TIME" => [
            "DEFAULT" => 3600
        ],
    ],
];
