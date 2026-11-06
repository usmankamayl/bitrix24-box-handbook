<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @var CBitrixComponentTemplate $this */

$this->setFrameMode(true);
?>

<div class="view-counter">
    <!-- Основной счетчик -->
    <div class="view-counter__main">
        <div class="view-counter__icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" fill="currentColor"/>
            </svg>
        </div>
        <div class="view-counter__value">
            <?= number_format($arResult['VIEW_COUNT'], 0, '.', ' ') ?>
        </div>
        <div class="view-counter__label">просмотров</div>
    </div>

    <!-- Детальная статистика -->
    <?php if (!empty($arResult['STATISTICS'])): ?>
        <div class="view-counter__stats">
            <div class="stats-grid">
                <!-- Всего просмотров -->
                <div class="stat-item">
                    <div class="stat-item__value"><?= number_format($arResult['STATISTICS']['TOTAL_VIEWS'], 0, '.', ' ') ?></div>
                    <div class="stat-item__label">Всего</div>
                </div>

                <!-- За сегодня -->
                <div class="stat-item">
                    <div class="stat-item__value"><?= number_format($arResult['STATISTICS']['VIEWS_TODAY'], 0, '.', ' ') ?></div>
                    <div class="stat-item__label">За сегодня</div>
                </div>

                <!-- За неделю -->
                <div class="stat-item">
                    <div class="stat-item__value"><?= number_format($arResult['STATISTICS']['VIEWS_WEEK'], 0, '.', ' ') ?></div>
                    <div class="stat-item__label">За неделю</div>
                </div>

                <!-- Уникальные пользователи -->
                <div class="stat-item">
                    <div class="stat-item__value"><?= number_format($arResult['STATISTICS']['UNIQUE_USERS'], 0, '.', ' ') ?></div>
                    <div class="stat-item__label">Уникальных</div>
                </div>
            </div>

            <!-- График по дням -->
            <?php if (!empty($arResult['STATISTICS']['VIEWS_BY_DAY'])): ?>
                <div class="view-counter__chart">
                    <h3>Просмотры по дням</h3>
                    <div class="chart">
                        <?php
                        $maxViews = max(array_column($arResult['STATISTICS']['VIEWS_BY_DAY'], 'VIEWS_COUNT'));
                        foreach ($arResult['STATISTICS']['VIEWS_BY_DAY'] as $dayData):
                            $percent = $maxViews > 0 ? ($dayData['VIEWS_COUNT'] / $maxViews * 100) : 0;
                            $date = is_object($dayData['VIEW_DATE'])
                                ? $dayData['VIEW_DATE']->format('d.m')
                                : date('d.m', strtotime($dayData['VIEW_DATE']));
                        ?>
                            <div class="chart__bar">
                                <div class="chart__bar-fill" style="height: <?= $percent ?>%">
                                    <span class="chart__bar-value"><?= $dayData['VIEWS_COUNT'] ?></span>
                                </div>
                                <div class="chart__bar-label"><?= $date ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Мета-информация -->
    <div class="view-counter__meta">
        <small>
            Тип: <?= htmlspecialcharsbx($arResult['ENTITY_TYPE']) ?> |
            ID: <?= $arResult['ENTITY_ID'] ?>
        </small>
    </div>
</div>
