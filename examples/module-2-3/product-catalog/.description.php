<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Каталог товаров с фильтром",
    "DESCRIPTION" => "Выводит список товаров с возможностью фильтрации по свойствам",
    "ICON" => "/images/icon.gif",
    "CACHE_PATH" => "Y",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Учебные компоненты",
        "CHILD" => [
            "ID" => "catalog",
            "NAME" => "Каталог"
        ]
    ]
];
