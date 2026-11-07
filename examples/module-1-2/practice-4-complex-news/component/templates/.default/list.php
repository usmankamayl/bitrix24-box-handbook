<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Страница списка. Подключает штатный bitrix:news.list как дочерний компонент
 * и передаёт ему четвёртым аргументом ссылку на родителя — от этого зависит
 * поиск переопределённых шаблонов (см. README).
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 */

$component = $this->getComponent();
?>
<div class="study-news-page">
    <?php
    $APPLICATION->IncludeComponent(
        'bitrix:news.list',
        '',
        [
            'IBLOCK_TYPE' => $arParams['IBLOCK_TYPE'],
            'IBLOCK_ID' => $arParams['IBLOCK_ID'],

            'NEWS_COUNT' => $arParams['NEWS_COUNT'],

            'SORT_BY1' => 'ACTIVE_FROM',
            'SORT_ORDER1' => 'DESC',
            'SORT_BY2' => 'ID',
            'SORT_ORDER2' => 'DESC',

            'CHECK_DATES' => 'Y',

            'FIELD_CODE' => ['ID', 'NAME', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'DATE_ACTIVE_FROM'],
            'PROPERTY_CODE' => [],

            'DETAIL_URL' => $arResult['FOLDER'] . $arResult['URL_TEMPLATES']['detail'],
            'IBLOCK_URL' => $arResult['FOLDER'],

            'DISPLAY_DATE' => 'Y',
            'DISPLAY_NAME' => 'Y',
            'DISPLAY_PICTURE' => 'Y',
            'DISPLAY_PREVIEW_TEXT' => 'Y',

            'SET_TITLE' => 'Y',
            'INCLUDE_IBLOCK_INTO_CHAIN' => 'N',
            'ADD_SECTIONS_CHAIN' => 'N',

            'CACHE_TYPE' => $arParams['CACHE_TYPE'],
            'CACHE_TIME' => $arParams['CACHE_TIME'],
            'CACHE_GROUPS' => $arParams['CACHE_GROUPS'] ? 'Y' : 'N',

            'DISPLAY_TOP_PAGER' => 'N',
            'DISPLAY_BOTTOM_PAGER' => 'Y',
            'PAGER_TITLE' => 'Новости',
            'PAGER_SHOW_ALWAYS' => 'N',
            'PAGER_TEMPLATE' => '.default',
        ],
        $component
    );
    ?>
</div>
