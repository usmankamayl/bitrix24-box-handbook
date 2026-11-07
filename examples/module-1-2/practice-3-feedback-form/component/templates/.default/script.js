/**
 * Прогрессивное улучшение: без JS форма отправляется обычным POST.
 * С JS — тот же POST, но ответ приходит JSON (ветка isAjax() в компоненте).
 *
 * Что важно: сервер решает, что вернуть, по заголовку X-Requested-With,
 * а не по параметру в форме. Клиент не может «попросить» JSON вместо HTML
 * и наоборот — падение JS не оставит пользователя с голым JSON на экране.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-feedback]');
    if (!root) {
        return;
    }

    var form = root.querySelector('[data-feedback-form]');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var submitButton = form.querySelector('.study-feedback__submit');
        if (submitButton) {
            submitButton.disabled = true;
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    var message = document.createElement('p');
                    message.className = 'study-feedback__success';
                    message.textContent = data.message;
                    form.replaceWith(message);
                    return;
                }

                root.querySelectorAll('.study-feedback__error[data-js]').forEach(function (node) {
                    node.remove();
                });

                var box = document.createElement('p');
                box.className = 'study-feedback__error';
                box.setAttribute('data-js', '1');
                box.textContent = (data.errors || ['Не удалось отправить форму.']).join(' ');
                form.parentNode.insertBefore(box, form);

                if (data.captcha && data.captcha.sid) {
                    var image = root.querySelector('[data-captcha-image]');
                    var sid = root.querySelector('[data-captcha-sid]');

                    if (image) {
                        image.src = '/bitrix/tools/captcha.php?captcha_sid=' + encodeURIComponent(data.captcha.sid);
                    }
                    if (sid) {
                        sid.value = data.captcha.sid;
                    }
                }

                if (submitButton) {
                    submitButton.disabled = false;
                }
            })
            .catch(function () {
                // Сеть или парсинг упали — отправляем форму обычным способом.
                if (submitButton) {
                    submitButton.disabled = false;
                }
                form.submit();
            });
    });
}());
