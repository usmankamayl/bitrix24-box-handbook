<?php

/**
 * ЭТАЛОН задачи 4 (вариант 1): тот же поиск, но корректная точка входа,
 * валидация, ограничение выборки и экранирование.
 *
 * Куда положить: /local/pages/catalog-search.php (или любой URL через urlrewrite).
 *
 * Что осталось legacy и почему — в README. Коротко: `CIBlockElement::GetList()`
 * здесь не заменяется на ORM, потому что ORM-выборка меняет контракт (нет
 * свойств, другой формат LIKE, нет DETAIL_PAGE_URL, нет прав и фильтра по
 * активности разделов).
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

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

// getQuery() возвращает string|array|null: массив приходит от q[]=x.
$rawQuery = $request->getQuery('q');
$query = is_string($rawQuery) ? trim($rawQuery) : '';

// Длинная строка в LIKE — лишняя нагрузка и обход лимита по длине URL.
if (mb_strlen($query) > SEARCH_MAX_LEN) {
    $query = mb_substr($query, 0, SEARCH_MAX_LEN);
}

$APPLICATION->SetTitle('Поиск по каталогу');

// prolog.php подключает шаблон сайта (header.php) — без него SetTitle никуда не выводится.
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
<?php else: ?>
    <?php
    /*
     * `%NAME` — оператор подстроки: ядро само оборачивает значение в %%.
     * `%=NAME` потребовал бы вписать % вручную в значение — это разные вещи,
     * и «переписать красивее» здесь означает «поменять семантику поиска».
     *
     * nTopCount вместо false: полный обход инфоблока на каждый запрос — это
     * время и память, растущие линейно от размера каталога.
     */
    $filter = [
        'IBLOCK_ID' => SEARCH_IBLOCK_ID,
        'ACTIVE' => 'Y',
        '%NAME' => $query,
    ];

    $items = [];
    $res = CIBlockElement::GetList(
        ['SORT' => 'ASC', 'NAME' => 'ASC'],
        $filter,
        false,
        ['nTopCount' => SEARCH_LIMIT],
        ['ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL']
    );

    while ($row = $res->Fetch()) {
        $items[] = $row;
    }
    ?>

    <?php if ($items === []): ?>
        <p>Ничего не найдено по запросу «<?= htmlspecialcharsbx($query) ?>».</p>
    <?php else: ?>
        <ul>
            <?php foreach ($items as $item): ?>
                <?php
                // DETAIL_PAGE_URL приходит пустым, если у инфоблока не задан шаблон URL.
                $url = ($item['DETAIL_PAGE_URL'] ?? '') !== ''
                    ? (string)$item['DETAIL_PAGE_URL']
                    : '/catalog/' . rawurlencode((string)($item['CODE'] ?? $item['ID'])) . '/';
                ?>
                <li>
                    <a href="<?= htmlspecialcharsbx($url) ?>"><?= htmlspecialcharsbx((string)$item['NAME']) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p>Показано не больше <?= SEARCH_LIMIT ?> результатов.</p>
    <?php endif; ?>
<?php endif; ?>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php';
