<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "CRM форма с кастомными полями",
    "DESCRIPTION" => "Модификация стандартной CRM формы: добавление и кастомизация полей",
    "ICON" => "/images/icon.gif",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Кастомные компоненты",
        "CHILD" => [
            "ID" => "crm",
            "NAME" => "CRM"
        ]
    ],
];
