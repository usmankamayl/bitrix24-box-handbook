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
            "NAME" => "ID Highload-блока для логов",
            "TYPE" => "STRING",
            "DEFAULT" => "1",
        ],

        // Количество записей на странице
        "PAGE_SIZE" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Количество записей на странице",
            "TYPE" => "STRING",
            "DEFAULT" => "20",
        ],

        // Фильтр по пользователю
        "FILTER_USER_ID" => [
            "PARENT" => "SETTINGS",
            "NAME" => "ID пользователя для фильтрации (0 - все)",
            "TYPE" => "STRING",
            "DEFAULT" => "0",
        ],

        // Период логирования (дни)
        "LOG_PERIOD_DAYS" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Показать логи за последние N дней (0 - все)",
            "TYPE" => "STRING",
            "DEFAULT" => "7",
        ],

        // Кеширование
        "CACHE_TIME" => [
            "DEFAULT" => 3600
        ],
    ],
];
