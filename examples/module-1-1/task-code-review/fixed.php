<?php

/**
 * ЭТАЛОН задачи 5: тот же отчёт «за период», переписанный после ревью.
 *
 * Куда положить: /local/pages/report.php (доступ — только администратору).
 *
 * Отличия от кода на ревью:
 *   1. права проверяются на сервере до любой работы с данными;
 *   2. входные данные читаются через Request и валидируются как даты;
 *   3. интервал полуоткрытый [from 00:00:00; to+1day 00:00:00);
 *   4. фильтр по DATE_CREATE (дата создания), а не по DATE_ACTIVE_FROM;
 *   5. выборка ограничена, отчёт постранично;
 *   6. вывод экранирован;
 *   7. CSV не пишется в /upload/, а отдаётся в поток: файла на диске нет;
 *   8. GET остаётся GET: побочных эффектов у него нет.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Context;
use Bitrix\Main\Loader;

/** @var CMain $APPLICATION */
/** @var CUser $USER */

const REPORT_IBLOCK_ID = 12;
const REPORT_PAGE_SIZE = 50;
const REPORT_EXPORT_LIMIT = 5000;

// --- 1. Права: до подключения шаблона, до запроса к данным ------------------
if (!$USER->IsAdmin()) {
    \CHTTP::SetStatus('403 Forbidden');
    echo 'Доступ запрещён';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    die();
}

if (!Loader::includeModule('iblock')) {
    \CHTTP::SetStatus('500 Internal Server Error');
    echo 'Модуль iblock не установлен';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    die();
}

// --- 2. Входные данные: тип, формат, значения по умолчанию -------------------
$request = Context::getCurrent()->getRequest();

$asDate = static function ($value): \DateTimeImmutable {
    // Массив из q[]=… отсекается здесь же: is_string() до разбора.
    if (!is_string($value)) {
        return new \DateTimeImmutable('today');
    }

    $value = trim($value);
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    // createFromFormat('!Y-m-d', '2024-13-45') вернёт объект с «нормализованной»
    // датой, а warnings — нет; поэтому проверяем обратное форматирование.
    if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $value) {
        return new \DateTimeImmutable('today');
    }

    return $dt;
};

$from = $asDate($request->getQuery('from'));
$to = $asDate($request->getQuery('to'));

if ($from > $to) {
    [$from, $to] = [$to, $from];
}

// --- 3. Полуоткрытый интервал: последний день не теряется -------------------
$filter = [
    'IBLOCK_ID' => REPORT_IBLOCK_ID,
    'ACTIVE' => 'Y',
    '>=DATE_CREATE' => $from->format('Y-m-d') . ' 00:00:00',
    '<DATE_CREATE' => $to->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
];

$select = ['ID', 'NAME', 'DATE_CREATE', 'PROPERTY_PRICE'];
$order = ['DATE_CREATE' => 'DESC', 'ID' => 'DESC'];

// --- 4. Экспорт CSV: в поток, без файла на диске ---------------------------
$exportRaw = $request->getQuery('export');

if (is_string($exportRaw) && $exportRaw === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="report-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel без него ломает кириллицу
    fputcsv($out, ['ID', 'Название', 'Дата создания', 'Цена'], ';');

    $res = CIBlockElement::GetList(
        $order,
        $filter,
        false,
        ['nTopCount' => REPORT_EXPORT_LIMIT],
        $select
    );

    while ($row = $res->Fetch()) {
        fputcsv($out, [
            (int)$row['ID'],
            (string)$row['NAME'],
            (string)$row['DATE_CREATE'],
            (string)($row['PROPERTY_PRICE_VALUE'] ?? ''),
        ], ';');
    }

    fclose($out);
    die(); // никакого шаблона после бинарного потока
}

// --- 5. HTML-версия: постранично ------------------------------------------
$pageRaw = $request->getQuery('page');
$page = (is_string($pageRaw) && ctype_digit($pageRaw) && (int)$pageRaw > 0) ? (int)$pageRaw : 1;

$res = CIBlockElement::GetList(
    $order,
    $filter,
    false,
    ['nPageSize' => REPORT_PAGE_SIZE, 'iNumPage' => $page],
    $select
);

$rows = [];
while ($row = $res->Fetch()) {
    $rows[] = $row;
}

$APPLICATION->SetTitle('Отчёт за период');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog.php';
?>
<h1>Отчёт за период</h1>

<form action="" method="get">
    <label for="from">С</label>
    <input id="from" type="date" name="from" value="<?= htmlspecialcharsbx($from->format('Y-m-d')) ?>">
    <label for="to">По</label>
    <input id="to" type="date" name="to" value="<?= htmlspecialcharsbx($to->format('Y-m-d')) ?>">
    <button type="submit">Показать</button>
</form>

<p>
    <a href="?from=<?= urlencode($from->format('Y-m-d')) ?>&amp;to=<?= urlencode($to->format('Y-m-d')) ?>&amp;export=csv">
        Скачать CSV (до <?= REPORT_EXPORT_LIMIT ?> строк)
    </a>
</p>

<table>
    <thead>
    <tr><th>ID</th><th>Название</th><th>Создан</th><th>Цена</th></tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="4">За период записей нет.</td></tr>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= (int)$row['ID'] ?></td>
                <td><?= htmlspecialcharsbx((string)$row['NAME']) ?></td>
                <td><?= htmlspecialcharsbx((string)$row['DATE_CREATE']) ?></td>
                <td><?= htmlspecialcharsbx((string)($row['PROPERTY_PRICE_VALUE'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($page > 1): ?>
    <a href="?from=<?= urlencode($from->format('Y-m-d')) ?>&amp;to=<?= urlencode($to->format('Y-m-d')) ?>&amp;page=<?= $page - 1 ?>">Назад</a>
<?php endif; ?>
<?php if (count($rows) === REPORT_PAGE_SIZE): ?>
    <a href="?from=<?= urlencode($from->format('Y-m-d')) ?>&amp;to=<?= urlencode($to->format('Y-m-d')) ?>&amp;page=<?= $page + 1 ?>">Вперёд</a>
<?php endif; ?>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php';
