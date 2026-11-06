/**
 * JavaScript для формы обратной связи
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.feedback-form');

        forms.forEach(function(form) {
            initFeedbackForm(form);
        });
    });

    /**
     * Инициализация формы
     */
    function initFeedbackForm(form) {
        const wrapper = form.closest('.feedback-form-wrapper');
        const isAjax = form.dataset.ajax === 'true';

        // Ajax отправка
        if (isAjax) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitFormAjax(form);
            });
        }

        // Обновление капчи
        const captchaReload = form.querySelector('.feedback-form__captcha-reload');
        if (captchaReload) {
            captchaReload.addEventListener('click', function() {
                reloadCaptcha(form);
            });
        }

        // Валидация при вводе
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(function(input) {
            input.addEventListener('blur', function() {
                validateField(input);
            });
        });

        // Маска для телефона (опционально)
        const phoneInput = form.querySelector('input[name="phone"]');
        if (phoneInput) {
            initPhoneMask(phoneInput);
        }
    }

    /**
     * Ajax отправка формы
     */
    function submitFormAjax(form) {
        const submitBtn = form.querySelector('.feedback-form__submit');
        const formData = new FormData(form);

        // Блокируем кнопку
        submitBtn.classList.add('is-loading');
        submitBtn.disabled = true;

        // Очищаем предыдущие ошибки
        clearErrors(form);

        // Отправляем запрос
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            submitBtn.classList.remove('is-loading');
            submitBtn.disabled = false;

            if (data.success) {
                showSuccess(form, data.message);
            } else {
                showErrors(form, data.errors);

                // Обновляем капчу если была ошибка
                if (data.new_captcha) {
                    updateCaptcha(form, data.new_captcha);
                }
            }
        })
        .catch(function(error) {
            submitBtn.classList.remove('is-loading');
            submitBtn.disabled = false;

            showErrors(form, {
                GENERAL: 'Ошибка отправки формы. Попробуйте позже.'
            });

            console.error('Form submit error:', error);
        });
    }

    /**
     * Показать сообщение об успехе
     */
    function showSuccess(form, message) {
        const wrapper = form.closest('.feedback-form-wrapper');

        const successHtml = `
            <div class="feedback-form__success">
                <svg class="feedback-form__success-icon" width="48" height="48" viewBox="0 0 48 48">
                    <circle cx="24" cy="24" r="22" fill="#4CAF50" opacity="0.1"/>
                    <path d="M14 24l8 8 16-16" stroke="#4CAF50" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h3>${escapeHtml(message)}</h3>
                <button type="button" class="feedback-form__reset-btn" onclick="location.reload()">
                    Отправить еще одно сообщение
                </button>
            </div>
        `;

        wrapper.innerHTML = successHtml;

        // Скролл к сообщению
        wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /**
     * Показать ошибки
     */
    function showErrors(form, errors) {
        const wrapper = form.closest('.feedback-form-wrapper');

        // Удаляем старый блок ошибок
        const oldErrors = wrapper.querySelector('.feedback-form__errors');
        if (oldErrors) {
            oldErrors.remove();
        }

        // Создаем новый блок ошибок
        const errorsHtml = document.createElement('div');
        errorsHtml.className = 'feedback-form__errors';

        let errorsList = '<h4>Пожалуйста, исправьте ошибки:</h4><ul>';
        for (let key in errors) {
            errorsList += '<li>' + escapeHtml(errors[key]) + '</li>';
        }
        errorsList += '</ul>';

        errorsHtml.innerHTML = errorsList;

        // Вставляем перед формой
        form.parentElement.insertBefore(errorsHtml, form);

        // Скролл к ошибкам
        errorsHtml.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * Очистить ошибки
     */
    function clearErrors(form) {
        const wrapper = form.closest('.feedback-form-wrapper');
        const errors = wrapper.querySelector('.feedback-form__errors');
        if (errors) {
            errors.remove();
        }
    }

    /**
     * Обновить капчу
     */
    function updateCaptcha(form, captchaData) {
        const img = form.querySelector('[data-captcha-image]');
        const sid = form.querySelector('[data-captcha-sid]');
        const input = form.querySelector('input[name="captcha_word"]');

        if (img && sid) {
            img.src = captchaData.URL;
            sid.value = captchaData.CODE;
            if (input) {
                input.value = '';
            }
        }
    }

    /**
     * Перезагрузить капчу
     */
    function reloadCaptcha(form) {
        const img = form.querySelector('[data-captcha-image]');
        if (img) {
            // Генерируем новый ID капчи
            const newSid = 'captcha_' + Math.random().toString(36).substr(2, 9);
            img.src = '/bitrix/tools/captcha.php?captcha_sid=' + newSid;

            const sidInput = form.querySelector('[data-captcha-sid]');
            if (sidInput) {
                sidInput.value = newSid;
            }

            const wordInput = form.querySelector('input[name="captcha_word"]');
            if (wordInput) {
                wordInput.value = '';
            }
        }
    }

    /**
     * Валидация поля
     */
    function validateField(input) {
        const value = input.value.trim();
        const name = input.name;

        // Проверка email
        if (name === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                setFieldError(input, 'Некорректный email адрес');
                return false;
            }
        }

        // Проверка телефона (базовая)
        if (name === 'phone' && value) {
            const phoneRegex = /^[\d\s\-\+\(\)]+$/;
            if (!phoneRegex.test(value) || value.replace(/\D/g, '').length < 10) {
                setFieldError(input, 'Некорректный номер телефона');
                return false;
            }
        }

        clearFieldError(input);
        return true;
    }

    /**
     * Установить ошибку поля
     */
    function setFieldError(input, message) {
        input.style.borderColor = '#e53935';

        // Добавляем текст ошибки
        let errorEl = input.parentElement.querySelector('.field-error');
        if (!errorEl) {
            errorEl = document.createElement('span');
            errorEl.className = 'field-error';
            errorEl.style.cssText = 'display:block;margin-top:4px;font-size:13px;color:#e53935;';
            input.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
    }

    /**
     * Очистить ошибку поля
     */
    function clearFieldError(input) {
        input.style.borderColor = '';
        const errorEl = input.parentElement.querySelector('.field-error');
        if (errorEl) {
            errorEl.remove();
        }
    }

    /**
     * Маска для телефона
     */
    function initPhoneMask(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');

            // Ограничиваем длину
            if (value.length > 11) {
                value = value.slice(0, 11);
            }

            // Применяем маску
            if (value.length > 0) {
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

    /**
     * Экранирование HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
