<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

Loader::includeModule('iblock');

$iblockList = [];
$iterator = \Bitrix\Iblock\IblockTable::getList([
    'select' => ['ID', 'NAME'],
    'filter' => ['IBLOCK_TYPE_ID' => 'catalog'],
]);
while ($row = $iterator->fetch()) {
    $iblockList[$row['ID']] = $row['NAME'];
}

$arComponentParameters = [
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT'      => 'BASE',
            'NAME'        => 'Инфоблок товаров',
            'TYPE'        => 'LIST',
            'VALUES'      => $iblockList,
            'REFRESH'     => 'Y',
            'ADDITIONAL_VALUES' => 'N',
        ],
        'SECTION_ID' => [
            'PARENT' => 'BASE',
            'NAME'   => 'ID раздела (0 — все)',
            'TYPE'   => 'STRING',
            'DEFAULT' => 0,
        ],
        'SITE_ID' => [
            'PARENT' => 'BASE',
            'NAME'   => 'Сайт (для цен)',
            'TYPE'   => 'STRING',
            'DEFAULT' => SITE_ID,
        ],
    ],
];
