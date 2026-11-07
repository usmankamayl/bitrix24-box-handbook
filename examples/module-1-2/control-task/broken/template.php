<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
?>
<div class="news-sidebar">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <article class="news-sidebar__item">
            <a href="<?= $item['DETAIL_PAGE_URL'] ?>"><?= $item['NAME'] ?></a>
            <div class="news-sidebar__text"><?= $item['PREVIEW_TEXT'] ?></div>
        </article>
    <?php endforeach; ?>

    <?= $arResult['NAV_STRING'] ?>
</div>
