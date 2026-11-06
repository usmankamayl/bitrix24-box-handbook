# Компонент "Профиль пользователя с редактированием"

## Описание

Компонент личного кабинета пользователя с возможностью просмотра и редактирования своих данных, включая стандартные поля и пользовательские (UF-поля).

## Возможности

- ✅ Просмотр профиля текущего пользователя
- ✅ Редактирование личных данных
- ✅ Загрузка фото профиля
- ✅ Работа с UF-полями (пользовательскими полями)
- ✅ Валидация полей
- ✅ Проверка прав доступа
- ✅ Гибкая настройка редактируемых полей
- ✅ Адаптивная верстка

## Установка

Скопируйте папку компонента в `/local/components/bitrix-course/user.profile.edit/`

## Использование

### Базовый вызов (профиль текущего пользователя)

```php
<?$APPLICATION->IncludeComponent(
    "bitrix-course:user.profile.edit",
    ".default",
    [
        "ONLY_OWN_PROFILE" => "Y",
        "EDITABLE_FIELDS" => [
            "NAME",
            "LAST_NAME",
            "EMAIL",
            "PERSONAL_PHONE",
            "PERSONAL_PHOTO"
        ],
    ]
);?>
```

### Редактирование конкретного пользователя (для админа)

```php
<?$APPLICATION->IncludeComponent(
    "bitrix-course:user.profile.edit",
    ".default",
    [
        "USER_ID" => 123,
        "ONLY_OWN_PROFILE" => "N",
        "SUCCESS_PAGE" => "/admin/users/",
    ]
);?>
```

## Параметры компонента

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `USER_ID` | int | ID пользователя (0 - текущий) | 0 |
| `ONLY_OWN_PROFILE` | string | Разрешить редактирование только своего профиля (Y/N) | Y |
| `SUCCESS_PAGE` | string | Страница редиректа после сохранения | '' |
| `EDITABLE_FIELDS` | array | Массив редактируемых полей | ['NAME', 'LAST_NAME', 'EMAIL', 'PERSONAL_PHONE', 'PERSONAL_PHOTO'] |

### Доступные поля для EDITABLE_FIELDS

- `NAME` — Имя
- `LAST_NAME` — Фамилия
- `SECOND_NAME` — Отчество
- `EMAIL` — Email
- `PERSONAL_PHONE` — Телефон
- `PERSONAL_MOBILE` — Мобильный
- `PERSONAL_PHOTO` — Фото
- `PERSONAL_BIRTHDAY` — День рождения
- `PERSONAL_GENDER` — Пол
- `WORK_COMPANY` — Компания
- `WORK_POSITION` — Должность

## Структура компонента

```
user-profile-edit/
├── class.php              # Логика компонента
├── component.php          # Точка входа
├── .description.php       # Описание
├── .parameters.php        # Параметры
└── templates/
    └── .default/
        ├── template.php   # HTML-шаблон
        ├── style.css      # Стили
        └── README.md      # Документация
```

## Основные методы класса

### checkAccess()

Проверка прав на редактирование профиля:

```php
protected function checkAccess()
{
    global $USER;
    
    if (!$USER->IsAuthorized()) {
        throw new \Exception('Необходима авторизация');
    }
    
    $userId = $this->arParams['USER_ID'] ?: $USER->GetID();
    
    // Только свой профиль или администратор
    if ($this->arParams['ONLY_OWN_PROFILE'] === 'Y' && !$USER->IsAdmin()) {
        if ($userId != $USER->GetID()) {
            throw new \Exception('Недостаточно прав');
        }
    }
    
    return $userId;
}
```

### getUserData($userId)

Получение данных пользователя через UserTable:

```php
protected function getUserData($userId)
{
    $result = UserTable::getList([
        'filter' => ['=ID' => $userId],
        'select' => array_merge(
            ['ID', 'LOGIN', 'ACTIVE'],
            $this->arParams['EDITABLE_FIELDS']
        ),
    ]);
    
    $user = $result->fetch();
    
    // Получение UF-полей
    $user['UF_FIELDS'] = $this->getUserFields($userId);
    
    return $user;
}
```

### getUserFields($userId)

Получение пользовательских полей (UF):

```php
protected function getUserFields($userId)
{
    global $USER_FIELD_MANAGER;
    
    $arUserFields = $USER_FIELD_MANAGER->GetUserFields('USER', $userId, LANGUAGE_ID);
    $arResult = [];
    
    foreach ($arUserFields as $fieldName => $arField) {
        $arResult[$fieldName] = [
            'FIELD_NAME' => $fieldName,
            'EDIT_FORM_LABEL' => $arField['EDIT_FORM_LABEL'],
            'USER_TYPE_ID' => $arField['USER_TYPE_ID'],
            'VALUE' => $arUser[$fieldName] ?? null,
            'MANDATORY' => $arField['MANDATORY'],
        ];
    }
    
    return $arResult;
}
```

### updateUser($userId, $arFields)

Обновление данных пользователя:

