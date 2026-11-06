<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "PARAMETERS" => [
        "AUTO_AUTH" => [
            "PARENT" => "BASE",
            "NAME" => "Автоматическая авторизация после регистрации",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],
        "SUCCESS_PAGE" => [
            "PARENT" => "BASE",
            "NAME" => "Страница после успешной регистрации",
            "TYPE" => "STRING",
            "DEFAULT" => "/",
        ],
        "USER_GROUPS" => [
            "PARENT" => "BASE",
            "NAME" => "Группы для нового пользователя",
            "TYPE" => "STRING",
            "DEFAULT" => "2",
            "MULTIPLE" => "Y",
        ],
        "CACHE_TIME" => ["DEFAULT" => 0],
    ],
];
