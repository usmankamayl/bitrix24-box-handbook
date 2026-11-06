# Модуль 14.3: UI.Form и кастомизация интерфейса

## Теория

### 1. BX.UI.EntityEditor - Редактор сущностей

`BX.UI.EntityEditor` — это мощная система для создания форм редактирования сущностей в стиле CRM Bitrix24.

#### Основные компоненты:

```javascript
// Инициализация редактора
BX.UI.EntityEditor.create(
    "editor-id",  // ID редактора
    {
        entityTypeId: 1,           // Тип сущности
        entityId: 0,               // ID сущности (0 - создание)
        config: configData,        // Конфигурация полей
        initialData: formData,     // Начальные данные
        controllers: [             // Контроллеры обработки
            {
                name: "MY_CONTROLLER",
                action: "save"
            }
        ]
    }
);
```

#### Структура конфигурации полей:

```javascript
const config = [
    {
        name: "main",              // Секция
        title: "Основная информация",
        type: "section",
        elements: [
            {
                name: "NAME",      // Имя поля
                title: "Название",
                type: "text",      // Тип поля
                required: true
            },
            {
                name: "EMAIL",
                title: "Email",
                type: "email"
            }
        ]
    }
];
```

### 2. Типы полей в UI.EntityEditor

| Тип | Описание | Пример использования |
|-----|----------|---------------------|
| `text` | Текстовое поле | Имя, заголовок |
| `textarea` | Многострочный текст | Описание, комментарий |
| `number` | Числовое поле | Цена, количество |
| `email` | Email с валидацией | Email адрес |
| `phone` | Телефон с форматированием | Номер телефона |
| `date` | Дата с календарем | Дата рождения |
| `datetime` | Дата и время | Дата создания |
| `checkbox` | Чекбокс | Активность, согласие |
| `list` | Выпадающий список | Статус, тип |
| `user` | Выбор пользователя | Ответственный |
| `file` | Загрузка файла | Документ, изображение |
| `url` | URL с валидацией | Сайт, ссылка |

### 3. BX.Main.ui.selector - Селекторы

#### EntitySelector - выбор сущностей:

```javascript
BX.Main.ui.selector.open({
    context: 'MY_CONTEXT',
    multiple: true,              // Множественный выбор
    enableSearch: true,          // Поиск
    entities: [
        {
            id: 'user',          // Тип сущности
            options: {
                intranetUsersOnly: true
            }
        }
    ],
    callback: function(selected) {
        // Обработка выбранных элементов
        selected.forEach(item => {
            console.log(item.id, item.title);
        });
    }
});
```

### 4. BX.UI.FormManager - Управление формами

`BX.UI.FormManager` предоставляет удобный API для работы с формами:

```javascript
// Создание менеджера формы
const formManager = new BX.UI.FormManager({
    id: 'my-form',
    fields: {
        name: {
            required: true,
            validator: (value) => value.length >= 3
        },
        email: {
            required: true,
            validator: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
        }
    },
    onSubmit: function(data) {
        // Обработка отправки
        BX.ajax.runComponentAction('my:component', 'save', {
            mode: 'class',
            data: data
        });
    }
});
```

### 5. Кастомизация CRM форм

#### Добавление пользовательских полей в CRM:

```javascript
// Подписка на событие инициализации редактора
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    // Добавление кастомного поля
    editor.addField({
        name: 'UF_CUSTOM_FIELD',
        title: 'Моё поле',
        type: 'text',
        schemeElement: {
            name: 'UF_CUSTOM_FIELD',
            title: 'Моё поле',
            type: 'text'
        }
    });
});
```

#### Модификация существующих полей:

```javascript
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    // Получение поля
    const field = editor.getControlByIdRecursive('OPPORTUNITY');
    
    if (field) {
        // Изменение заголовка
        field.setTitle('Сумма сделки');
        
        // Скрытие поля
        field.setVisible(false);
        
        // Блокировка редактирования
        field.setReadOnly(true);
    }
});
```

### 6. UI.Dialogs - Кастомные диалоги

#### Создание модального окна с формой:

