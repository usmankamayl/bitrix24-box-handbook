# Задача 1: Кастомная форма редактирования сущности

## Описание

Полнофункциональная форма редактирования сущности с различными типами полей, валидацией и автосохранением.

## Возможности

### 1. Типы полей
- **Text** — текстовые поля с валидацией длины
- **Email** — email с форматной валидацией
- **Tel** — телефон с форматированием
- **Date** — дата с календарем
- **Textarea** — многострочный текст
- **Checkbox** — чекбоксы
- **User** — выбор пользователя через селектор
- **File** — загрузка файлов с drag-and-drop

### 2. Валидация
- **Клиентская** — мгновенная проверка при заполнении
- **Серверная** — проверка при сохранении
- Визуальная индикация ошибок
- Автоматическая прокрутка к первой ошибке

### 3. Автосохранение
- Сохранение черновиков каждые 30 секунд
- Восстановление несохраненных данных
- Индикатор статуса автосохранения
- Предупреждение при уходе со страницы

### 4. UI/UX
- Табы для группировки полей
- Адаптивный дизайн
- Анимации и переходы
- Индикация загрузки
- Уведомления о действиях

## Установка

### 1. Скопируйте компонент

```bash
# Путь в проекте Bitrix
/local/components/custom/entity.form/
```

### 2. Подключите на странице

```php
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetTitle("Редактирование сущности");

// Создание новой записи
$APPLICATION->IncludeComponent(
    "custom:entity.form",
    "",
    [
        "ENTITY_ID" => 0,
        "ENABLE_AUTOSAVE" => "Y",
        "AUTOSAVE_INTERVAL" => "30",
        "SUCCESS_URL" => "/local/entity/list.php",
        "CACHE_TIME" => 3600,
    ]
);

// Редактирование существующей
$entityId = (int)$_GET['ID'];

$APPLICATION->IncludeComponent(
    "custom:entity.form",
    "",
    [
        "ENTITY_ID" => $entityId,
        "ENABLE_AUTOSAVE" => "Y",
        "AUTOSAVE_INTERVAL" => "30",
        "SUCCESS_URL" => "/local/entity/detail.php?ID=#ID#",
        "CACHE_TIME" => 3600,
    ]
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
```

## Параметры компонента

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `ENTITY_ID` | int | ID сущности (0 для создания) | 0 |
| `ENABLE_AUTOSAVE` | Y/N | Включить автосохранение | Y |
| `AUTOSAVE_INTERVAL` | int | Интервал автосохранения (сек) | 30 |
| `SUCCESS_URL` | string | URL после сохранения | - |
| `CACHE_TIME` | int | Время кеширования | 3600 |

## Структура файлов

```
custom-entity-form/
├── .description.php          # Описание компонента
├── .parameters.php           # Параметры
├── class.php                 # Логика компонента
└── templates/
    └── .default/
        ├── template.php      # HTML шаблон
        ├── script.js         # JavaScript логика
        └── style.css         # Стили
```

## Использование

### 1. Базовое использование

```php
// Создание новой записи
$APPLICATION->IncludeComponent("custom:entity.form", "", [
    "ENTITY_ID" => 0
]);

// Редактирование
$APPLICATION->IncludeComponent("custom:entity.form", "", [
    "ENTITY_ID" => $_GET['ID']
]);
```

### 2. С автосохранением

```php
$APPLICATION->IncludeComponent("custom:entity.form", "", [
    "ENTITY_ID" => 0,
    "ENABLE_AUTOSAVE" => "Y",
    "AUTOSAVE_INTERVAL" => "30" // секунды
]);
```

### 3. С редиректом после сохранения

```php
$APPLICATION->IncludeComponent("custom:entity.form", "", [
    "ENTITY_ID" => 0,
    "SUCCESS_URL" => "/local/entity/detail.php?ID=#ID#"
]);
```

## API методы компонента

### save (POST)
Сохранение формы

**Параметры:**
```javascript
{
    NAME: "Название",
    EMAIL: "test@example.com",
    PHONE: "+7 999 123-45-67",
    BIRTH_DATE: "2000-01-01",
    DESCRIPTION: "Описание",
    RESPONSIBLE_ID: 1,
    ACTIVE: true
}
```

**Ответ:**
```javascript
{
    success: true,
    entityId: 123,
    message: "Данные успешно сохранены",
    redirectUrl: "/local/entity/detail.php?ID=123"
}
```

### autoSave (POST)
Автосохранение черновика

**Параметры:** те же, что у `save`

**Ответ:**
```javascript
{
    success: true,
    message: "Черновик сохранён"
}
```

### uploadFile (POST)
Загрузка файла

**Параметры:** `FormData` с файлом

**Ответ:**
```javascript
{
    success: true,
    fileId: 456,
    fileName: "document.pdf",
    fileSize: 102400,
    filePath: "/upload/iblock/123/document.pdf"
}
```

## Кастомизация

### 1. Добавление нового типа поля

В `class.php`:

```php
protected function getFormConfig()
{
    return [
        'sections' => [
            [
                'id' => 'main',
                'title' => 'Основное',
                'fields' => [
                    // ... существующие поля
                    [
                        'name' => 'MY_CUSTOM_FIELD',
                        'title' => 'Моё поле',
                        'type' => 'custom',
                        'required' => true,
                    ],
                ],
            ],
        ],
    ];
}
```

В `template.php`:

