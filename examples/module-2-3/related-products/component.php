<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Точка входа компонента "Связанные товары"
 */

$APPLICATION->IncludeComponent(
    "custom:related.products",
    "",
    $arParams,
    $component
);
