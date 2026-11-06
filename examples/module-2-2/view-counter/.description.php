<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Счетчик просмотров",
    "DESCRIPTION" => "Компонент для подсчета и отображения просмотров элементов через Highload-блок с агрегацией данных",
    "ICON" => "/images/icon.gif",
    "SORT" => 20,
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
