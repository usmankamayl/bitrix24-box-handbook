<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams Параметры компонента
 * @var array $arResult Результаты работы компонента
 * @var CBitrixComponentTemplate $this Шаблон компонента
 */
?>

<div class="product-catalog">

    <?php if (!empty($arResult['SECTIONS']) || $arParams['FILTER_PRICE_FROM'] === 'Y' || $arParams['FILTER_PRICE_TO'] === 'Y'): ?>
    <!-- Блок фильтрации -->
    <div class="catalog-filter">
        <h3>Фильтр товаров</h3>

        <form method="get" class="filter-form">

            <?php if ($arParams['FILTER_PRICE_FROM'] === 'Y' || $arParams['FILTER_PRICE_TO'] === 'Y'): ?>
            <div class="filter-group">
                <label>Цена</label>
                <div class="price-range">
                    <?php if ($arParams['FILTER_PRICE_FROM'] === 'Y'): ?>
                    <input
                        type="number"
                        name="PRICE_FROM"
                        placeholder="От"
                        value="<?= htmlspecialcharsbx($_GET['PRICE_FROM'] ?? '') ?>"
                        class="filter-input"
                    >
                    <?php endif; ?>

                    <?php if ($arParams['FILTER_PRICE_TO'] === 'Y'): ?>
                    <input
                        type="number"
                        name="PRICE_TO"
                        placeholder="До"
                        value="<?= htmlspecialcharsbx($_GET['PRICE_TO'] ?? '') ?>"
                        class="filter-input"
                    >
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($arResult['SECTIONS'])): ?>
            <div class="filter-group">
                <label for="section-filter">Категория</label>
                <select name="SECTION_ID" id="section-filter" class="filter-select">
                    <option value="">Все категории</option>
                    <?php foreach ($arResult['SECTIONS'] as $section): ?>
                    <option
                        value="<?= $section['ID'] ?>"
                        <?= (isset($_GET['SECTION_ID']) && $_GET['SECTION_ID'] == $section['ID']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialcharsbx($section['NAME']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Применить</button>
                <a href="<?= $APPLICATION->GetCurPageParam('', ['PRICE_FROM', 'PRICE_TO', 'SECTION_ID']) ?>"
                   class="btn btn-secondary">Сбросить</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Список товаров -->
    <div class="catalog-items">
        <?php if (!empty($arResult['ITEMS'])): ?>
            <div class="products-grid">
                <?php foreach ($arResult['ITEMS'] as $item): ?>
                <div class="product-card">

                    <?php if (!empty($item['PREVIEW_PICTURE_SRC'])): ?>
                    <div class="product-image">
                        <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                            <img src="<?= $item['PREVIEW_PICTURE_SRC'] ?>" alt="<?= htmlspecialcharsbx($item['NAME']) ?>">
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="product-info">
                        <h4 class="product-title">
                            <a href="<?= $item['DETAIL_PAGE_URL'] ?>">
                                <?= htmlspecialcharsbx($item['NAME']) ?>
                            </a>
                        </h4>

                        <?php if (!empty($item['ARTICLE_VALUE'])): ?>
                        <div class="product-article">
                            Артикул: <?= htmlspecialcharsbx($item['ARTICLE_VALUE']) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($item['PREVIEW_TEXT'])): ?>
                        <div class="product-description">
                            <?= htmlspecialcharsbx($item['PREVIEW_TEXT']) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($item['PRICE_VALUE'])): ?>
                        <div class="product-price">
                            <?= number_format($item['PRICE_VALUE'], 0, ',', ' ') ?> ₽
                        </div>
                        <?php endif; ?>

                        <div class="product-actions">
                            <a href="<?= $item['DETAIL_PAGE_URL'] ?>" class="btn btn-detail">
                                Подробнее
                            </a>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-products">
                <p>По заданным критериям товары не найдены</p>
            </div>
        <?php endif; ?>
    </div>

</div>
