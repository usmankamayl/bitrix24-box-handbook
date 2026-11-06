# Задача 2: CRM форма с кастомными полями

## Описание

Демонстрация кастомизации стандартной CRM формы Bitrix24: добавление пользовательских полей, модификация существующих полей и интеграция с бизнес-процессами.

## Возможности

### 1. Добавление кастомных полей
- **Проект** — привязка к системе управления проектами
- **Оценка качества** — числовая оценка от 1 до 100
- **Приоритет** — выбор из списка (Низкий/Средний/Высокий/Критичный)
- **Теги** — теги через запятую
- **Оценка рисков** — описание возможных рисков
- **Внешний ID** — связь с внешними системами
- **Последняя активность** — автоматическое обновление времени
- **Команда** — множественный выбор пользователей

### 2. Модификация стандартных полей
- Изменение заголовков полей
- Скрытие/показ полей
- Блокировка редактирования
- Изменение порядка полей

### 3. Валидация
- Клиентская валидация перед сохранением
- Проверка диапазонов значений
- Проверка обязательных связанных полей
- Ограничения на форматы данных

### 4. Автоматизация
- Автозаполнение полей
- Запуск бизнес-процессов
- Обновление связанных данных
- Нормализация данных

## Установка

### 1. Создайте пользовательские поля в CRM

Откройте **CRM → Настройки → Настройка полей**:

```
UF_CRM_PROJECT_ID
  Тип: Строка
  Название: Проект

UF_CRM_QUALITY_SCORE
  Тип: Число
  Название: Оценка качества

UF_CRM_PRIORITY
  Тип: Список
  Название: Приоритет
  Значения: LOW, MEDIUM, HIGH, CRITICAL

UF_CRM_TAGS
  Тип: Строка
  Название: Теги

UF_CRM_RISK_ASSESSMENT
  Тип: Текст
  Название: Оценка рисков

UF_CRM_EXTERNAL_ID
  Тип: Строка
  Название: Внешний ID

UF_CRM_LAST_ACTIVITY
  Тип: Дата/Время
  Название: Последняя активность

UF_CRM_RESPONSIBLE_TEAM
  Тип: Привязка к пользователю (множественная)
  Название: Команда
```

### 2. Подключите скрипт кастомизации

В `init.php` проекта:

```php
// /local/php_interface/init.php

use Bitrix\Main\Page\Asset;

// Подключение скрипта кастомизации CRM
$eventManager = \Bitrix\Main\EventManager::getInstance();

$eventManager->addEventHandler(
    'main',
    'OnEpilog',
    function() {
        if (defined('CRM_ENTITY_EDITOR')) {
            Asset::getInstance()->addJs('/local/js/crm-custom-fields.js');
            Asset::getInstance()->addCss('/local/js/crm-custom-fields.css');
        }
    }
);
```

### 3. Скопируйте файлы

```bash
# Скопируйте JavaScript
/local/js/crm-custom-fields.js

# Скопируйте CSS
/local/js/crm-custom-fields.css
```

## Использование

### Базовое использование

Скрипт автоматически подключается к CRM формам и применяет кастомизации.

### Инициализация вручную

```javascript
BX.ready(function() {
    CrmCustomFields.init({
        entityType: 'DEAL',
        entityId: 123,
        enableCustomFields: true,
        modifyStandardFields: true,
        customFields: [
            {
                name: 'UF_CRM_PROJECT_ID',
                title: 'Проект',
                type: 'text',
                required: false
            }
            // ... другие поля
        ]
    });
});
```

## API

### Методы объекта CrmCustomFields

#### init(config)
Инициализация кастомизации

```javascript
CrmCustomFields.init({
    entityType: 'DEAL',
    entityId: 0,
    enableCustomFields: true,
    modifyStandardFields: true
});
```

#### getFieldValue(fieldName)
Получение значения поля

```javascript
var priority = CrmCustomFields.getFieldValue('UF_CRM_PRIORITY');
console.log('Приоритет:', priority);
```

#### setFieldValue(fieldName, value)
Установка значения поля

```javascript
CrmCustomFields.setFieldValue('UF_CRM_QUALITY_SCORE', 85);
```

#### toggleField(fieldName, visible)
Показать/скрыть поле

