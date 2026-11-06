<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;

Loader::includeModule('iblock');

// Получаем список инфоблоков
$arIBlocks = [];
$rsIBlock = IblockTable::getList([
    'select' => ['ID', 'NAME'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order' => ['SORT' => 'ASC']
]);

while ($arIBlock = $rsIBlock->fetch()) {
    $arIBlocks[$arIBlock['ID']] = '[' . $arIBlock['ID'] . '] ' . $arIBlock['NAME'];
}

$arComponentParameters = [
    "GROUPS" => [],
    "PARAMETERS" => [
        "IBLOCK_ID" => [
            "PARENT" => "BASE",
            "NAME" => "Инфоблок",
            "TYPE" => "LIST",
            "VALUES" => $arIBlocks,
            "REFRESH" => "Y"
        ],
        "ELEMENT_COUNT" => [
            "PARENT" => "BASE",
            "NAME" => "Количество элементов на странице",
            "TYPE" => "STRING",
            "DEFAULT" => "12"
        ],
        "FILTER_PRICE_FROM" => [
            "PARENT" => "BASE",
            "NAME" => "Показывать фильтр 'Цена от'",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y"
        ],
        "FILTER_PRICE_TO" => [
            "PARENT" => "BASE",
            "NAME" => "Показывать фильтр 'Цена до'",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y"
        ],
        "FILTER_SECTION" => [
            "PARENT" => "BASE",
            "NAME" => "Показывать фильтр по разделам",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y"
        ],
        "CACHE_TIME" => ["DEFAULT" => 3600],
        "CACHE_GROUPS" => [
            "PARENT" => "CACHE_SETTINGS",
            "NAME" => "Учитывать права доступа",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y"
        ]
    ]
];
