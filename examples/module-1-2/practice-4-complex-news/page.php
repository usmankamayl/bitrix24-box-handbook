<?php

/**
 * Страница раздела: сюда попадают все адреса, начинающиеся с /study-news/.
 * Файл лежит в DOCUMENT_ROOT/study-news/index.php
 *
 * Подключает комплексный компонент. Собственной логики у страницы нет —
 * вся маршрутизация внутри компонента.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->IncludeComponent(
    'study:news',
    '.default',
    [
        'IBLOCK_TYPE' => 'content',
        'IBLOCK_ID' => 3,

        'SEF_MODE' => 'Y',
        'SEF_FOLDER' => '/study-news/',
        'SEF_URL_TEMPLATES' => [
            'list' => '',
            'detail' => '#ELEMENT_ID#/',
        ],

        'NEWS_COUNT' => 10,

        'SET_STATUS_404' => 'Y',
        'SHOW_404' => 'N',

        'CACHE_TYPE' => 'A',
        'CACHE_TIME' => 3600,
        'CACHE_GROUPS' => 'Y',
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
