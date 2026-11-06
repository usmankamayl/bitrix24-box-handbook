<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Параметры компонента "Форма обратной связи"
 */

$arComponentParameters = [
    "GROUPS" => [
        "EMAIL_SETTINGS" => [
            "NAME" => "Настройки отправки",
        ],
        "VALIDATION" => [
            "NAME" => "Валидация и защита",
        ],
    ],

    "PARAMETERS" => [
        // === EMAIL НАСТРОЙКИ ===

        "EMAIL_TO" => [
            "PARENT" => "EMAIL_SETTINGS",
            "NAME" => "Email получателя",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],

        "EMAIL_SUBJECT" => [
            "PARENT" => "EMAIL_SETTINGS",
            "NAME" => "Тема письма",
            "TYPE" => "STRING",
            "DEFAULT" => "Сообщение с сайта",
        ],

        "USE_BITRIX_MAIL_EVENT" => [
            "PARENT" => "EMAIL_SETTINGS",
            "NAME" => "Использовать почтовые события Bitrix",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        "EVENT_NAME" => [
            "PARENT" => "EMAIL_SETTINGS",
            "NAME" => "Код почтового события",
            "TYPE" => "STRING",
            "DEFAULT" => "FEEDBACK_FORM",
        ],

        // === ВАЛИДАЦИЯ ===

        "REQUIRED_FIELDS" => [
            "PARENT" => "VALIDATION",
            "NAME" => "Обязательные поля",
            "TYPE" => "LIST",
            "MULTIPLE" => "Y",
            "VALUES" => [
                "NAME" => "Имя",
                "EMAIL" => "Email",
                "PHONE" => "Телефон",
                "MESSAGE" => "Сообщение",
            ],
            "DEFAULT" => ["NAME", "EMAIL", "MESSAGE"],
        ],

        "USE_CAPTCHA" => [
            "PARENT" => "VALIDATION",
            "NAME" => "Использовать капчу",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        "USE_HONEYPOT" => [
            "PARENT" => "VALIDATION",
            "NAME" => "Использовать honeypot (защита от ботов)",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        "MIN_FILL_TIME" => [
            "PARENT" => "VALIDATION",
            "NAME" => "Минимальное время заполнения (сек)",
            "TYPE" => "STRING",
            "DEFAULT" => "3",
        ],

        // === СООБЩЕНИЯ ===

        "SUCCESS_MESSAGE" => [
            "PARENT" => "BASE",
            "NAME" => "Сообщение об успешной отправке",
            "TYPE" => "STRING",
            "DEFAULT" => "Ваше сообщение отправлено!",
        ],

        "ERROR_MESSAGE" => [
            "PARENT" => "BASE",
            "NAME" => "Сообщение об ошибке",
            "TYPE" => "STRING",
            "DEFAULT" => "Ошибка отправки. Попробуйте позже.",
        ],

        // === AJAX ===

        "USE_AJAX" => [
            "PARENT" => "BASE",
            "NAME" => "Использовать Ajax отправку",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],
    ],
];
