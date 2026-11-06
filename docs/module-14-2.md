# Модуль 14.2: BX.JS - клиентский JavaScript

## Содержание
1. [Введение в BX.JS](#введение-в-bxjs)
2. [BX.ajax - AJAX запросы](#bxajax---ajax-запросы)
3. [BX.message - работа с языковыми сообщениями](#bxmessage---работа-с-языковыми-сообщениями)
4. [BX.UI - интерфейсные компоненты](#bxui---интерфейсные-компоненты)
5. [BX.Event - работа с событиями](#bxevent---работа-с-событиями)
6. [BX.ready - инициализация](#bxready---инициализация)
7. [Вопросы для собеседования](#вопросы-для-собеседования)

---

## Введение в BX.JS

**BX.JS** - это клиентская JavaScript библиотека Bitrix Framework, предоставляющая единый API для работы с DOM, AJAX, событиями и UI компонентами.

### Основные возможности

```javascript
// Глобальный объект BX доступен на всех страницах Bitrix
BX.ready(function() {
    // Код выполнится после загрузки DOM
    console.log('BX готов к работе');
});

// Работа с элементами DOM (аналог jQuery)
var element = BX('my-element-id'); // getElementById
var elements = BX.findChildren(parent, {class: 'my-class'}, true); // querySelectorAll

// AJAX запросы
BX.ajax.runComponentAction('my:component', 'getData', {
    mode: 'class',
    data: { id: 123 }
}).then(function(response) {
    console.log(response.data);
});

// UI компоненты
BX.UI.Notification.Center.notify({
    content: 'Операция выполнена успешно',
    autoHideDelay: 3000
});
```

### Подключение BX.JS

```php
<?php
// В PHP файле (компонент, шаблон)
use Bitrix\Main\UI\Extension;

// Подключаем core библиотеку (обычно уже подключена)
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/main/core/core.js');

// Современный способ через Extension API
Extension::load('ui.notification');
Extension::load('ui.dialogs.messagebox');
Extension::load('ui.buttons');
?>
```

---

## BX.ajax - AJAX запросы

### Основные методы

#### 1. BX.ajax.runComponentAction() - вызов методов компонента

```javascript
// Вызов метода класса компонента
BX.ajax.runComponentAction(
    'my:component.name',    // Название компонента
    'methodName',           // Название метода в классе
    {
        mode: 'class',      // 'class' или 'ajax' (component.php)
        data: {             // Данные для метода
            id: 123,
            action: 'update'
        },
        signedParameters: 'signedString' // Подписанные параметры компонента
    }
).then(
    function(response) {
        // Успешный ответ
        console.log(response.data);
        console.log(response.status); // 'success'
    },
    function(response) {
        // Ошибка
        console.error(response.errors);
    }
);
```

**Важно:** На стороне PHP метод должен быть публичным и возвращать данные через `return`:

```php
<?php
namespace Vendor\Component;

class ComponentClass extends \CBitrixComponent
{
    public function getDataAction($id, $action)
    {
        // Проверка прав
        if (!$this->checkAccess()) {
            return ['error' => 'Access denied'];
        }
        
        // Бизнес-логика
        $result = $this->getData($id, $action);
        
        // Возвращаем данные (автоматически преобразуются в JSON)
        return [
            'items' => $result,
            'count' => count($result)
        ];
    }
}
```

#### 2. BX.ajax() - классический AJAX

```javascript
BX.ajax({
    url: '/local/ajax/handler.php',
    method: 'POST',
    dataType: 'json',
    data: {
        action: 'getData',
        id: 123,
        sessid: BX.bitrix_sessid() // CSRF защита
    },
    onsuccess: function(data) {
        console.log('Успех:', data);
    },
    onfailure: function() {
        console.error('Ошибка запроса');
    }
});
```

#### 3. BX.ajax.post() - POST запрос

```javascript
BX.ajax.post(
    '/local/ajax/handler.php',
    {
        action: 'save',
        data: JSON.stringify(formData),
        sessid: BX.bitrix_sessid()
    },
    function(response) {
        var data = JSON.parse(response);
        if (data.success) {
            BX.UI.Notification.Center.notify({
                content: 'Данные сохранены'
            });
        }
    }
);
```

#### 4. BX.ajax.submitAjax() - отправка формы через AJAX

```javascript
// HTML форма
<form id="my-form" action="/local/ajax/form-handler.php" method="post">
    <input type="text" name="name" value="">
    <input type="email" name="email" value="">
    <button type="submit">Отправить</button>
</form>

// JavaScript
BX.ready(function() {
    var form = BX('my-form');
    
    BX.bind(form, 'submit', function(e) {
        e.preventDefault();
        BX.ajax.submitAjax(form, {
            onsuccess: function(response) {
                var data = JSON.parse(response);
                BX.UI.Notification.Center.notify({
                    content: data.message
                });
            }
        });
    });
});
```

### CSRF защита

```javascript
// Получение токена сессии для защиты от CSRF
var sessid = BX.bitrix_sessid();

// Автоматическая проверка на сервере
if (!check_bitrix_sessid()) {
    die('CSRF error');
}
```

---

## BX.message - работа с языковыми сообщениями

### Передача сообщений из PHP в JavaScript

```php
<?php
// В PHP файле (component.php, template.php)
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Передаем сообщения в JavaScript
?>
<script>
BX.message({
    COMPONENT_TITLE: '<?= CUtil::JSEscape(Loc::getMessage('COMPONENT_TITLE')) ?>',
    ERROR_REQUIRED: '<?= CUtil::JSEscape(Loc::getMessage('ERROR_REQUIRED')) ?>',
    SUCCESS_SAVED: '<?= CUtil::JSEscape(Loc::getMessage('SUCCESS_SAVED')) ?>'
});
</script>
```

### Использование в JavaScript

```javascript
// Получение сообщения
var title = BX.message('COMPONENT_TITLE');
var error = BX.message('ERROR_REQUIRED');

// С подстановкой значений
BX.message({
    USER_GREETING: 'Привет, #NAME#!'
});

var greeting = BX.message('USER_GREETING').replace('#NAME#', userName);
```

### Массовая передача сообщений

```php
<?php
$messages = [
    'BTN_SAVE' => Loc::getMessage('BTN_SAVE'),
    'BTN_CANCEL' => Loc::getMessage('BTN_CANCEL'),
    'CONFIRM_DELETE' => Loc::getMessage('CONFIRM_DELETE'),
    'ERROR_NETWORK' => Loc::getMessage('ERROR_NETWORK'),
];
?>
<script>
BX.message(<?= \Bitrix\Main\Web\Json::encode($messages) ?>);
</script>
```

---

## BX.UI - интерфейсные компоненты

### 1. BX.UI.Notification - уведомления

```javascript
// Простое уведомление
BX.UI.Notification.Center.notify({
    content: 'Операция выполнена успешно',
    autoHideDelay: 3000 // скрыть через 3 секунды
});

// С кастомными настройками
BX.UI.Notification.Center.notify({
    content: 'Это важное уведомление',
    position: 'top-right', // top-right, top-left, bottom-right, bottom-left
    autoHideDelay: 5000,
    closeButton: true,
    category: 'custom-category' // для группировки
});
```

### 2. BX.UI.Dialogs.MessageBox - диалоговые окна

```javascript
// Подключаем расширение
BX.Runtime.loadExtension('ui.dialogs.messagebox').then(function() {
    
    // Информационное окно
    BX.UI.Dialogs.MessageBox.alert('Данные успешно сохранены');
    
    // Подтверждение действия
    BX.UI.Dialogs.MessageBox.confirm(
        'Вы уверены, что хотите удалить этот элемент?',
        function(messageBox) {
            // Пользователь нажал "Да"
            deleteItem();
            messageBox.close();
        },
        'Удалить'
    );
    
    // Окно с кастомными кнопками
    BX.UI.Dialogs.MessageBox.show({
        title: 'Выберите действие',
        message: 'Что сделать с этим элементом?',
        buttons: [
            new BX.UI.Button({
                text: 'Редактировать',
                onclick: function() {
                    editItem();
                    this.getContext().close();
                }
            }),
            new BX.UI.CancelButton({
                text: 'Отмена',
                onclick: function() {
                    this.getContext().close();
                }
            })
        ]
    });
});
```

### 3. BX.UI.EntitySelector - выбор сущностей

```javascript
// Подключаем расширение
BX.Runtime.loadExtension('ui.entity-selector').then(function() {
    
    // Диалог выбора пользователей
    var dialog = new BX.UI.EntitySelector.Dialog({
        targetNode: document.getElementById('user-selector'),
        context: 'my-component',
        entities: [
            {
                id: 'user',
                options: {
                    inviteEmployeeLink: false
                }
            }
        ],
        multiple: true, // множественный выбор
        dropdownMode: false,
        showAvatars: true,
        enableSearch: true,
        events: {
            'Item:onSelect': function(event) {
                var item = event.getData().item;
                console.log('Выбран:', item.id, item.title);
            },
            'Item:onDeselect': function(event) {
                var item = event.getData().item;
                console.log('Удален:', item.id);
            }
        }
    });
    
    dialog.show();
});
```

### 4. BX.UI.Loader - индикатор загрузки

```javascript
// Создаем лоадер для контейнера
var loader = new BX.Loader({
    target: document.getElementById('content-container')
});

// Показываем
loader.show();

// Выполняем AJAX запрос
BX.ajax.runComponentAction('my:component', 'getData', {
    mode: 'class'
}).then(function(response) {
    // Скрываем лоадер
    loader.hide();
    
    // Обрабатываем данные
    renderData(response.data);
});
```

---

## BX.Event - работа с событиями

### Привязка обработчиков событий

```javascript
// BX.bind - привязать событие к элементу
var button = BX('my-button');
BX.bind(button, 'click', function(e) {
    e.preventDefault();
    console.log('Кнопка нажата');
});

// BX.unbind - отвязать событие
BX.unbind(button, 'click', handlerFunction);

// BX.bindDelegate - делегирование событий (для динамических элементов)
BX.bindDelegate(
    document.body,              // Родительский элемент
    'click',                    // Событие
    {className: 'delete-btn'},  // Селектор целевого элемента
    function(e) {
        var target = e.target;
        var itemId = target.getAttribute('data-id');
        deleteItem(itemId);
    }
);
```

### Кастомные события

```javascript
// Создание и отправка кастомного события
BX.onCustomEvent('MyComponent:onDataLoaded', [data, timestamp]);

// Подписка на кастомное событие
BX.addCustomEvent('MyComponent:onDataLoaded', function(data, timestamp) {
    console.log('Данные загружены:', data);
    updateUI(data);
});

// Глобальные события окна
BX.addCustomEvent(window, 'MyApp:userLoggedIn', function(userId) {
    console.log('Пользователь вошел:', userId);
});

// Отписка от события
BX.removeCustomEvent('MyComponent:onDataLoaded', handlerFunction);
```

---

## BX.ready - инициализация

### Выполнение кода после загрузки DOM

```javascript
// Аналог $(document).ready() в jQuery
BX.ready(function() {
    // DOM полностью загружен
    var button = BX('submit-button');
    BX.bind(button, 'click', handleClick);
    
    initializeComponent();
});

// Можно вызывать несколько раз
BX.ready(function() {
    console.log('Первый обработчик');
});

BX.ready(function() {
    console.log('Второй обработчик');
});
```

### Ожидание загрузки расширений

```javascript
// Загрузка одного расширения
BX.Runtime.loadExtension('ui.notification').then(function() {
    BX.UI.Notification.Center.notify({
        content: 'Расширение загружено'
    });
});

// Загрузка нескольких расширений
BX.Runtime.loadExtension(['ui.notification', 'ui.buttons', 'ui.dialogs']).then(function() {
    // Все расширения загружены
    initializeApp();
});
```

---

## Вопросы для собеседования

### 1. Что такое BX.JS и зачем он нужен?

**Ответ:**

BX.JS - клиентская JavaScript библиотека Bitrix Framework, предоставляющая:

- **Унифицированный API** для работы с DOM, AJAX, событиями
- **Кроссбраузерную совместимость** (работает в старых браузерах)
- **Интеграцию с серверной частью** Bitrix (вызов методов компонентов, CSRF защита)
- **Готовые UI компоненты** (уведомления, диалоги, селекторы)
- **Систему расширений** (модульная загрузка функционала)

**Преимущества:**
```javascript
// Вместо нативного fetch или XMLHttpRequest
BX.ajax.runComponentAction('my:component', 'getData', {
    mode: 'class',
    data: { id: 123 }
}).then(function(response) {
    // Автоматическая обработка ошибок, CSRF, сессий
    console.log(response.data);
});

// Вместо addEventListener
BX.bind(element, 'click', handler);

// Кроссбраузерная работа с DOM
var el = BX('my-id'); // getElementById с проверками
```

---

### 2. В чем разница между BX.ajax() и BX.ajax.runComponentAction()?

**Ответ:**

**BX.ajax()** - классический AJAX запрос к произвольному URL:

```javascript
BX.ajax({
    url: '/local/ajax/handler.php',
    method: 'POST',
    data: { action: 'getData', sessid: BX.bitrix_sessid() },
    onsuccess: function(response) {
        // Обработка ответа (обычно строка, нужен JSON.parse)
        var data = JSON.parse(response);
    }
});
```

**BX.ajax.runComponentAction()** - вызов метода класса компонента:

```javascript
BX.ajax.runComponentAction('my:component', 'getData', {
    mode: 'class', // вызов метода класса ComponentClass::getDataAction()
    data: { id: 123 },
    signedParameters: '...' // подписанные параметры компонента
}).then(function(response) {
    // response.data - уже распарсенный JSON
    // response.status - 'success' или 'error'
    console.log(response.data);
}, function(response) {
    // Обработка ошибок
    console.error(response.errors);
});
```

**Когда что использовать:**

| Ситуация | Метод |
|----------|-------|
| Вызов метода компонента | `runComponentAction()` |
| Отправка на произвольный PHP скрипт | `ajax()` или `ajax.post()` |
| Отправка формы | `ajax.submitAjax()` |
| Нужен Promise API | `runComponentAction()` |
| Работа с legacy кодом | `ajax()` |

---

### 3. Как правильно передавать языковые сообщения из PHP в JavaScript?

**Ответ:**

**Правильный способ:**

```php
<?php
// template.php или component.php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;

Loc::loadMessages(__FILE__);

$messages = [
    'ERROR_REQUIRED' => Loc::getMessage('ERROR_REQUIRED'),
    'SUCCESS_SAVED' => Loc::getMessage('SUCCESS_SAVED'),
    'CONFIRM_DELETE' => Loc::getMessage('CONFIRM_DELETE'),
];
?>
<script>
BX.message(<?= Json::encode($messages) ?>);
</script>
```

**В JavaScript:**

```javascript
BX.ready(function() {
    var errorText = BX.message('ERROR_REQUIRED');
    
    BX.UI.Notification.Center.notify({
        content: BX.message('SUCCESS_SAVED')
    });
});
```

**Почему так:**
- `Json::encode()` правильно экранирует спецсимволы
- `CUtil::JSEscape()` используется для одиночных строк
- Нельзя просто писать `getMessage()` в JS - нет экранирования
- Все сообщения централизованы в языковых файлах

**Неправильно:**
```php
<script>
var error = '<?= Loc::getMessage('ERROR') ?>'; // нет экранирования!
</script>
```

---

### 4. Как показать уведомление пользователю в Bitrix?

**Ответ:**

**Современный способ (Bitrix 20+):**

```javascript
// Простое уведомление
BX.UI.Notification.Center.notify({
    content: 'Данные сохранены',
    autoHideDelay: 3000
});

// С позиционированием и категорией
BX.UI.Notification.Center.notify({
    content: '<b>Ошибка!</b> Не все поля заполнены',
    position: 'top-right',
    autoHideDelay: 5000,
    closeButton: true,
    category: 'form-errors'
});
```

**Legacy способ (старые версии):**

```javascript
// Bitrix Framework до 20 версии
BX.UI.Notification.Center.notify({
    content: 'Сообщение'
});

// Еще более старый способ
if (window.BXCL && window.BXCL.notify) {
    window.BXCL.notify('Сообщение', 3000);
}
```

**Подключение расширения:**

```php
<?php
use Bitrix\Main\UI\Extension;

// В template.php или component.php
Extension::load('ui.notification');
?>
```

**Типы уведомлений:**
- Информационные (по умолчанию)
- Успешные операции
- Ошибки
- Предупреждения

---

### 5. Как вызвать метод класса компонента через AJAX?

**Ответ:**

**Шаг 1: Создаем метод в классе компонента**

```php
<?php
// /local/components/myvendor/my.component/class.php
namespace Myvendor\Components;

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class MyComponent extends \CBitrixComponent implements Controllerable
{
    /**
     * Настройка фильтров для AJAX действий
     */
    public function configureActions()
    {
        return [
            'getData' => [
                'prefilters' => [
                    new ActionFilter\Authentication(), // требуется авторизация
                    new ActionFilter\Csrf(),          // CSRF защита
                ],
            ],
        ];
    }
    
    /**
     * AJAX метод (суффикс Action обязателен!)
     */
    public function getDataAction($id, $filter = [])
    {
        // Проверка прав
        if (!$this->checkPermissions()) {
            return ['error' => 'Access denied'];
        }
        
        // Бизнес-логика
        $items = $this->loadData($id, $filter);
        
        // Возвращаем массив (автоматически преобразуется в JSON)
        return [
            'items' => $items,
            'count' => count($items),
            'timestamp' => time(),
        ];
    }
}
```

**Шаг 2: Вызываем из JavaScript**

```javascript
BX.ready(function() {
    var button = BX('load-data-btn');
    
    BX.bind(button, 'click', function() {
        // Показываем лоадер
        var loader = new BX.Loader({
            target: BX('content-container')
        });
        loader.show();
        
        // Вызываем метод getDataAction
        BX.ajax.runComponentAction(
            'myvendor:my.component',  // Название компонента
            'getData',                 // Название метода (без Action)
            {
                mode: 'class',         // Вызов метода класса
                data: {                // Параметры метода
                    id: 123,
                    filter: {
                        active: 'Y',
                        date_from: '2024-01-01'
                    }
                },
                signedParameters: BX.message('signedParameters') // из arResult
            }
        ).then(
            function(response) {
                // Успех
                loader.hide();
                console.log('Получено элементов:', response.data.count);
                renderData(response.data.items);
            },
            function(response) {
                // Ошибка
                loader.hide();
                console.error('Ошибки:', response.errors);
                BX.UI.Notification.Center.notify({
                    content: response.errors[0].message
                });
            }
        );
    });
});
```

**Шаг 3: Передаем signedParameters в шаблон**

```php
<?php
// template.php
?>
<div id="content-container"></div>
<button id="load-data-btn">Загрузить данные</button>

<script>
BX.message({
    signedParameters: '<?= \CUtil::JSEscape($this->getComponent()->getSignedParameters()) ?>'
});
</script>
```

**Важные моменты:**
- Метод должен заканчиваться на `Action`
- Класс должен реализовывать `Controllerable`
- Используем `configureActions()` для фильтров
- `signedParameters` защищают от подделки параметров компонента

---

### 6. Как работать с событиями в BX.JS?

**Ответ:**

**1. Стандартные DOM события:**

```javascript
// BX.bind - привязка обработчика
var button = BX('my-button');
BX.bind(button, 'click', function(e) {
    e.preventDefault();
    console.log('Клик по кнопке');
});

// BX.unbind - отвязка обработчика
function clickHandler(e) {
    console.log('Обработчик');
}
BX.bind(button, 'click', clickHandler);
BX.unbind(button, 'click', clickHandler);

// Делегирование для динамических элементов
BX.bindDelegate(
    document.body,              // Родитель
    'click',                    // Событие
    {className: 'delete-btn'},  // Селектор цели
    function(e) {
        var itemId = e.target.getAttribute('data-id');
        deleteItem(itemId);
    }
);
```

**2. Кастомные события BX:**

```javascript
// Создание и отправка события
BX.onCustomEvent('MyApp:dataChanged', [newData, timestamp]);

// Подписка на событие
BX.addCustomEvent('MyApp:dataChanged', function(data, time) {
    console.log('Данные изменились:', data);
    updateUI(data);
});

// Событие на конкретном объекте
var component = new MyComponent();
BX.onCustomEvent(component, 'onDataLoaded', [data]);

// Подписка на событие объекта
BX.addCustomEvent(component, 'onDataLoaded', function(data) {
    console.log('Компонент загрузил данные');
});

// Отписка от события
BX.removeCustomEvent('MyApp:dataChanged', handlerFunction);
```

**3. Типовые паттерны:**

```javascript
// Паттерн: Компонент с событиями
var MyComponent = function(container) {
    this.container = container;
    this.data = null;
    
    this.init();
};

MyComponent.prototype = {
    init: function() {
        BX.bind(this.container, 'click', this.handleClick.bind(this));
    },
    
    loadData: function() {
        var self = this;
        
        BX.ajax.runComponentAction('my:component', 'getData', {
            mode: 'class'
        }).then(function(response) {
            self.data = response.data;
            
            // Генерируем событие после загрузки
            BX.onCustomEvent(self, 'onDataLoaded', [self.data]);
        });
    },
    
    handleClick: function(e) {
        BX.onCustomEvent(this, 'onClick', [e]);
    }
};

// Использование
var component = new MyComponent(BX('container'));

BX.addCustomEvent(component, 'onDataLoaded', function(data) {
    console.log('Данные загружены:', data);
});

BX.addCustomEvent(component, 'onClick', function(e) {
    console.log('Клик по компоненту');
});
```

---

### 7. Как правильно инициализировать JavaScript код после загрузки DOM?

**Ответ:**

**Способ 1: BX.ready() - основной способ**

```javascript
BX.ready(function() {
    // DOM загружен, можно работать с элементами
    var button = BX('my-button');
    BX.bind(button, 'click', handleClick);
    
    initializeApp();
});
```

**Способ 2: Inline скрипт в конце body**

```php
<?php
// template.php
?>
<div id="app"></div>
<button id="my-button">Кнопка</button>

<script>
// DOM уже загружен, скрипт в конце body
(function() {
    var button = BX('my-button');
    BX.bind(button, 'click', function() {
        console.log('Клик');
    });
})();
</script>
```

**Способ 3: С загрузкой расширений**

```javascript
BX.ready(function() {
    // Сначала загружаем нужные расширения
    BX.Runtime.loadExtension(['ui.notification', 'ui.buttons']).then(function() {
        // Расширения загружены, инициализируем компонент
        initializeComponent();
    });
});
```

**Способ 4: Отложенная инициализация (для тяжелых компонентов)**

```javascript
BX.ready(function() {
    // Быстрая инициализация критичного функционала
    initializeCriticalFeatures();
    
    // Тяжелые компоненты загружаем с задержкой
    setTimeout(function() {
        BX.Runtime.loadExtension('heavy.component').then(function() {
            initializeHeavyComponent();
        });
    }, 100);
});
```

**Типичная ошибка:**

```javascript
// НЕПРАВИЛЬНО - элемент может не существовать
var button = BX('my-button');
BX.bind(button, 'click', handler); // button = null, ошибка!

// ПРАВИЛЬНО
BX.ready(function() {
    var button = BX('my-button');
    if (button) {
        BX.bind(button, 'click', handler);
    }
});
```

---

### 8. Как создать диалоговое окно подтверждения в Bitrix?

**Ответ:**

**Способ 1: BX.UI.Dialogs.MessageBox (современный)**

```javascript
// Подключаем расширение
BX.Runtime.loadExtension('ui.dialogs.messagebox').then(function() {
    
    // Простое подтверждение
    BX.UI.Dialogs.MessageBox.confirm(
        'Вы уверены, что хотите удалить этот элемент?',
        function(messageBox) {
            // Пользователь нажал "Да"
            deleteItem();
            messageBox.close();
        },
        'Удалить',    // Текст кнопки подтверждения
        function(messageBox) {
            // Пользователь нажал "Отмена"
            messageBox.close();
        }
    );
    
    // С заголовком и кастомными кнопками
    BX.UI.Dialogs.MessageBox.show({
        title: 'Подтверждение удаления',
        message: 'Элемент будет удален безвозвратно. Продолжить?',
        modal: true,
        buttons: BX.UI.Dialogs.MessageBoxButtons.YES_CANCEL,
        onYes: function(messageBox) {
            deleteItem();
            messageBox.close();
            
            BX.UI.Notification.Center.notify({
                content: 'Элемент удален'
            });
        },
        onCancel: function(messageBox) {
            messageBox.close();
        }
    });
});
```

**Способ 2: BX.Main.Popup (более гибкий)**

```javascript
var popup = new BX.Main.Popup('confirm-popup', null, {
    content: 'Вы уверены, что хотите выполнить это действие?',
    titleBar: 'Подтверждение',
    closeIcon: true,
    buttons: [
        new BX.PopupWindowButton({
            text: 'Да, выполнить',
            className: 'ui-btn ui-btn-success',
            events: {
                click: function() {
                    performAction();
                    popup.close();
                }
            }
        }),
        new BX.PopupWindowButtonLink({
            text: 'Отмена',
            className: 'ui-btn ui-btn-link',
            events: {
                click: function() {
                    popup.close();
                }
            }
        })
    ]
});

popup.show();
```

**Способ 3: Нативный confirm() (не рекомендуется)**

```javascript
// Работает, но выглядит некрасиво
if (confirm('Вы уверены?')) {
    deleteItem();
}
```

**Практический пример:**

```javascript
BX.ready(function() {
    // Обработчик кнопок удаления
    BX.bindDelegate(
        document.body,
        'click',
        {className: 'delete-item-btn'},
        function(e) {
            e.preventDefault();
            
            var button = e.target;
            var itemId = button.getAttribute('data-id');
            var itemName = button.getAttribute('data-name');
            
            BX.Runtime.loadExtension('ui.dialogs.messagebox').then(function() {
                BX.UI.Dialogs.MessageBox.confirm(
                    'Удалить элемент "' + itemName + '"?',
                    function(messageBox) {
                        // Показываем лоадер на кнопке
                        BX.addClass(button, 'ui-btn-wait');
                        
                        // Удаляем через AJAX
                        BX.ajax.runComponentAction('my:component', 'deleteItem', {
                            mode: 'class',
                            data: { id: itemId }
                        }).then(
                            function(response) {
                                // Успех
                                BX.removeClass(button, 'ui-btn-wait');
                                messageBox.close();
                                
                                // Удаляем элемент из DOM
                                var row = button.closest('tr');
                                BX.remove(row);
                                
                                BX.UI.Notification.Center.notify({
                                    content: 'Элемент удален'
                                });
                            },
                            function(response) {
                                // Ошибка
                                BX.removeClass(button, 'ui-btn-wait');
                                messageBox.close();
                                
                                BX.UI.Notification.Center.notify({
                                    content: 'Ошибка: ' + response.errors[0].message
                                });
                            }
                        );
                    },
                    'Удалить'
                );
            });
        }
    );
});
```

---

### 9. Как защититься от CSRF атак при AJAX запросах?

**Ответ:**

**Использование bitrix_sessid:**

```javascript
// Получаем токен сессии
var sessid = BX.bitrix_sessid();

// Отправляем в AJAX запросе
BX.ajax.post('/local/ajax/handler.php', {
    action: 'saveData',
    data: formData,
    sessid: sessid // CSRF токен
}, function(response) {
    console.log('Данные сохранены');
});
```

**Проверка на сервере:**

```php
<?php
// handler.php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Проверка CSRF токена
if (!check_bitrix_sessid()) {
    echo json_encode(['error' => 'CSRF validation failed']);
    die();
}

// Обработка данных
$action = $_POST['action'];
$data = $_POST['data'];

// ...
```

**Автоматическая защита в runComponentAction:**

```javascript
// CSRF проверка встроена автоматически
BX.ajax.runComponentAction('my:component', 'saveData', {
    mode: 'class',
    data: { name: 'Test' }
    // sessid добавляется автоматически!
}).then(function(response) {
    console.log('Сохранено');
});
```

**Настройка фильтров в классе компонента:**

```php
<?php
namespace Myvendor\Components;

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class MyComponent extends \CBitrixComponent implements Controllerable
{
    public function configureActions()
    {
        return [
            'saveData' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),    // Авторизация
                    new ActionFilter\HttpMethod([          // Только POST
                        ActionFilter\HttpMethod::METHOD_POST
                    ]),
                    new ActionFilter\Csrf(),               // CSRF защита
                ],
            ],
        ];
    }
    
    public function saveDataAction($name, $value)
    {
        // Если дошли сюда - все проверки пройдены
        return ['success' => true];
    }
}
```

**Важные моменты:**
- Всегда передавайте `sessid` в AJAX запросах
- Проверяйте `check_bitrix_sessid()` на сервере
- Используйте `ActionFilter\Csrf()` в компонентах
- Не отключайте CSRF защиту без крайней необходимости

---

### 10. Как работать с BX.UI.EntitySelector для выбора пользователей?

**Ответ:**

**Базовый пример:**

```javascript
BX.ready(function() {
    // Загружаем расширение
    BX.Runtime.loadExtension('ui.entity-selector').then(function() {
        
        var selectedUsers = [];
        
        // Создаем селектор
        var dialog = new BX.UI.EntitySelector.Dialog({
            targetNode: BX('user-selector-container'),
            context: 'my-component-users',
            multiple: true,           // Множественный выбор
            dropdownMode: true,       // Режим dropdown
            showAvatars: true,        // Показывать аватары
            enableSearch: true,       // Поиск
            height: 300,
            entities: [
                {
                    id: 'user',       // Тип сущности
                    options: {
                        inviteEmployeeLink: false,    // Скрыть "Пригласить"
                        emailUsers: false,            // Без email пользователей
                        departmentSelectDisable: true // Без выбора отделов
                    }
                }
            ],
            events: {
                'Item:onSelect': function(event) {
                    var item = event.getData().item;
                    selectedUsers.push({
                        id: item.id,
                        name: item.title,
                        avatar: item.avatar
                    });
                    console.log('Выбран:', item.title);
                    updateSelectedList();
                },
                'Item:onDeselect': function(event) {
                    var item = event.getData().item;
                    selectedUsers = selectedUsers.filter(function(u) {
                        return u.id !== item.id;
                    });
                    console.log('Удален:', item.title);
                    updateSelectedList();
                }
            }
        });
        
        // Показываем диалог
        BX('select-users-btn').addEventListener('click', function() {
            dialog.show();
        });
        
        function updateSelectedList() {
            var container = BX('selected-users');
            container.innerHTML = '';
            
            selectedUsers.forEach(function(user) {
                var div = BX.create('div', {
                    props: { className: 'selected-user' },
                    text: user.name
                });
                container.appendChild(div);
            });
        }
    });
});
```

**HTML разметка:**

```html
<div id="user-selector-container"></div>
<button id="select-users-btn">Выбрать пользователей</button>
<div id="selected-users"></div>
```

**Подключение в PHP:**

```php
<?php
use Bitrix\Main\UI\Extension;

// В template.php
Extension::load('ui.entity-selector');
?>
```

**Полный пример с сохранением:**

```javascript
BX.ready(function() {
    BX.Runtime.loadExtension('ui.entity-selector').then(function() {
        
        var selectedUserIds = [];
        
        var selector = new BX.UI.EntitySelector.Dialog({
            targetNode: BX('users-field'),
            multiple: true,
            entities: [{ id: 'user' }],
            events: {
                'Item:onSelect': function(event) {
                    var userId = event.getData().item.id.toString().replace('user-', '');
                    selectedUserIds.push(userId);
                },
                'Item:onDeselect': function(event) {
                    var userId = event.getData().item.id.toString().replace('user-', '');
                    selectedUserIds = selectedUserIds.filter(function(id) {
                        return id !== userId;
                    });
                }
            }
        });
        
        // Открытие селектора
        BX.bind(BX('select-users-btn'), 'click', function() {
            selector.show();
        });
        
        // Сохранение
        BX.bind(BX('save-form-btn'), 'click', function() {
            BX.ajax.runComponentAction('my:component', 'saveUsers', {
                mode: 'class',
                data: {
                    userIds: selectedUserIds
                }
            }).then(function(response) {
                BX.UI.Notification.Center.notify({
                    content: 'Пользователи сохранены'
                });
            });
        });
    });
});
```

---

## Практические задачи

Решения всех задач находятся в папке `examples/module-14-2/`:

1. **Задача 1:** AJAX форма с валидацией
2. **Задача 2:** Компонент с live-поиском
3. **Задача 3:** Админ-панель с уведомлениями и диалогами

---

## Полезные ссылки

- [Документация BX.JS](https://dev.1c-bitrix.ru/api_help/js_lib/index.php)
- [BX.ajax](https://dev.1c-bitrix.ru/api_help/js_lib/ajax/bx_ajax_runcomponentaction.php)
- [UI.Notification](https://dev.1c-bitrix.ru/api_d7/bitrix/ui/notification/index.php)
- [Расширения (Extension API)](https://dev.1c-bitrix.ru/api_d7/bitrix/main/ui/extension/index.php)
- [BX.Main.Popup](https://dev.1c-bitrix.ru/api_help/js_lib/popup/index.php)
