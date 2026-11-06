<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Лог запросов пользователей",
    "DESCRIPTION" => "Компонент для логирования и отображения запросов пользователей через Highload-блок",
    "ICON" => "/images/icon.gif",
    "SORT" => 10,
    "CACHE_PATH" => "Y",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Примеры компонентов",
        "CHILD" => [
            "ID" => "highload",
            "NAME" => "Highload-блоки",
        ]
    ],
];