```javascript
const dialog = new BX.UI.Dialogs.MessageBox({
    title: 'Заполните форму',
    message: BX.create('DIV', {
        html: '<form id="custom-form">...</form>'
    }),
    buttons: [
        new BX.UI.Button({
            text: 'Сохранить',
            color: BX.UI.Button.Color.SUCCESS,
            onclick: function() {
                const formData = new FormData(
                    document.getElementById('custom-form')
                );
                
                BX.ajax.runComponentAction('my:component', 'save', {
                    mode: 'class',
                    data: Object.fromEntries(formData)
                }).then(function(response) {
                    dialog.close();
                    BX.UI.Notification.Center.notify({
                        content: 'Сохранено!',
                        type: 'success'
                    });
                });
                
                return false;
            }
        }),
        new BX.UI.Button({
            text: 'Отмена',
            color: BX.UI.Button.Color.LINK,
            onclick: function() {
                dialog.close();
                return false;
            }
        })
    ]
});

dialog.show();
```

### 7. Валидация форм

#### Клиентская валидация:

```javascript
class FormValidator {
    constructor(form) {
        this.form = form;
        this.errors = {};
    }
    
    validate() {
        this.errors = {};
        
        // Проверка обязательных полей
        this.form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                this.addError(field.name, 'Поле обязательно для заполнения');
            }
        });
        
        // Проверка email
        const emailField = this.form.querySelector('[type="email"]');
        if (emailField && emailField.value) {
            if (!this.isValidEmail(emailField.value)) {
                this.addError(emailField.name, 'Некорректный email');
            }
        }
        
        // Проверка телефона
        const phoneField = this.form.querySelector('[type="tel"]');
        if (phoneField && phoneField.value) {
            if (!this.isValidPhone(phoneField.value)) {
                this.addError(phoneField.name, 'Некорректный телефон');
            }
        }
        
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
        this.form.querySelectorAll('.error-message').forEach(el => el.remove());
        this.form.querySelectorAll('.error').forEach(el => {
            el.classList.remove('error');
        });
        
        // Показ новых ошибок
        Object.keys(this.errors).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.classList.add('error');
                
                const errorDiv = BX.create('DIV', {
                    props: { className: 'error-message' },
                    text: this.errors[fieldName].join(', ')
                });
                
                field.parentNode.appendChild(errorDiv);
            }
        });
    }
    
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    isValidPhone(phone) {
        return /^\+?[0-9]{10,15}$/.test(phone.replace(/[\s\-\(\)]/g, ''));
    }
}

// Использование
const validator = new FormValidator(document.getElementById('my-form'));
if (validator.validate()) {
    // Форма валидна
} else {
    validator.showErrors();
}
```

### 8. Табы и секции в формах

```javascript
// Создание табов
const tabManager = new BX.UI.TabManager({
    container: document.getElementById('tabs-container'),
    tabs: [
        {
            id: 'main',
            title: 'Основное',
            content: document.getElementById('tab-main')
        },
        {
            id: 'additional',
            title: 'Дополнительно',
            content: document.getElementById('tab-additional')
        },
        {
            id: 'files',
            title: 'Файлы',
            content: document.getElementById('tab-files')
        }
    ],
    activeTab: 'main'
});
```

### 9. Автосохранение форм

```javascript
class FormAutoSave {
    constructor(form, saveInterval = 30000) {
        this.form = form;
        this.saveInterval = saveInterval;
        this.timer = null;
        this.isDirty = false;
        
        this.init();
    }
    
    init() {
        // Отслеживание изменений
        this.form.addEventListener('input', () => {
            this.isDirty = true;
            this.resetTimer();
        });
        
        // Автосохранение при уходе со страницы
        window.addEventListener('beforeunload', () => {
            if (this.isDirty) {
                this.save();
            }
        });
        
        this.startTimer();
    }
    
    startTimer() {
        this.timer = setInterval(() => {
            if (this.isDirty) {
                this.save();
            }
        }, this.saveInterval);
    }
    
    resetTimer() {
        clearInterval(this.timer);
        this.startTimer();
    }
    
    save() {
        const formData = new FormData(this.form);
        
        BX.ajax.runComponentAction('my:component', 'autoSave', {
            mode: 'class',
            data: Object.fromEntries(formData)
        }).then(() => {
            this.isDirty = false;
            
            BX.UI.Notification.Center.notify({
                content: 'Черновик сохранён',
                autoHideDelay: 2000
            });
        });
    }
    
    destroy() {
        clearInterval(this.timer);
    }
}

// Использование
const autoSave = new FormAutoSave(
    document.getElementById('my-form'),
    30000 // 30 секунд
);
```

### 10. Кастомизация административного интерфейса

#### Добавление кнопок в тулбар:

