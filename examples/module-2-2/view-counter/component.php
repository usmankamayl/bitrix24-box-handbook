<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

if (!Loader::includeModule('highloadblock')) {
    ShowError('Модуль highloadblock не установлен');
    return;
}

// Подключаем класс компонента
require_once(__DIR__ . '/class.php');

// Создаем экземпляр и запускаем компонент
$component = new ViewCounterComponent($this);
$component->executeComponent();
