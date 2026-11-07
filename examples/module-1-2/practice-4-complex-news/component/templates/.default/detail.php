<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Детальная страница.
 *
 * Маршрутизация уже проверила, что ELEMENT_ID — целое > 0. Существует ли такой
 * элемент, активен ли он и доступен ли текущему пользователю, проверяет
 * дочерний bitrix:news.detail: он и отдаст 404, если элемента нет.
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 */

$component = $this->getComponent();
?>
<div class="study-news-page study-news-page--detail">
    <?php
    $APPLICATION->IncludeComponent(
        'bitrix:news.detail',
        '',
        [
            'IBLOCK_TYPE' => $arParams['IBLOCK_TYPE'],
            'IBLOCK_ID' => $arParams['IBLOCK_ID'],

            'ELEMENT_ID' => $arResult['VARIABLES']['ELEMENT_ID'],

            'CHECK_DATES' => 'Y',

            'FIELD_CODE' => ['ID', 'NAME', 'DETAIL_TEXT', 'DETAIL_PICTURE', 'DATE_ACTIVE_FROM'],
            'PROPERTY_CODE' => [],

            'DISPLAY_DATE' => 'Y',
            'DISPLAY_NAME' => 'Y',
            'DISPLAY_PICTURE' => 'Y',

            'SET_TITLE' => 'Y',
            'ADD_ELEMENT_CHAIN' => 'Y',
            'INCLUDE_IBLOCK_INTO_CHAIN' => 'N',
            'ADD_SECTIONS_CHAIN' => 'N',

            'CACHE_TYPE' => $arParams['CACHE_TYPE'],
            'CACHE_TIME' => $arParams['CACHE_TIME'],
            'CACHE_GROUPS' => $arParams['CACHE_GROUPS'] ? 'Y' : 'N',

            'SET_STATUS_404' => $arParams['SET_STATUS_404'] ? 'Y' : 'N',
            'SHOW_404' => $arParams['SHOW_404'] ? 'Y' : 'N',
            'MESSAGE_404' => 'Новость не найдена.',
        ],
        $component
    );
    ?>
</div>
