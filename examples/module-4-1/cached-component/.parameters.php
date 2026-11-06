<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
/**
 * Параметры компонента.
 */
$arComponentParameters = [
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Инфоблок',
            'TYPE'    => 'STRING',
            'DEFAULT' => '',
        ],
        'SECTION_ID' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Раздел (необязательно)',
            'TYPE'    => 'STRING',
            'DEFAULT' => '',
        ],
        'PAGE_SIZE' => [
            'PARENT'  => 'BASE',
            'NAME'    => 'Элементов на странице',
            'TYPE'    => 'STRING',
            'DEFAULT' => '20',
        ],
        // Стандартные параметры кеша — Bitrix сам добавляет
        // CACHE_TYPE, CACHE_TIME, CACHE_GROUPS
        'CACHE_TIME' => ['DEFAULT' => '3600'],
    ],
];
