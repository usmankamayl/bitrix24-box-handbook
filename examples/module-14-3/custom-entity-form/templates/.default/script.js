/**
 * JavaScript для компонента формы редактирования сущности
 *
 * Функционал:
 * - Валидация формы на клиенте
 * - Автосохранение черновиков
 * - Работа с табами
 * - Выбор пользователей
 * - Загрузка файлов
 */

var EntityForm = {
    config: null,
    form: null,
    autosaveTimer: null,
    isDirty: false,
    validator: null,

    /**
     * Инициализация
     */
    init: function(config) {
        this.config = config;
        this.form = document.getElementById(config.formId);

        if (!this.form) {
            console.error('Форма не найдена');
            return;
        }

        // Инициализация валидатора
        this.validator = new FormValidator(this.form, this.getValidationRules());

        // Обработчик отправки формы
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitForm();
        });

        // Отслеживание изменений для автосохранения
        if (this.config.enableAutosave) {
            this.initAutosave();
        }

        // Предупреждение при уходе со страницы
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty) {
                e.preventDefault();
                e.returnValue = 'Есть несохранённые изменения. Покинуть страницу?';
            }
        });
    },

    /**
     * Правила валидации
     */
    getValidationRules: function() {
        const rules = {};

        this.config.formConfig.sections.forEach(section => {
            section.fields.forEach(field => {
                if (field.required || field.minLength || field.type === 'email') {
                    rules[field.name] = {
                        required: field.required,
                        minLength: field.minLength,
                        type: field.type
                    };
                }
            });
        });

        return rules;
    },

    /**
     * Инициализация автосохранения
     */
    initAutosave: function() {
        // Отслеживание изменений
        this.form.addEventListener('input', () => {
            this.isDirty = true;
            this.resetAutosaveTimer();
        });

        // Запуск таймера
        this.resetAutosaveTimer();
    },

    /**
     * Сброс таймера автосохранения
     */
    resetAutosaveTimer: function() {
        if (this.autosaveTimer) {
            clearTimeout(this.autosaveTimer);
        }

        this.autosaveTimer = setTimeout(() => {
            if (this.isDirty) {
                this.autoSave();
            }
        }, this.config.autosaveInterval);
    },

    /**
     * Автосохранение черновика
     */
    autoSave: function() {
        const formData = this.getFormData();

        BX.ajax.runComponentAction(this.config.componentName, 'autoSave', {
            mode: 'class',
            data: formData,
            signedParameters: this.config.signedParameters
        }).then((response) => {
            if (response.data.success) {
                this.isDirty = false;
                this.showAutosaveStatus('Черновик сохранён');
            }
        }).catch((error) => {
            console.error('Ошибка автосохранения:', error);
        });
    },

    /**
     * Показ статуса автосохранения
     */
    showAutosaveStatus: function(message) {
        const statusEl = document.getElementById('autosave-status');
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.classList.add('success');

            setTimeout(() => {
                statusEl.textContent = 'Автосохранение включено';
                statusEl.classList.remove('success');
            }, 3000);
        }
    },

    /**
     * Отправка формы
     */
    submitForm: function() {
        // Валидация
        if (!this.validator.validate()) {
            this.validator.showErrors();

            BX.UI.Notification.Center.notify({
                content: 'Заполните обязательные поля',
                position: 'top-right',
                autoHideDelay: 3000
            });

            return;
        }

        // Блокировка кнопки
        const submitBtn = this.form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.querySelector('.btn-text').style.display = 'none';
        submitBtn.querySelector('.btn-loader').style.display = 'inline';

        // Получение данных
        const formData = this.getFormData();

        // Отправка
        BX.ajax.runComponentAction(this.config.componentName, 'save', {
            mode: 'class',
            data: formData,
            signedParameters: this.config.signedParameters
        }).then((response) => {
            if (response.data.success) {
                this.isDirty = false;

                BX.UI.Notification.Center.notify({
                    content: response.data.message,
                    position: 'top-right',
                    autoHideDelay: 3000
                });

                // Редирект
                setTimeout(() => {
                    if (response.data.redirectUrl) {
                        window.location.href = response.data.redirectUrl;
                    }
                }, 1000);
            }
        }).catch((error) => {
            // Показ ошибок сервера
            if (error.errors && error.errors.length > 0) {
                error.errors.forEach(err => {
                    this.showFieldError(err.customData, err.message);
                });

                BX.UI.Notification.Center.notify({
                    content: 'Исправьте ошибки в форме',
                    position: 'top-right',
                    autoHideDelay: 5000
                });
            } else {
                BX.UI.Notification.Center.notify({
                    content: 'Ошибка при сохранении',
                    position: 'top-right',
                    autoHideDelay: 5000
                });
            }

            // Разблокировка кнопки
            submitBtn.disabled = false;
            submitBtn.querySelector('.btn-text').style.display = 'inline';
            submitBtn.querySelector('.btn-loader').style.display = 'none';
        });
    },

    /**
     * Получение данных формы
     */
    getFormData: function() {
        const formData = new FormData(this.form);
        const data = {};

        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        return data;
    },

    /**
     * Показ ошибки поля
     */
    showFieldError: function(fieldName, message) {
        const fieldWrapper = this.form.querySelector(`[data-field="${fieldName}"]`);
        if (fieldWrapper) {
            const errorEl = fieldWrapper.querySelector('.form-field-error');
            const inputEl = fieldWrapper.querySelector('.form-input');

            errorEl.textContent = message;
            errorEl.style.display = 'block';
            inputEl.classList.add('error');
        }
    },

    /**
     * Переключение табов
     */
    switchTab: function(tabId) {
        // Деактивация всех табов
        this.form.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        this.form.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });

        // Активация выбранного
        this.form.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
        document.getElementById(`tab-${tabId}`).classList.add('active');
    },

    /**
     * Выбор пользователя
     */
    selectUser: function(fieldName) {
        BX.Main.ui.selector.open({
            context: 'ENTITY_FORM_USER_' + fieldName,
            multiple: false,
            enableSearch: true,
            entities: [
                {
                    id: 'user',
                    options: {
                        intranetUsersOnly: true
                    }
                }
            ],
            callback: (selected) => {
                if (selected.length > 0) {
                    const user = selected[0];
                    const userId = user.id.replace('U', '');

                    // Установка значения
                    document.getElementById(fieldName).value = userId;

                    // Отображение выбранного пользователя
                    const selectedUserDiv = document.getElementById(`selected-user-${fieldName}`);
                    selectedUserDiv.innerHTML = `
                        <span class="user-name">${user.title.text}</span>
                        <button type="button" class="user-remove" onclick="EntityForm.clearUser('${fieldName}')">×</button>
                    `;

                    this.isDirty = true;
                }
            }
        });
    },

    /**
     * Очистка выбранного пользователя
     */
    clearUser: function(fieldName) {
        document.getElementById(fieldName).value = '';
        document.getElementById(`selected-user-${fieldName}`).innerHTML = '';
        this.isDirty = true;
    },

    /**
     * Загрузка файла
     */
    uploadFile: function(fieldName, input) {
        const file = input.files[0];

        if (!file) {
            return;
        }

        // Проверка размера
        if (file.size > 10 * 1024 * 1024) {
            BX.UI.Notification.Center.notify({
                content: 'Файл слишком большой (макс 10 МБ)',
                position: 'top-right',
                autoHideDelay: 3000
            });
            return;
        }

        // Показ лоадера
        const fileList = document.getElementById(`file-list-${fieldName}`);
        const loader = BX.create('DIV', {
            props: { className: 'file-loader' },
            text: 'Загрузка...'
        });
        fileList.appendChild(loader);

        // Создание FormData
        const formData = new FormData();
        formData.append('file', file);

        // Загрузка
        BX.ajax.runComponentAction(this.config.componentName, 'uploadFile', {
            mode: 'class',
            data: formData,
            signedParameters: this.config.signedParameters,
            preparePost: false
        }).then((response) => {
            loader.remove();

            if (response.data.success) {
                const fileItem = BX.create('DIV', {
                    props: { className: 'file-item' },
                    html: `
                        <span class="file-name">${response.data.fileName}</span>
                        <span class="file-size">(${this.formatFileSize(response.data.fileSize)})</span>
                        <button type="button" class="file-remove" onclick="EntityForm.removeFile('${fieldName}', ${response.data.fileId})">×</button>
                        <input type="hidden" name="${fieldName}[]" value="${response.data.fileId}">
                    `
                });

                fileList.appendChild(fileItem);

                BX.UI.Notification.Center.notify({
                    content: 'Файл загружен',
                    position: 'top-right',
                    autoHideDelay: 2000
                });

                this.isDirty = true;
            }
        }).catch((error) => {
            loader.remove();

            BX.UI.Notification.Center.notify({
                content: error.errors[0]?.message || 'Ошибка загрузки файла',
                position: 'top-right',
                autoHideDelay: 5000
            });
        });

        // Очистка input
        input.value = '';
    },

    /**
     * Удаление файла
     */
    removeFile: function(fieldName, fileId) {
        const fileItem = document.querySelector(`input[value="${fileId}"]`).closest('.file-item');
        if (fileItem) {
            fileItem.remove();
            this.isDirty = true;
        }
    },

    /**
     * Форматирование размера файла
     */
    formatFileSize: function(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        } else if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        } else {
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
    },

    /**
     * Очистка формы
     */
    clearForm: function() {
        if (confirm('Очистить все поля формы?')) {
            this.form.reset();
            this.validator.clearErrors();
            this.isDirty = false;
        }
    },

    /**
     * Очистка черновика
     */
    clearDraft: function() {
        const notification = document.querySelector('.draft-notification');
        if (notification) {
            notification.remove();
        }
    }
};

