# Задача 2: Автозаполнение символьного кода элементов

## Описание

Автоматическая генерация символьного кода элемента инфоблока из его названия с использованием транслитерации. Если код не указан при создании или обновлении элемента, он генерируется автоматически.

## Что делает решение

- Автоматически создаёт символьный код из названия при добавлении элемента
- Обновляет код при изменении названия (если код пустой)
- Использует транслитерацию для преобразования кириллицы в латиницу
- Проверяет уникальность кода в рамках инфоблока
- Добавляет числовой суффикс при совпадении кодов

## Структура файлов

```
auto-code-translit/
├── init.php          # Основной файл с обработчиками событий
└── README.md         # Эта инструкция
```

## Установка

### Шаг 1: Скопируйте файлы

Скопируйте папку `auto-code-translit` в `/local/examples/`:

```
/local/examples/auto-code-translit/
```

### Шаг 2: Подключите обработчики

Добавьте в файл `/local/php_interface/init.php`:

```php
<?php
// Подключаем автогенерацию кодов элементов
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/auto-code-translit/init.php';
```

## Использование

После установки коды будут генерироваться автоматически:

### Пример 1: Создание элемента без кода

```php
$el = new CIBlockElement;
$arFields = [
    'IBLOCK_ID' => 5,
    'NAME' => 'Новости компании',
    // CODE не указан
];
$elementId = $el->Add($arFields);

// Результат: CODE будет установлен как "novosti-kompanii"
```

### Пример 2: Создание элемента с явным кодом

```php
$arFields = [
    'IBLOCK_ID' => 5,
    'NAME' => 'Новости компании',
    'CODE' => 'company-news',  // Код указан явно
];
$elementId = $el->Add($arFields);

// Результат: CODE останется "company-news" (не изменится)
```

### Пример 3: Дубликаты кодов

```php
// Первый элемент
$arFields = [
    'IBLOCK_ID' => 5,
    'NAME' => 'Новости',
];
$el->Add($arFields); // CODE = "novosti"

// Второй элемент с таким же названием
$arFields = [
    'IBLOCK_ID' => 5,
    'NAME' => 'Новости',
];
$el->Add($arFields); // CODE = "novosti-1"

// Третий элемент
$arFields = [
    'IBLOCK_ID' => 5,
    'NAME' => 'Новости',
];
$el->Add($arFields); // CODE = "novosti-2"
```

## Правила транслитерации

### Основные параметры

```php
\Cutil::translit($string, 'ru', [
    'max_len' => 100,              // Максимальная длина кода
    'change_case' => 'L',          // Нижний регистр
    'replace_space' => '-',        // Пробелы → дефисы
    'replace_other' => '-',        // Спецсимволы → дефисы
    'delete_repeat_replace' => true, // Удалять повторяющиеся дефисы
]);
```

### Примеры преобразований

| Название | Код |
|----------|-----|
| Новости компании | `novosti-kompanii` |
| iPhone 15 Pro Max | `iphone-15-pro-max` |
| Акция "Скидка 50%!" | `aktsiya-skidka-50` |
| Товар №123 | `tovar-123` |
| Hello, World! | `hello-world` |

## Проверка уникальности

Обработчик автоматически проверяет уникальность кода:

1. Ищет элементы с таким же кодом в том же инфоблоке
2. При обновлении исключает текущий элемент из проверки
3. Если код занят — добавляет суффикс `-1`, `-2`, и т.д.
4. Защита от бесконечного цикла (максимум 1000 попыток)

## Какие события используются

### 1. OnBeforeIBlockElementAdd
**Модуль:** iblock  
**Когда срабатывает:** Перед добавлением элемента инфоблока  
**Параметры:** Массив полей элемента (по ссылке)

### 2. OnBeforeIBlockElementUpdate
**Модуль:** iblock  
**Когда срабатывает:** Перед обновлением элемента инфоблока  
**Параметры:** Массив полей элемента (по ссылке)

## Настройка

### Изменение максимальной длины кода

В методе `generateCode()`:

```php
$code = \Cutil::translit(
    $arFields['NAME'],
    'ru',
    [
        'max_len' => 200,  // Увеличить до 200 символов
        // ...
    ]
);
```

### Изменение разделителя

Использовать подчёркивание вместо дефиса:

```php
[
    'replace_space' => '_',   // Пробелы → подчёркивания
    'replace_other' => '_',   // Спецсимволы → подчёркивания
]
```

Результат: `novosti_kompanii`

### Верхний регистр

```php
[
    'change_case' => 'U',  // U = Upper (верхний регистр)
]
```

Результат: `NOVOSTI-KOMPANII`

### Отключить для определённых инфоблоков

В методе `generateCode()`:

```php
private static function generateCode(&$arFields)
{
    // Пропускаем инфоблоки 1 и 2
    if (in_array($arFields['IBLOCK_ID'], [1, 2])) {
        return;
    }
    
    // Остальной код...
}
```

## Проверка работы

### 1. Через административный интерфейс

1. Откройте любой инфоблок
2. Создайте новый элемент
3. Заполните только поле "Название"
4. Оставьте поле "Символьный код" пустым
5. Сохраните

