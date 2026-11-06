<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams Параметры компонента
 * @var array $arResult Результаты работы компонента
 * @var CBitrixComponentTemplate $this Шаблон компонента
 */

if (empty($arResult['ITEMS'])) {
    return;
}
?>

<div class="related-products">

    <h3 class="related-products__title">
        <?php if ($arResult['TYPE'] === 'RELATED'): ?>
            С этим товаром покупают
        <?php else: ?>
            Похожие товары
        <?php endif; ?>
    </h3>

    <div class="related-products__grid">
        <?php foreach ($arResult['ITEMS'] as $item): ?>
        <div class="related-product-card">

            <?php if (!empty($item['PREVIEW_PICTURE_SRC'])): ?>
            <div class="related-product-card__image">
                <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                    <img
                        src="<?= $item['PREVIEW_PICTURE_SRC'] ?>"
                        alt="<?= htmlspecialcharsbx($item['NAME']) ?>"
                        loading="lazy"
                    >
                </a>
            </div>
            <?php else: ?>
            <div class="related-product-card__image related-product-card__image--placeholder">
                <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                    <svg width="100" height="100" viewBox="0 0 100 100">
                        <rect width="100" height="100" fill="#f0f0f0"/>
                        <text x="50" y="50" text-anchor="middle" dy=".3em" fill="#999" font-size="14">
                            Нет фото
                        </text>
                    </svg>
                </a>
            </div>
            <?php endif; ?>

            <div class="related-product-card__content">
                <h4 class="related-product-card__name">
                    <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                        <?= htmlspecialcharsbx($item['NAME']) ?>
                    </a>
                </h4>

                <?php if (!empty($item['PREVIEW_TEXT'])): ?>
                <div class="related-product-card__description">
                    <?= htmlspecialcharsbx($item['PREVIEW_TEXT']) ?>
                </div>
                <?php endif; ?>

                <?php if ($arParams['SHOW_PRICE'] === 'Y' && !empty($item['PRICE_VALUE'])): ?>
                <div class="related-product-card__price">
                    <?= number_format($item['PRICE_VALUE'], 0, ',', ' ') ?> ₽
                </div>
                <?php endif; ?>

                <div class="related-product-card__action">
                    <a href="<?= $item['DETAIL_PAGE_URL'] ?>" class="related-product-card__link">
                        Подробнее
                    </a>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

</div>
