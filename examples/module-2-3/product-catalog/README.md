# Каталог товаров с фильтром

Компонент для вывода списка товаров из инфоблока с возможностью фильтрации по цене и разделам.

## Возможности

- ✅ Вывод товаров из инфоблока через D7 ORM
- ✅ Фильтрация по цене (от/до)
- ✅ Фильтрация по разделам
- ✅ Тегированное кеширование с автоматической очисткой
- ✅ Адаптивный дизайн
- ✅ Работа с свойствами элементов (цена, артикул)
- ✅ Оптимизированные запросы к базе данных

## Структура файлов

```
product-catalog/
├── .description.php       # Описание компонента для админки
├── .parameters.php        # Параметры компонента
├── class.php             # Класс компонента с логикой
├── component.php         # Точка входа
├── templates/
│   └── .default/
│       ├── template.php  # HTML-разметка
│       └── style.css     # Стили компонента
└── README.md             # Документация (этот файл)
```

## Установка

### 1. Копирование файлов

Скопируйте папку `product-catalog` в директорию компонентов вашего проекта:

```
/local/components/custom/product.catalog/
```

### 2. Подготовка инфоблока

Создайте инфоблок для товаров со следующими свойствами:

- **PRICE** (Число) — цена товара
- **ARTICLE** (Строка) — артикул товара

```php
// Пример создания инфоблока через код
use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\TypeTable;

// Создание типа инфоблока
$typeId = 'catalog';
TypeTable::add([
    'ID' => $typeId,
    'SECTIONS' => 'Y',
    'EDIT_FILE_BEFORE' => '',
    'EDIT_FILE_AFTER' => ''
]);

// Создание инфоблока
$iblock = IblockTable::add([
    'IBLOCK_TYPE_ID' => $typeId,
    'NAME' => 'Товары',
    'CODE' => 'products',
    'ACTIVE' => 'Y',
    'SORT' => 500
]);
```

## Использование

### Базовый пример

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:product.catalog",
    ".default",
    [
        "IBLOCK_ID" => 1,                    // ID инфоблока товаров
        "ELEMENT_COUNT" => 12,               // Количество товаров на странице
        "FILTER_PRICE_FROM" => "Y",          // Показывать фильтр "Цена от"
        "FILTER_PRICE_TO" => "Y",            // Показывать фильтр "Цена до"
        "FILTER_SECTION" => "Y",             // Показывать фильтр по разделам
        "CACHE_TYPE" => "A",                 // Автокеширование
        "CACHE_TIME" => 3600,                // Время кеширования (1 час)
        "CACHE_GROUPS" => "Y"                // Учитывать права доступа
    ],
    false
);
?>
```

### Пример с отключенными фильтрами

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:product.catalog",
    ".default",
    [
        "IBLOCK_ID" => 1,
        "ELEMENT_COUNT" => 20,
        "FILTER_PRICE_FROM" => "N",          // Отключен фильтр "от"
        "FILTER_PRICE_TO" => "N",            // Отключен фильтр "до"
        "FILTER_SECTION" => "N",             // Отключен фильтр по разделам
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => 7200
    ]
);
?>
```

## Параметры компонента

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `IBLOCK_ID` | int | - | ID инфоблока товаров (обязательный) |
| `ELEMENT_COUNT` | int | 12 | Количество товаров на странице |
| `FILTER_PRICE_FROM` | Y/N | Y | Показывать фильтр "Цена от" |
| `FILTER_PRICE_TO` | Y/N | Y | Показывать фильтр "Цена до" |
| `FILTER_SECTION` | Y/N | Y | Показывать фильтр по разделам |
| `CACHE_TIME` | int | 3600 | Время кеширования в секундах |
| `CACHE_GROUPS` | Y/N | Y | Учитывать права доступа при кешировании |

## Работа с кешем

Компонент использует тегированное кеширование для автоматической очистки при изменении данных:

```php
// Кеш автоматически очищается при изменении инфоблока
use Bitrix\Main\Application;

$taggedCache = Application::getInstance()->getTaggedCache();
$taggedCache->clearByTag('iblock_id_1'); // Очистка кеша для инфоблока #1
```

### Уникальный кеш для каждого набора фильтров

Компонент формирует отдельный кеш для каждой комбинации фильтров:

- Фильтр "Цена от 1000 до 5000" → свой кеш
- Фильтр "Раздел: Электроника" → свой кеш
- Без фильтров → свой кеш

## Ключевые особенности кода

### 1. Использование D7 ORM с фабрикой

```php
// Получение ORM-класса для работы с элементами инфоблока
$iblock = Iblock::wakeUp($this->arParams['IBLOCK_ID']);
$entityDataClass = $iblock->getEntityDataClass();

// Выборка элементов со свойствами
$elements = $entityDataClass::getList([
    'select' => [
        'ID',
        'NAME',
        'PRICE_VALUE' => 'PRICE.VALUE',      // Обращение к свойству
        'ARTICLE_VALUE' => 'ARTICLE.VALUE'
    ],
    'filter' => $filter
])->fetchAll();
```

### 2. Динамический фильтр из GET-параметров

```php
$filter = ['=IBLOCK_ID' => $iblockId, '=ACTIVE' => 'Y'];

// Добавляем условия только если параметры переданы
if ($priceFrom > 0) {
    $filter['>=PRICE.VALUE'] = $priceFrom;
}

if ($priceTo > 0) {
    $filter['<=PRICE.VALUE'] = $priceTo;
}
```

