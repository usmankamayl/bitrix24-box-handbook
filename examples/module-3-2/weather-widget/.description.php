<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Виджет погоды",
    "DESCRIPTION" => "Отображает текущую погоду через OpenWeatherMap API",
    "ICON" => "/images/icon.gif",
    "CACHE_PATH" => "Y",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Пользовательские компоненты",
        "CHILD" => [
            "ID" => "weather",
            "NAME" => "Погода"
        ]
    ],
];
