<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "История изменений заказов",
    "DESCRIPTION" => "Компонент для логирования и отображения истории изменений заказов через Highload-блок",
    "ICON" => "/images/icon.gif",
    "SORT" => 30,
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
