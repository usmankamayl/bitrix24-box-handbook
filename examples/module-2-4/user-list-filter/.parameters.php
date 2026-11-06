<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "PARAMETERS" => [
        "PAGE_SIZE" => [
            "PARENT" => "BASE",
            "NAME" => "Количество элементов на странице",
            "TYPE" => "STRING",
            "DEFAULT" => "20",
        ],
        "SHOW_INACTIVE" => [
            "PARENT" => "BASE",
            "NAME" => "Показывать неактивных пользователей",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "N",
        ],
        "SORT_BY" => [
            "PARENT" => "BASE",
            "NAME" => "Поле сортировки",
            "TYPE" => "LIST",
            "VALUES" => [
                "ID" => "ID",
                "NAME" => "Имя",
                "LAST_NAME" => "Фамилия",
                "EMAIL" => "Email",
                "DATE_REGISTER" => "Дата регистрации",
            ],
            "DEFAULT" => "ID",
        ],
        "SORT_ORDER" => [
            "PARENT" => "BASE",
            "NAME" => "Направление сортировки",
            "TYPE" => "LIST",
            "VALUES" => [
                "ASC" => "По возрастанию",
                "DESC" => "По убыванию",
            ],
            "DEFAULT" => "DESC",
        ],
        "DISPLAY_FIELDS" => [
            "PARENT" => "BASE",
            "NAME" => "Отображаемые поля",
            "TYPE" => "LIST",
            "VALUES" => [
                "ID" => "ID",
                "NAME" => "Имя",
                "LAST_NAME" => "Фамилия",
                "EMAIL" => "Email",
                "LOGIN" => "Логин",
                "ACTIVE" => "Активность",
                "DATE_REGISTER" => "Дата регистрации",
                "LAST_LOGIN" => "Последний вход",
            ],
            "MULTIPLE" => "Y",
            "DEFAULT" => ["ID", "NAME", "LAST_NAME", "EMAIL", "ACTIVE", "DATE_REGISTER"],
        ],
        "CACHE_TIME" => ["DEFAULT" => 3600],
    ],
];
