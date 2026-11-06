
# Пример 2: Компонент "Форма обратной связи"

Полнофункциональный компонент формы обратной связи с валидацией, защитой от спама и Ajax-отправкой.

## Возможности

### 🛡️ Защита от спама
- **Honeypot** - скрытое поле, которое заполняют только боты
- **Проверка времени заполнения** - форма не может быть отправлена слишком быстро
- **Капча** - визуальная проверка пользователя
- **Токен времени** - защита от повторной отправки устаревшей формы

### ✅ Валидация
- Проверка обязательных полей
- Валидация email
- Валидация телефона
- Проверка капчи
- Клиентская валидация (JavaScript)
- Серверная валидация (PHP)

### 📧 Отправка email
- **Через почтовые события Bitrix** (рекомендуется)
- Через стандартный PHP mail()
- Настраиваемая тема и получатель

### 🚀 Ajax
- Отправка без перезагрузки страницы
- Анимация загрузки
- Красивое отображение ошибок и успеха
- Автообновление капчи при ошибке

## Установка

### 1. Копирование компонента

Скопируйте папку `feedback-form` в `/local/components/custom/`

### 2. Создание почтового события (рекомендуется)

Создайте почтовое событие в админке:

**Настройки → Настройки продукта → Почтовые события → Добавить тип события**

```
Тип события: FEEDBACK_FORM
Название: Форма обратной связи
Описание: Отправка сообщения с формы обратной связи
```

**Шаблон почтового события:**

```
От кого: #DEFAULT_EMAIL_FROM#
Кому: info@example.com
Тема: Сообщение с формы обратной связи: #SUBJECT#

Имя: #NAME#
Email: #EMAIL#
Телефон: #PHONE#

Тема: #SUBJECT#

Сообщение:
#MESSAGE#
```

### 3. Использование на странице

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:feedback.form",
    ".default",
    [
        // Email получателя
        "EMAIL_TO" => "info@example.com",
        
        // Тема письма
        "EMAIL_SUBJECT" => "Сообщение с сайта",
        
        // Использовать почтовые события Bitrix
        "USE_BITRIX_MAIL_EVENT" => "Y",
        "EVENT_NAME" => "FEEDBACK_FORM",
        
        // Обязательные поля
        "REQUIRED_FIELDS" => ["NAME", "EMAIL", "MESSAGE"],
        
        // Защита от спама
        "USE_CAPTCHA" => "Y",
        "USE_HONEYPOT" => "Y",
        "MIN_FILL_TIME" => 3, // секунды
        
        // Сообщения
        "SUCCESS_MESSAGE" => "Спасибо! Ваше сообщение отправлено.",
        "ERROR_MESSAGE" => "Ошибка отправки. Попробуйте позже.",
        
        // Ajax
        "USE_AJAX" => "Y",
    ],
    false
);
?>
```

## Структура компонента

```
/local/components/custom/feedback.form/
├── .description.php     # Описание
├── .parameters.php      # Параметры
├── class.php           # Класс с логикой
├── component.php       # Точка входа
└── templates/
    └── .default/
        ├── template.php    # HTML шаблон
        ├── style.css      # Стили
        └── script.js      # JavaScript (Ajax, валидация)
```

## Основные методы класса

### `onPrepareComponentParams($arParams)`
Нормализация и установка параметров по умолчанию

### `isFormSubmitted()`
Проверка отправки формы (POST-запрос)

### `getFormData()`
Получение данных из POST-запроса

### `validateForm()`
Полная валидация формы:
- Обязательные поля
- Формат email
- Honeypot
- Время заполнения
- Капча

### `sendEmail()`
Отправка email через:
- `sendViaMailEvent()` - почтовые события Bitrix
- `sendViaPhpMail()` - стандартный mail()

### `sendAjaxResponse()`
Отправка JSON-ответа при Ajax-запросе

## Защита от спама

### 1. Honeypot
```html
<!-- Скрытое поле, которое видят только боты -->
<input type="text" name="website" class="feedback-form__honeypot">
```

```css
.feedback-form__honeypot {
    position: absolute;
    left: -9999px;
    opacity: 0;
}
```

Если поле заполнено - это бот.

### 2. Проверка времени заполнения

```php
// В форме
<input type="hidden" name="form_time" value="<?= time() ?>">

// В классе
protected function validateFillTime(): void
{
    $fillTime = time() - intval($this->formData["FORM_TIME"]);
    
    if ($fillTime < $this->arParams["MIN_FILL_TIME"]) {
        $this->errors["SPAM"] = "Форма заполнена слишком быстро";
    }
}
```

### 3. Капча

Стандартная капча Bitrix с автообновлением при ошибке.

## Ajax отправка

### Запрос
```javascript
fetch(form.action, {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        showSuccess(form, data.message);
    } else {
        showErrors(form, data.errors);
        
        // Обновляем капчу
        if (data.new_captcha) {
            updateCaptcha(form, data.new_captcha);
        }
    }
});
```

### Ответ сервера
```json
{
    "success": true,
    "message": "Ваше сообщение отправлено!"
}

