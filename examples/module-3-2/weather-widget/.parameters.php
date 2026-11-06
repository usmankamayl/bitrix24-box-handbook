<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "GROUPS" => [
        "SETTINGS" => [
            "NAME" => "Настройки",
        ],
    ],
    "PARAMETERS" => [
        "API_KEY" => [
            "PARENT" => "SETTINGS",
            "NAME" => "API ключ OpenWeatherMap",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "CITY" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Город",
            "TYPE" => "STRING",
            "DEFAULT" => "Moscow",
        ],
        "UNITS" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Единицы измерения",
            "TYPE" => "LIST",
            "VALUES" => [
                "metric" => "Цельсий",
                "imperial" => "Фаренгейт",
            ],
            "DEFAULT" => "metric",
        ],
        "LANG" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Язык",
            "TYPE" => "LIST",
            "VALUES" => [
                "ru" => "Русский",
                "en" => "English",
            ],
            "DEFAULT" => "ru",
        ],
        "CACHE_TIME" => [
            "DEFAULT" => 3600,
        ],
    ],
];
