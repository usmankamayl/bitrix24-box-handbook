<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Описание компонента "Список с фильтром"
 */

$arComponentDescription = [
    "NAME" => "Список элементов с фильтром",
    "DESCRIPTION" => "Компонент выводит список элементов с возможностью фильтрации по разделам и свойствам",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Мои компоненты",
        "CHILD" => [
            "ID" => "catalog",
            "NAME" => "Каталог"
        ]
    ],
    "CACHE_PATH" => "Y",
    "SORT" => 30,
];
