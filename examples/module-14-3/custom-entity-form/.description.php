<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    "NAME" => "Форма редактирования сущности",
    "DESCRIPTION" => "Кастомная форма с различными типами полей, валидацией и автосохранением",
    "ICON" => "/images/icon.gif",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Кастомные компоненты",
        "CHILD" => [
            "ID" => "forms",
            "NAME" => "Формы"
        ]
    ],
];
