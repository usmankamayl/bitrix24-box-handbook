<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "GROUPS" => [
        "SETTINGS" => [
            "NAME" => "Настройки API",
        ],
        "FORM" => [
            "NAME" => "Настройки формы",
        ],
    ],
    "PARAMETERS" => [
        "API_LOGIN" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Логин SMSC.ru",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "API_PASSWORD" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Пароль SMSC.ru",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "SENDER_NAME" => [
            "PARENT" => "SETTINGS",
            "NAME" => "Имя отправителя",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "SUCCESS_MESSAGE" => [
            "PARENT" => "FORM",
            "NAME" => "Сообщение об успехе",
            "TYPE" => "STRING",
            "DEFAULT" => "SMS успешно отправлено",
        ],
        "AJAX_MODE" => [
            "PARENT" => "FORM",
            "NAME" => "Включить AJAX режим",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],
    ],
];
