<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    'NAME' => 'Live-поиск с автодополнением',
    'DESCRIPTION' => 'Компонент поиска с мгновенными результатами через BX.ajax и debounce',
    'ICON' => '/images/icon.gif',
    'PATH' => [
        'ID' => 'bitrix-course',
        'NAME' => 'Bitrix Course',
        'CHILD' => [
            'ID' => 'module-14-2',
            'NAME' => 'Модуль 14.2: BX.JS',
        ],
    ],
];
