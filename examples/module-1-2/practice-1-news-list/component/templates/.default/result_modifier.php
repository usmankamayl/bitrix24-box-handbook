<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * result_modifier.php — преобразования, специфичные для ЭТОГО шаблона.
 *
 * Доступны: $arResult (по ссылке), $arParams, $templateFolder, $templateName,
 * $component. Ничего не кешируется отдельно: файл выполняется при построении
 * шаблона, то есть только на промахе кеша.
 *
 * @var array $arParams
 * @var array $arResult
 */

$arResult['HAS_ITEMS'] = !empty($arResult['ITEMS']);
$arResult['ITEMS_BY_YEAR'] = [];

foreach ($arResult['ITEMS'] ?? [] as $item) {
    $year = $item['ACTIVE_FROM'] !== ''
        ? mb_substr($item['ACTIVE_FROM'], 0, 4)
        : 'Без даты';

    $arResult['ITEMS_BY_YEAR'][$year][] = $item;
}