```php
case 'custom':
    ?>
    <div class="my-custom-field">
        <!-- Ваш HTML для кастомного поля -->
    </div>
    <?php
    break;
```

### 2. Изменение правил валидации

В `script.js`:

```javascript
getValidationRules: function() {
    const rules = {
        // ... стандартные правила
        
        MY_FIELD: {
            required: true,
            validator: (value) => {
                // Ваша логика валидации
                return value.length >= 5;
            },
            message: 'Минимум 5 символов'
        }
    };
    
    return rules;
}
```

### 3. Добавление кастомной логики сохранения

В `class.php`:

```php
protected function saveEntity($data)
{
    // Предобработка данных
    $data['NAME'] = mb_strtoupper($data['NAME']);
    
    // Вызов родительского метода
    $entityId = parent::saveEntity($data);
    
    // Постобработка
    $this->sendNotification($entityId);
    
    return $entityId;
}
```

### 4. Изменение интервала автосохранения

```javascript
EntityForm.init({
    // ...
    autosaveInterval: 60000 // 60 секунд
});
```

## Примеры использования

### Пример 1: Форма регистрации пользователя

```php
$APPLICATION->IncludeComponent("custom:entity.form", "registration", [
    "ENTITY_ID" => 0,
    "ENABLE_AUTOSAVE" => "N",
    "SUCCESS_URL" => "/personal/",
]);
```

### Пример 2: Форма заявки с файлами

```php
$APPLICATION->IncludeComponent("custom:entity.form", "request", [
    "ENTITY_ID" => 0,
    "ENABLE_AUTOSAVE" => "Y",
    "AUTOSAVE_INTERVAL" => "60",
    "SUCCESS_URL" => "/requests/detail.php?ID=#ID#",
]);
```

### Пример 3: Админ-форма редактирования

```php
$APPLICATION->IncludeComponent("custom:entity.form", "admin", [
    "ENTITY_ID" => $_GET['ID'],
    "ENABLE_AUTOSAVE" => "Y",
    "SUCCESS_URL" => "/local/admin/entity_list.php",
]);
```

## JavaScript API

### Методы объекта EntityForm

```javascript
// Инициализация
EntityForm.init(config);

// Отправка формы
EntityForm.submitForm();

// Автосохранение
EntityForm.autoSave();

// Переключение табов
EntityForm.switchTab('additional');

// Выбор пользователя
EntityForm.selectUser('RESPONSIBLE_ID');

// Загрузка файла
EntityForm.uploadFile('FILES', inputElement);

// Очистка формы
EntityForm.clearForm();

// Получение данных формы
const data = EntityForm.getFormData();

// Показ ошибки поля
EntityForm.showFieldError('EMAIL', 'Некорректный email');
```

### События

```javascript
// Событие переключения таба
BX.addCustomEvent('EntityForm:onTabSwitch', function(tabId) {
    console.log('Переключено на таб:', tabId);
});

// Событие перед отправкой
BX.addCustomEvent('EntityForm:beforeSubmit', function(formData) {
    console.log('Отправка формы:', formData);
});

// Событие после успешного сохранения
BX.addCustomEvent('EntityForm:afterSave', function(response) {
    console.log('Сохранено:', response);
});

// Событие автосохранения
BX.addCustomEvent('EntityForm:afterAutoSave', function() {
    console.log('Черновик сохранён');
});
```

## Адаптация под проект

### 1. Изменение источника данных

Замените в `class.php` метод `loadEntity()` на работу с вашей ORM-сущностью:

```php
protected function loadEntity($id)
{
    // Вместо инфоблока используйте свою сущность
    $entity = MyEntityTable::getById($id)->fetch();
    
    return [
        'ID' => $entity['ID'],
        'NAME' => $entity['NAME'],
        // ...
    ];
}
```

### 2. Настройка полей под задачу

Измените `getFormConfig()` под ваши требования:

```php
protected function getFormConfig()
{
    return [
        'sections' => [
            [
                'id' => 'main',
                'title' => 'Информация о клиенте',
                'fields' => [
                    ['name' => 'FULL_NAME', 'title' => 'ФИО', 'type' => 'text', 'required' => true],
                    ['name' => 'EMAIL', 'title' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'PHONE', 'title' => 'Телефон', 'type' => 'tel', 'required' => true],
                ],
            ],
        ],
    ];
}
```

## Производительность

- Автосохранение использует debounce для оптимизации запросов
- Загрузка файлов асинхронная
- Валидация на клиенте предотвращает лишние запросы
- Используется кеширование данных

## Безопасность

- CSRF защита через `ActionFilter\Csrf()`
- Проверка авторизации через `ActionFilter\Authentication()`
- Валидация на сервере всех данных
- Проверка размера и типа файлов
- Экранирование вывода через `htmlspecialcharsbx()`

## Совместимость

- Bitrix Framework 22.0+
- PHP 7.4+
- Современные браузеры (Chrome, Firefox, Safari, Edge)
- Адаптивный дизайн для мобильных устройств

## Доработки и расширения

Компонент легко расширяется:
1. Добавление новых типов полей
2. Интеграция с внешними сервисами
3. Кастомные валидаторы
4. Свои обработчики событий
5. Интеграция с CRM или другими модулями

---

## Полезные ссылки

- [UI.EntityEditor документация](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=13705)
- [BX.ajax.runComponentAction](https://dev.1c-bitrix.ru/api_d7/bitrix/main/engine/controller/index.php)
- [ActionFilter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/engine/actionfilter/index.php)
