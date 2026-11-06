<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\PropertyTable;

/**
 * Параметры компонента "Список с фильтром"
 */

Loader::includeModule('iblock');

// Получаем список инфоблоков
$arIblocks = [];
$rsIblock = IblockTable::getList([
    'select' => ['ID', 'NAME'],
    'filter' => ['ACTIVE' => 'Y'],
    'order' => ['SORT' => 'ASC']
]);

while ($arIblock = $rsIblock->fetch()) {
    $arIblocks[$arIblock['ID']] = '[' . $arIblock['ID'] . '] ' . $arIblock['NAME'];
}

$arComponentParameters = [
    "GROUPS" => [
        "FILTER" => [
            "NAME" => "Настройки фильтра",
        ],
        "PAGINATION" => [
            "NAME" => "Постраничная навигация",
        ],
    ],

    "PARAMETERS" => [
        // === ОСНОВНЫЕ ПАРАМЕТРЫ ===

        "IBLOCK_ID" => [
            "PARENT" => "BASE",
            "NAME" => "Инфоблок",
            "TYPE" => "LIST",
            "VALUES" => $arIblocks,
            "REFRESH" => "Y",
        ],

        "SECTION_ID" => [
            "PARENT" => "BASE",
            "NAME" => "ID раздела (0 - все разделы)",
            "TYPE" => "STRING",
            "DEFAULT" => "0",
        ],

        "INCLUDE_SUBSECTIONS" => [
            "PARENT" => "BASE",
            "NAME" => "Показывать элементы подразделов",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // === СОРТИРОВКА ===

        "SORT_BY" => [
            "PARENT" => "BASE",
            "NAME" => "Поле для сортировки",
            "TYPE" => "LIST",
            "VALUES" => [
                "ID" => "ID",
                "NAME" => "Название",
                "ACTIVE_FROM" => "Дата начала активности",
                "SORT" => "Индекс сортировки",
                "SHOWS" => "Количество показов",
            ],
            "DEFAULT" => "SORT",
        ],

        "SORT_ORDER" => [
            "PARENT" => "BASE",
            "NAME" => "Порядок сортировки",
            "TYPE" => "LIST",
            "VALUES" => [
                "ASC" => "По возрастанию",
                "DESC" => "По убыванию",
            ],
            "DEFAULT" => "ASC",
        ],

        // === ФИЛЬТР ===

        "FILTER_NAME" => [
            "PARENT" => "FILTER",
            "NAME" => "Имя массива фильтра",
            "TYPE" => "STRING",
            "DEFAULT" => "arrFilter",
        ],

        "SHOW_SECTIONS_FILTER" => [
            "PARENT" => "FILTER",
            "NAME" => "Показывать фильтр по разделам",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        "SHOW_PROPERTIES_FILTER" => [
            "PARENT" => "FILTER",
            "NAME" => "Показывать фильтр по свойствам",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        "FILTER_PROPERTIES" => [
            "PARENT" => "FILTER",
            "NAME" => "Свойства для фильтра (коды через запятую)",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],

        "SAVE_FILTER_IN_SESSION" => [
            "PARENT" => "FILTER",
            "NAME" => "Сохранять фильтр в сессии",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // === ПАГИНАЦИЯ ===

        "PAGE_ELEMENT_COUNT" => [
            "PARENT" => "PAGINATION",
            "NAME" => "Количество элементов на странице",
            "TYPE" => "STRING",
            "DEFAULT" => "12",
        ],

        "SHOW_PAGINATION" => [
            "PARENT" => "PAGINATION",
            "NAME" => "Показывать постраничную навигацию",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // === AJAX ===

        "USE_AJAX" => [
            "PARENT" => "BASE",
            "NAME" => "Использовать Ajax для фильтра",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // === КЕШИРОВАНИЕ ===

        "CACHE_TIME" => [
            "DEFAULT" => 3600
        ],

        "CACHE_FILTER" => [
            "PARENT" => "CACHE_SETTINGS",
            "NAME" => "Кешировать при установленном фильтре",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "N",
        ],

        "CACHE_GROUPS" => [
            "PARENT" => "CACHE_SETTINGS",
            "NAME" => "Учитывать права доступа",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],
    ],
];
