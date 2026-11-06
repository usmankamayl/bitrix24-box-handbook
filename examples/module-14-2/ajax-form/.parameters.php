<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'EMAIL_TO' => [
            'PARENT' => 'BASE',
            'NAME' => 'Email получателя',
            'TYPE' => 'STRING',
            'DEFAULT' => 'info@example.com',
        ],
        'SUCCESS_MESSAGE' => [
            'PARENT' => 'BASE',
            'NAME' => 'Сообщение об успехе',
            'TYPE' => 'STRING',
            'DEFAULT' => 'Ваше сообщение отправлено!',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];
