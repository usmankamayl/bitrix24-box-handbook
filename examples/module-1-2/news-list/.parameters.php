<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;

/**
 * Параметры компонента "Список новостей"
 *
 * Этот файл определяет настройки, которые будут доступны
 * при размещении компонента на странице
 */

// Загружаем модуль инфоблоков для получения списка инфоблоков
Loader::includeModule('iblock');

// Получаем список инфоблоков для селектора
$arIblocks = [];
$rsIblock = IblockTable::getList([
    'select' => ['ID', 'NAME', 'IBLOCK_TYPE_ID'],
    'filter' => ['ACTIVE' => 'Y'],
    'order' => ['SORT' => 'ASC']
]);

while ($arIblock = $rsIblock->fetch()) {
    $arIblocks[$arIblock['ID']] = '[' . $arIblock['ID'] . '] ' . $arIblock['NAME'];
}

$arComponentParameters = [
    // Группы параметров (для организации в интерфейсе)
    "GROUPS" => [
        "DISPLAY" => [
            "NAME" => "Настройки отображения",
        ],
    ],

    "PARAMETERS" => [
        // === ОСНОВНЫЕ ПАРАМЕТРЫ ===

        // ID инфоблока
        "IBLOCK_ID" => [
            "PARENT" => "BASE",
            "NAME" => "Инфоблок",
            "TYPE" => "LIST",
            "VALUES" => $arIblocks,
            "REFRESH" => "Y", // Обновить страницу при изменении
        ],

        // Количество элементов
        "COUNT" => [
            "PARENT" => "BASE",
            "NAME" => "Количество элементов",
            "TYPE" => "STRING",
            "DEFAULT" => "10",
        ],

        // === СОРТИРОВКА ===

        // Поле для сортировки
        "SORT_BY" => [
            "PARENT" => "BASE",
            "NAME" => "Поле для сортировки",
            "TYPE" => "LIST",
            "VALUES" => [
                "ID" => "ID",
                "NAME" => "Название",
                "ACTIVE_FROM" => "Дата начала активности",
                "SORT" => "Индекс сортировки",
                "TIMESTAMP_X" => "Дата изменения",
            ],
            "DEFAULT" => "ACTIVE_FROM",
        ],

        // Порядок сортировки
        "SORT_ORDER" => [
            "PARENT" => "BASE",
            "NAME" => "Порядок сортировки",
            "TYPE" => "LIST",
            "VALUES" => [
                "ASC" => "По возрастанию",
                "DESC" => "По убыванию",
            ],
            "DEFAULT" => "DESC",
        ],

        // === НАСТРОЙКИ ОТОБРАЖЕНИЯ ===

        // Показывать картинки
        "SHOW_PICTURE" => [
            "PARENT" => "DISPLAY",
            "NAME" => "Показывать изображения",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // Показывать дату
        "SHOW_DATE" => [
            "PARENT" => "DISPLAY",
            "NAME" => "Показывать дату",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // Показывать превью текст
        "SHOW_PREVIEW_TEXT" => [
            "PARENT" => "DISPLAY",
            "NAME" => "Показывать анонс",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "Y",
        ],

        // Длина анонса
        "PREVIEW_TEXT_LENGTH" => [
            "PARENT" => "DISPLAY",
            "NAME" => "Максимальная длина анонса (0 - без ограничений)",
            "TYPE" => "STRING",
            "DEFAULT" => "200",
        ],

        // === НАСТРОЙКИ КЕШИРОВАНИЯ ===

        "CACHE_TIME" => [
            "DEFAULT" => 3600
        ],

        "CACHE_FILTER" => [
            "PARENT" => "CACHE_SETTINGS",
            "NAME" => "Кешировать при установленном фильтре",
            "TYPE" => "CHECKBOX",
            "DEFAULT" => "N",
        ],
    ],
];