```php
// В административной странице
$APPLICATION->SetTitle("Мой раздел");

// Добавление кнопки
$APPLICATION->AddHeadScript('/local/js/my-script.js');

$aContext = [
    [
        "TEXT" => "Добавить",
        "ICON" => "btn_new",
        "LINK" => "/local/admin/add.php",
        "TITLE" => "Добавить новый элемент"
    ],
    [
        "TEXT" => "Экспорт",
        "ICON" => "btn_export",
        "LINK" => "javascript:exportData()",
        "TITLE" => "Экспортировать в Excel"
    ]
];

$context = new CAdminContextMenu($aContext);
$context->Show();
```

---

## Вопросы для собеседования

### 1. Что такое BX.UI.EntityEditor и для чего он используется?

**Ответ:**
`BX.UI.EntityEditor` — это фреймворк для создания форм редактирования сущностей в стиле CRM Bitrix24. Используется для:

- Создания кастомных форм редактирования сущностей
- Интеграции с CRM формами
- Добавления пользовательских полей
- Управления секциями и вкладками
- Валидации и обработки данных

Основные возможности:
- Динамическое управление полями
- Drag & Drop секций и полей
- Автосохранение
- Интеграция с правами доступа
- Расширяемая система контроллеров

---

### 2. Какие типы полей поддерживает UI.EntityEditor?

**Ответ:**

**Базовые типы:**
- `text` — текстовое поле
- `textarea` — многострочный текст
- `number` — числовое поле
- `checkbox` — чекбокс

**Специализированные:**
- `email` — email с валидацией
- `phone` — телефон с форматированием
- `url` — URL с валидацией
- `date` — дата с календарем
- `datetime` — дата и время
- `money` — денежное поле

**Селекторы:**
- `list` — выпадающий список
- `user` — выбор пользователя
- `file` — загрузка файла
- `client` — выбор клиента CRM
- `product` — выбор товара

Каждое поле настраивается через параметры `required`, `placeholder`, `editable`.

---

### 3. Как добавить кастомное поле в CRM форму?

**Ответ:**

```javascript
// Подписка на событие инициализации редактора
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    // Добавление поля в конфигурацию
    editor.addField({
        name: 'UF_MY_FIELD',
        title: 'Моё кастомное поле',
        type: 'text',
        schemeElement: {
            name: 'UF_MY_FIELD',
            title: 'Моё кастомное поле',
            type: 'text',
            data: {
                fieldType: 'text'
            }
        }
    });
});

// Или модификация существующего
BX.addCustomEvent('BX.Crm.EntityEditor:onInit', function(editor) {
    const field = editor.getControlByIdRecursive('OPPORTUNITY');
    if (field) {
        field.setTitle('Бюджет проекта');
        field.setVisible(true);
    }
});
```

**Важно:** Поле должно быть создано как пользовательское поле в админке.

---

### 4. Как реализовать клиентскую валидацию формы?

**Ответ:**

```javascript
class FormValidator {
    constructor(form, rules) {
        this.form = form;
        this.rules = rules; // Правила валидации
        this.errors = {};
    }
    
    validate() {
        this.errors = {};
        
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            const rule = this.rules[fieldName];
            
            // Проверка обязательности
            if (rule.required && !field.value.trim()) {
                this.addError(fieldName, 'Поле обязательно');
            }
            
            // Минимальная длина
            if (rule.minLength && field.value.length < rule.minLength) {
                this.addError(fieldName, `Минимум ${rule.minLength} символов`);
            }
            
            // Кастомная валидация
            if (rule.validator && !rule.validator(field.value)) {
                this.addError(fieldName, rule.message || 'Некорректное значение');
            }
        });
        
        return Object.keys(this.errors).length === 0;
    }
    
    addError(field, message) {
        if (!this.errors[field]) this.errors[field] = [];
        this.errors[field].push(message);
    }
    
    showErrors() {
        // Показ ошибок пользователю
        Object.keys(this.errors).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            field.classList.add('error');
            // Показ сообщения об ошибке
        });
    }
}

// Использование
const validator = new FormValidator(form, {
    name: { required: true, minLength: 3 },
    email: {
        required: true,
        validator: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
        message: 'Некорректный email'
    }
});

if (!validator.validate()) {
    validator.showErrors();
    return false;
}
```

---

### 5. Как создать модальное окно с формой?

**Ответ:**

```javascript
// Создание контента формы
const formHTML = `
    <form id="modal-form" class="ui-form">
        <div class="ui-form-row">
            <label>Название</label>
            <input type="text" name="name" required>
        </div>
        <div class="ui-form-row">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
    </form>
