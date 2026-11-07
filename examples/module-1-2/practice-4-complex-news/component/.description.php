<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'Новости раздела: список + детальная (учебный)',
    'DESCRIPTION' => 'Комплексный компонент с ЧПУ и дочерними bitrix:news.list / bitrix:news.detail',
    'COMPLEX' => 'Y',
    'SORT' => 40,
    'CACHE_PATH' => 'N',
    'PATH' => [
        'ID' => 'study',
        'NAME' => 'Учебные компоненты',
    ],
];
