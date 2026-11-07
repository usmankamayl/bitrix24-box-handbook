<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arIblocks = [];
$arIblockTypes = [];

if (Loader::includeModule('iblock')) {
    // Штатный помощник: список типов инфоблоков для селектора.
    $arIblockTypes = \CIBlockParameters::GetIBlockTypes();

    $rsIblock = IblockTable::getList([
        'select' => ['ID', 'NAME'],
        'filter' => ['=ACTIVE' => 'Y'],
        'order' => ['SORT' => 'ASC'],
    ]);

    while ($arIblock = $rsIblock->fetch()) {
        $arIblocks[$arIblock['ID']] = '[' . $arIblock['ID'] . '] ' . $arIblock['NAME'];
    }
}

$arComponentParameters = [
    'PARAMETERS' => [
        // Структура SEF: редактор сам построит SEF_MODE и SEF_FOLDER.
        // DEFAULT — шаблон URL, VARIABLES — переменные, извлекаемые из адреса.
        'SEF_MODE' => [
            'list' => [
                'NAME' => Loc::getMessage('STUDY_NEWS_SEF_LIST'),
                'DEFAULT' => '',
                'VARIABLES' => [],
            ],
            'detail' => [
                'NAME' => Loc::getMessage('STUDY_NEWS_SEF_DETAIL'),
                'DEFAULT' => '#ELEMENT_ID#/',
                'VARIABLES' => ['ELEMENT_ID'],
            ],
        ],
        // Нужно только для SEF_MODE = N: имена переменных в query-строке.
        'VARIABLE_ALIASES' => [
            'ELEMENT_ID' => ['NAME' => 'ID элемента'],
        ],
        'IBLOCK_TYPE' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_IBLOCK_TYPE'),
            'TYPE' => 'LIST',
            'VALUES' => $arIblockTypes,
            'REFRESH' => 'Y',
        ],
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_IBLOCK_ID'),
            'TYPE' => 'LIST',
            'VALUES' => $arIblocks,
            'ADDITIONAL_VALUES' => 'Y',
            'REFRESH' => 'Y',
        ],
        'NEWS_COUNT' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_COUNT'),
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
        ],
        'SET_STATUS_404' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_SET_STATUS_404'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SHOW_404' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_SHOW_404'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
        ],
        'FILE_404' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILE_404'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'CACHE_TIME' => [
            'DEFAULT' => 3600,
        ],
        'CACHE_GROUPS' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_CACHE_GROUPS'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
    ],
];
