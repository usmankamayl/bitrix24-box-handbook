# Компонент "Форма регистрации"

## Описание

Компонент для регистрации новых пользователей с валидацией полей и возможностью автоматической авторизации после успешной регистрации.

## Возможности

- ✅ Валидация полей на стороне сервера и клиента
- ✅ Проверка уникальности логина и email
- ✅ Автоматическая авторизация после регистрации
- ✅ Назначение пользователю групп
- ✅ Редирект на указанную страницу после успеха
- ✅ Вывод ошибок с сохранением введенных данных
- ✅ Адаптивная верстка

## Установка

Скопируйте папку компонента в `/local/components/bitrix-course/user.registration/`

## Использование

### Базовый вызов

```php
<?$APPLICATION->IncludeComponent(
    "bitrix-course:user.registration",
    ".default",
    [
        "AUTO_AUTH" => "Y",
        "SUCCESS_PAGE" => "/personal/",
        "USER_GROUPS" => [2],
    ]
);?>
```

### Параметры компонента

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `AUTO_AUTH` | string | Автоматическая авторизация после регистрации (Y/N) | Y |
| `SUCCESS_PAGE` | string | Страница редиректа после успешной регистрации | / |
| `USER_GROUPS` | array | Массив ID групп для нового пользователя | [2] |

## Структура компонента

```
user-registration/
├── class.php              # Логика компонента
├── component.php          # Точка входа
├── .description.php       # Описание компонента
├── .parameters.php        # Параметры компонента
└── templates/
    └── .default/
        ├── template.php   # HTML-шаблон формы
        ├── style.css      # Стили формы
        └── script.js      # Валидация на клиенте
```

## Основные методы

### validateForm($arFields)

Валидация полей формы на сервере:
- Проверка обязательных полей
- Проверка минимальной длины логина/пароля
- Проверка формата email
- Проверка совпадения паролей

```php
protected function validateForm($arFields)
{
    $arErrors = [];
    
    if (empty($arFields['LOGIN'])) {
        $arErrors[] = 'Не указан логин';
    }
    
    return $arErrors;
}
```

### registerUser($arFields)

Создание нового пользователя через CUser::Add():

```php
protected function registerUser($arFields)
{
    $user = new CUser;
    
    $userId = $user->Add([
        'LOGIN' => $arFields['LOGIN'],
        'EMAIL' => $arFields['EMAIL'],
        'PASSWORD' => $arFields['PASSWORD'],
        'CONFIRM_PASSWORD' => $arFields['PASSWORD_CONFIRM'],
        'ACTIVE' => 'Y',
        'GROUP_ID' => $this->arParams['USER_GROUPS'],
    ]);
    
    if ($userId && $this->arParams['AUTO_AUTH'] === 'Y') {
        global $USER;
        $USER->Authorize($userId);
    }
    
    return ['success' => !!$userId, 'user_id' => $userId];
}
```

## Пример использования в проекте

### На странице `/auth/registration.php`:

```php
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Регистрация");?>

<?$APPLICATION->IncludeComponent(
    "bitrix-course:user.registration",
    ".default",
    [
        "AUTO_AUTH" => "Y",
        "SUCCESS_PAGE" => "/personal/",
        "USER_GROUPS" => [2, 5], // Авторизованные + Покупатели
    ]
);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
```

## Обрабатываемые поля

### Обязательные:
- `LOGIN` — логин пользователя (минимум 3 символа)
- `EMAIL` — email (проверка формата)
- `NAME` — имя
- `PASSWORD` — пароль (минимум 6 символов)
- `PASSWORD_CONFIRM` — подтверждение пароля

### Необязательные:
- `LAST_NAME` — фамилия
- `PHONE` — телефон

## Результат работы ($arResult)

```php
$arResult = [
    'ERRORS' => [],          // Массив ошибок валидации
    'SUCCESS' => false,      // Флаг успешной регистрации
    'USER_ID' => null,       // ID созданного пользователя
    'FORM_DATA' => [],       // Данные формы (для восстановления при ошибке)
];
```

## Валидация на клиенте

JavaScript проверяет:
- Совпадение паролей в реальном времени
- HTML5 валидацию (required, minlength, type="email")

## Доработки и расширения

### Добавление капчи

```php
// В class.php добавить проверку
if (!$APPLICATION->CaptchaCheckCode($_POST['captcha_word'], $_POST['captcha_sid'])) {
    $arErrors[] = 'Неверный код с картинки';
}
```

### Отправка письма с подтверждением

```php
// Использовать CUser::Register() вместо Add()
$userId = $user->Register($arFields);
// Автоматически отправит письмо
```

### Добавление пользовательских полей

```php
$arUserFields['UF_PHONE_INNER'] = $arFields['PHONE_INNER'];
```

## Безопасность

- ✅ Проверка CSRF-токена через `check_bitrix_sessid()`
- ✅ Экранирование вывода через `htmlspecialcharsbx()`
- ✅ Валидация на сервере (клиентская валидация не заменяет серверную)
- ✅ Проверка формата email через встроенную `check_email()`

## Тестирование

1. Регистрация с корректными данными
2. Попытка регистрации с коротким логином/паролем
3. Регистрация с несовпадающими паролями
4. Регистрация с некорректным email
5. Проверка автоматической авторизации
6. Проверка редиректа после регистрации
