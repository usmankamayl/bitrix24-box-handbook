<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 */
?>

<div class="user-list-container">
    <!-- Фильтр -->
    <div class="user-filter">
        <form method="get" class="filter-form">
            <div class="filter-row">
                <div class="filter-field">
                    <label for="search">Поиск:</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="<?= htmlspecialcharsbx($arResult['FILTER']['search']) ?>"
                        placeholder="Имя, фамилия, email или логин"
                    >
                </div>

                <div class="filter-field">
                    <label for="group_id">Группа:</label>
                    <select id="group_id" name="group_id">
                        <option value="">Все группы</option>
                        <?php foreach ($arResult['GROUPS'] as $group): ?>
                            <option
                                value="<?= $group['ID'] ?>"
                                <?= ($arResult['FILTER']['group_id'] == $group['ID']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialcharsbx($group['NAME']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="active">Активность:</label>
                    <select id="active" name="active">
                        <option value="">Все</option>
                        <option value="Y" <?= ($arResult['FILTER']['active'] === 'Y') ? 'selected' : '' ?>>
                            Активные
                        </option>
                        <option value="N" <?= ($arResult['FILTER']['active'] === 'N') ? 'selected' : '' ?>>
                            Неактивные
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Применить</button>
                    <a href="<?= $APPLICATION->GetCurPage() ?>" class="btn btn-secondary">Сбросить</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Информация о результатах -->
    <div class="results-info">
        Найдено пользователей: <strong><?= $arResult['PAGINATION']['total_items'] ?></strong>
        (страница <?= $arResult['PAGINATION']['current_page'] ?> из <?= $arResult['PAGINATION']['total_pages'] ?>)
    </div>

    <!-- Таблица пользователей -->
    <?php if (!empty($arResult['USERS'])): ?>
        <div class="user-table-wrapper">
            <table class="user-table">
                <thead>
                    <tr>
                        <?php if (in_array('ID', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>ID</th>
                        <?php endif; ?>
                        <th>Фото</th>
                        <?php if (in_array('NAME', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>Имя</th>
                        <?php endif; ?>
                        <?php if (in_array('LAST_NAME', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>Фамилия</th>
                        <?php endif; ?>
                        <?php if (in_array('EMAIL', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>Email</th>
                        <?php endif; ?>
                        <?php if (in_array('LOGIN', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>Логин</th>
                        <?php endif; ?>
                        <?php if (in_array('ACTIVE', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>Активен</th>
                        <?php endif; ?>
                        <?php if (in_array('DATE_REGISTER', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>Дата регистрации</th>
                        <?php endif; ?>
                        <?php if (in_array('LAST_LOGIN', $arResult['DISPLAY_FIELDS'])): ?>
                            <th>Последний вход</th>
                        <?php endif; ?>
                        <th>Группы</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($arResult['USERS'] as $user): ?>
                        <tr class="user-row <?= ($user['ACTIVE'] === 'N') ? 'inactive' : '' ?>">
                            <?php if (in_array('ID', $arResult['DISPLAY_FIELDS'])): ?>
                                <td><?= $user['ID'] ?></td>
                            <?php endif; ?>
                            <td class="user-photo">
                                <?php if (!empty($user['PERSONAL_PHOTO_SRC'])): ?>
                                    <img src="<?= $user['PERSONAL_PHOTO_SRC'] ?>" alt="">
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <?= mb_substr($user['NAME'] ?? 'U', 0, 1) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <?php if (in_array('NAME', $arResult['DISPLAY_FIELDS'])): ?>
                                <td><?= htmlspecialcharsbx($user['NAME']) ?></td>
                            <?php endif; ?>
                            <?php if (in_array('LAST_NAME', $arResult['DISPLAY_FIELDS'])): ?>
                                <td><?= htmlspecialcharsbx($user['LAST_NAME']) ?></td>
                            <?php endif; ?>
                            <?php if (in_array('EMAIL', $arResult['DISPLAY_FIELDS'])): ?>
                                <td>
                                    <a href="mailto:<?= htmlspecialcharsbx($user['EMAIL']) ?>">
                                        <?= htmlspecialcharsbx($user['EMAIL']) ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                            <?php if (in_array('LOGIN', $arResult['DISPLAY_FIELDS'])): ?>
                                <td><?= htmlspecialcharsbx($user['LOGIN']) ?></td>
                            <?php endif; ?>
                            <?php if (in_array('ACTIVE', $arResult['DISPLAY_FIELDS'])): ?>
                                <td>
                                    <span class="status-badge <?= ($user['ACTIVE'] === 'Y') ? 'active' : 'inactive' ?>">
                                        <?= ($user['ACTIVE'] === 'Y') ? 'Да' : 'Нет' ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <?php if (in_array('DATE_REGISTER', $arResult['DISPLAY_FIELDS'])): ?>
                                <td><?= $user['DATE_REGISTER'] ?></td>
                            <?php endif; ?>
                            <?php if (in_array('LAST_LOGIN', $arResult['DISPLAY_FIELDS'])): ?>
                                <td><?= $user['LAST_LOGIN'] ?? '-' ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="user-groups">
                                    <?php
                                    $groupNames = [];
                                    foreach ($user['GROUPS'] as $groupId) {
                                        foreach ($arResult['GROUPS'] as $group) {
                                            if ($group['ID'] == $groupId) {
                                                $groupNames[] = $group['NAME'];
                                                break;
                                            }
                                        }
                                    }
                                    echo htmlspecialcharsbx(implode(', ', $groupNames));
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Пагинация -->
        <?php if ($arResult['PAGINATION']['total_pages'] > 1): ?>
            <div class="pagination">
                <?php if ($arResult['PAGINATION']['has_prev']): ?>
                    <a href="?<?= http_build_query(array_merge($arResult['FILTER'], ['page' => 1])) ?>" class="page-link">
                        &laquo; Первая
                    </a>
                    <a href="?<?= http_build_query(array_merge($arResult['FILTER'], ['page' => $arResult['PAGINATION']['current_page'] - 1])) ?>" class="page-link">
                        &lsaquo; Предыдущая
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $arResult['PAGINATION']['current_page'] - 2);
                $end = min($arResult['PAGINATION']['total_pages'], $arResult['PAGINATION']['current_page'] + 2);

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a
                        href="?<?= http_build_query(array_merge($arResult['FILTER'], ['page' => $i])) ?>"
                        class="page-link <?= ($i == $arResult['PAGINATION']['current_page']) ? 'active' : '' ?>"
                    >
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($arResult['PAGINATION']['has_next']): ?>
                    <a href="?<?= http_build_query(array_merge($arResult['FILTER'], ['page' => $arResult['PAGINATION']['current_page'] + 1])) ?>" class="page-link">
                        Следующая &rsaquo;
                    </a>
                    <a href="?<?= http_build_query(array_merge($arResult['FILTER'], ['page' => $arResult['PAGINATION']['total_pages']])) ?>" class="page-link">
                        Последняя &raquo;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="no-results">
            <p>Пользователи не найдены</p>
        </div>
    <?php endif; ?>
</div>
