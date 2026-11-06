<?php
// examples/module-5-4/order-list-component/.parameters.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    'PARAMETERS' => [
        'ORDERS_PER_PAGE' => [
            'NAME'    => 'Заказов на странице',
            'TYPE'    => 'NUMBER',
            'DEFAULT' => 10,
        ],
        'STATUS_FILTER' => [
            'NAME'    => 'Фильтр по статусу по умолчанию',
            'TYPE'    => 'STRING',
            'DEFAULT' => '',
        ],
        'CACHE_TIME' => [
            'NAME'    => 'Время кеширования (сек)',
            'TYPE'    => 'NUMBER',
            'DEFAULT' => 300,
        ],
    ],
];
