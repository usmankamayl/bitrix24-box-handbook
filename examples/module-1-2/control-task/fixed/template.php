<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Дефект 5: всё, что пришло из БД, экранируется при выводе.
 * PREVIEW_TEXT типа html здесь намеренно выводится как текст: рендер HTML
 * из инфоблока — отдельный контракт доверия, а не «так исторически сложилось».
 *
 * @var array $arResult
 */

$items = $arResult['ITEMS'] ?? [];
?>
<div class="news-sidebar">
    <?php if ($items === []): ?>
        <p class="news-sidebar__empty">Новостей нет.</p>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>
        <article class="news-sidebar__item">
            <a href="<?= htmlspecialcharsbx($item['DETAIL_PAGE_URL']) ?>">
                <?= htmlspecialcharsbx($item['NAME']) ?>
            </a>
            <div class="news-sidebar__text"><?= htmlspecialcharsbx($item['PREVIEW_TEXT']) ?></div>
        </article>
    <?php endforeach; ?>

    <?= $arResult['NAV_STRING'] ?? '' ?>
</div>
