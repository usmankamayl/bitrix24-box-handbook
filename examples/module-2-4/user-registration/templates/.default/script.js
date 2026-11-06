// Валидация формы регистрации на клиенте
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.registration-form-fields');

    if (!form) return;

    const passwordField = document.getElementById('reg-password');
    const confirmField = document.getElementById('reg-password-confirm');

    // Проверка совпадения паролей
    function validatePasswords() {
        if (passwordField.value && confirmField.value) {
            if (passwordField.value !== confirmField.value) {
                confirmField.setCustomValidity('Пароли не совпадают');
            } else {
                confirmField.setCustomValidity('');
            }
        }
    }

    passwordField.addEventListener('input', validatePasswords);
    confirmField.addEventListener('input', validatePasswords);

    // Валидация формы перед отправкой
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            return false;
        }

        validatePasswords();

        if (confirmField.validationMessage) {
            e.preventDefault();
            alert('Пожалуйста, исправьте ошибки в форме');
            return false;
        }
    });
});
