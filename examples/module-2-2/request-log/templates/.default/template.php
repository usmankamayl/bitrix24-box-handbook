<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

$this->setFrameMode(true);
?>

<div class="request-log">
    <!-- Статистика -->
    <?php if (!empty($arResult['STATISTICS'])): ?>
        <div class="request-log__statistics">
            <h2>Статистика запросов</h2>
            <div class="statistics-grid">
                <div class="stat-card">
                    <div class="stat-card__value"><?= $arResult['STATISTICS']['TOTAL_REQUESTS'] ?></div>
                    <div class="stat-card__label">Всего запросов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__value"><?= $arResult['STATISTICS']['UNIQUE_USERS'] ?></div>
                    <div class="stat-card__label">Уникальных пользователей</div>
                </div>
            </div>

            <?php if (!empty($arResult['STATISTICS']['TOP_URLS'])): ?>
                <div class="top-urls">
                    <h3>Топ-5 URL по количеству запросов</h3>
                    <table class="top-urls__table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Количество</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($arResult['STATISTICS']['TOP_URLS'] as $item): ?>
                                <tr>
                                    <td><?= htmlspecialcharsbx($item['UF_REQUEST_URL']) ?></td>
                                    <td><?= $item['REQUEST_COUNT'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Список логов -->
    <?php if (!empty($arResult['ITEMS'])): ?>
        <div class="request-log__list">
            <h2>Последние запросы</h2>
            <table class="log-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Дата</th>
                        <th>Пользователь</th>
                        <th>URL</th>
                        <th>Метод</th>
                        <th>IP</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($arResult['ITEMS'] as $item): ?>
                        <tr>
                            <td><?= $item['ID'] ?></td>
                            <td><?= htmlspecialcharsbx($item['UF_REQUEST_DATE_FORMATTED']) ?></td>
                            <td>
                                <?php if ($item['UF_USER_ID'] > 0): ?>
                                    <a href="/bitrix/admin/user_edit.php?ID=<?= $item['UF_USER_ID'] ?>&lang=ru">
                                        ID: <?= $item['UF_USER_ID'] ?>
                                    </a>
                                <?php else: ?>
                                    Гость
                                <?php endif; ?>
                            </td>
                            <td class="log-table__url">
                                <?= htmlspecialcharsbx($item['UF_REQUEST_URL']) ?>
                            </td>
                            <td>
                                <span class="method-badge method-badge--<?= strtolower($item['UF_REQUEST_METHOD']) ?>">
                                    <?= htmlspecialcharsbx($item['UF_REQUEST_METHOD']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialcharsbx($item['UF_IP_ADDRESS']) ?></td>
                            <td class="log-table__user-agent">
                                <?= htmlspecialcharsbx($item['UF_USER_AGENT']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="request-log__empty">
            <p>Записей не найдено</p>
        </div>
    <?php endif; ?>

    <!-- Ошибки логирования -->
    <?php if (!empty($arResult['LOG_ERRORS'])): ?>
        <div class="request-log__errors">
            <h3>Ошибки при логировании:</h3>
            <ul>
                <?php foreach ($arResult['LOG_ERRORS'] as $error): ?>
                    <li><?= htmlspecialcharsbx($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
