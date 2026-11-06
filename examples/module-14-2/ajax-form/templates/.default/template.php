<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\UI\Extension;

Extension::load('ui.notification');
Extension::load('ui.buttons');
?>

<!-- AJAX форма с валидацией -->
<form id="<?= $arResult['FORM_ID'] ?>" class="ajax-form">
    <div class="form-group">
        <label for="form-name">Ваше имя *</label>
        <input
            type="text"
            id="form-name"
            name="name"
            class="form-control"
            placeholder="Введите ваше имя"
            required
        >
        <small class="text-danger form-error" data-field="name"></small>
    </div>

    <div class="form-group">
        <label for="form-email">Email *</label>
        <input
            type="email"
            id="form-email"
            name="email"
            class="form-control"
            placeholder="Введите ваш email"
            required
        >
        <small class="text-danger form-error" data-field="email"></small>
    </div>

    <div class="form-group">
        <label for="form-phone">Телефон *</label>
        <input
            type="tel"
            id="form-phone"
            name="phone"
            class="form-control"
            placeholder="+7 (XXX) XXX-XX-XX"
            required
        >
        <small class="text-danger form-error" data-field="phone"></small>
    </div>

    <div class="form-group">
        <label for="form-message">Сообщение *</label>
        <textarea
            id="form-message"
            name="message"
            class="form-control"
            rows="5"
            placeholder="Введите ваше сообщение"
            required
        ></textarea>
        <small class="text-danger form-error" data-field="message"></small>
    </div>

    <button type="submit" id="form-submit-btn" class="btn btn-primary">
        Отправить
    </button>
</form>

<!-- Общие ошибки -->
<div id="form-general-error" class="alert alert-danger" style="display: none;"></div>

<!-- Успешное сообщение -->
<div id="form-success" class="alert alert-success" style="display: none;"></div>

<style>
.ajax-form {
    max-width: 600px;
    margin: 20px 0;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.form-error {
    display: block;
    margin-top: 4px;
    color: #dc3545;
    font-size: 12px;
}

.text-danger {
    color: #dc3545;
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 16px;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-primary:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
    opacity: 0.65;
}
</style>

<script>
BX.ready(function() {
    // Инициализация формы
    var form = BX('<?= $arResult['FORM_ID'] ?>');
    var submitBtn = BX('form-submit-btn');

    // Обработчик отправки формы
    BX.bind(form, 'submit', function(e) {
        e.preventDefault();

        // Очищаем ошибки
        clearErrors();

        // Собираем данные формы
        var formData = {
            name: form.elements['name'].value.trim(),
            email: form.elements['email'].value.trim(),
            phone: form.elements['phone'].value.trim(),
            message: form.elements['message'].value.trim(),
        };

        // Клиентская валидация
        if (!validateFormClient(formData)) {
            return;
        }

        // Отключаем кнопку и показываем процесс загрузки
        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';

        // AJAX запрос на сервер
        BX.ajax.runComponentAction(
            'bitrix:module.14.2.ajax-form',
            'submitForm',
            {
                mode: 'class',
                data: formData
            }
        ).then(
            function(response) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Отправить';

                if (response.data.success) {
                    // Успешная отправка
                    handleSuccess(response.data, form);
                } else {
                    // Ошибки валидации
                    displayErrors(response.data.errors);
                }
            },
            function(response) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Отправить';

                // Серверная ошибка
                console.error('AJAX ошибка:', response);
                BX.UI.Notification.Center.notify({
                    content: 'Ошибка при отправке формы',
                    position: 'top-right',
                    autoHideDelay: 5000
                });
            }
        );
    });

    /**
     * Клиентская валидация
     */
    function validateFormClient(data) {
        var errors = {};

        if (!data.name || data.name.length < 2) {
            errors.name = 'Введите ваше имя (минимум 2 символа)';
        }

        if (!data.email || !isValidEmail(data.email)) {
            errors.email = 'Введите корректный email адрес';
        }

        if (!data.phone || !isValidPhone(data.phone)) {
            errors.phone = 'Введите корректный номер телефона';
        }

        if (!data.message || data.message.length < 10) {
            errors.message = 'Введите сообщение (минимум 10 символов)';
        }

        if (Object.keys(errors).length > 0) {
            displayErrors(errors);
            return false;
        }

        return true;
    }

    /**
     * Проверка email
     */
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    /**
     * Проверка телефона
     */
    function isValidPhone(phone) {
        var digits = phone.replace(/[^0-9+]/g, '');
        return digits.length >= 10 && digits.length <= 15;
    }

    /**
     * Вывод ошибок
     */
    function displayErrors(errors) {
        for (var field in errors) {
            if (errors.hasOwnProperty(field)) {
                var errorElem = document.querySelector('.form-error[data-field="' + field + '"]');
                if (errorElem) {
                    errorElem.textContent = errors[field];
                    errorElem.style.display = 'block';

                    // Отмечаем поле ошибкой
                    var input = form.elements[field];
                    BX.addClass(input, 'is-invalid');
                }
            }
        }
    }

    /**
     * Очистка ошибок
     */
    function clearErrors() {
        var errorElems = document.querySelectorAll('.form-error');
        errorElems.forEach(function(elem) {
            elem.textContent = '';
            elem.style.display = 'none';
        });

        var inputs = form.querySelectorAll('.is-invalid');
        inputs.forEach(function(input) {
            BX.removeClass(input, 'is-invalid');
        });
    }

    /**
     * Обработка успешной отправки
     */
    function handleSuccess(data, form) {
        // Показываем уведомление
        BX.UI.Notification.Center.notify({
            content: data.message,
            position: 'top-right',
            autoHideDelay: 5000
        });

        // Очищаем форму
        form.reset();

        // Опционально: показываем сообщение в DOM
        var successDiv = BX('form-success');
        successDiv.textContent = data.message;
        successDiv.style.display = 'block';

        // Скрываем сообщение через 3 секунды
        setTimeout(function() {
            successDiv.style.display = 'none';
        }, 3000);
    }
});
</script>
