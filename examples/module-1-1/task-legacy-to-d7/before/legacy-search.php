<?php

/**
 * АНТИПАТТЕРН — исходный скрипт из задачи 4, оставлен как есть.
 *
 * Не запускать на рабочем сайте: это то, что переписывается в after/.
 * Дефекты: нет пролога/эпилога, нет модуля с проверкой, $_GET без валидации,
 * LIKE '%q%' без ограничения выборки, вывод без экранирования.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('iblock');

$q = $_GET['q'];
$res = CIBlockElement::GetList(
    ['SORT' => 'ASC'],
    ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y', '%NAME' => $q],
    false,
    false,
    ['ID', 'NAME']
);

while ($el = $res->Fetch()) {
    echo '<a href="/catalog/' . $el['ID'] . '/">' . $el['NAME'] . '</a><br>';
}
