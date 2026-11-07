<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * component_epilog.php — выполняется на КАЖДОМ запросе, включая попадание в кеш:
 * ядро вызывает его из startResultCache() уже после печати кешированного HTML.
 *
 * Здесь $arResult содержит только те ключи, что перечислены в
 * $this->setResultCacheKeys() — то есть ITEMS_COUNT, но не ITEMS.
 *
 * Ниже — демонстрация порядка вызовов, а не полезная функция: добавление
 * элемента цепочки навигации видно и на холодном кеше, и на горячем.
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $component
 */

$count = (int)($arResult['ITEMS_COUNT'] ?? 0);

if ($count > 0) {
    $APPLICATION->AddChainItem('Найдено: ' . $count);
}
