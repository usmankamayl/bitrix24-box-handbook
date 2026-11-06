# Модуль 2.4: Пользователи и группы

## Теория

### Работа с пользователями в Bitrix

Bitrix предоставляет мощный API для работы с пользователями через класс `\Bitrix\Main\UserTable` (ORM) и класс `CUser` (старое API).

#### Основные концепции

**Пользователь** — основная сущность системы, которая имеет:
- Личные данные (имя, фамилия, email, телефон)
- Учетные данные (логин, пароль)
- Права доступа через группы
- Пользовательские поля (UF_*)
- Активность и авторизацию

**Группа** — набор прав доступа, который можно назначить пользователям:
- Группы определяют права на модули и функции
- Пользователь может входить в несколько групп
- Права наследуются аддитивно (суммируются)

### API для работы с пользователями

#### Класс UserTable (D7 ORM)

```php
use Bitrix\Main\UserTable;

// Получение пользователя по ID
$user = UserTable::getById(1)->fetch();

// Получение списка пользователей с фильтром
$result = UserTable::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL'],
    'order' => ['ID' => 'DESC'],
    'limit' => 10
]);

while ($user = $result->fetch()) {
    // Обработка
}
```

#### Класс CUser (старое API)

```php
$user = new CUser;

// Создание пользователя
$userId = $user->Add([
    'LOGIN' => 'newuser',
    'EMAIL' => 'user@example.com',
    'NAME' => 'Иван',
    'LAST_NAME' => 'Иванов',
    'PASSWORD' => 'password123',
    'CONFIRM_PASSWORD' => 'password123',
    'ACTIVE' => 'Y',
    'GROUP_ID' => [2] // ID групп
]);

// Обновление пользователя
$user->Update($userId, [
    'NAME' => 'Петр'
]);

// Получение списка
$rsUsers = CUser::GetList(
    ($by = 'id'), 
    ($order = 'desc'),
    ['ACTIVE' => 'Y']
);
```

### Глобальный объект $USER

```php
global $USER;

// Проверка авторизации
if ($USER->IsAuthorized()) {
    $userId = $USER->GetID();
    $userGroups = $USER->GetUserGroupArray();
}

// Проверка прав
if ($USER->IsAdmin()) {
    // Администратор
}

// Получение полей
$userName = $USER->GetFullName();
$userEmail = $USER->GetEmail();
```

### Работа с группами

#### Класс GroupTable (D7)

```php
use Bitrix\Main\GroupTable;

// Получение группы
$group = GroupTable::getById(5)->fetch();

// Список групп
$groups = GroupTable::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['ID', 'NAME', 'STRING_ID']
])->fetchAll();
```

#### Класс CGroup (старое API)

```php
$group = new CGroup;

// Создание группы
$groupId = $group->Add([
    'ACTIVE' => 'Y',
    'NAME' => 'Менеджеры',
    'DESCRIPTION' => 'Группа менеджеров'
]);

// Получение списка групп пользователя
$rsGroups = CUser::GetUserGroup($userId);
while ($arGroup = $rsGroups->Fetch()) {
    echo $arGroup['GROUP_ID'];
}
```

### Пользовательские поля (UF_*)

```php
// Получение значения UF-поля
$rsUser = CUser::GetByID($userId);
$arUser = $rsUser->Fetch();
$ufValue = $arUser['UF_DEPARTMENT'];

// Установка UF-поля
$user = new CUser;
$user->Update($userId, [
    'UF_DEPARTMENT' => [1, 5] // Массив для множественных
]);

// Работа через UserFieldTable
use Bitrix\Main\UserFieldTable;

$field = UserFieldTable::getList([
    'filter' => [
        'ENTITY_ID' => 'USER',
        'FIELD_NAME' => 'UF_DEPARTMENT'
    ]
])->fetch();
```

### Авторизация и регистрация

```php
global $USER;

// Авторизация
$arAuthResult = $USER->Login('login', 'password', 'Y');
if ($arAuthResult === true) {
    // Успешная авторизация
} else {
    // $arAuthResult содержит массив с ошибками
}

// Выход
$USER->Logout();

// Регистрация
$user = new CUser;
$arFields = [
    'LOGIN' => 'newuser',
    'EMAIL' => 'user@example.com',
    'PASSWORD' => 'pass',
    'CONFIRM_PASSWORD' => 'pass',
    'NAME' => 'Имя',
    'ACTIVE' => 'Y'
];

$userId = $user->Add($arFields);
if ($userId) {
    $USER->Authorize($userId); // Автоматическая авторизация
}
```

### Проверка прав доступа

```php
global $USER;

// Проверка на администратора
if ($USER->IsAdmin()) { }

// Проверка вхождения в группу
if (in_array(5, $USER->GetUserGroupArray())) { }

// Проверка прав на модуль
if ($USER->CanDoOperation('edit_php')) { }

// Через класс CMain
global $APPLICATION;
if ($APPLICATION->GetGroupRight('main') >= 'W') {
    // Уровень прав W или выше
}
```

