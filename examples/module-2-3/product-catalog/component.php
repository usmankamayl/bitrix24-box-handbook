<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Точка входа компонента "Каталог товаров с фильтром"
 *
 * Этот файл подключается на странице и запускает компонент
 */

$APPLICATION->IncludeComponent(
    "custom:product.catalog",
    "",
    $arParams,
    $component
);
