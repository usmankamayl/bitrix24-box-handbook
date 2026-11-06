/**
 * JavaScript для кастомизации CRM формы
 *
 * Демонстрирует:
 * - Добавление кастомных полей через события
 * - Модификацию существующих полей
 * - Валидацию и сохранение
 * - Интеграцию с бизнес-процессами
 */

(function() {
    'use strict';

    var CrmCustomFields = {
        editor: null,
        config: null,

        /**
         * Инициализация
         */
        init: function(config) {
            this.config = config;

            // Подписка на инициализацию редактора CRM
            BX.addCustomEvent('BX.Crm.EntityEditor:onInit', this.onEditorInit.bind(this));

            // Подписка на контроль перед сохранением
            BX.addCustomEvent('BX.Crm.EntityEditor:onBeforeSave', this.onBeforeSave.bind(this));

            // Подписка на успешное сохранение
            BX.addCustomEvent('BX.Crm.EntityEditor:onSave', this.onAfterSave.bind(this));
        },

        /**
         * Обработчик инициализации редактора
         */
        onEditorInit: function(editor) {
            this.editor = editor;

            console.log('CRM Editor инициализирован:', editor);

            // Добавление кастомных полей
            if (this.config.enableCustomFields) {
                this.addCustomFields();
            }

            // Модификация стандартных полей
            if (this.config.modifyStandardFields) {
                this.modifyStandardFields();
            }

            // Добавление кастомных кнопок
            this.addCustomButtons();
        },

        /**
         * Добавление кастомных полей
         */
        addCustomFields: function() {
            console.log('Добавление кастомных полей...');

            var customFields = this.config.customFields || [];

            customFields.forEach(function(fieldConfig) {
                this.addField(fieldConfig);
            }.bind(this));
        },

        /**
         * Добавление одного поля
         */
        addField: function(fieldConfig) {
            var field = this.editor.getControlByIdRecursive(fieldConfig.name);

            if (field) {
                console.log('Поле уже существует:', fieldConfig.name);
                return;
            }

            // Определение схемы поля
            var schemeElement = {
                name: fieldConfig.name,
                title: fieldConfig.title,
                type: this.mapFieldType(fieldConfig.type),
                data: {
                    fieldType: fieldConfig.type,
                    isRequired: fieldConfig.required || false,
                    isReadOnly: fieldConfig.readonly || false,
                }
            };

            // Для списков добавляем значения
            if (fieldConfig.type === 'list' && fieldConfig.values) {
                schemeElement.data.items = this.convertListValues(fieldConfig.values);
            }

            // Добавление поля
            this.editor.addField({
                name: fieldConfig.name,
                title: fieldConfig.title,
                type: this.mapFieldType(fieldConfig.type),
                schemeElement: schemeElement
            });

            console.log('Добавлено поле:', fieldConfig.name);
        },

        /**
         * Маппинг типов полей
         */
        mapFieldType: function(type) {
            var typeMap = {
                'text': 'text',
                'textarea': 'textarea',
                'number': 'number',
                'date': 'date',
                'datetime': 'datetime',
                'list': 'list',
                'user': 'client',
                'user_multi': 'client',
                'checkbox': 'boolean',
            };

            return typeMap[type] || 'text';
        },

        /**
         * Конвертация значений списка
         */
        convertListValues: function(values) {
            var items = [];

            for (var key in values) {
                if (values.hasOwnProperty(key)) {
                    items.push({
                        VALUE: key,
                        NAME: values[key]
                    });
                }
            }

            return items;
        },

        /**
         * Модификация стандартных полей
         */
        modifyStandardFields: function() {
            console.log('Модификация стандартных полей...');

            // Пример 1: Изменение заголовка поля "Сумма"
            var opportunityField = this.editor.getControlByIdRecursive('OPPORTUNITY');
            if (opportunityField) {
                opportunityField.setTitle('Бюджет проекта');
                console.log('Изменён заголовок поля OPPORTUNITY');
            }

            // Пример 2: Скрытие поля "Валюта"
            var currencyField = this.editor.getControlByIdRecursive('CURRENCY_ID');
            if (currencyField) {
                // currencyField.setVisible(false);
                // console.log('Скрыто поле CURRENCY_ID');
            }

            // Пример 3: Блокировка поля "Дата закрытия" до определенной стадии
            var closeDateField = this.editor.getControlByIdRecursive('CLOSEDATE');
            if (closeDateField) {
                var stageField = this.editor.getControlByIdRecursive('STAGE_ID');
                if (stageField) {
                    var stageValue = stageField.getValue();
                    if (stageValue === 'NEW') {
                        closeDateField.setReadOnly(true);
                        console.log('Поле CLOSEDATE заблокировано для стадии NEW');
                    }
                }
            }

            // Пример 4: Добавление валидации
            this.addCustomValidation();
        },

        /**
         * Добавление кастомной валидации
         */
        addCustomValidation: function() {
            // Валидация оценки качества (должна быть от 1 до 100)
            var qualityField = this.editor.getControlByIdRecursive('UF_CRM_QUALITY_SCORE');
            if (qualityField) {
                qualityField.addValidator(function(value) {
                    if (value && (value < 1 || value > 100)) {
                        return {
                            valid: false,
                            message: 'Оценка качества должна быть от 1 до 100'
                        };
                    }
                    return { valid: true };
                });
            }
        },

        /**
         * Добавление кастомных кнопок
         */
        addCustomButtons: function() {
            // Добавление кнопки "Запустить бизнес-процесс"
            var toolbar = document.querySelector('.crm-entity-card-widget-toolbar');
            if (toolbar) {
                var btnHtml = '<button class="ui-btn ui-btn-light-border" onclick="CrmCustomFields.runBusinessProcess()">' +
                    'Запустить БП' +
                    '</button>';

                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = btnHtml;
                toolbar.appendChild(tempDiv.firstChild);
            }
        },

        /**
         * Обработчик перед сохранением
         */
        onBeforeSave: function(event) {
            console.log('Перед сохранением:', event);

            // Получение данных формы
            var data = this.editor.getDataForSave();

            // Кастомная валидация
            var validationErrors = this.validateData(data);

            if (validationErrors.length > 0) {
                // Показ ошибок
                validationErrors.forEach(function(error) {
                    BX.UI.Notification.Center.notify({
                        content: error,
                        position: 'top-right',
                        autoHideDelay: 5000
                    });
                });

                // Отмена сохранения
                event.cancel = true;
                return false;
            }

            // Модификация данных перед сохранением
            this.modifyDataBeforeSave(data);

            return true;
        },

        /**
         * Валидация данных
         */
        validateData: function(data) {
            var errors = [];

            // Пример: проверка суммы сделки
            if (data.OPPORTUNITY && data.OPPORTUNITY < 0) {
                errors.push('Сумма сделки не может быть отрицательной');
            }

            // Пример: проверка обязательности проекта для высокоприоритетных сделок
            var priority = data.UF_CRM_PRIORITY;
            var projectId = data.UF_CRM_PROJECT_ID;

            if ((priority === 'HIGH' || priority === 'CRITICAL') && !projectId) {
                errors.push('Для сделок с высоким приоритетом необходимо указать проект');
            }

            // Пример: проверка тегов
            var tags = data.UF_CRM_TAGS;
            if (tags && tags.split(',').length > 10) {
                errors.push('Максимум 10 тегов');
            }

            return errors;
        },

        /**
         * Модификация данных перед сохранением
         */
        modifyDataBeforeSave: function(data) {
            // Пример 1: Автоматическое заполнение внешнего ID
            if (!data.UF_CRM_EXTERNAL_ID) {
                data.UF_CRM_EXTERNAL_ID = 'CRM_' + Date.now();
            }

            // Пример 2: Обновление времени последней активности
            data.UF_CRM_LAST_ACTIVITY = new Date().toISOString();

            // Пример 3: Нормализация тегов
            if (data.UF_CRM_TAGS) {
                data.UF_CRM_TAGS = data.UF_CRM_TAGS
                    .split(',')
                    .map(function(tag) { return tag.trim(); })
                    .filter(function(tag) { return tag.length > 0; })
                    .join(', ');
            }
        },

        /**
         * Обработчик после сохранения
         */
        onAfterSave: function(event) {
            console.log('После сохранения:', event);

            BX.UI.Notification.Center.notify({
                content: 'Сделка успешно сохранена!',
                position: 'top-right',
                autoHideDelay: 3000
            });

            // Пример: запуск бизнес-процесса после создания
            if (event.isNew) {
                this.checkAndRunBusinessProcess(event.entityId);
            }
        },

        /**
         * Проверка и запуск бизнес-процесса
         */
        checkAndRunBusinessProcess: function(entityId) {
            console.log('Проверка БП для сущности:', entityId);

            // Получение данных сделки
            var data = this.editor.getDataForSave();

            // Если приоритет критичный - запустить БП автоматически
            if (data.UF_CRM_PRIORITY === 'CRITICAL') {
                BX.UI.Dialogs.MessageBox.confirm(
                    'Запустить бизнес-процесс "Критичная сделка"?',
                    function(result) {
                        if (result) {
                            CrmCustomFields.runBusinessProcess();
                        }
                    }
                );
            }
        },

        /**
         * Запуск бизнес-процесса
         */
        runBusinessProcess: function() {
            BX.UI.Notification.Center.notify({
                content: 'Бизнес-процесс запущен...',
                position: 'top-right',
                autoHideDelay: 3000
            });

            // Здесь должна быть логика запуска БП
            // Например, через BX.ajax.runAction()
        },

        /**
         * Получение значения поля
         */
        getFieldValue: function(fieldName) {
            var field = this.editor.getControlByIdRecursive(fieldName);
            return field ? field.getValue() : null;
        },

        /**
         * Установка значения поля
         */
        setFieldValue: function(fieldName, value) {
            var field = this.editor.getControlByIdRecursive(fieldName);
            if (field) {
                field.setValue(value);
            }
        },

        /**
         * Показ/скрытие поля
         */
        toggleField: function(fieldName, visible) {
            var field = this.editor.getControlByIdRecursive(fieldName);
            if (field) {
                field.setVisible(visible);
            }
        },

        /**
         * Блокировка/разблокировка поля
         */
        toggleFieldReadOnly: function(fieldName, readOnly) {
            var field = this.editor.getControlByIdRecursive(fieldName);
            if (field) {
                field.setReadOnly(readOnly);
            }
        }
    };

    // Экспорт в глобальную область
    window.CrmCustomFields = CrmCustomFields;

})();