Результат: код заполнится автоматически

### 2. Через API

```php
\CModule::IncludeModule('iblock');

$el = new CIBlockElement;

// Создаём элемент без кода
$arFields = [
    'IBLOCK_ID' => 5,
    'NAME' => 'Тестовый элемент',
];

$elementId = $el->Add($arFields);

if ($elementId) {
    // Проверяем, что код создался
    $element = CIBlockElement::GetByID($elementId)->Fetch();
    echo "Создан элемент с кодом: " . $element['CODE'];
    // Вывод: Создан элемент с кодом: testovyy-element
}
```

### 3. Проверка уникальности

```php
// Создаём несколько элементов с одинаковым названием
for ($i = 0; $i < 5; $i++) {
    $arFields = [
        'IBLOCK_ID' => 5,
        'NAME' => 'Дубликат',
    ];
    $elementId = $el->Add($arFields);
    
    $element = CIBlockElement::GetByID($elementId)->Fetch();
    echo $element['CODE'] . "\n";
}

// Вывод:
// dublikat
// dublikat-1
// dublikat-2
// dublikat-3
// dublikat-4
```

## Типичные проблемы

### Код не генерируется

**Причина 1:** Файл не подключён в init.php  
**Решение:** Проверьте путь в `require_once`

**Причина 2:** Поле CODE заполнено (даже пробелами)  
**Решение:** Убедитесь, что `$arFields['CODE']` пустое или `null`

### Генерируются некорректные коды

**Причина:** Неправильные параметры транслитерации  
**Решение:** Проверьте параметры в `\Cutil::translit()`

### Ошибка при сохранении элемента

**Причина:** Модуль iblock не подключён  
**Решение:** Убедитесь, что `\CModule::IncludeModule('iblock')` выполняется

### Коды дублируются

**Причина:** Проверка уникальности не работает  
**Решение:** Проверьте логику в методе `makeUniqueCode()`

## Расширение функциональности

### Логирование генерации кодов

Добавьте в метод `generateCode()`:

```php
private static function generateCode(&$arFields)
{
    if ((empty($arFields['CODE']) || trim($arFields['CODE']) === '')
        && !empty($arFields['NAME']))
    {
        $code = \Cutil::translit(/* ... */);
        $code = self::makeUniqueCode($code, $arFields);
        
        // Логируем
        AddMessage2Log(sprintf(
            'Auto-generated code: "%s" → "%s" (IBLOCK_ID: %d)',
            $arFields['NAME'],
            $code,
            $arFields['IBLOCK_ID']
        ), 'auto_code');
        
        $arFields['CODE'] = $code;
    }
}
```

### Использование ID в качестве суффикса

При обновлении можно использовать ID элемента:

```php
if (!empty($arFields['ID'])) {
    $code = $originalCode . '-' . $arFields['ID'];
}
```

### Разные правила для разных инфоблоков

```php
private static function generateCode(&$arFields)
{
    // Для каталога товаров используем короткие коды
    if ($arFields['IBLOCK_ID'] == 2) {
        $arFields['CODE'] = 'product-' . time();
        return;
    }
    
    // Для новостей — полная транслитерация
    if ($arFields['IBLOCK_ID'] == 5) {
        // Существующая логика
    }
}
```

## Обучающие моменты

### 1. Параметр по ссылке (&$arFields)

В событиях `OnBefore*` параметры передаются по ссылке, поэтому можно изменить данные перед сохранением:

```php
public static function onBeforeElementAdd(&$arFields)
{
    // Изменения сохранятся
    $arFields['CODE'] = 'new-code';
}
```

### 2. Транслитерация в Bitrix

Функция `\Cutil::translit()` — стандартный способ преобразования строк:
- Поддерживает множество языков
- Гибкие настройки
- Учитывает SEO-требования

### 3. Проверка уникальности

Важно исключать текущий элемент при обновлении:

```php
if (!empty($arFields['ID'])) {
    $filter['!ID'] = $arFields['ID'];
}
```

### 4. Защита от бесконечного цикла

При генерации уникального кода добавлена защита:

```php
if ($counter > 1000) {
    $code = $originalCode . '-' . time();
    break;
}
```

### 5. OnBefore vs OnAfter

Используется `OnBefore*`, потому что нужно изменить данные **до** сохранения в БД.

## Вопросы для самопроверки

1. Почему используется `&$arFields` (ссылка)?
2. В чём разница между `OnBeforeIBlockElementAdd` и `OnAfterIBlockElementAdd`?
3. Зачем проверять уникальность кода?
4. Что произойдёт, если убрать проверку `!empty($arFields['ID'])`?
5. Можно ли использовать этот обработчик для разделов инфоблоков?

## Полезные ссылки

- [Транслитерация CUtil::translit()](https://dev.1c-bitrix.ru/api_help/main/functions/translit/index.php)
- [События инфоблоков](https://dev.1c-bitrix.ru/api_help/iblock/events/index.php)
- [CIBlockElement::GetList()](https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/getlist.php)
