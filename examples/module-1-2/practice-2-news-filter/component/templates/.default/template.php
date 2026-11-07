<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 */

$filter = $arResult['FILTER'] ?? [];
$items = $arResult['ITEMS'] ?? [];
$sections = $arResult['SECTIONS'] ?? [];
$formUrl = (string)($arResult['FORM_URL'] ?? '');
$resetUrl = (string)($arResult['RESET_URL'] ?? '');
?>
<div class="study-filter">
    <form class="study-filter__form" method="get" action="<?= htmlspecialcharsbx($formUrl) ?>">
        <?php if ($arParams['SHOW_SEARCH']): ?>
            <label class="study-filter__field">
                <span><?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_FILTERED_SEARCH_LABEL')) ?></span>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlspecialcharsbx($filter['SEARCH'] ?? '') ?>"
                    maxlength="64"
                >
            </label>
        <?php endif; ?>

        <?php if ($arParams['SHOW_SECTION_FILTER'] && $sections !== []): ?>
            <label class="study-filter__field">
                <span><?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_FILTERED_SECTION_LABEL')) ?></span>
                <select name="section">
                    <option value=""><?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_FILTERED_SECTION_ALL')) ?></option>
                    <?php foreach ($sections as $section): ?>
                        <option
                            value="<?= (int)$section['ID'] ?>"
                            <?= (int)($filter['SECTION_ID'] ?? 0) === $section['ID'] ? 'selected' : '' ?>
                        ><?= str_repeat('— ', max(0, $section['DEPTH_LEVEL'] - 1))
                            . htmlspecialcharsbx($section['NAME']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <button type="submit" class="study-filter__submit">
            <?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_FILTERED_SUBMIT')) ?>
        </button>

        <a class="study-filter__reset" href="<?= htmlspecialcharsbx($resetUrl) ?>">
            <?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_FILTERED_RESET')) ?>
        </a>
    </form>

    <?php if ($items === []): ?>
        <p class="study-filter__empty"><?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_FILTERED_EMPTY')) ?></p>
    <?php else: ?>
        <ul class="study-filter__list">
            <?php foreach ($items as $item): ?>
                <li class="study-filter__item">
                    <time class="study-filter__date"><?= htmlspecialcharsbx($item['DISPLAY_ACTIVE_FROM']) ?></time>
                    <?php if ($item['DETAIL_PAGE_URL'] !== ''): ?>
                        <a href="<?= htmlspecialcharsbx($item['DETAIL_PAGE_URL']) ?>">
                            <?= htmlspecialcharsbx($item['NAME']) ?>
                        </a>
                    <?php else: ?>
                        <span><?= htmlspecialcharsbx($item['NAME']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (($arResult['NAV_STRING'] ?? '') !== ''): ?>
            <div class="study-filter__nav"><?= $arResult['NAV_STRING'] ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
