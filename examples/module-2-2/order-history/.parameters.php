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
            "NAME" => "ID Highload-блока для истории",
            "TYPE" => "STRING",
            "DEFAULT" => "3",
        ],

        // ID заказа
        "ORDER_ID" => [
            "PARENT" => "SETTINGS",
            "NAME" => "ID заказа (0 - показать все)",
            "TYPE" => "STRING",
            "DEFAULT" => "0",
        ],

        // Количество записей
        "PAGE_SIZE" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Количество записей на странице",
            "TYPE" => "STRING",
            "DEFAULT" => "50",
        ],

        // Типы изменений для фильтрации
        "FILTER_CHANGE_TYPE" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Тип изменений (пусто - все)",
            "TYPE" => "LIST",
            "VALUES" => [
                "" => "Все типы",
                "status" => "Изменение статуса",
                "payment" => "Оплата",
                "shipment" => "Отгрузка",
                "properties" => "Свойства заказа",
                "price" => "Изменение цены",
            ],
            "DEFAULT" => "",
        ],

        // Период
        "FILTER_DAYS" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Показать за последние N дней (0 - все)",
            "TYPE" => "STRING",
            "DEFAULT" => "0",
        ],

        // Кеширование
        "CACHE_TIME" => [
            "DEFAULT" => 3600
        ],
    ],
];
