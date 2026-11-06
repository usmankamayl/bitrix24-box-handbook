<?php

/**
 * Одноразовый скрипт создания HL-блока PartnerCategoryMarkup.
 * Запустить один раз, например через /bitrix/admin/php_command_line.php,
 * либо вызвать из install.php модуля при реальной разработке.
 */

use Bitrix\Highloadblock as HL;
use Bitrix\Main\Loader;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('highloadblock')) {
    die('Модуль highloadblock не установлен');
}

$existing = HL\HighloadBlockTable::getRow(['filter' => ['NAME' => 'PartnerCategoryMarkup']]);
if ($existing) {
    die('HL-блок уже существует, ID = ' . $existing['ID']);
}

$result = HL\HighloadBlockTable::add([
    'NAME'          => 'PartnerCategoryMarkup',
    'TABLE_NAME'    => 'bc_partner_category_markup',
]);

if (!$result->isSuccess()) {
    die('Ошибка создания HL-блока: ' . implode('; ', $result->getErrorMessages()));
}

$hlBlockId = $result->getId();
$entity = HL\HighloadBlockTable::compileEntity($hlBlockId);
$entityClass = $entity->getDataClass();

$fields = [
    'UF_SECTION_ID' => [
        'XML_ID'            => 'UF_SECTION_ID',
        'FIELD_NAME'        => 'UF_SECTION_ID',
        'USER_TYPE_ID'      => 'integer',
        'MANDATORY'         => 'Y',
        'SORT'              => 100,
        'EDIT_FORM_LABEL'   => ['ru' => 'ID раздела каталога'],
    ],
    'UF_USER_GROUP_ID' => [
        'XML_ID'            => 'UF_USER_GROUP_ID',
        'FIELD_NAME'        => 'UF_USER_GROUP_ID',
        'USER_TYPE_ID'      => 'integer',
        'MANDATORY'         => 'Y',
        'SORT'              => 200,
        'EDIT_FORM_LABEL'   => ['ru' => 'ID группы пользователей (партнёра)'],
    ],
    'UF_MARKUP' => [
        'XML_ID'            => 'UF_MARKUP',
        'FIELD_NAME'        => 'UF_MARKUP',
        'USER_TYPE_ID'      => 'double',
        'MANDATORY'         => 'Y',
        'SORT'              => 300,
        'EDIT_FORM_LABEL'   => ['ru' => 'Наценка, %'],
    ],
];

$userTypeManager = \CUserTypeManager::class;
foreach ($fields as $code => $field) {
    $uf = [
        'ENTITY_ID'    => 'HLBLOCK_' . $hlBlockId,
        'FIELD_NAME'   => $field['FIELD_NAME'],
        'USER_TYPE_ID' => $field['USER_TYPE_ID'],
        'XML_ID'       => $field['XML_ID'],
        'SORT'         => $field['SORT'],
        'MULTIPLE'     => 'N',
        'MANDATORY'    => $field['MANDATORY'],
        'EDIT_FORM_LABEL'   => $field['EDIT_FORM_LABEL'],
        'LIST_COLUMN_LABEL' => $field['EDIT_FORM_LABEL'],
    ];
    $obUserField = new CUserTypeEntity();
    $obUserField->Add($uf);
}

echo "HL-блок создан, ID = {$hlBlockId}, таблица bc_partner_category_markup" . PHP_EOL;
echo "Класс данных: {$entityClass}" . PHP_EOL;
