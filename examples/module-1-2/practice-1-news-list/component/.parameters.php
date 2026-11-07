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
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_GROUP_SORT'),
        ],
        'PAGER_SETTINGS' => [
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_GROUP_PAGER'),
        ],
    ],
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_IBLOCK_ID'),
            'TYPE' => 'LIST',
            'VALUES' => $arIblocks,
            'ADDITIONAL_VALUES' => 'Y',
            'REFRESH' => 'Y',
        ],
        'SECTION_ID' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_SECTION_ID'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'INCLUDE_SUBSECTIONS' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_INCLUDE_SUBSECTIONS'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'NEWS_COUNT' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_NEWS_COUNT'),
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
        ],
        'CHECK_DATES' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_CHECK_DATES'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SORT_BY1' => [
            'PARENT' => 'SORT_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_SORT_BY1'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'ACTIVE_FROM' => 'ACTIVE_FROM',
                'TIMESTAMP_X' => 'TIMESTAMP_X',
                'SORT' => 'SORT',
                'NAME' => 'NAME',
                'ID' => 'ID',
            ],
            'DEFAULT' => 'ACTIVE_FROM',
        ],
        'SORT_ORDER1' => [
            'PARENT' => 'SORT_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_SORT_ORDER1'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'DESC' => Loc::getMessage('STUDY_NEWS_LIST_ORDER_DESC'),
                'ASC' => Loc::getMessage('STUDY_NEWS_LIST_ORDER_ASC'),
            ],
            'DEFAULT' => 'DESC',
        ],
        'PREVIEW_TRUNCATE_LEN' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_TRUNCATE_LEN'),
            'TYPE' => 'STRING',
            'DEFAULT' => '200',
        ],
        'DISPLAY_PAGER' => [
            'PARENT' => 'PAGER_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_DISPLAY_PAGER'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'PAGER_TEMPLATE' => [
            'PARENT' => 'PAGER_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_PAGER_TEMPLATE'),
            'TYPE' => 'STRING',
            'DEFAULT' => '.default',
        ],
        'SET_TITLE' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_SET_TITLE'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TIME' => [
            'DEFAULT' => 3600,
        ],
        'CACHE_GROUPS' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => Loc::getMessage('STUDY_NEWS_LIST_CACHE_GROUPS'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
    ],
];