### 3. Тегированное кеширование

```php
$taggedCache = Application::getInstance()->getTaggedCache();
$taggedCache->startTagCache($cacheDir);

// ... получение данных ...

$taggedCache->registerTag('iblock_id_' . $iblockId);
$taggedCache->endTagCache();
```

## Расширение функционала

### Добавление нового фильтра

1. **Добавьте параметр в `.parameters.php`:**

```php
"FILTER_BRAND" => [
    "PARENT" => "BASE",
    "NAME" => "Показывать фильтр по брендам",
    "TYPE" => "CHECKBOX",
    "DEFAULT" => "Y"
]
```

2. **Обработайте в `getFilterFromRequest()`:**

```php
if ($this->arParams['FILTER_BRAND'] === 'Y') {
    $brandId = (int)$request->get('BRAND_ID');
    if ($brandId > 0) {
        $filter['=BRAND.VALUE'] = $brandId;
    }
}
```

3. **Добавьте в шаблон:**

```php
<select name="BRAND_ID">
    <option value="">Все бренды</option>
    <?php foreach ($arResult['BRANDS'] as $brand): ?>
    <option value="<?= $brand['ID'] ?>"><?= $brand['NAME'] ?></option>
    <?php endforeach; ?>
</select>
```

### Добавление пагинации

```php
use Bitrix\Main\UI\PageNavigation;

$nav = new PageNavigation('nav');
$nav->setPageSize($this->arParams['ELEMENT_COUNT']);
$nav->initFromUri();

$elements = $entityDataClass::getList([
    'select' => [...],
    'filter' => $filter,
    'count_total' => true,
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit()
]);

$nav->setRecordCount($elements->getCount());
$this->arResult['NAV'] = $nav;
```

## Производительность

### Оптимизации в компоненте

1. **Один запрос для элементов** — используется ORM D7 с join к свойствам
2. **Тегированный кеш** — автоматическая очистка при изменениях
3. **Уникальный кеш для фильтров** — не пересчитываем одинаковые выборки
4. **Лимит элементов** — не загружаем лишние данные

### Рекомендации

- Создайте индексы на поля свойств PRICE и ARTICLE
- Используйте CDN для изображений товаров
- Настройте композитный кеш для статических страниц

## Отладка

### Проверка работы фильтра

Добавьте в `class.php` перед выборкой:

```php
// Отладка: вывод фильтра
echo '<pre>';
print_r($filter);
echo '</pre>';
```

### Проверка кеша

```php
// Временно отключите кеш
$this->arParams['CACHE_TIME'] = 0;
```

### Проверка SQL-запросов

```php
// В конфиге включите лог запросов
$DB->ShowSqlStat = true;
```

## Частые ошибки

### ❌ Модуль iblock не подключен

```
Fatal error: Class 'Bitrix\Iblock\Iblock' not found
```

**Решение:** Добавьте в начало `class.php`:
```php
use Bitrix\Main\Loader;
Loader::includeModule('iblock');
```

### ❌ Не работает фильтр по свойствам

**Проблема:** Используете старое API `CIBlockElement::GetList()`

**Решение:** Используйте D7 ORM с фабрикой:
```php
$iblock = Iblock::wakeUp($iblockId);
$class = $iblock->getEntityDataClass();
```

### ❌ Кеш не очищается при изменении товаров

**Проблема:** Не зарегистрирован тег кеша

**Решение:** Добавьте тегированный кеш:
```php
$taggedCache->registerTag('iblock_id_' . $iblockId);
```

## Примеры использования на сайте

### Каталог на главной странице

```php
// /index.php
$APPLICATION->IncludeComponent(
    "custom:product.catalog",
    ".default",
    [
        "IBLOCK_ID" => 2,
        "ELEMENT_COUNT" => 8,
        "FILTER_PRICE_FROM" => "N",
        "FILTER_PRICE_TO" => "N",
        "FILTER_SECTION" => "Y",
        "CACHE_TIME" => 86400  // 24 часа для главной
    ]
);
```

### Каталог с полным фильтром

```php
// /catalog/index.php
$APPLICATION->IncludeComponent(
    "custom:product.catalog",
    ".default",
    [
        "IBLOCK_ID" => 2,
        "ELEMENT_COUNT" => 24,
        "FILTER_PRICE_FROM" => "Y",
        "FILTER_PRICE_TO" => "Y",
        "FILTER_SECTION" => "Y",
        "CACHE_TIME" => 3600
    ]
);
```

## Что изучается в этом примере

1. ✅ Работа с инфоблоками через D7 ORM
2. ✅ Использование фабрики `Iblock::wakeUp()`
3. ✅ Получение элементов со свойствами через join
4. ✅ Динамическое построение фильтров
5. ✅ Тегированное кеширование
6. ✅ Работа с GET-параметрами через Request
7. ✅ Обработка изображений через CFile
8. ✅ Формирование URL детальных страниц

## Дополнительные материалы

- [Документация по инфоблокам D7](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=11770)
- [Тегированный кеш](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5748)
- [ORM для инфоблоков](https://dev.1c-bitrix.ru/community/blogs/vws/iblock-orm-1.php)

---

**Автор:** Учебный курс подготовки Bitrix-разработчика  
**Модуль:** 2.3 — Инфоблоки и их API  
**Сложность:** Средняя
