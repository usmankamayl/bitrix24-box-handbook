<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Курсы валют",
    "DESCRIPTION" => "Отображает актуальные курсы валют с ЦБ РФ",
    "ICON" => "/images/icon.gif",
    "CACHE_PATH" => "Y",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Пользовательские компоненты",
        "CHILD" => [
            "ID" => "finance",
            "NAME" => "Финансы"
        ]
    ],
];
