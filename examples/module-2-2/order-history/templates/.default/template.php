<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @var CBitrixComponentTemplate $this */

$this->setFrameMode(true);
?>

<div class="order-history">
    <!-- Заголовок -->
    <div class="order-history__header">
        <h2>История изменений заказа <?= $arResult['PARAMS']['ORDER_ID'] > 0 ? '#' . $arResult['PARAMS']['ORDER_ID'] : '' ?></h2>

        <?php if (!empty($arResult['STATISTICS'])): ?>
            <div class="order-history__summary">
                Всего изменений: <strong><?= $arResult['STATISTICS']['TOTAL_CHANGES'] ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- Статистика по типам -->
    <?php if (!empty($arResult['STATISTICS']['CHANGES_BY_TYPE'])): ?>
        <div class="order-history__stats">
            <h3>Изменения по типам</h3>
            <div class="stats-badges">
                <?php foreach ($arResult['STATISTICS']['CHANGES_BY_TYPE'] as $stat): ?>
                    <div class="stat-badge">
                        <span class="stat-badge__type"><?= htmlspecialcharsbx($stat['UF_CHANGE_TYPE']) ?></span>
                        <span class="stat-badge__count"><?= $stat['CHANGES_COUNT'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Временная шкала изменений -->
    <?php if (!empty($arResult['ITEMS'])): ?>
        <div class="order-history__timeline">
            <?php foreach ($arResult['ITEMS'] as $item): ?>
                <div class="timeline-item">
                    <div class="timeline-item__date">
                        <?= $item['UF_CHANGE_DATE_FORMATTED'] ?>
                    </div>

                    <div class="timeline-item__marker">
                        <div class="timeline-item__dot"></div>
                        <div class="timeline-item__line"></div>
                    </div>

                    <div class="timeline-item__content">
                        <div class="timeline-item__header">
                            <span class="timeline-item__type timeline-item__type--<?= htmlspecialcharsbx($item['UF_CHANGE_TYPE']) ?>">
                                <?= htmlspecialcharsbx($item['CHANGE_TYPE_NAME']) ?>
                            </span>
                            <span class="timeline-item__user">
                                <?= htmlspecialcharsbx($item['USER_NAME']) ?>
                            </span>
                        </div>

                        <div class="timeline-item__body">
                            <div class="field-change">
                                <div class="field-change__name">
                                    Поле: <strong><?= htmlspecialcharsbx($item['UF_FIELD_NAME']) ?></strong>
                                </div>

                                <div class="field-change__values">
                                    <div class="field-change__old">
                                        <label>Было:</label>
                                        <div class="value-box value-box--old">
                                            <?= $item['UF_OLD_VALUE_DISPLAY'] ?>
                                        </div>
                                    </div>

                                    <div class="field-change__arrow">→</div>

                                    <div class="field-change__new">
                                        <label>Стало:</label>
                                        <div class="value-box value-box--new">
                                            <?= $item['UF_NEW_VALUE_DISPLAY'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($item['UF_COMMENT'])): ?>
                                <div class="timeline-item__comment">
                                    <strong>Комментарий:</strong>
                                    <?= htmlspecialcharsbx($item['UF_COMMENT']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="order-history__empty">
            <p>История изменений пуста</p>
        </div>
    <?php endif; ?>
</div>
