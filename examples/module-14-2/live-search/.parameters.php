<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    'GROUPS' => [],
    'PARAMETERS' => [
        'IBLOCK_TYPE' => [
            'PARENT' => 'BASE',
            'NAME' => 'Тип инфоблока',
            'TYPE' => 'STRING',
            'DEFAULT' => 'content',
        ],
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'Инфоблок',
            'TYPE' => 'STRING',
            'DEFAULT' => '1',
        ],
        'MIN_QUERY_LENGTH' => [
            'PARENT' => 'BASE',
            'NAME' => 'Минимальная длина запроса',
            'TYPE' => 'STRING',
            'DEFAULT' => '3',
        ],
        'MAX_RESULTS' => [
            'PARENT' => 'BASE',
            'NAME' => 'Максимум результатов',
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
        ],
        'CACHE_TIME' => ['DEFAULT' => 3600],
    ],
];
