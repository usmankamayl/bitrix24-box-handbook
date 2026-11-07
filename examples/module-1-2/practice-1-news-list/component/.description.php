<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'Список новостей (учебный)',
    'DESCRIPTION' => 'Активные элементы инфоблока с пагинацией, кешем и тегами инфоблока',
    'SORT' => 10,
    'CACHE_PATH' => 'Y',
    'PATH' => [
        'ID' => 'study',
        'NAME' => 'Учебные компоненты',
    ],
];
