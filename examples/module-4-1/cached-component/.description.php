<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
/**
 * Описание компонента для визуального редактора.
 */
$arComponentDescription = [
    'NAME'        => 'Список статей (продвинутый кеш)',
    'DESCRIPTION' => 'Компонент с тегированным кешем и поддержкой групп',
    'ICON'        => '/images/news_list.gif',
    'SORT'        => 10,
    'CACHE_TIME'  => 3600,
    'PATH'        => [
        'ID'   => 'content',
        'NAME' => 'Контент',
        'CHILD' => [
            'ID'   => 'articles',
            'NAME' => 'Статьи',
        ],
    ],
];
