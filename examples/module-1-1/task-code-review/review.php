<?php

/**
 * КОД НА РЕВЬЮ (задача 5) — как он пришёл, без правок.
 *
 * Восемь дефектов, разбор — в README.md. Файл не запускать на рабочем сайте:
 * он пишет в /upload/ и показывает данные без проверки прав.
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

$APPLICATION->SetTitle('Отчёт');

$from = $_GET['from'];
$to = $_GET['to'];

$res = CIBlockElement::GetList(
    [],
    [
        'IBLOCK_ID' => 12,
        'ACTIVE' => 'Y',
        '>=DATE_ACTIVE_FROM' => $from,
        '<=DATE_ACTIVE_FROM' => $to,
    ],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_PRICE']
);

echo '<h1>Отчёт за период</h1>';

while ($el = $res->Fetch()) {
    echo $el['NAME'] . ' — ' . $el['PROPERTY_PRICE_VALUE'] . '<br>';
}

file_put_contents(
    $_SERVER['DOCUMENT_ROOT'] . '/upload/report.csv',
    implode(';', [$from, $to]) . "\n",
    FILE_APPEND
);
