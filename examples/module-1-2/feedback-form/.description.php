<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Описание компонента "Форма обратной связи"
 */

$arComponentDescription = [
    "NAME" => "Форма обратной связи",
    "DESCRIPTION" => "Форма отправки сообщений с валидацией и защитой от спама",
    "PATH" => [
        "ID" => "custom",
        "NAME" => "Мои компоненты",
        "CHILD" => [
            "ID" => "forms",
            "NAME" => "Формы"
        ]
    ],
    "CACHE_PATH" => "N", // Формы не кешируются
    "SORT" => 20,
];
