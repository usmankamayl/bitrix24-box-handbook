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
        "ELEMENT_ID" => [
            "PARENT" => "BASE",
            "NAME" => "ID текущего элемента",
            "TYPE" => "STRING",
            "DEFAULT" => "={$_REQUEST['ELEMENT_ID']}"
        ],
        "PROPERTY_CODE" => [
            "PARENT" => "BASE",
            "NAME" => "Код свойства со связанными товарами",
            "TYPE" => "STRING",
            "DEFAULT" => "RELATED_PRODUCTS"
        ],
        "ELEMENT_COUNT" => [
            "PARENT" => "BASE",
            "NAME" => "Количество выводимых элементов",
            "TYPE" => "STRING",
            "DEFAULT" => "4"
        ],
        "SHOW_PRICE" => [
            "PARENT" => "BASE",
            "NAME" => "Показывать цену",
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
