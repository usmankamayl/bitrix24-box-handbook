<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Подключаем класс компонента
if (!isset($arParams['SKIP_TEMPLATE'])) {
    $arParams['SKIP_TEMPLATE'] = 'N';
}

$this->__component->arParams = $arParams;
$this->__component->executeComponent();
