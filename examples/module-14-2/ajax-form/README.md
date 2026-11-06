# Задача 1: AJAX форма с валидацией

## Описание

Форма обратной связи с **клиентской и серверной валидацией** через BX.JS. Демонстрирует работу с:
- `BX.ajax.runComponentAction()` - вызов метода компонента
- `BX.UI.Notification` - уведомления пользователя
- Валидация на клиенте и сервере
- Обработка ошибок
- UX: отключение кнопки, индикация загрузки

## Структура компонента

```
ajax-form/
├── .description.php           # Описание компонента
├── .parameters.php            # Параметры компонента
├── class.php                  # Класс с AJAX методом
├── lang/ru/class.php          # Языковые сообщения
└── templates/.default/
    └── template.php           # Шаблон с формой и JS
```

## Установка

### 1. Копируем компонент

```bash
# Копируем в папку компонентов вашего проекта
cp -r ajax-form /local/components/bitrix/module.14.2.ajax-form/
```

### 2. Настраиваем почтовое событие (опционально)

Компонент использует почтовое событие `FEEDBACK_FORM`. Создайте его в административной панели:

**Bitrix Admin → Настройки → Почтовые события → Добавить тип события**

- **ID:** `FEEDBACK_FORM`
- **Название:** Обратная связь

**Создайте почтовый шаблон:**

```
Тема: Новое сообщение с сайта

От кого: #EMAIL#
Имя: #NAME#
Телефон: #PHONE#

Сообщение:
#MESSAGE#
```

### 3. Подключаем компонент на странице

```php
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->SetTitle('Обратная связь');
?>

<?php
$APPLICATION->IncludeComponent(
    'bitrix:module.14.2.ajax-form',
    '.default',
    [
        'EMAIL_TO' => 'info@example.com',
        'SUCCESS_MESSAGE' => 'Спасибо! Ваше сообщение отправлено.',
    ]
);
?>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
?>
```

## Как это работает

### Клиентская часть (JavaScript)

```javascript
// 1. Сбор данных формы
var formData = {
    name: form.elements['name'].value.trim(),
    email: form.elements['email'].value.trim(),
    phone: form.elements['phone'].value.trim(),
    message: form.elements['message'].value.trim(),
};

// 2. Валидация на клиенте (быстрая проверка)
if (!validateFormClient(formData)) {
    return; // показываем ошибки
}

// 3. AJAX запрос к серверу
BX.ajax.runComponentAction(
    'bitrix:module.14.2.ajax-form',
    'submitForm',
    {
        mode: 'class',
        data: formData
    }
).then(
    function(response) {
        // Успех
        if (response.data.success) {
            BX.UI.Notification.Center.notify({
                content: response.data.message
            });
            form.reset();
        }
    },
    function(response) {
        // Ошибка
        console.error(response.errors);
    }
);
```

### Серверная часть (PHP)

```php
/**
 * AJAX метод в class.php
 */
public function submitFormAction($name, $email, $phone, $message)
{
    // 1. Серверная валидация (обязательно!)
    $errors = $this->validateFormData($name, $email, $phone, $message);
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'errors' => $errors,
        ];
    }
    
    // 2. Отправка email
    Event::send([
        'EVENT_NAME' => 'FEEDBACK_FORM',
        'LID' => SITE_ID,
        'C_FIELDS' => [
            'EMAIL_TO' => $this->arParams['EMAIL_TO'],
            'NAME' => $name,
            'EMAIL' => $email,
            'PHONE' => $phone,
            'MESSAGE' => $message,
        ],
    ]);
    
    // 3. Возврат результата
    return [
        'success' => true,
        'message' => 'Сообщение отправлено!',
    ];
}
```

## Ключевые моменты

### 1. Двойная валидация

**Клиент (быстрая):**
```javascript
function validateFormClient(data) {
    var errors = {};
    
    if (!data.name || data.name.length < 2) {
        errors.name = 'Введите имя';
    }
    
    if (!isValidEmail(data.email)) {
        errors.email = 'Некорректный email';
    }
    
    return Object.keys(errors).length === 0;
}
```

**Сервер (защита):**
```php
private function validateFormData($name, $email, $phone, $message)
{
    $errors = [];
    
    if (mb_strlen($name) < 2) {
        $errors['name'] = Loc::getMessage('ERROR_NAME_REQUIRED');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = Loc::getMessage('ERROR_EMAIL_INVALID');
    }
    
    return $errors;
}
```

### 2. UX при отправке

```javascript
// Блокируем кнопку
submitBtn.disabled = true;
submitBtn.textContent = 'Отправка...';

// После ответа разблокируем
submitBtn.disabled = false;
submitBtn.textContent = 'Отправить';
```

### 3. Вывод ошибок для каждого поля

