<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
?>

<div class="weather-widget">
    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="weather-widget__error">
            <?= htmlspecialchars($arResult['ERROR']) ?>
        </div>
    <?php elseif (!empty($arResult['WEATHER'])): ?>
        <?php $weather = $arResult['WEATHER']; ?>

        <div class="weather-widget__container">
            <!-- Основная информация -->
            <div class="weather-widget__main">
                <div class="weather-widget__location">
                    <h3><?= htmlspecialchars($weather['CITY']) ?>, <?= htmlspecialchars($weather['COUNTRY']) ?></h3>
                </div>

                <div class="weather-widget__current">
                    <div class="weather-widget__icon">
                        <img src="<?= htmlspecialchars($weather['ICON_URL']) ?>"
                             alt="<?= htmlspecialchars($weather['DESCRIPTION']) ?>">
                    </div>
                    <div class="weather-widget__temp">
                        <span class="weather-widget__temp-value">
                            <?= $weather['TEMPERATURE'] ?><?= $weather['TEMP_UNIT'] ?>
                        </span>
                        <span class="weather-widget__description">
                            <?= htmlspecialchars($weather['DESCRIPTION']) ?>
                        </span>
                        <span class="weather-widget__feels-like">
                            Ощущается как <?= $weather['FEELS_LIKE'] ?><?= $weather['TEMP_UNIT'] ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Дополнительная информация -->
            <div class="weather-widget__details">
                <div class="weather-widget__detail-item">
                    <span class="weather-widget__detail-label">Влажность</span>
                    <span class="weather-widget__detail-value"><?= $weather['HUMIDITY'] ?>%</span>
                </div>

                <div class="weather-widget__detail-item">
                    <span class="weather-widget__detail-label">Давление</span>
                    <span class="weather-widget__detail-value"><?= $weather['PRESSURE'] ?> гПа</span>
                </div>

                <div class="weather-widget__detail-item">
                    <span class="weather-widget__detail-label">Ветер</span>
                    <span class="weather-widget__detail-value">
                        <?= $weather['WIND_SPEED'] ?> <?= $weather['WIND_UNIT'] ?>
                    </span>
                </div>

                <div class="weather-widget__detail-item">
                    <span class="weather-widget__detail-label">Облачность</span>
                    <span class="weather-widget__detail-value"><?= $weather['CLOUDS'] ?>%</span>
                </div>
            </div>

            <!-- Восход и закат -->
            <div class="weather-widget__sun">
                <div class="weather-widget__sun-item">
                    <span class="weather-widget__sun-label">🌅 Восход</span>
                    <span class="weather-widget__sun-value"><?= $weather['SUNRISE'] ?></span>
                </div>
                <div class="weather-widget__sun-item">
                    <span class="weather-widget__sun-label">🌇 Закат</span>
                    <span class="weather-widget__sun-value"><?= $weather['SUNSET'] ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