```javascript
CrmCustomFields.toggleField('CURRENCY_ID', false); // скрыть
CrmCustomFields.toggleField('CURRENCY_ID', true);  // показать
```

#### toggleFieldReadOnly(fieldName, readOnly)
Заблокировать/разблокировать поле

```javascript
CrmCustomFields.toggleFieldReadOnly('CLOSEDATE', true);  // заблокировать
CrmCustomFields.toggleFieldReadOnly('CLOSEDATE', false); // разблокировать
```

#### addField(fieldConfig)
Добавление нового поля

```javascript
CrmCustomFields.addField({
    name: 'UF_MY_FIELD',
    title: 'Моё поле',
    type: 'text',
    required: true
});
```

#### runBusinessProcess()
Запуск бизнес-процесса

```javascript
CrmCustomFields.runBusinessProcess();
```

## События

### BX.Crm.EntityEditor:onInit
Инициализация редактора

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    console.log('Редактор инициализирован:', editor);
    
    // Ваша кастомизация
    editor.addField({
        name: 'UF_CUSTOM',
        title: 'Кастомное поле',
        type: 'text'
    });
});
```

### BX.Crm.EntityEditor:onBeforeSave
Перед сохранением

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onBeforeSave', function(event) {
    var data = event.editor.getDataForSave();
    
    // Валидация
    if (data.OPPORTUNITY < 0) {
        event.cancel = true;
        alert('Сумма не может быть отрицательной');
        return false;
    }
});
```

### BX.Crm.EntityEditor:onSave
После сохранения

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onSave', function(event) {
    console.log('Сохранено:', event);
    
    if (event.isNew) {
        console.log('Создана новая сущность:', event.entityId);
    }
});
```

## Примеры кастомизации

### Пример 1: Динамическое скрытие полей

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    // Скрываем поле "Валюта" если сумма = 0
    var opportunityField = editor.getControlByIdRecursive('OPPORTUNITY');
    var currencyField = editor.getControlByIdRecursive('CURRENCY_ID');
    
    if (opportunityField && currencyField) {
        opportunityField.subscribe('onChange', function() {
            var value = opportunityField.getValue();
            currencyField.setVisible(value > 0);
        });
    }
});
```

### Пример 2: Автозаполнение поля

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    var titleField = editor.getControlByIdRecursive('TITLE');
    var companyField = editor.getControlByIdRecursive('COMPANY_ID');
    
    if (titleField && companyField) {
        companyField.subscribe('onChange', function() {
            var company = companyField.getValue();
            
            if (company && !titleField.getValue()) {
                // Автозаполнение названия сделки
                titleField.setValue('Сделка с ' + company.title);
            }
        });
    }
});
```

### Пример 3: Валидация связанных полей

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onBeforeSave', function(event) {
    var data = event.editor.getDataForSave();
    
    // Проверка: если приоритет высокий, обязателен проект
    if ((data.UF_CRM_PRIORITY === 'HIGH' || data.UF_CRM_PRIORITY === 'CRITICAL') 
        && !data.UF_CRM_PROJECT_ID) {
        
        event.cancel = true;
        
        BX.UI.Notification.Center.notify({
            content: 'Для сделок с высоким приоритетом необходимо указать проект',
            position: 'top-right',
            autoHideDelay: 5000
        });
        
        return false;
    }
});
```

### Пример 4: Запуск БП при изменении стадии

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onSave', function(event) {
    var data = event.editor.getDataForSave();
    
    // Если стадия изменилась на "Выигрыш"
    if (data.STAGE_ID === 'WON') {
        // Запуск бизнес-процесса
        BX.ajax.runAction('mymodule:bp.start', {
            data: {
                entityTypeId: event.entityTypeId,
                entityId: event.entityId,
                templateId: 123 // ID шаблона БП
            }
        }).then(function(response) {
            BX.UI.Notification.Center.notify({
                content: 'Бизнес-процесс запущен',
                position: 'top-right'
            });
        });
    }
});
```

### Пример 5: Кастомный виджет в форме

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    // Добавление HTML виджета
    var container = document.querySelector('.crm-entity-card-widget-content');
    
    if (container) {
        var widget = BX.create('DIV', {
            props: { className: 'crm-custom-widget' },
            html: '<h3>Статистика</h3><div id="stats-chart"></div>'
        });
        
        container.appendChild(widget);
        
        // Загрузка данных для виджета
        loadStatsChart(editor.getEntityId());
    }
});
```

