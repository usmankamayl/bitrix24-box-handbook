<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Точка входа компонента списка пользователей
 */

// Запрет кеширования (из-за фильтров и пагинации)
$this->setFrameMode(false);