## Вопросы для собеседования

### 1. В чем разница между UserTable и CUser?

**Ответ:**
- **UserTable** — современный D7 ORM-подход с типизацией и поддержкой всех возможностей ORM (Runtime fields, подзапросы)
- **CUser** — старое API с процедурным подходом, но содержит методы для авторизации (Login, Logout), которых нет в UserTable
- CUser требуется для создания/обновления пользователей с валидацией
- UserTable лучше для выборок с join и сложными фильтрами

### 2. Как получить список групп текущего пользователя?

**Ответ:**
```php
global $USER;
$groups = $USER->GetUserGroupArray();
// Возвращает массив ID групп, включая динамические (например, группа 2 для всех авторизованных)
```

### 3. Какие системные группы есть в Bitrix?

**Ответ:**
- **1** — Администраторы (неудаляемая)
- **2** — Все авторизованные пользователи (динамическая)
- **3** — Все пользователи, включая гостей (динамическая)

### 4. Как проверить, авторизован ли пользователь?

**Ответ:**
```php
global $USER;
if ($USER->IsAuthorized()) {
    // Пользователь авторизован
}
```

### 5. Что такое UF-поля пользователей?

**Ответ:**
Пользовательские поля (User Fields) — дополнительные поля, которые можно добавить к сущности USER. Имеют префикс `UF_`. Создаются через админку или API. Поддерживают разные типы: строка, число, список, файл, привязка к элементам и др.

### 6. Как добавить нового пользователя программно?

**Ответ:**
```php
$user = new CUser;
$arFields = [
    'LOGIN' => 'login',
    'EMAIL' => 'email@test.com',
    'NAME' => 'Имя',
    'PASSWORD' => 'password',
    'CONFIRM_PASSWORD' => 'password',
    'ACTIVE' => 'Y',
    'GROUP_ID' => [2, 5] // Группы
];

$userId = $user->Add($arFields);
if (!$userId) {
    echo $user->LAST_ERROR;
}
```

### 7. Как программно авторизовать пользователя?

**Ответ:**
```php
global $USER;

// Способ 1: По логину/паролю
$result = $USER->Login('login', 'password', 'Y'); // Y - запомнить

// Способ 2: По ID (без проверки пароля)
$USER->Authorize($userId);
```

### 8. Чем отличается Add от Register в CUser?

**Ответ:**
- **Add()** — просто создает пользователя в БД
- **Register()** — создает пользователя и отправляет письмо с подтверждением email (если включена опция подтверждения регистрации)

### 9. Как получить email текущего пользователя?

**Ответ:**
```php
global $USER;
$email = $USER->GetEmail();

// Или через UserTable
$user = \Bitrix\Main\UserTable::getById($USER->GetID())->fetch();
$email = $user['EMAIL'];
```

### 10. Как проверить, является ли пользователь администратором?

**Ответ:**
```php
global $USER;
if ($USER->IsAdmin()) {
    // Администратор (входит в группу 1)
}
```

## Практические задачи

### Задача 1: Компонент "Форма регистрации"
Создать компонент регистрации с проверкой полей и автоматической авторизацией после регистрации.

### Задача 2: Компонент "Список пользователей с фильтрацией"
Список пользователей с фильтром по группам, активности и поиском по имени/email.

### Задача 3: Компонент "Профиль пользователя с редактированием"
Личный кабинет с возможностью редактирования своих данных и UF-полей.

**Решения задач находятся в `examples/module-2-4/`**

## Частые ошибки

### 1. Забывают проверять авторизацию

```php
// ❌ Неправильно
global $USER;
$userId = $USER->GetID(); // Может быть false

// ✅ Правильно
global $USER;
if ($USER->IsAuthorized()) {
    $userId = $USER->GetID();
}
```

### 2. Не проверяют ошибки при создании пользователя

```php
// ❌ Неправильно
$user = new CUser;
$userId = $user->Add($arFields);

// ✅ Правильно
$user = new CUser;
$userId = $user->Add($arFields);
if (!$userId) {
    $errors = $user->LAST_ERROR;
}
```

### 3. Путают GetUserGroupArray() и GetUserGroup()

```php
// GetUserGroupArray() — массив ID групп
$groups = $USER->GetUserGroupArray(); // [1, 2, 5]

// CUser::GetUserGroup($userId) — объект выборки
$rsGroups = CUser::GetUserGroup($userId);
while ($group = $rsGroups->Fetch()) { }
```

## Ссылки на документацию

- [Работа с пользователями (D7)](https://dev.1c-bitrix.ru/api_d7/bitrix/main/user/index.php)
- [Класс CUser](https://dev.1c-bitrix.ru/api_help/main/reference/cuser/index.php)
- [Группы пользователей](https://dev.1c-bitrix.ru/api_help/main/reference/cgroup/index.php)
- [Пользовательские поля](https://dev.1c-bitrix.ru/api_d7/bitrix/main/userfield/index.php)
