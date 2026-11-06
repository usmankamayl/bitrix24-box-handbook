<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var CMain $APPLICATION */

use Bitrix\Main\Page\Asset;

Asset::getInstance()->addCss($templateFolder . '/style.css');
Asset::getInstance()->addJs($templateFolder . '/script.js');

$currency = '';
?>
<div class="sc-grid">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <?php
        $hasOffers = !empty($item['OFFERS']);
        $inStock = ($item['PRODUCT_CAN_BUY_ZERO'] === 'Y')
            || (float)($item['PRODUCT_QUANTITY'] ?? 0) > 0;
        ?>
        <div class="sc-card" data-product-id="<?= (int)$item['ID'] ?>">
            <a href="<?= htmlspecialcharsbx($item['DETAIL_PAGE_URL']) ?>" class="sc-card__title">
                <?= htmlspecialcharsbx($item['NAME']) ?>
            </a>

            <div class="sc-card__price">
                <?php if ($item['DISPLAY_PRICE'] !== null): ?>
                    <span class="sc-price-value" data-price-for="<?= (int)$item['ID'] ?>">
                        <?= number_format((float)$item['DISPLAY_PRICE'], 2, '.', ' ') ?>
                    </span>
                <?php else: ?>
                    <span class="sc-price-unavailable">Цена по запросу</span>
                <?php endif; ?>
            </div>

            <div class="sc-card__stock <?= $inStock ? 'sc-card__stock--in' : 'sc-card__stock--out' ?>">
                <?= $inStock ? 'В наличии' : 'Нет в наличии' ?>
            </div>

            <?php if (!empty($item['PRICE_MATRIX'])): ?>
                <table class="sc-price-matrix">
                    <?php foreach ($item['PRICE_MATRIX'] as $groupId => $rows): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int)$row['QUANTITY_FROM'] ?: 1 ?>+</td>
                                <td><?= number_format((float)$row['PRICE'], 2, '.', ' ') ?> <?= htmlspecialcharsbx($row['CURRENCY']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php if ($hasOffers): ?>
                <select class="sc-offer-select" data-parent-id="<?= (int)$item['ID'] ?>">
                    <?php foreach ($item['OFFERS'] as $offer): ?>
                        <option value="<?= (int)$offer['ID'] ?>">
                            <?= htmlspecialcharsbx($offer['NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <button class="sc-buy-btn" data-id="<?= (int)($item['OFFERS'][0]['ID'] ?? $item['ID']) ?>">
                В корзину
            </button>
        </div>
    <?php endforeach; ?>
</div>
