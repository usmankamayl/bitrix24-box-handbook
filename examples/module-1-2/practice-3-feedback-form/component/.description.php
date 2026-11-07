<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'Форма обратной связи (учебная)',
    'DESCRIPTION' => 'POST с sessid, капчей, ограничением частоты и почтовым событием',
    'SORT' => 30,
    // Форма не кешируется: редактор не должен предлагать кеш для компонента,
    // который зависит от $_SESSION, $_POST и $USER.
    'CACHE_PATH' => 'N',
    'PATH' => [
        'ID' => 'study',
        'NAME' => 'Учебные компоненты',
    ],
];
