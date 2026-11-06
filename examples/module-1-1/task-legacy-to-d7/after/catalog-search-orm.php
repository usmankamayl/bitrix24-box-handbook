<?php

/**
 * ЭТАЛОН задачи 4 (вариант 2): где D7 действительно можно применить.
 *
 * Это НЕ «лучшая версия» предыдущего файла, а другой контракт. Что меняется:
 *   - нет свойств: PROPERTY_* в select недоступны без join'ов;
 *   - LIKE задаётся вручную (`%' . $q . '%'`), автообёртки как у `%NAME` нет;
 *   - нет DETAIL_PAGE_URL — URL собирается из CODE;
 *   - нет проверки прав на инфоблок и фильтра по активности разделов;
 *   - `ACTIVE` фильтруется вручную (ORM не подставляет его сам).
 *
 * Поэтому вариант выбирается по задаче: нужна выдача как в каталоге (свойства,
 * SEO-URL, права) — остаётся CIBlockElement; нужен «тонкий» поиск по NAME —
 * ORM короче и предсказуемее.
 *
 * Куда положить: /local/pages/catalog-search-orm.php
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;

/** @var CMain $APPLICATION */

const SEARCH_IBLOCK_ID = 5;
const SEARCH_LIMIT = 10;
const SEARCH_MAX_LEN = 100;

if (!Loader::includeModule('iblock')) {
    \CHTTP::SetStatus('500 Internal Server Error');
    echo 'Модуль iblock не установлен';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    die();
}

$request = Context::getCurrent()->getRequest();
$rawQuery = $request->getQuery('q');
$query = is_string($rawQuery) ? trim($rawQuery) : '';

if (mb_strlen($query) > SEARCH_MAX_LEN) {
    $query = mb_substr($query, 0, SEARCH_MAX_LEN);
}

$items = [];

if ($query !== '') {
    // `%` — LIKE со значением как есть: шаблон собирается вручную.
    // Значит `%` и `_` из запроса пользователя работают как подстановочные —
    // для поиска это безобидно, но проверять надо (см. README).
    $rows = ElementTable::getList([
        'select' => ['ID', 'NAME', 'CODE'],
        'filter' => [
            '=IBLOCK_ID' => SEARCH_IBLOCK_ID,
            '=ACTIVE' => 'Y',
            '%NAME' => '%' . $query . '%',
        ],
        'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        'limit' => SEARCH_LIMIT,
    ]);

    while ($row = $rows->fetch()) {
        $items[] = $row;
    }   // fetchObject() даст объект с типизированными полями — вариант для сервисов
}

$APPLICATION->SetTitle('Поиск по каталогу (ORM)');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog.php';
?>
<h1>Поиск по каталогу</h1>

<form action="" method="get">
    <label for="q">Что ищем</label>
    <input id="q" type="search" name="q" maxlength="<?= SEARCH_MAX_LEN ?>"
           value="<?= htmlspecialcharsbx($query) ?>">
    <button type="submit">Найти</button>
</form>

<?php if ($query === ''): ?>
    <p>Введите запрос.</p>
<?php elseif ($items === []): ?>
    <p>Ничего не найдено по запросу «<?= htmlspecialcharsbx($query) ?>».</p>
<?php else: ?>
    <ul>
        <?php foreach ($items as $item): ?>
            <li>
                <a href="/catalog/<?= rawurlencode((string)(($item['CODE'] ?? '') !== '' ? $item['CODE'] : $item['ID'])) ?>/">
                    <?= htmlspecialcharsbx((string)$item['NAME']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php';
