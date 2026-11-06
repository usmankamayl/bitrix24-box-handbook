# Компонент "Список пользователей с фильтрацией"

## Описание

Компонент для вывода списка пользователей с возможностью фильтрации по группам, активности и поиску по имени/email. Включает пагинацию и гибкую настройку отображаемых полей.

## Возможности

- ✅ Фильтрация по группам пользователей
- ✅ Фильтрация по активности (активные/неактивные)
- ✅ Поиск по имени, фамилии, email и логину
- ✅ Пагинация результатов
- ✅ Настраиваемые поля для отображения
- ✅ Сортировка по различным полям
- ✅ Работа через D7 ORM (UserTable)
- ✅ Адаптивная верстка

## Установка

Скопируйте папку компонента в `/local/components/bitrix-course/user.list.filter/`

## Использование

### Базовый вызов

```php
<?$APPLICATION->IncludeComponent(
    "bitrix-course:user.list.filter",
    ".default",
    [
        "PAGE_SIZE" => 20,
        "SHOW_INACTIVE" => "N",
        "SORT_BY" => "ID",
        "SORT_ORDER" => "DESC",
    ]
);?>
```

### Параметры компонента

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `PAGE_SIZE` | int | Количество элементов на странице | 20 |
| `SHOW_INACTIVE` | string | Показывать неактивных пользователей (Y/N) | N |
| `SORT_BY` | string | Поле сортировки (ID, NAME, EMAIL и др.) | ID |
| `SORT_ORDER` | string | Направление сортировки (ASC/DESC) | DESC |
| `DISPLAY_FIELDS` | array | Массив отображаемых полей | ['ID', 'NAME', 'LAST_NAME', 'EMAIL', 'ACTIVE', 'DATE_REGISTER'] |

### Доступные поля для DISPLAY_FIELDS

- `ID` — ID пользователя
- `NAME` — Имя
- `LAST_NAME` — Фамилия
- `EMAIL` — Email
- `LOGIN` — Логин
- `ACTIVE` — Активность
- `DATE_REGISTER` — Дата регистрации
- `LAST_LOGIN` — Последний вход

## Структура компонента

```
user-list-filter/
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

### buildFilter($arRequestFilter)

Построение фильтра для запроса к UserTable:

```php
protected function buildFilter($arRequestFilter)
{
    $arFilter = [];
    
    // Активность
    if ($arRequestFilter['active'] === 'Y') {
        $arFilter['=ACTIVE'] = 'Y';
    }
    
    // Поиск
    if (!empty($arRequestFilter['search'])) {
        $arFilter[] = [
            'LOGIC' => 'OR',
            ['%NAME' => $arRequestFilter['search']],
            ['%EMAIL' => $arRequestFilter['search']],
        ];
    }
    
    return $arFilter;
}
```

### getUsers($arFilter, $page)

Получение списка пользователей через UserTable:

```php
protected function getUsers($arFilter, $page)
{
    $offset = ($page - 1) * $this->arParams['PAGE_SIZE'];
    
    $result = UserTable::getList([
        'filter' => $arFilter,
        'select' => $this->arParams['DISPLAY_FIELDS'],
        'order' => [$this->arParams['SORT_BY'] => $this->arParams['SORT_ORDER']],
        'limit' => $this->arParams['PAGE_SIZE'],
        'offset' => $offset,
        'count_total' => true,
    ]);
    
    return [
        'users' => $result->fetchAll(),
        'total' => $result->getCount(),
    ];
}
```

### getGroups()

Получение списка групп для фильтра:

```php
protected function getGroups()
{
    return GroupTable::getList([
        'filter' => ['=ACTIVE' => 'Y'],
        'select' => ['ID', 'NAME'],
        'order' => ['NAME' => 'ASC'],
    ])->fetchAll();
}
```

## Пример использования

### Страница `/users/index.php`:

```php
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Список пользователей");?>

