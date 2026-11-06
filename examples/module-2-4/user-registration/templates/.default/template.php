<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 */
?>

<div class="registration-form">
    <?php if (!empty($arResult['ERRORS'])): ?>
        <div class="registration-errors">
            <?php foreach ($arResult['ERRORS'] as $error): ?>
                <div class="error-message"><?= htmlspecialcharsbx($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($arResult['SUCCESS']): ?>
        <div class="registration-success">
            <p>Регистрация успешно завершена!</p>
            <?php if ($arParams['AUTO_AUTH'] === 'Y'): ?>
                <p>Вы автоматически авторизованы.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <form method="post" action="<?= POST_FORM_ACTION_URI ?>" class="registration-form-fields">
            <?= bitrix_sessid_post() ?>

            <div class="form-group">
                <label for="reg-login">
                    Логин <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="reg-login"
                    name="LOGIN"
                    value="<?= htmlspecialcharsbx($arResult['FORM_DATA']['LOGIN'] ?? '') ?>"
                    required
                    minlength="3"
                    placeholder="Введите логин"
                >
                <small>Минимум 3 символа</small>
            </div>

            <div class="form-group">
                <label for="reg-email">
                    Email <span class="required">*</span>
                </label>
                <input
                    type="email"
                    id="reg-email"
                    name="EMAIL"
                    value="<?= htmlspecialcharsbx($arResult['FORM_DATA']['EMAIL'] ?? '') ?>"
                    required
                    placeholder="example@mail.com"
                >
            </div>

            <div class="form-group">
                <label for="reg-name">
                    Имя <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="reg-name"
                    name="NAME"
                    value="<?= htmlspecialcharsbx($arResult['FORM_DATA']['NAME'] ?? '') ?>"
                    required
                    placeholder="Ваше имя"
                >
            </div>

            <div class="form-group">
                <label for="reg-last-name">Фамилия</label>
                <input
                    type="text"
                    id="reg-last-name"
                    name="LAST_NAME"
                    value="<?= htmlspecialcharsbx($arResult['FORM_DATA']['LAST_NAME'] ?? '') ?>"
                    placeholder="Ваша фамилия"
                >
            </div>

            <div class="form-group">
                <label for="reg-phone">Телефон</label>
                <input
                    type="tel"
                    id="reg-phone"
                    name="PHONE"
                    value="<?= htmlspecialcharsbx($arResult['FORM_DATA']['PHONE'] ?? '') ?>"
                    placeholder="+7 (999) 999-99-99"
                >
            </div>

            <div class="form-group">
                <label for="reg-password">
                    Пароль <span class="required">*</span>
                </label>
                <input
                    type="password"
                    id="reg-password"
                    name="PASSWORD"
                    required
                    minlength="6"
                    placeholder="Минимум 6 символов"
                >
                <small>Минимум 6 символов</small>
            </div>

            <div class="form-group">
                <label for="reg-password-confirm">
                    Подтверждение пароля <span class="required">*</span>
                </label>
                <input
                    type="password"
                    id="reg-password-confirm"
                    name="PASSWORD_CONFIRM"
                    required
                    minlength="6"
                    placeholder="Повторите пароль"
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Зарегистрироваться
                </button>
            </div>

            <div class="form-footer">
                <p>Уже есть аккаунт? <a href="/auth/">Войти</a></p>
            </div>
        </form>
    <?php endif; ?>
</div>
