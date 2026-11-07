<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Контракт: в $arResult лежат ИСХОДНЫЕ значения (Fetch без HTML-подготовки),
 * экранирование выполняется здесь, при выводе.
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 * @var string $templateFolder
 */

$items = $arResult['ITEMS'] ?? [];
?>
<div class="study-news">
    <?php if ($items === []): ?>
        <p class="study-news__empty"><?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_LIST_EMPTY')) ?></p>
    <?php else: ?>
        <div class="study-news__list">
            <?php foreach ($items as $item): ?>
                <article class="study-news__item">
                    <?php if (!empty($item['PREVIEW_PICTURE']['SRC'])): ?>
                        <img
                            class="study-news__image"
                            src="<?= htmlspecialcharsbx($item['PREVIEW_PICTURE']['SRC']) ?>"
                            alt="<?= htmlspecialcharsbx($item['PREVIEW_PICTURE']['ALT'] ?? $item['NAME']) ?>"
                            loading="lazy"
                        >
                    <?php endif; ?>

                    <?php if ($item['DISPLAY_ACTIVE_FROM'] !== ''): ?>
                        <div class="study-news__date"><?= htmlspecialcharsbx($item['DISPLAY_ACTIVE_FROM']) ?></div>
                    <?php endif; ?>

                    <h2 class="study-news__title">
                        <?php if ($item['DETAIL_PAGE_URL'] !== ''): ?>
                            <a href="<?= htmlspecialcharsbx($item['DETAIL_PAGE_URL']) ?>">
                                <?= htmlspecialcharsbx($item['NAME']) ?>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialcharsbx($item['NAME']) ?>
                        <?php endif; ?>
                    </h2>

                    <?php if ($item['PREVIEW_TEXT_PLAIN'] !== ''): ?>
                        <p class="study-news__preview"><?= htmlspecialcharsbx($item['PREVIEW_TEXT_PLAIN']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (($arResult['NAV_STRING'] ?? '') !== ''): ?>
            <div class="study-news__nav"><?= $arResult['NAV_STRING'] ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
