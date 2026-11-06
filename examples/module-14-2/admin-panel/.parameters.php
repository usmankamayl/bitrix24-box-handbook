<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID инфоблока для управления',
            'TYPE' => 'STRING',
            'DEFAULT' => '1',
        ],
        'PAGE_SIZE' => [
            'PARENT' => 'BASE',
            'NAME' => 'Элементов на странице',
            'TYPE' => 'STRING',
            'DEFAULT' => '20',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];
