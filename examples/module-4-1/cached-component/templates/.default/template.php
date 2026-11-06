<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
/**
 * Шаблон компонента.
 *
 * ВАЖНО: шаблон попадает в кеш компонента!
 * Если здесь выводишь персонализированное (имя пользователя, корзину) —
 * это закешируется и покажется всем.
 *
 * Для персонализированного вывода внутри кешированного шаблона:
 * - используй deferred-загрузку (ajax после загрузки)
 * - или frame (см. модуль 4.2 Composite)
 */

/** @var array $arResult */
/** @var ArticlesListComponent $component */

if (!empty($arResult['EMPTY'])): ?>
    <div class="articles-empty">Статей пока нет.</div>
    <?php return; ?>
<?php endif; ?>

<div class="articles-list">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <article class="article-card" id="article-<?= $item['ID'] ?>">
            <?php if ($item['PREVIEW_PICTURE_SRC']): ?>
                <img src="<?= $item['PREVIEW_PICTURE_SRC'] ?>"
                     alt="<?= htmlspecialcharsbx($item['NAME']) ?>"
                     loading="lazy">
            <?php endif; ?>
            <h3>
                <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                    <?= htmlspecialcharsbx($item['NAME']) ?>
                </a>
            </h3>
            <time datetime="<?= $item['DATE_ACTIVE_FROM'] ?>">
                <?= FormatDate('d F Y', MakeTimeStamp($item['DATE_ACTIVE_FROM'])) ?>
            </time>
            <p><?= $item['PREVIEW_TEXT'] ?></p>
        </article>
    <?php endforeach; ?>
</div>

<?php
// Пагинация — внутри кеша. Ссылки пагинации должны быть
// cache-friendly (GET-параметр PAGEN_1 — часть ключа кеша)
if ($arResult['NAV_RESULT']['NavPageCount'] > 1):
?>
<nav class="pagination">
    <?php for ($p = 1; $p <= $arResult['NAV_RESULT']['NavPageCount']; $p++): ?>
        <?php if ($p === (int)$arResult['NAV_RESULT']['NavPageNomer']): ?>
            <span class="current"><?= $p ?></span>
        <?php else: ?>
            <a href="?PAGEN_1=<?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>