// или при ошибке

{
    "success": false,
    "errors": {
        "EMAIL": "Некорректный email адрес",
        "MESSAGE": "Поле «Сообщение» обязательно"
    },
    "new_captcha": {
        "CODE": "abc123",
        "URL": "/bitrix/tools/captcha.php?captcha_sid=abc123"
    }
}
```

## Дополнительные возможности

### Маска телефона

В `script.js` реализована маска для поля телефона:

```javascript
+7 (999) 123-45-67
```

### Валидация на клиенте

Валидация email и телефона при потере фокуса (blur).

### Адаптивный дизайн

Форма полностью адаптивна для мобильных устройств.

## Расширение функционала

### Добавление нового поля

**1. В template.php:**

```php
<div class="feedback-form__field">
    <label for="<?= $formId ?>_company">Компания</label>
    <input type="text" name="company" id="<?= $formId ?>_company">
</div>
```

**2. В class.php (getFormData):**

```php
"COMPANY" => trim($request->getPost("company") ?? ""),
```

**3. В class.php (sendViaMailEvent):**

```php
$fields = [
    // ...
    "COMPANY" => $this->formData["COMPANY"],
];
```

### Сохранение в инфоблок

Добавьте в метод `processForm()`:

```php
if ($this->sendEmail()) {
    // Сохраняем в инфоблок
    $el = new CIBlockElement();
    $el->Add([
        "IBLOCK_ID" => 10, // ID инфоблока
        "NAME" => $this->formData["NAME"],
        "ACTIVE" => "Y",
        "PROPERTY_VALUES" => [
            "EMAIL" => $this->formData["EMAIL"],
            "PHONE" => $this->formData["PHONE"],
            "MESSAGE" => $this->formData["MESSAGE"],
        ]
    ]);
    
    $this->arResult["SUCCESS"] = true;
}
```

### Отправка в CRM

```php
// После успешной валидации
use Bitrix\Crm\LeadTable;

LeadTable::add([
    "TITLE" => "Обращение с сайта",
    "NAME" => $this->formData["NAME"],
    "EMAIL" => [["VALUE" => $this->formData["EMAIL"], "VALUE_TYPE" => "WORK"]],
    "PHONE" => [["VALUE" => $this->formData["PHONE"], "VALUE_TYPE" => "WORK"]],
    "COMMENTS" => $this->formData["MESSAGE"],
    "SOURCE_ID" => "WEB",
]);
```

## Частые вопросы

**Q: Почему не приходят письма?**  
A: Проверьте:
1. Правильность email в параметре `EMAIL_TO`
2. Настройку почтовых событий в админке
3. Работу функции `mail()` на сервере
4. Логи `/bitrix/modules/main/classes/general/event_log.php`

**Q: Как отключить капчу для авторизованных пользователей?**  
A: В `.parameters.php` или при вызове компонента проверяйте `$USER->IsAuthorized()`

**Q: Как добавить файл во вложение?**  
A: Добавьте `<input type="file">` и в `sendViaMailEvent()` прикрепите файл к почтовому событию

**Q: Можно ли использовать Google reCAPTCHA?**  
A: Да, замените стандартную капчу на reCAPTCHA в шаблоне и валидации

## Тестирование

### Проверка защиты от спама

1. **Honeypot**: Заполните скрытое поле через DevTools - должна быть ошибка
2. **Время заполнения**: Отправьте форму сразу после загрузки - ошибка
3. **Капча**: Введите неверный код - ошибка

### Проверка валидации

- Оставьте обязательные поля пустыми
- Введите некорректный email
- Введите короткий номер телефона

## Безопасность

✅ **Реализовано:**
- Защита от CSRF (проверка POST)
- Экранирование HTML в шаблоне
- Валидация всех полей
- Защита от спам-ботов
- Ограничение времени сеанса

⚠️ **Рекомендации:**
- Добавьте rate limiting (ограничение запросов с одного IP)
- Используйте HTTPS
- Логируйте подозрительную активность
- Регулярно обновляйте Bitrix

---

## Итог

Полностью рабочий компонент формы обратной связи с:
- ✅ Многоуровневой защитой от спама
- ✅ Серверной и клиентской валидацией
- ✅ Ajax-отправкой
- ✅ Красивым дизайном
- ✅ Адаптивностью
- ✅ Расширяемостью
