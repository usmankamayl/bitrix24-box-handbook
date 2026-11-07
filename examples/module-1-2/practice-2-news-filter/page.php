<?php

/**
 * Страница со списком и внешним фильтром.
 * Кладётся в DOCUMENT_ROOT, например /study-news-filtered/index.php.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Новости с фильтром');

// Внешний фильтр: так список связывается с умным фильтром или с кодом страницы.
// Массив должен существовать до вызова компонента — компонент читает его по имени.
$GLOBALS['arrFilter'] = [
    // 'PROPERTY_TAG' => 'важное',
];

$APPLICATION->IncludeComponent(
    'study:news.filtered',
    '.default',
    [
        'IBLOCK_ID' => 3,
        'NEWS_COUNT' => 10,
        'SORT_FIELD' => 'ACTIVE_FROM',
        'SORT_ORDER' => 'DESC',
        'FILTER_NAME' => 'arrFilter',
        'SHOW_SEARCH' => 'Y',
        'SHOW_SECTION_FILTER' => 'Y',
        'CACHE_TYPE' => 'A',
        'CACHE_TIME' => 3600,
        'CACHE_FILTER' => 'Y',
        'CACHE_GROUPS' => 'Y',
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
