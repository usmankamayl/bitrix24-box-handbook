<?php

/**
 * Публичная страница с компонентом.
 * Положить в DOCUMENT_ROOT, например /study-news/index.php.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Новости');

$APPLICATION->IncludeComponent(
    'study:news.list',
    '.default',
    [
        'IBLOCK_ID' => 3,
        'NEWS_COUNT' => 10,
        'SORT_BY1' => 'ACTIVE_FROM',
        'SORT_ORDER1' => 'DESC',
        'CHECK_DATES' => 'Y',
        'PREVIEW_TRUNCATE_LEN' => 200,
        'DISPLAY_PAGER' => 'Y',
        'PAGER_TEMPLATE' => '.default',
        'SET_TITLE' => 'N',
        'CACHE_TYPE' => 'A',
        'CACHE_TIME' => 3600,
        'CACHE_GROUPS' => 'Y',
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
