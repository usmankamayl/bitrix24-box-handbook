<?php

/**
 * Страница поиска по инфоблоку.
 *
 * Путь в проекте: /search/index.php (или любой публичный раздел)
 *
 * Что здесь показано:
 *   - входные данные читаются через Request, а не через $_GET;
 *   - параметр проверяется на тип: ?q[]=x даёт массив, а не строку;
 *   - поиск отдаётся инфоблоку, а не собирается SQL-склейкой;
 *   - вывод экранируется в момент вывода (htmlspecialcharsbx), а не на входе.
 *
 * Что здесь сознательно не делается: «очистка от XSS» на входе. Данные
 * пользователя не хранятся в этом примере вообще, а ищутся как есть;
 * экранирование — только в HTML-контексте вывода.
 */

use Bitrix\Main\Context;
use Bitrix\Main\Loader;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

/** @var CMain $APPLICATION */

$APPLICATION->SetTitle('Поиск по каталогу');

$iblockId = 5;            // инфоблок каталога на своём сайте
$maxQueryLength = 100;
$pageSize = 20;

$request = Context::getCurrent()->getRequest();
$rawQuery = $request->getQuery('q');

$errors = [];
$query = '';
$elements = [];

if ($rawQuery !== null) {
    if (!is_string($rawQuery)) {
        // ?q[]=1 — массив. Дальше mb_strlen() на массиве упал бы в TypeError.
        $errors[] = 'Некорректный параметр запроса.';
    } else {
        $query = trim($rawQuery);

        if ($query === '') {
            $errors[] = 'Введите поисковый запрос.';
        } elseif (mb_strlen($query) > $maxQueryLength) {
            $errors[] = 'Запрос длиннее ' . $maxQueryLength . ' символов.';
        }
    }
}

if ($query !== '' && $errors === []) {
    if (!Loader::includeModule('iblock')) {
        $errors[] = 'Модуль инфоблоков недоступен.';
    } else {
        $result = CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            [
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
                // '%' — оператор LIKE: значение экранируется API, символы % и _
                // внутри запроса не превращаются в подстановочные знаки.
                '%NAME' => $query,
            ],
            false,
            ['nTopCount' => $pageSize],
            ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_TEXT', 'PREVIEW_TEXT_TYPE']
        );

        while ($row = $result->Fetch()) {
            $elements[] = $row;
        }
    }
}
?>

<h1>Поиск по каталогу</h1>

<form method="get" action="">
    <input type="text" name="q" value="<?= htmlspecialcharsbx($query) ?>" maxlength="<?= (int)$maxQueryLength ?>">
    <button type="submit">Найти</button>
</form>

<?php foreach ($errors as $error): ?>
    <p class="errortext"><?= htmlspecialcharsbx($error) ?></p>
<?php endforeach; ?>

<?php if ($query !== '' && $errors === []): ?>
    <?php if ($elements === []): ?>
        <p>Ничего не найдено.</p>
    <?php else: ?>
        <p>Найдено: <?= count($elements) ?><?= count($elements) >= $pageSize ? ' (показаны первые ' . (int)$pageSize . ')' : '' ?></p>

        <?php foreach ($elements as $element): ?>
            <div class="search-result">
                <a href="<?= htmlspecialcharsbx((string)$element['DETAIL_PAGE_URL']) ?>">
                    <?= htmlspecialcharsbx((string)$element['NAME']) ?>
                </a>

                <?php if ((string)$element['PREVIEW_TEXT'] !== ''): ?>
                    <p>
                        <?php if ($element['PREVIEW_TEXT_TYPE'] === 'html'): ?>
                            <?php
                            // HTML-описание из инфоблока — доверенный контент админки.
                            // Если он может содержать пользовательский ввод, его надо
                            // прогонять через санитайзер, а не выводить как есть.
                            echo $element['PREVIEW_TEXT'];
                            ?>
                        <?php else: ?>
                            <?= nl2br(htmlspecialcharsbx((string)$element['PREVIEW_TEXT'])) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';