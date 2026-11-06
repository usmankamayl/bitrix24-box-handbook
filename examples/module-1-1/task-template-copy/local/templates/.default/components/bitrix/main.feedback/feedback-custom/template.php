<?php

/**
 * Копия шаблона .default компонента bitrix:main.feedback (задача 3).
 *
 * Куда положить:
 *   /local/templates/<шаблон сайта>/components/bitrix/main.feedback/feedback-custom/
 *
 * Что изменено относительно оригинала:
 *   1. убрано поле EMAIL (заказчик «не собирает почту»);
 *   2. подписи вынесены в lang/ru/template.php и берутся из $MESS;
 *   3. добавлены id/for у полей и required-подсказка;
 *   4. вывод ошибок и сообщений — через htmlspecialcharsbx().
 *
 * Оригинал НЕ копируется сюда целиком и не переписывается по памяти:
 * база — файл из /bitrix/components/bitrix/main.feedback/templates/.default/,
 * правки — только перечисленные выше. Проверка:
 *   diff -u bitrix/components/bitrix/main.feedback/templates/.default/template.php \
 *           local/templates/.default/components/bitrix/main.feedback/feedback-custom/template.php
 * В диффе должно быть ровно четыре смысловые правки.
 *
 * [проверить] Имена полей (user_name, user_email, MESSAGE) и ключи $arResult
 * (ERRORS, MESSAGE, PARAMS_HASH, AUTHOR_NAME) — часть контракта компонента,
 * а не шаблона. Если в твоей версии ядра они отличаются, шаблон отправляет не
 * то, что компонент ждёт: форма «успешно» отправится и письмо придёт пустым.
 * Сверяй с component.php своей сборки.
 *
 * @var array                    $arParams
 * @var array                    $arResult
 * @var CBitrixComponentTemplate $this
 * @var array                    $MESS
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$errors = (isset($arResult['ERRORS']) && is_array($arResult['ERRORS'])) ? $arResult['ERRORS'] : [];
$success = isset($arResult['MESSAGE']) ? (string)$arResult['MESSAGE'] : '';
$paramsHash = isset($arResult['PARAMS_HASH']) ? (string)$arResult['PARAMS_HASH'] : '';

$requiredFields = (isset($arParams['REQUIRED_FIELDS']) && is_array($arParams['REQUIRED_FIELDS']))
    ? $arParams['REQUIRED_FIELDS']
    : [];

/** Подпись из lang/ru/template.php; fallback — ключ, чтобы пустая строка не прятала ошибку. */
$label = static fn(string $key): string => (string)($MESS[$key] ?? $key);

$isRequired = static function (string $field) use ($requiredFields): bool {
    return $requiredFields === [] || in_array($field, $requiredFields, true);
};

$authorName = isset($arResult['AUTHOR_NAME']) ? (string)$arResult['AUTHOR_NAME'] : '';
$messageText = isset($arResult['MESSAGE_TEXT']) ? (string)$arResult['MESSAGE_TEXT'] : '';
?>
<div class="feedback-custom">
    <?php if ($errors !== []): ?>
        <div class="feedback-custom__errors" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialcharsbx((string)$error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="feedback-custom__message"><?= htmlspecialcharsbx($success) ?></div>
    <?php endif; ?>

    <form action="" method="post" class="feedback-custom__form">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="PARAMS_HASH"
               value="<?= htmlspecialcharsbx($paramsHash) ?>">

        <p class="feedback-custom__field">
            <label for="fc-name">
                <?= htmlspecialcharsbx($label('FC_LABEL_NAME')) ?><?php if ($isRequired('NAME')): ?> <span class="feedback-custom__req" aria-hidden="true">*</span><?php endif; ?>
            </label>
            <input id="fc-name" type="text" name="user_name" maxlength="100"
                   value="<?= htmlspecialcharsbx($authorName) ?>"
                   <?php if ($isRequired('NAME')): ?>required<?php endif; ?>>
        </p>

        <?php
        /*
         * Поле EMAIL удалено по требованию заказчика.
         * Цена решения — в README: без user_email компонент не заполняет
         * Reply-To письма, и менеджер не может ответить кнопкой «Ответить».
         * Если почта нужна для ответа клиенту — поле оставляют, а меняют
         * только подпись и REQUIRED_FIELDS в вызове компонента.
         */
        ?>

        <p class="feedback-custom__field">
            <label for="fc-message">
                <?= htmlspecialcharsbx($label('FC_LABEL_MESSAGE')) ?><?php if ($isRequired('MESSAGE')): ?> <span class="feedback-custom__req" aria-hidden="true">*</span><?php endif; ?>
            </label>
            <textarea id="fc-message" name="MESSAGE" rows="5" cols="40"
                      <?php if ($isRequired('MESSAGE')): ?>required<?php endif; ?>><?= htmlspecialcharsbx($messageText) ?></textarea>
        </p>

        <?php if (($arParams['USE_CAPTCHA'] ?? 'N') === 'Y'): ?>
            <p class="feedback-custom__field">
                <input type="hidden" name="captcha_sid"
                       value="<?= htmlspecialcharsbx((string)($arResult['capCode'] ?? '')) ?>">
                <img src="/bitrix/tools/captcha.php?captcha_sid=<?= urlencode((string)($arResult['capCode'] ?? '')) ?>"
                     width="180" height="40" alt="CAPTCHA">
                <label for="fc-captcha"><?= htmlspecialcharsbx($label('FC_LABEL_CAPTCHA')) ?></label>
                <input id="fc-captcha" type="text" name="captcha_word" maxlength="50" size="30"
                       autocomplete="off" required>
            </p>
        <?php endif; ?>

        <p class="feedback-custom__field">
            <button type="submit" name="web_form_submit" value="Y">
                <?= htmlspecialcharsbx($label('FC_BUTTON_SEND')) ?>
            </button>
        </p>
    </form>
</div>
