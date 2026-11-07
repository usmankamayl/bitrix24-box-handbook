<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'Список с фильтром (учебный)',
    'DESCRIPTION' => 'Элементы инфоблока с серверным фильтром, сортировкой и пагинацией',
    'SORT' => 20,
    'CACHE_PATH' => 'Y',
    'PATH' => [
        'ID' => 'study',
        'NAME' => 'Учебные компоненты',
    ],
];
