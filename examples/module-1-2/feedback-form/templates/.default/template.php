<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Шаблон формы обратной связи
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $component
 */

$this->addExternalCss($templateFolder . "/style.css");
$this->addExternalJs($templateFolder . "/script.js");

// Уникальный ID формы для ajax
$formId = "feedback-form-" . randString(6);
?>

<div class="feedback-form-wrapper" data-form-id="<?= $formId ?>">

    <?php if ($arResult["SUCCESS"] === true): ?>
        <!-- Сообщение об успешной отправке -->
        <div class="feedback-form__success">
            <svg class="feedback-form__success-icon" width="48" height="48" viewBox="0 0 48 48">
                <circle cx="24" cy="24" r="22" fill="#4CAF50" opacity="0.1"/>
                <path d="M14 24l8 8 16-16" stroke="#4CAF50" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h3><?= htmlspecialcharsEx($arResult["MESSAGE"]) ?></h3>
            <button type="button" class="feedback-form__reset-btn" onclick="location.reload()">
                Отправить еще одно сообщение
            </button>
        </div>

    <?php elseif ($arResult["SUCCESS"] === false && !empty($arResult["ERRORS"])): ?>
        <!-- Ошибки валидации -->
        <div class="feedback-form__errors">
            <h4>Пожалуйста, исправьте ошибки:</h4>
            <ul>
                <?php foreach ($arResult["ERRORS"] as $error): ?>
                    <li><?= htmlspecialcharsEx($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($arResult["SUCCESS"] !== true): ?>
        <!-- Форма -->
        <form
            id="<?= $formId ?>"
            class="feedback-form"
            method="post"
            action="<?= POST_FORM_ACTION_URI ?>"
            data-ajax="<?= $arParams["USE_AJAX"] ? "true" : "false" ?>"
        >
            <input type="hidden" name="feedback_form" value="Y">
            <input type="hidden" name="form_time" value="<?= $arResult["FORM_TIME"] ?>">

            <?php if ($arParams["USE_HONEYPOT"]): ?>
                <!-- Honeypot: скрытое поле для защиты от ботов -->
                <input type="text" name="website" class="feedback-form__honeypot" tabindex="-1" autocomplete="off">
            <?php endif; ?>

            <div class="feedback-form__grid">

                <!-- Имя -->
                <div class="feedback-form__field <?= in_array("NAME", $arParams["REQUIRED_FIELDS"]) ? "feedback-form__field--required" : "" ?>">
                    <label for="<?= $formId ?>_name" class="feedback-form__label">
                        Ваше имя
                        <?php if (in_array("NAME", $arParams["REQUIRED_FIELDS"])): ?>
                            <span class="feedback-form__required">*</span>
                        <?php endif; ?>
                    </label>
                    <input
                        type="text"
                        id="<?= $formId ?>_name"
                        name="name"
                        class="feedback-form__input"
                        value="<?= htmlspecialcharsEx($arResult["FORM_DATA"]["NAME"] ?? "") ?>"
                        placeholder="Иван Иванов"
                    >
                </div>

                <!-- Email -->
                <div class="feedback-form__field <?= in_array("EMAIL", $arParams["REQUIRED_FIELDS"]) ? "feedback-form__field--required" : "" ?>">
                    <label for="<?= $formId ?>_email" class="feedback-form__label">
                        Email
                        <?php if (in_array("EMAIL", $arParams["REQUIRED_FIELDS"])): ?>
                            <span class="feedback-form__required">*</span>
                        <?php endif; ?>
                    </label>
                    <input
                        type="email"
                        id="<?= $formId ?>_email"
                        name="email"
                        class="feedback-form__input"
                        value="<?= htmlspecialcharsEx($arResult["FORM_DATA"]["EMAIL"] ?? "") ?>"
                        placeholder="ivan@example.com"
                    >
                </div>

                <!-- Телефон -->
                <div class="feedback-form__field <?= in_array("PHONE", $arParams["REQUIRED_FIELDS"]) ? "feedback-form__field--required" : "" ?>">
                    <label for="<?= $formId ?>_phone" class="feedback-form__label">
                        Телефон
                        <?php if (in_array("PHONE", $arParams["REQUIRED_FIELDS"])): ?>
                            <span class="feedback-form__required">*</span>
                        <?php endif; ?>
                    </label>
                    <input
                        type="tel"
                        id="<?= $formId ?>_phone"
                        name="phone"
                        class="feedback-form__input"
                        value="<?= htmlspecialcharsEx($arResult["FORM_DATA"]["PHONE"] ?? "") ?>"
                        placeholder="+7 (999) 123-45-67"
                    >
                </div>

                <!-- Тема -->
                <div class="feedback-form__field <?= in_array("SUBJECT", $arParams["REQUIRED_FIELDS"]) ? "feedback-form__field--required" : "" ?>">
                    <label for="<?= $formId ?>_subject" class="feedback-form__label">
                        Тема сообщения
                        <?php if (in_array("SUBJECT", $arParams["REQUIRED_FIELDS"])): ?>
                            <span class="feedback-form__required">*</span>
                        <?php endif; ?>
                    </label>
                    <input
                        type="text"
                        id="<?= $formId ?>_subject"
                        name="subject"
                        class="feedback-form__input"
                        value="<?= htmlspecialcharsEx($arResult["FORM_DATA"]["SUBJECT"] ?? "") ?>"
                        placeholder="Тема вашего обращения"
                    >
                </div>

            </div>

            <!-- Сообщение -->
            <div class="feedback-form__field feedback-form__field--full <?= in_array("MESSAGE", $arParams["REQUIRED_FIELDS"]) ? "feedback-form__field--required" : "" ?>">
                <label for="<?= $formId ?>_message" class="feedback-form__label">
                    Сообщение
                    <?php if (in_array("MESSAGE", $arParams["REQUIRED_FIELDS"])): ?>
                        <span class="feedback-form__required">*</span>
                    <?php endif; ?>
                </label>
                <textarea
                    id="<?= $formId ?>_message"
                    name="message"
                    class="feedback-form__textarea"
                    rows="6"
                    placeholder="Опишите ваш вопрос или предложение..."
                ><?= htmlspecialcharsEx($arResult["FORM_DATA"]["MESSAGE"] ?? "") ?></textarea>
            </div>

            <?php if ($arParams["USE_CAPTCHA"]): ?>
                <!-- Капча -->
                <div class="feedback-form__captcha">
                    <label for="<?= $formId ?>_captcha" class="feedback-form__label">
                        Введите текст с картинки
                        <span class="feedback-form__required">*</span>
                    </label>
                    <div class="feedback-form__captcha-wrapper">
                        <img
                            src="<?= $arResult["CAPTCHA"]["URL"] ?>"
                            alt="Captcha"
                            class="feedback-form__captcha-image"
                            data-captcha-image
                        >
                        <input type="hidden" name="captcha_sid" value="<?= $arResult["CAPTCHA"]["CODE"] ?>" data-captcha-sid>
                        <input
                            type="text"
                            id="<?= $formId ?>_captcha"
                            name="captcha_word"
                            class="feedback-form__input"
                            placeholder="Введите код"
                            autocomplete="off"
                        >
                        <button type="button" class="feedback-form__captcha-reload" title="Обновить капчу">
                            ↻
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Кнопка отправки -->
            <div class="feedback-form__submit-wrapper">
                <button type="submit" class="feedback-form__submit">
                    <span class="feedback-form__submit-text">Отправить</span>
                    <span class="feedback-form__submit-loader"></span>
                </button>
                <p class="feedback-form__hint">
                    <span class="feedback-form__required">*</span> — обязательные поля
                </p>
            </div>

        </form>
    <?php endif; ?>

</div>