`;

// Создание диалога
const dialog = new BX.UI.Dialogs.MessageBox({
    title: 'Новый контакт',
    message: BX.create('DIV', { html: formHTML }),
    modal: true,
    minWidth: 500,
    buttons: [
        new BX.UI.Button({
            text: 'Сохранить',
            color: BX.UI.Button.Color.SUCCESS,
            onclick: function() {
                const form = document.getElementById('modal-form');
                const formData = new FormData(form);
                
                // Валидация
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                // Отправка
                BX.ajax.runComponentAction('my:component', 'save', {
                    mode: 'class',
                    data: Object.fromEntries(formData)
                }).then(function(response) {
                    BX.UI.Notification.Center.notify({
                        content: 'Контакт создан!',
                        type: 'success'
                    });
                    dialog.close();
                });
                
                return false;
            }
        }),
        new BX.UI.Button({
            text: 'Отмена',
            color: BX.UI.Button.Color.LINK,
            onclick: function() {
                dialog.close();
                return false;
            }
        })
    ]
});

dialog.show();
```

---

### 6. Как реализовать автосохранение формы?

**Ответ:**

```javascript
class FormAutoSave {
    constructor(formId, options = {}) {
        this.form = document.getElementById(formId);
        this.interval = options.interval || 30000; // 30 сек
        this.storageKey = options.storageKey || `autosave_${formId}`;
        this.isDirty = false;
        this.timer = null;
        
        this.init();
    }
    
    init() {
        // Восстановление из localStorage
        this.restore();
        
        // Отслеживание изменений
        this.form.addEventListener('input', () => {
            this.isDirty = true;
        });
        
        // Автосохранение по таймеру
        this.timer = setInterval(() => {
            if (this.isDirty) {
                this.save();
            }
        }, this.interval);
        
        // Сохранение перед уходом
        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty) {
                this.save();
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
    
    save() {
        const formData = new FormData(this.form);
        const data = Object.fromEntries(formData);
        
        // Сохранение в localStorage
        localStorage.setItem(this.storageKey, JSON.stringify({
            data: data,
            timestamp: Date.now()
        }));
        
        // Опционально: сохранение на сервер
        BX.ajax.runComponentAction('my:component', 'autoSave', {
            mode: 'class',
            data: data
        });
        
        this.isDirty = false;
        
        BX.UI.Notification.Center.notify({
            content: 'Черновик сохранён',
            autoHideDelay: 2000
        });
    }
    
    restore() {
        const saved = localStorage.getItem(this.storageKey);
        if (saved) {
            const { data, timestamp } = JSON.parse(saved);
            
            // Проверка давности (24 часа)
            if (Date.now() - timestamp < 86400000) {
                Object.keys(data).forEach(key => {
                    const field = this.form.querySelector(`[name="${key}"]`);
                    if (field) field.value = data[key];
                });
                
                BX.UI.Notification.Center.notify({
                    content: 'Восстановлен черновик',
                    autoHideDelay: 3000
                });
            }
        }
    }
    
    clear() {
        localStorage.removeItem(this.storageKey);
        clearInterval(this.timer);
    }
}

// Использование
const autoSave = new FormAutoSave('my-form', {
    interval: 30000,
    storageKey: 'my_form_draft'
});
```

---

### 7. Как работать с BX.Main.ui.selector для выбора пользователей?

**Ответ:**

```javascript
// Открытие селектора пользователей
BX.Main.ui.selector.open({
    context: 'MY_APP_USERS',
    multiple: true,              // Множественный выбор
    enableSearch: true,          // Поиск
    clearUnavailable: true,      // Очистка недоступных
    
    entities: [
        {
            id: 'user',          // Тип - пользователи
            options: {
                intranetUsersOnly: true,  // Только сотрудники
                emailUsers: false,         // Без email-пользователей
                inviteEmployeeLink: false  // Без ссылки "Пригласить"
            }
        },
        {
            id: 'department',    // Можно добавить отделы
            options: {
                selectMode: 'usersAndDepartments'
            }
        }
    ],
    
    // Обработка выбора
    callback: function(selected) {
        const userIds = [];
        
        selected.forEach(item => {
            if (item.entityId === 'user') {
                userIds.push(item.id);
                console.log(`Выбран: ${item.title.text}`);
            }
        });
        
        // Сохранение выбранных
        document.getElementById('selected-users').value = userIds.join(',');
        
        BX.UI.Notification.Center.notify({
            content: `Выбрано пользователей: ${userIds.length}`
        });
    }
});

// Программное добавление пользователей в селектор
const dialog = BX.Main.selectorManagerV2.getById('MY_APP_USERS');
if (dialog) {
    dialog.addTag({
        id: 'U1',
        entityId: 'user',
        title: 'Администратор'
    });
}
```