```javascript
function displayErrors(errors) {
    for (var field in errors) {
        var errorElem = document.querySelector('.form-error[data-field="' + field + '"]');
        errorElem.textContent = errors[field];
        
        var input = form.elements[field];
        BX.addClass(input, 'is-invalid');
    }
}
```

### 4. CSRF защита встроена

```php
public function configureActions()
{
    return [
        'submitForm' => [
            'prefilters' => [
                new ActionFilter\Csrf(),  // Автоматическая CSRF защита
            ],
        ],
    ];
}
```

## Расширение функционала

### 1. Добавить капчу

```php
// В class.php
use Bitrix\Main\Context;

public function submitFormAction($name, $email, $phone, $message, $captchaWord, $captchaSid)
{
    // Проверка капчи
    $captcha = new \CCaptcha();
    if (!$captcha->CheckCode($captchaWord, $captchaSid)) {
        return [
            'success' => false,
            'errors' => ['captcha' => 'Неверный код капчи'],
        ];
    }
    
    // ... остальная логика
}
```

### 2. Сохранить в инфоблок

```php
use Bitrix\Iblock\Elements\ElementFeedbackTable;

public function submitFormAction($name, $email, $phone, $message)
{
    // Сохраняем в инфоблок
    $el = new \CIBlockElement;
    $arFields = [
        'IBLOCK_ID' => 10, // ID инфоблока "Обращения"
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'PROPERTY_VALUES' => [
            'EMAIL' => $email,
            'PHONE' => $phone,
            'MESSAGE' => $message,
        ],
    ];
    
    $elementId = $el->Add($arFields);
    
    if ($elementId) {
        return ['success' => true, 'id' => $elementId];
    }
    
    return ['success' => false, 'errors' => [$el->LAST_ERROR]];
}
```

### 3. Интеграция с CRM

```php
use Bitrix\Crm\LeadTable;

public function submitFormAction($name, $email, $phone, $message)
{
    // Создаем лид в CRM
    $leadId = LeadTable::add([
        'TITLE' => 'Обращение с сайта: ' . $name,
        'NAME' => $name,
        'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
        'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
        'COMMENTS' => $message,
        'SOURCE_ID' => 'WEB',
    ])->getId();
    
    return ['success' => true, 'leadId' => $leadId];
}
```

## Тестирование

### 1. Проверка клиентской валидации

```javascript
// Откройте консоль браузера и вызовите:
validateFormClient({
    name: 'A',
    email: 'invalid',
    phone: '123',
    message: 'short'
});
// Должны увидеть ошибки
```

### 2. Проверка AJAX запроса

```javascript
// В консоли браузера:
BX.ajax.runComponentAction(
    'bitrix:module.14.2.ajax-form',
    'submitForm',
    {
        mode: 'class',
        data: {
            name: 'Тест',
            email: 'test@example.com',
            phone: '+79991234567',
            message: 'Тестовое сообщение для проверки'
        }
    }
).then(console.log, console.error);
```

### 3. Проверка серверной валидации

```bash
# CURL запрос для тестирования без клиента
curl -X POST 'https://your-site.ru/bitrix/services/main/ajax.php?action=bitrix:module.14.2.ajax-form::submitForm' \
  -d 'name=T&email=invalid&phone=123&message=short'
```

## Частые ошибки

### 1. Не работает AJAX

**Проблема:** `Component not found`

**Решение:** Проверьте namespace компонента:
```javascript
// Правильно
BX.ajax.runComponentAction('bitrix:module.14.2.ajax-form', ...)

// Неправильно
BX.ajax.runComponentAction('module.14.2.ajax-form', ...)
```

### 2. CSRF ошибка

**Проблема:** `Invalid CSRF token`

**Решение:** Убедитесь, что `ActionFilter\Csrf()` добавлен в `configureActions()`:
```php
public function configureActions()
{
    return [
        'submitForm' => [
            'prefilters' => [
                new ActionFilter\Csrf(),
            ],
        ],
    ];
}
```

### 3. Не приходят письма

**Проблема:** Метод `Event::send()` возвращает `false`

**Решение:**
1. Проверьте, что почтовое событие `FEEDBACK_FORM` создано
2. Проверьте настройки SMTP в Bitrix
3. Проверьте логи: `/bitrix/modules/main/classes/general/event.php`

## Выводы

Эта задача демонстрирует:

✅ **BX.ajax.runComponentAction** - современный способ AJAX запросов  
✅ **BX.UI.Notification** - красивые уведомления  
✅ **Двойная валидация** - на клиенте и сервере  
✅ **CSRF защита** - автоматически через ActionFilter  
✅ **UX паттерны** - блокировка кнопки, индикация загрузки  
✅ **Обработка ошибок** - для каждого поля отдельно

---

**Следующие задачи:**
- Задача 2: Компонент с live-поиском
- Задача 3: Админ-панель с уведомлениями и диалогами
