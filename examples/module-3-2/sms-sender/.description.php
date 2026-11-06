<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Отправка SMS",
    "DESCRIPTION" => "Форма отправки SMS через внешний сервис (SMSC.ru)",
    "ICON" => "/images/icon.gif",
    "CACHE_PATH" => "Y",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Пользовательские компоненты",
        "CHILD" => [
            "ID" => "communication",
            "NAME" => "Коммуникации"
        ]
    ],
];