## Интеграция с бизнес-процессами

### Автозапуск БП

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onSave', function(event) {
    var data = event.editor.getDataForSave();
    
    // Условие для автозапуска
    if (event.isNew && data.UF_CRM_PRIORITY === 'CRITICAL') {
        
        BX.UI.Dialogs.MessageBox.confirm(
            'Запустить бизнес-процесс "Критичная сделка"?',
            function(result) {
                if (result) {
                    startBusinessProcess(event.entityId, 'CRITICAL_DEAL');
                }
            }
        );
    }
});

function startBusinessProcess(entityId, templateCode) {
    BX.ajax.runAction('bizproc.workflow.start', {
        data: {
            TEMPLATE_ID: getTemplateIdByCode(templateCode),
            DOCUMENT_ID: ['crm', 'CCrmDocumentDeal', entityId]
        }
    }).then(function(response) {
        if (response.data.workflowId) {
            BX.UI.Notification.Center.notify({
                content: 'Бизнес-процесс запущен',
                position: 'top-right'
            });
        }
    });
}
```

## Маппинг типов полей

| Тип в конфигурации | Тип CRM | Описание |
|--------------------|---------|----------|
| `text` | text | Текстовое поле |
| `textarea` | textarea | Многострочный текст |
| `number` | number | Числовое поле |
| `date` | date | Дата |
| `datetime` | datetime | Дата и время |
| `list` | list | Выпадающий список |
| `user` | client | Выбор пользователя |
| `user_multi` | client | Множественный выбор пользователей |
| `checkbox` | boolean | Чекбокс |

## Отладка

### Просмотр редактора

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    // Вывод в консоль для отладки
    console.log('Editor:', editor);
    console.log('Config:', editor.getConfig());
    console.log('Scheme:', editor.getScheme());
});
```

### Просмотр всех полей

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    var scheme = editor.getScheme();
    
    scheme.forEach(function(section) {
        console.log('Section:', section.name);
        
        section.elements.forEach(function(element) {
            console.log('  Field:', element.name, element.title);
        });
    });
});
```

## Безопасность

- Все данные валидируются на сервере
- CSRF защита встроена в CRM
- Проверка прав доступа к сущностям
- Логирование всех изменений

## Совместимость

- Bitrix24 (коробка и облако)
- CRM версии 22.0+
- Работает с: Лидами, Сделками, Контактами, Компаниями
- Поддержка динамических типов CRM

## Производительность

- Минимальное влияние на загрузку формы
- Ленивая загрузка кастомных виджетов
- Кеширование конфигурации полей
- Оптимизированные обработчики событий

## Часто задаваемые вопросы

**Q: Как добавить поле только для определенных пользователей?**

A: Используйте проверку прав в событии `onInit`:

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    var currentUserId = BX.message('USER_ID');
    var adminIds = [1, 5, 10]; // ID администраторов
    
    if (adminIds.indexOf(parseInt(currentUserId)) !== -1) {
        // Добавляем поле только для админов
        editor.addField({
            name: 'UF_ADMIN_NOTES',
            title: 'Заметки администратора',
            type: 'textarea'
        });
    }
});
```

**Q: Можно ли добавить поле в определенную секцию?**

A: Да, укажите ID секции:

```javascript
editor.addField({
    name: 'UF_MY_FIELD',
    title: 'Моё поле',
    type: 'text'
}, 'main'); // ID секции
```

**Q: Как обновить поле после AJAX запроса?**

A: Используйте метод `setValue()`:

```javascript
BX.ajax.runAction('mymodule:data.get').then(function(response) {
    var field = editor.getControlByIdRecursive('UF_MY_FIELD');
    if (field) {
        field.setValue(response.data.value);
    }
});
```

---

## Полезные ссылки

- [BX.Crm.EntityEditor документация](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/entity/index.php)
- [Пользовательские поля CRM](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=135&LESSON_ID=13706)
- [Бизнес-процессы CRM](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=135&LESSON_ID=13708)
- [События CRM](https://dev.1c-bitrix.ru/api_help/crm/events/index.php)
