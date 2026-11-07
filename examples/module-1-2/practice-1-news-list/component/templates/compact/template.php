<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Второй шаблон того же компонента: логика компонента не меняется,
 * меняется только разметка. Переопределяется в шаблоне сайта:
 * /local/templates/<tpl>/components/study/news.list/compact/template.php
 *
 * @var array $arResult
 */

$items = $arResult['ITEMS'] ?? [];
?>
<ul class="study-news-compact">
    <?php if ($items === []): ?>
        <li class="study-news-compact__empty"><?= htmlspecialcharsbx(GetMessage('STUDY_NEWS_LIST_EMPTY')) ?></li>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>
        <li class="study-news-compact__item">
            <time class="study-news-compact__date"><?= htmlspecialcharsbx($item['DISPLAY_ACTIVE_FROM']) ?></time>
            <?php if ($item['DETAIL_PAGE_URL'] !== ''): ?>
                <a class="study-news-compact__link" href="<?= htmlspecialcharsbx($item['DETAIL_PAGE_URL']) ?>">
                    <?= htmlspecialcharsbx($item['NAME']) ?>
                </a>
            <?php else: ?>
                <span class="study-news-compact__link"><?= htmlspecialcharsbx($item['NAME']) ?></span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (($arResult['NAV_STRING'] ?? '') !== ''): ?>
    <div class="study-news-compact__nav"><?= $arResult['NAV_STRING'] ?></div>
<?php endif; ?>
