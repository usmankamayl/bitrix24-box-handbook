<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
?>

<div class="sms-sender" id="sms-sender-form">
    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="sms-sender__error">
            <?= htmlspecialchars($arResult['ERROR']) ?>
        </div>
    <?php else: ?>
        <!-- Результат отправки -->
        <?php if (!empty($arResult['FORM_RESULT'])): ?>
            <?php if ($arResult['FORM_RESULT']['success']): ?>
                <div class="sms-sender__success">
                    <?= htmlspecialchars($arParams['SUCCESS_MESSAGE']) ?>
                </div>
            <?php else: ?>
                <div class="sms-sender__error">
                    <?= htmlspecialchars($arResult['FORM_RESULT']['error']) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Форма отправки SMS -->
        <form class="sms-sender__form" method="POST" id="sms-form">
            <?= bitrix_sessid_post() ?>

            <div class="sms-sender__field">
                <label for="phone" class="sms-sender__label">
                    Номер телефона <span class="sms-sender__required">*</span>
                </label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    class="sms-sender__input"
                    placeholder="+7 (999) 123-45-67"
                    required
                >
                <small class="sms-sender__hint">
                    Формат: +7XXXXXXXXXX или 8XXXXXXXXXX
                </small>
            </div>

            <div class="sms-sender__field">
                <label for="message" class="sms-sender__label">
                    Текст сообщения <span class="sms-sender__required">*</span>
                </label>
                <textarea
                    id="message"
                    name="message"
                    class="sms-sender__textarea"
                    rows="4"
                    maxlength="1000"
                    placeholder="Введите текст SMS"
                    required
                ></textarea>
                <small class="sms-sender__hint">
                    <span id="char-counter">0</span> / 1000 символов
                </small>
            </div>

            <button type="submit" class="sms-sender__button" id="submit-btn">
                Отправить SMS
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($arParams['AJAX_MODE'] === 'Y'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('sms-form');
    const submitBtn = document.getElementById('submit-btn');
    const messageField = document.getElementById('message');
    const charCounter = document.getElementById('char-counter');
    const container = document.getElementById('sms-sender-form');

    // Счетчик символов
    if (messageField && charCounter) {
        messageField.addEventListener('input', function() {
            charCounter.textContent = this.value.length;
        });
    }

    // Маска для телефона
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');

            if (value.startsWith('8')) {
                value = '7' + value.substring(1);
            }

            if (value.startsWith('7') && value.length <= 11) {
                let formatted = '+7';
                if (value.length > 1) {
                    formatted += ' (' + value.substring(1, 4);
                }
                if (value.length >= 5) {
                    formatted += ') ' + value.substring(4, 7);
                }
                if (value.length >= 8) {
                    formatted += '-' + value.substring(7, 9);
                }
                if (value.length >= 10) {
                    formatted += '-' + value.substring(9, 11);
                }
                e.target.value = formatted;
            }
        });
    }

    // AJAX отправка формы
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Блокируем кнопку
            submitBtn.disabled = true;
            submitBtn.textContent = 'Отправка...';

            // Собираем данные
            const formData = new FormData(form);

            // Отправляем запрос
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Удаляем старые сообщения
                const oldMessages = container.querySelectorAll('.sms-sender__success, .sms-sender__error');
                oldMessages.forEach(msg => msg.remove());

                // Показываем результат
                const messageDiv = document.createElement('div');
                if (data.success) {
                    messageDiv.className = 'sms-sender__success';
                    messageDiv.textContent = '<?= CUtil::JSEscape($arParams['SUCCESS_MESSAGE']) ?>';
                    form.reset();
                    charCounter.textContent = '0';
                } else {
                    messageDiv.className = 'sms-sender__error';
                    messageDiv.textContent = data.error || 'Ошибка отправки';
                }

                container.insertBefore(messageDiv, form);

                // Прокручиваем к сообщению
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .catch(error => {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'sms-sender__error';
                messageDiv.textContent = 'Ошибка соединения';
                container.insertBefore(messageDiv, form);
            })
            .finally(() => {
                // Разблокируем кнопку
                submitBtn.disabled = false;
                submitBtn.textContent = 'Отправить SMS';
            });
        });
    }
});
</script>
<?php endif; ?>
