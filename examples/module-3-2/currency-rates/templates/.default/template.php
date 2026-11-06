<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
?>

<div class="currency-rates">
    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="currency-rates__error">
            <?= htmlspecialchars($arResult['ERROR']) ?>
        </div>
    <?php elseif (!empty($arResult['RATES'])): ?>
        <div class="currency-rates__container">
            <div class="currency-rates__header">
                <h3>Курсы валют ЦБ РФ</h3>
                <?php if (!empty($arResult['UPDATE_DATE'])): ?>
                    <span class="currency-rates__update">
                        Обновлено: <?= htmlspecialchars($arResult['UPDATE_DATE']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="currency-rates__list">
                <?php foreach ($arResult['RATES'] as $rate): ?>
                    <div class="currency-rates__item">
                        <div class="currency-rates__currency">
                            <span class="currency-rates__code"><?= htmlspecialchars($rate['CODE']) ?></span>
                            <span class="currency-rates__name"><?= htmlspecialchars($rate['NAME']) ?></span>
                        </div>

                        <div class="currency-rates__value-block">
                            <span class="currency-rates__value">
                                <?= $rate['VALUE'] ?> ₽
                                <?php if ($rate['NOMINAL'] > 1): ?>
                                    <span class="currency-rates__nominal">
                                        за <?= $rate['NOMINAL'] ?>
                                    </span>
                                <?php endif; ?>
                            </span>

                            <?php if ($arParams['SHOW_DYNAMICS'] === 'Y'): ?>
                                <?php if ($rate['IS_GROWTH']): ?>
                                    <span class="currency-rates__change currency-rates__change--up">
                                        ↑ <?= abs($rate['CHANGE']) ?>
                                        (<?= abs($rate['CHANGE_PERCENT']) ?>%)
                                    </span>
                                <?php elseif ($rate['IS_DECLINE']): ?>
                                    <span class="currency-rates__change currency-rates__change--down">
                                        ↓ <?= abs($rate['CHANGE']) ?>
                                        (<?= abs($rate['CHANGE_PERCENT']) ?>%)
                                    </span>
                                <?php else: ?>
                                    <span class="currency-rates__change currency-rates__change--neutral">
                                        без изменений
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
