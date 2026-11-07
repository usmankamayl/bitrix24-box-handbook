<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 * @var string $templateFolder
 */

$data = $arResult['FORM_DATA'] ?? [];
$errors = $arResult['ERRORS'] ?? [];
$success = (bool)($arResult['SUCCESS'] ?? false);
?>
<div class="study-feedback" data-feedback>
    <?php if ($success): ?>
        <p class="study-feedback__success">
            <?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_DONE')) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($errors['FORM']) || !empty($errors['SESSID'])): ?>
        <p class="study-feedback__error">
            <?= htmlspecialcharsbx($errors['FORM'] ?? $errors['SESSID']) ?>
        </p>
    <?php endif; ?>

    <form
        class="study-feedback__form"
        method="post"
        action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>"
        data-feedback-form
    >
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="study_feedback" value="Y">
        <input type="hidden" name="_ts" value="<?= htmlspecialcharsbx($arResult['TIME_TOKEN'] ?? '') ?>">

        <?php // Honeypot: поле уведено за пределы экрана, автозаполнение браузера его не трогает. ?>
        <div class="study-feedback__hp" aria-hidden="true">
            <label>
                Website
                <input type="text" name="_website" value="" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <label class="study-feedback__field">
            <span><?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_NAME')) ?> *</span>
            <input type="text" name="name" maxlength="100"
                   value="<?= htmlspecialcharsbx($data['name'] ?? '') ?>">
            <?php if (!empty($errors['name'])): ?>
                <em class="study-feedback__error"><?= htmlspecialcharsbx($errors['name']) ?></em>
            <?php endif; ?>
        </label>

        <label class="study-feedback__field">
            <span><?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_EMAIL')) ?> *</span>
            <input type="email" name="email" maxlength="254"
                   value="<?= htmlspecialcharsbx($data['email'] ?? '') ?>">
            <?php if (!empty($errors['email'])): ?>
                <em class="study-feedback__error"><?= htmlspecialcharsbx($errors['email']) ?></em>
            <?php endif; ?>
        </label>

        <label class="study-feedback__field">
            <span><?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_PHONE')) ?></span>
            <input type="tel" name="phone" maxlength="32"
                   value="<?= htmlspecialcharsbx($data['phone'] ?? '') ?>">
        </label>

        <label class="study-feedback__field">
            <span><?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_MESSAGE')) ?> *</span>
            <textarea name="message" rows="5" maxlength="4000"><?= htmlspecialcharsbx($data['message'] ?? '') ?></textarea>
            <?php if (!empty($errors['message'])): ?>
                <em class="study-feedback__error"><?= htmlspecialcharsbx($errors['message']) ?></em>
            <?php endif; ?>
        </label>

        <?php if ($arParams['USE_CAPTCHA'] && !empty($arResult['CAPTCHA_CODE'])): ?>
            <div class="study-feedback__captcha">
                <img
                    src="/bitrix/tools/captcha.php?captcha_sid=<?= urlencode($arResult['CAPTCHA_CODE']) ?>"
                    alt="<?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_CAPTCHA')) ?>"
                    width="180"
                    height="40"
                    data-captcha-image
                >
                <input type="hidden" name="captcha_sid"
                       value="<?= htmlspecialcharsbx($arResult['CAPTCHA_CODE']) ?>" data-captcha-sid>
                <input type="text" name="captcha_word" maxlength="50" autocomplete="off"
                       placeholder="<?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_CAPTCHA')) ?>">
                <?php if (!empty($errors['CAPTCHA'])): ?>
                    <em class="study-feedback__error"><?= htmlspecialcharsbx($errors['CAPTCHA']) ?></em>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="study-feedback__submit">
            <?= htmlspecialcharsbx(GetMessage('STUDY_FEEDBACK_SUBMIT')) ?>
        </button>
    </form>
</div>
