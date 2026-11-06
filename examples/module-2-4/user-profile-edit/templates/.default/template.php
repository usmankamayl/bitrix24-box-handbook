<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 */

$user = $arResult['USER'];
?>

<div class="user-profile-container">
    <?php if (!empty($arResult['ERRORS'])): ?>
        <div class="profile-errors">
            <?php foreach ($arResult['ERRORS'] as $error): ?>
                <div class="error-message"><?= htmlspecialcharsbx($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($arResult['SUCCESS']): ?>
        <div class="profile-success">
            <p>✓ Данные успешно сохранены</p>
        </div>
    <?php endif; ?>

    <div class="profile-layout">
        <!-- Информация о пользователе -->
        <div class="profile-sidebar">
            <div class="profile-photo-section">
                <?php if (!empty($user['PERSONAL_PHOTO'])): ?>
                    <img
                        src="<?= CFile::GetPath($user['PERSONAL_PHOTO']) ?>"
                        alt="Фото профиля"
                        class="profile-photo"
                    >
                <?php else: ?>
                    <div class="profile-photo-placeholder">
                        <?= mb_substr($user['NAME'] ?? 'U', 0, 1) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <h2><?= htmlspecialcharsbx($user['NAME'] . ' ' . $user['LAST_NAME']) ?></h2>
                <p class="user-login">@<?= htmlspecialcharsbx($user['LOGIN']) ?></p>
                <p class="user-status">
                    <span class="status-badge <?= ($user['ACTIVE'] === 'Y') ? 'active' : 'inactive' ?>">
                        <?= ($user['ACTIVE'] === 'Y') ? 'Активен' : 'Неактивен' ?>
                    </span>
                </p>
                <p class="user-registered">
                    Регистрация: <?= $user['DATE_REGISTER'] ?>
                </p>
            </div>
        </div>

        <!-- Форма редактирования -->
        <div class="profile-main">
            <h3>Редактирование профиля</h3>

            <form method="post" action="<?= POST_FORM_ACTION_URI ?>" enctype="multipart/form-data" class="profile-form">
                <?= bitrix_sessid_post() ?>

                <!-- Основные поля -->
                <div class="form-section">
                    <h4>Личные данные</h4>

                    <?php if (in_array('NAME', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="name">Имя <span class="required">*</span></label>
                            <input
                                type="text"
                                id="name"
                                name="NAME"
                                value="<?= htmlspecialcharsbx($user['NAME']) ?>"
                                required
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('LAST_NAME', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="last-name">Фамилия</label>
                            <input
                                type="text"
                                id="last-name"
                                name="LAST_NAME"
                                value="<?= htmlspecialcharsbx($user['LAST_NAME']) ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('SECOND_NAME', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="second-name">Отчество</label>
                            <input
                                type="text"
                                id="second-name"
                                name="SECOND_NAME"
                                value="<?= htmlspecialcharsbx($user['SECOND_NAME'] ?? '') ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('PERSONAL_BIRTHDAY', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="birthday">День рождения</label>
                            <input
                                type="date"
                                id="birthday"
                                name="PERSONAL_BIRTHDAY"
                                value="<?= htmlspecialcharsbx($user['PERSONAL_BIRTHDAY'] ?? '') ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('PERSONAL_GENDER', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="gender">Пол</label>
                            <select id="gender" name="PERSONAL_GENDER">
                                <option value="">Не указан</option>
                                <option value="M" <?= ($user['PERSONAL_GENDER'] === 'M') ? 'selected' : '' ?>>
                                    Мужской
                                </option>
                                <option value="F" <?= ($user['PERSONAL_GENDER'] === 'F') ? 'selected' : '' ?>>
                                    Женский
                                </option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('PERSONAL_PHOTO', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="photo">Фото профиля</label>
                            <input
                                type="file"
                                id="photo"
                                name="PERSONAL_PHOTO"
                                accept="image/*"
                            >
                            <small>Рекомендуемый размер: 300x300px</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Контактная информация -->
                <div class="form-section">
                    <h4>Контактная информация</h4>

                    <?php if (in_array('EMAIL', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input
                                type="email"
                                id="email"
                                name="EMAIL"
                                value="<?= htmlspecialcharsbx($user['EMAIL']) ?>"
                                required
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('PERSONAL_PHONE', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="phone">Телефон</label>
                            <input
                                type="tel"
                                id="phone"
                                name="PERSONAL_PHONE"
                                value="<?= htmlspecialcharsbx($user['PERSONAL_PHONE'] ?? '') ?>"
                                placeholder="+7 (999) 999-99-99"
                            >
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('PERSONAL_MOBILE', $arResult['EDITABLE_FIELDS'])): ?>
                        <div class="form-group">
                            <label for="mobile">Мобильный</label>
                            <input
                                type="tel"
                                id="mobile"
                                name="PERSONAL_MOBILE"
                                value="<?= htmlspecialcharsbx($user['PERSONAL_MOBILE'] ?? '') ?>"
                            >
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Работа -->
                <?php if (in_array('WORK_COMPANY', $arResult['EDITABLE_FIELDS']) || in_array('WORK_POSITION', $arResult['EDITABLE_FIELDS'])): ?>
                    <div class="form-section">
                        <h4>Место работы</h4>

                        <?php if (in_array('WORK_COMPANY', $arResult['EDITABLE_FIELDS'])): ?>
                            <div class="form-group">
                                <label for="company">Компания</label>
                                <input
                                    type="text"
                                    id="company"
                                    name="WORK_COMPANY"
                                    value="<?= htmlspecialcharsbx($user['WORK_COMPANY'] ?? '') ?>"
                                >
                            </div>
                        <?php endif; ?>

                        <?php if (in_array('WORK_POSITION', $arResult['EDITABLE_FIELDS'])): ?>
                            <div class="form-group">
                                <label for="position">Должность</label>
                                <input
                                    type="text"
                                    id="position"
                                    name="WORK_POSITION"
                                    value="<?= htmlspecialcharsbx($user['WORK_POSITION'] ?? '') ?>"
                                >
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Пользовательские поля -->
                <?php if (!empty($user['UF_FIELDS'])): ?>
                    <div class="form-section">
                        <h4>Дополнительные поля</h4>

                        <?php foreach ($user['UF_FIELDS'] as $ufField): ?>
                            <div class="form-group">
                                <label for="<?= $ufField['FIELD_NAME'] ?>">
                                    <?= htmlspecialcharsbx($ufField['EDIT_FORM_LABEL']) ?>
                                    <?php if ($ufField['MANDATORY'] === 'Y'): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>

                                <?php
                                // Простой вывод поля (для строк и чисел)
                                if (in_array($ufField['USER_TYPE_ID'], ['string', 'double', 'integer'])):
                                ?>
                                    <input
                                        type="<?= ($ufField['USER_TYPE_ID'] === 'integer' || $ufField['USER_TYPE_ID'] === 'double') ? 'number' : 'text' ?>"
                                        id="<?= $ufField['FIELD_NAME'] ?>"
                                        name="<?= $ufField['FIELD_NAME'] ?>"
                                        value="<?= htmlspecialcharsbx($ufField['VALUE']) ?>"
                                        <?= ($ufField['MANDATORY'] === 'Y') ? 'required' : '' ?>
                                    >
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Кнопки -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Сохранить изменения
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        Отменить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