/**
 * Класс валидатора формы
 */
class FormValidator {
    constructor(form, rules) {
        this.form = form;
        this.rules = rules;
        this.errors = {};
    }

    validate() {
        this.errors = {};

        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            const rule = this.rules[fieldName];

            if (!field) {
                return;
            }

            const value = field.value.trim();

            // Обязательное поле
            if (rule.required && !value) {
                this.addError(fieldName, 'Поле обязательно для заполнения');
            }

            // Минимальная длина
            if (rule.minLength && value.length > 0 && value.length < rule.minLength) {
                this.addError(fieldName, `Минимум ${rule.minLength} символов`);
            }

            // Email
            if (rule.type === 'email' && value.length > 0) {
                if (!this.isValidEmail(value)) {
                    this.addError(fieldName, 'Некорректный формат email');
                }
            }

            // Телефон
            if (rule.type === 'tel' && value.length > 0) {
                if (!this.isValidPhone(value)) {
                    this.addError(fieldName, 'Некорректный формат телефона');
                }
            }
        });

        return Object.keys(this.errors).length === 0;
    }

    addError(fieldName, message) {
        if (!this.errors[fieldName]) {
            this.errors[fieldName] = [];
        }
        this.errors[fieldName].push(message);
    }

    showErrors() {
        // Очистка предыдущих ошибок
        this.clearErrors();

        // Показ новых ошибок
        Object.keys(this.errors).forEach(fieldName => {
            const fieldWrapper = this.form.querySelector(`[data-field="${fieldName}"]`);

            if (fieldWrapper) {
                const errorEl = fieldWrapper.querySelector('.form-field-error');
                const inputEl = fieldWrapper.querySelector('.form-input');

                errorEl.textContent = this.errors[fieldName].join(', ');
                errorEl.style.display = 'block';
                inputEl.classList.add('error');
            }
        });

        // Прокрутка к первой ошибке
        const firstErrorField = Object.keys(this.errors)[0];
        const firstFieldWrapper = this.form.querySelector(`[data-field="${firstErrorField}"]`);
        if (firstFieldWrapper) {
            firstFieldWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    clearErrors() {
        this.form.querySelectorAll('.form-field-error').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
        this.form.querySelectorAll('.form-input').forEach(el => {
            el.classList.remove('error');
        });
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    isValidPhone(phone) {
        const cleaned = phone.replace(/[\s\-\(\)]/g, '');
        return /^\+?[0-9]{10,15}$/.test(cleaned);
    }
}
