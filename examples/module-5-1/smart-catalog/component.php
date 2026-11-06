<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var CBitrixComponent $this */
/** @var array $arParams */
/** @var array $arResult */

$arParams['IBLOCK_ID']  = (int)($arParams['IBLOCK_ID'] ?? 0);
$arParams['SECTION_ID'] = (int)($arParams['SECTION_ID'] ?? 0);
$arParams['SITE_ID']    = $arParams['SITE_ID'] ?: SITE_ID;

$this->arResult = [];
$this->includeComponentTemplate();
