<?php
// examples/module-5-4/order-list-component/.description.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    'NAME'        => 'ЛК: Список заказов (Acme)',
    'DESCRIPTION' => 'Кастомный список заказов с фильтром по статусу, пагинацией и кешем',
    'SORT'        => 10,
    'CACHE_PATH'  => 'Y',
    'PATH'        => ['ID' => 'acme', 'NAME' => 'Acme'],
];
