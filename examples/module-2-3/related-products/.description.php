<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Связанные товары",
    "DESCRIPTION" => "Выводит список связанных товаров на основе свойства-привязки",
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