<?$APPLICATION->IncludeComponent(
    "bitrix-course:user.list.filter",
    ".default",
    [
        "PAGE_SIZE" => 30,
        "SHOW_INACTIVE" => "Y",
        "SORT_BY" => "DATE_REGISTER",
        "SORT_ORDER" => "DESC",
        "DISPLAY_FIELDS" => [
            "ID",
            "NAME",
            "LAST_NAME",
            "EMAIL",
            "ACTIVE",
            "DATE_REGISTER",
            "LAST_LOGIN",
        ],
        "CACHE_TIME" => 3600,
    ]
);?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
```

## Результат работы ($arResult)

```php
$arResult = [
    'USERS' => [                // Массив пользователей
        [
            'ID' => 1,
            'NAME' => 'Иван',
            'EMAIL' => 'ivan@test.com',
            'GROUPS' => [1, 2],
            // ... другие поля
        ],
    ],
    'GROUPS' => [               // Список групп для фильтра
        ['ID' => 1, 'NAME' => 'Администраторы'],
        ['ID' => 2, 'NAME' => 'Пользователи'],
    ],
    'FILTER' => [               // Текущие параметры фильтра
        'search' => 'иван',
        'group_id' => 5,
        'active' => 'Y',
        'page' => 1,
    ],
    'PAGINATION' => [           // Данные пагинации
        'current_page' => 1,
        'total_pages' => 5,
        'total_items' => 95,
        'items_per_page' => 20,
        'has_prev' => false,
        'has_next' => true,
    ],
];
```

## Фильтрация

### Поиск

Ищет по полям: NAME, LAST_NAME, EMAIL, LOGIN:

```php
$arFilter[] = [
    'LOGIC' => 'OR',
    ['%NAME' => $search],
    ['%LAST_NAME' => $search],
    ['%EMAIL' => $search],
    ['%LOGIN' => $search],
];
```

### Фильтр по группе

Использует JOIN с таблицей групп:

```php
if (isset($arFilter['GROUPS.GROUP_ID'])) {
    $queryParams['runtime'] = [
        new \Bitrix\Main\Entity\ReferenceField(
            'GROUPS',
            '\Bitrix\Main\UserGroupTable',
            ['=this.ID' => 'ref.USER_ID']
        )
    ];
}
```

## Пагинация

Использует `offset` и `limit` для пагинации:

```php
$offset = ($page - 1) * $this->arParams['PAGE_SIZE'];

UserTable::getList([
    'limit' => $this->arParams['PAGE_SIZE'],
    'offset' => $offset,
    'count_total' => true,  // Получение общего количества
]);
```

## Доработки

### Добавление экспорта в Excel

```php
if ($_GET['export'] === 'excel') {
    // Экспорт всех пользователей в Excel
    $this->exportToExcel($arFilter);
    die();
}
```

### Множественные действия

```php
// Массовая активация/деактивация
if ($_POST['action'] === 'activate' && !empty($_POST['user_ids'])) {
    $user = new CUser;
    foreach ($_POST['user_ids'] as $userId) {
        $user->Update($userId, ['ACTIVE' => 'Y']);
    }
}
```

### Сохранение фильтра в сессии

```php
$_SESSION['user_filter'] = $arRequestFilter;
```

## Производительность

- Используется `count_total` только когда нужно (для пагинации)
- Select указывает только нужные поля
- Индексы на полях ACTIVE, DATE_REGISTER ускоряют выборку
- Кеширование списка групп

## Безопасность

- ✅ Экранирование вывода через `htmlspecialcharsbx()`
- ✅ Валидация параметров фильтра
- ✅ Использование ORM (защита от SQL-инъекций)
- ✅ Ограничение по правам доступа (при необходимости)

## Тестирование

1. Проверка пагинации на разных страницах
2. Фильтрация по группам
3. Поиск по различным полям
4. Работа с пустым результатом
5. Отображение неактивных пользователей
