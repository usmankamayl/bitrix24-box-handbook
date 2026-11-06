<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "PARAMETERS" => [
        "USER_ID" => [
            "PARENT" => "BASE",
            "NAME" => "ID пользователя (0 - текущий)",
            "TYPE" => "STRING",
            "DEFAULT" => "0",
        ],
        "ONLY_OWN_PROFILE" => [
            "PARENT" => "BASE",
            "NAME" => "Разрешить редактирование только своего профиля",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],
        "SUCCESS_PAGE" => [
            "PARENT" => "BASE",
            "NAME" => "Страница после успешного сохранения",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "EDITABLE_FIELDS" => [
            "PARENT" => "BASE",
            "NAME" => "Редактируемые поля",
            "TYPE" => "LIST",
            "VALUES" => [
                "NAME" => "Имя",
                "LAST_NAME" => "Фамилия",
                "SECOND_NAME" => "Отчество",
                "EMAIL" => "Email",
                "PERSONAL_PHONE" => "Телефон",
                "PERSONAL_MOBILE" => "Мобильный",
                "PERSONAL_PHOTO" => "Фото",
                "PERSONAL_BIRTHDAY" => "День рождения",
                "PERSONAL_GENDER" => "Пол",
                "WORK_COMPANY" => "Компания",
                "WORK_POSITION" => "Должность",
            ],
            "MULTIPLE" => "Y",
            "DEFAULT" => ["NAME", "LAST_NAME", "EMAIL", "PERSONAL_PHONE", "PERSONAL_PHOTO"],
        ],
        "CACHE_TIME" => ["DEFAULT" => 0],
    ],
];