---

### 8. Как создать табы в форме?

**Ответ:**

```javascript
// HTML структура
const tabsHTML = `
    <div class="ui-tabs">
        <div class="ui-tabs-nav">
            <button class="ui-tab-button active" data-tab="main">
                Основное
            </button>
            <button class="ui-tab-button" data-tab="additional">
                Дополнительно
            </button>
            <button class="ui-tab-button" data-tab="files">
                Файлы
            </button>
        </div>
        <div class="ui-tabs-content">
            <div class="ui-tab-pane active" id="tab-main">
                <!-- Контент вкладки "Основное" -->
            </div>
            <div class="ui-tab-pane" id="tab-additional">
                <!-- Контент вкладки "Дополнительно" -->
            </div>
            <div class="ui-tab-pane" id="tab-files">
                <!-- Контент вкладки "Файлы" -->
            </div>
        </div>
    </div>
`;

// JavaScript для переключения
class TabManager {
    constructor(container) {
        this.container = container;
        this.init();
    }
    
    init() {
        const buttons = this.container.querySelectorAll('.ui-tab-button');
        
        buttons.forEach(button => {
            button.addEventListener('click', (e) => {
                const tabId = e.target.dataset.tab;
                this.switchTab(tabId);
            });
        });
    }
    
    switchTab(tabId) {
        // Деактивация всех табов
        this.container.querySelectorAll('.ui-tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        this.container.querySelectorAll('.ui-tab-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        
        // Активация выбранного
        this.container.querySelector(`[data-tab="${tabId}"]`)
            .classList.add('active');
        this.container.querySelector(`#tab-${tabId}`)
            .classList.add('active');
        
        // Событие переключения
        BX.onCustomEvent('TabManager:onSwitch', [tabId]);
    }
}

// Использование
const tabManager = new TabManager(document.getElementById('tabs-container'));

// Подписка на переключение табов
BX.addCustomEvent('TabManager:onSwitch', function(tabId) {
    console.log('Переключено на таб:', tabId);
    
    // Можно подгружать контент при переключении
    if (tabId === 'files') {
        loadFiles();
    }
});
```

---

### 9. Как кастомизировать административный список (CAdminList)?

**Ответ:**

```php
<?php
// В административной странице
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

$sTableID = "tbl_my_entities";
$oSort = new CAdminSorting($sTableID, "ID", "DESC");
$lAdmin = new CAdminList($sTableID, $oSort);

// Настройка колонок
$lAdmin->AddHeaders([
    ["id" => "ID", "content" => "ID", "sort" => "ID", "default" => true],
    ["id" => "NAME", "content" => "Название", "sort" => "NAME", "default" => true],
    ["id" => "ACTIVE", "content" => "Активность", "sort" => "ACTIVE", "default" => true],
    ["id" => "DATE_CREATE", "content" => "Дата создания", "sort" => "DATE_CREATE", "default" => true],
]);

// Групповые действия
$lAdmin->AddGroupActionTable([
    "delete" => "Удалить",
    "activate" => "Активировать",
    "deactivate" => "Деактивировать",
]);

// Обработка групповых действий
if ($arID = $lAdmin->GroupAction()) {
    foreach ($arID as $ID) {
        $ID = intval($ID);
        
        switch ($_REQUEST['action']) {
            case 'delete':
                // Удаление
                MyEntityTable::delete($ID);
                break;
            case 'activate':
                MyEntityTable::update($ID, ['ACTIVE' => 'Y']);
                break;
            case 'deactivate':
                MyEntityTable::update($ID, ['ACTIVE' => 'N']);
                break;
        }
    }
}

// Получение данных
$rsData = MyEntityTable::getList([
    'select' => ['ID', 'NAME', 'ACTIVE', 'DATE_CREATE'],
    'order' => [$by => $order]
]);

