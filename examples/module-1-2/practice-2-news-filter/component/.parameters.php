<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arIblocks = [];

if (Loader::includeModule('iblock')) {
    $rsIblock = IblockTable::getList([
        'select' => ['ID', 'NAME'],
        'filter' => ['=ACTIVE' => 'Y'],
        'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
    ]);

    while ($arIblock = $rsIblock->fetch()) {
        $arIblocks[$arIblock['ID']] = '[' . $arIblock['ID'] . '] ' . $arIblock['NAME'];
    }
}

$arComponentParameters = [
    'GROUPS' => [
        'SORT_SETTINGS' => [
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_GROUP_SORT'),
        ],
        'FILTER_SETTINGS' => [
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_GROUP_FILTER'),
        ],
    ],
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_IBLOCK_ID'),
            'TYPE' => 'LIST',
            'VALUES' => $arIblocks,
            'ADDITIONAL_VALUES' => 'Y',
            'REFRESH' => 'Y',
        ],
        'NEWS_COUNT' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_NEWS_COUNT'),
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
        ],
        'SORT_FIELD' => [
            'PARENT' => 'SORT_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_SORT_FIELD'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'ACTIVE_FROM' => 'ACTIVE_FROM',
                'NAME' => 'NAME',
                'SORT' => 'SORT',
                'ID' => 'ID',
            ],
            'DEFAULT' => 'ACTIVE_FROM',
        ],
        'SORT_ORDER' => [
            'PARENT' => 'SORT_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_SORT_ORDER'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'DESC' => Loc::getMessage('STUDY_NEWS_FILTERED_ORDER_DESC'),
                'ASC' => Loc::getMessage('STUDY_NEWS_FILTERED_ORDER_ASC'),
            ],
            'DEFAULT' => 'DESC',
        ],
        'FILTER_NAME' => [
            'PARENT' => 'FILTER_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_FILTER_NAME'),
            'TYPE' => 'STRING',
            'DEFAULT' => 'arrFilter',
        ],
        'SHOW_SEARCH' => [
            'PARENT' => 'FILTER_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_SHOW_SEARCH'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SHOW_SECTION_FILTER' => [
            'PARENT' => 'FILTER_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_SHOW_SECTION'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TIME' => [
            'DEFAULT' => 3600,
        ],
        'CACHE_FILTER' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_CACHE_FILTER'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_GROUPS' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_FILTERED_CACHE_GROUPS'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
    ],
];