```php
protected function updateUser($userId, $arFields)
{
    $user = new \CUser;
    
    // Подготовка полей
    $arUpdateFields = [];
    foreach ($this->arParams['EDITABLE_FIELDS'] as $fieldName) {
        if (isset($arFields[$fieldName])) {
            $arUpdateFields[$fieldName] = $arFields[$fieldName];
        }
    }
    
    // Загрузка файла
    if (!empty($_FILES['PERSONAL_PHOTO']['name'])) {
        $arUpdateFields['PERSONAL_PHOTO'] = $_FILES['PERSONAL_PHOTO'];
    }
    
    // Обновление
    $result = $user->Update($userId, $arUpdateFields);
    
    return ['success' => !!$result];
}
```

## Пример использования

### Страница личного кабинета `/personal/profile.php`:

```php
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Мой профиль");?>

<?php
global $USER;
if (!$USER->IsAuthorized()) {
    LocalRedirect('/auth/');
}
?>

<?$APPLICATION->IncludeComponent(
    "bitrix-course:user.profile.edit",
    ".default",
    [
        "ONLY_OWN_PROFILE" => "Y",
        "EDITABLE_FIELDS" => [
            "NAME",
            "LAST_NAME",
            "SECOND_NAME",
            "EMAIL",
            "PERSONAL_PHONE",
            "PERSONAL_MOBILE",
            "PERSONAL_PHOTO",
            "PERSONAL_BIRTHDAY",
            "PERSONAL_GENDER",
            "WORK_COMPANY",
            "WORK_POSITION",
        ],
        "SUCCESS_PAGE" => "",  // Остаемся на странице
    ]
);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
```

## Результат работы ($arResult)

```php
$arResult = [
    'USER' => [                     // Данные пользователя
        'ID' => 1,
        'LOGIN' => 'admin',
        'NAME' => 'Иван',
        'LAST_NAME' => 'Иванов',
        'EMAIL' => 'ivan@test.com',
        'ACTIVE' => 'Y',
        'DATE_REGISTER' => '01.01.2024',
        'GROUPS' => [1, 2],
        'UF_FIELDS' => [            // Пользовательские поля
            'UF_DEPARTMENT' => [
                'FIELD_NAME' => 'UF_DEPARTMENT',
                'EDIT_FORM_LABEL' => 'Отдел',
                'USER_TYPE_ID' => 'string',
                'VALUE' => 'IT',
                'MANDATORY' => 'N',
            ],
        ],
    ],
    'EDITABLE_FIELDS' => [          // Разрешенные к редактированию поля
        'NAME', 'EMAIL', ...
    ],
    'ERRORS' => [],                 // Ошибки валидации
    'SUCCESS' => false,             // Флаг успешного сохранения
];
```

## Работа с UF-полями

UF-поля автоматически подключаются из системы:

```php
global $USER_FIELD_MANAGER;
$arUserFields = $USER_FIELD_MANAGER->GetUserFields('USER', $userId, LANGUAGE_ID);
```

Поддерживаемые типы UF-полей:
- `string` — строка
- `integer` — целое число
- `double` — число с плавающей точкой
- `date` — дата
- `boolean` — да/нет
- `file` — файл

## Загрузка файлов

Для загрузки фото профиля используется стандартный механизм:

```php
if (!empty($_FILES['PERSONAL_PHOTO']['name'])) {
    $arUpdateFields['PERSONAL_PHOTO'] = $_FILES['PERSONAL_PHOTO'];
}
```

Bitrix автоматически обрабатывает файл и сохраняет его ID.

## Валидация

Валидация на сервере:
- Проверка обязательных полей
- Проверка формата email
- Проверка прав доступа

```php
protected function validateForm($arFields)
{
    $arErrors = [];
    
    if (empty($arFields['EMAIL'])) {
        $arErrors[] = 'Email обязателен';
    } elseif (!check_email($arFields['EMAIL'])) {
        $arErrors[] = 'Некорректный email';
    }
    
    return $arErrors;
}
```

## Безопасность

- ✅ Проверка авторизации (`$USER->IsAuthorized()`)
- ✅ Проверка прав на редактирование (только свой профиль или админ)
- ✅ CSRF-защита через `check_bitrix_sessid()`
- ✅ Экранирование вывода через `htmlspecialcharsbx()`
- ✅ Валидация загружаемых файлов

## Доработки

### Смена пароля

Добавить поля для смены пароля:

```php
if (!empty($arFields['NEW_PASSWORD'])) {
    if ($arFields['NEW_PASSWORD'] === $arFields['NEW_PASSWORD_CONFIRM']) {
        $arUpdateFields['PASSWORD'] = $arFields['NEW_PASSWORD'];
        $arUpdateFields['CONFIRM_PASSWORD'] = $arFields['NEW_PASSWORD_CONFIRM'];
    }
}
```

### История изменений

Логирование изменений профиля:

```php
AddMessage2Log('User ' . $userId . ' updated profile: ' . print_r($arUpdateFields, true));
```

### Уведомления

Отправка email при изменении важных полей (email, телефон):

```php
if (isset($arFields['EMAIL']) && $arFields['EMAIL'] !== $oldEmail) {
    CEvent::Send('USER_EMAIL_CHANGED', SITE_ID, ['EMAIL' => $arFields['EMAIL']]);
}
```

## Тестирование

1. Редактирование своего профиля авторизованным пользователем
2. Попытка редактирования чужого профиля (должна быть ошибка)
3. Редактирование чужого профиля администратором
4. Загрузка фото
5. Валидация обязательных полей
6. Работа с UF-полями