// Заполнение списка
while ($arItem = $rsData->Fetch()) {
    $row =& $lAdmin->AddRow($arItem["ID"], $arItem);
    
    // Добавление действий для строки
    $row->AddActions([
        [
            "ICON" => "edit",
            "TEXT" => "Редактировать",
            "ACTION" => $lAdmin->ActionRedirect("edit.php?ID=".$arItem["ID"])
        ],
        [
            "ICON" => "delete",
            "TEXT" => "Удалить",
            "ACTION" => "if(confirm('Удалить?')) ".
                $lAdmin->ActionDoGroup($arItem["ID"], "delete")
        ]
    ]);
}

// Вывод списка
$lAdmin->CheckListMode();
$lAdmin->DisplayList();
?>
```

**JavaScript кастомизация:**
```javascript
// Добавление кнопок в тулбар
BX.ready(function() {
    const toolbar = document.querySelector('.adm-toolbar-panel');
    if (toolbar) {
        const exportBtn = BX.create('A', {
            props: { className: 'adm-btn', href: '#' },
            text: 'Экспорт в Excel',
            events: {
                click: function(e) {
                    e.preventDefault();
                    exportToExcel();
                }
            }
        });
        
        toolbar.appendChild(exportBtn);
    }
});
```

---

### 10. Как реализовать drag-and-drop сортировку в форме?

**Ответ:**

```javascript
class DragDropSorter {
    constructor(container) {
        this.container = container;
        this.draggingElement = null;
        this.init();
    }
    
    init() {
        const items = this.container.querySelectorAll('.sortable-item');
        
        items.forEach(item => {
            item.draggable = true;
            
            item.addEventListener('dragstart', (e) => {
                this.draggingElement = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            
            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                this.draggingElement = null;
            });
            
            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                const afterElement = this.getDragAfterElement(e.clientY);
                if (afterElement == null) {
                    this.container.appendChild(this.draggingElement);
                } else {
                    this.container.insertBefore(this.draggingElement, afterElement);
                }
            });
        });
    }
    
    getDragAfterElement(y) {
        const draggableElements = [
            ...this.container.querySelectorAll('.sortable-item:not(.dragging)')
        ];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
    
    getOrder() {
        const items = this.container.querySelectorAll('.sortable-item');
        return Array.from(items).map(item => item.dataset.id);
    }
    
    saveOrder() {
        const order = this.getOrder();
        
        BX.ajax.runComponentAction('my:component', 'saveOrder', {
            mode: 'class',
            data: { order: order }
        }).then(() => {
            BX.UI.Notification.Center.notify({
                content: 'Порядок сохранён',
                type: 'success'
            });
        });
    }
}

// Использование
const sorter = new DragDropSorter(document.getElementById('sortable-list'));

// Сохранение порядка
document.getElementById('save-order-btn').addEventListener('click', () => {
    sorter.saveOrder();
});
```

**HTML структура:**
```html
<div id="sortable-list" class="sortable-container">
    <div class="sortable-item" data-id="1">
        <span class="drag-handle">⋮⋮</span>
        <span class="item-content">Элемент 1</span>
    </div>
    <div class="sortable-item" data-id="2">
        <span class="drag-handle">⋮⋮</span>
        <span class="item-content">Элемент 2</span>
    </div>
</div>
```

**CSS:**
```css
.sortable-item {
    padding: 12px;
    margin: 8px 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: move;
    transition: all 0.2s;
}

.sortable-item.dragging {
    opacity: 0.5;
    transform: scale(1.05);
}

.drag-handle {
    color: #999;
    margin-right: 8px;
    cursor: grab;
}
```

---

## Практические задачи

Решения находятся в папке `examples/module-14-3/`:

1. **Задача 1: Кастомная форма редактирования сущности**
   - Создание формы в стиле CRM с различными типами полей
   - Валидация на клиенте и сервере
   - Автосохранение черновиков
   - Интеграция с файлами и пользователями

2. **Задача 2: CRM форма с кастомными полями**
   - Добавление пользовательских полей в форму сделки
   - Модификация существующих полей
   - Кастомная логика сохранения
   - Интеграция с бизнес-процессами

3. **Задача 3: Админ-интерфейс с табами**
   - Многовкладочная форма настроек
   - Drag-and-drop сортировка элементов
   - Массовые операции
   - Экспорт/импорт данных

---

## Полезные ссылки

- [UI.EntityEditor документация](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=13705)
- [BX.Main.ui.selector](https://dev.1c-bitrix.ru/api_d7/bitrix/main/ui/selector/index.php)
- [Кастомизация CRM](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=135)
- [Административный интерфейс](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2791)

---

**Следующий модуль:** 14.4 CRM Фабрики и динамические типы (SPA)
