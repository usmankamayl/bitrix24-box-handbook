# Связанные товары

Компонент для вывода связанных/похожих товаров на детальной странице товара через свойство-привязку к элементам инфоблока.

## Возможности

- ✅ Вывод товаров из свойства-привязки к элементам
- ✅ Автоматический фолбек на похожие товары из раздела
- ✅ Работа через D7 ORM с фабрикой инфоблоков
- ✅ Тегированное кеширование
- ✅ Адаптивный дизайн карточек
- ✅ Оптимизированные запросы
- ✅ Плейсхолдер для товаров без изображений

## Структура файлов

```
related-products/
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

Скопируйте папку `related-products` в директорию компонентов:

```
/local/components/custom/related.products/
```

### 2. Создание свойства в инфоблоке

Добавьте в инфоблок товаров свойство типа "Привязка к элементам":

- **Код:** RELATED_PRODUCTS
- **Тип:** Привязка к элементам (E)
- **Множественное:** Да
- **Привязка:** К элементам того же инфоблока

```php
// Пример создания свойства через код
use Bitrix\Iblock\PropertyTable;

$property = PropertyTable::add([
    'IBLOCK_ID' => 1,
    'NAME' => 'Связанные товары',
    'CODE' => 'RELATED_PRODUCTS',
    'PROPERTY_TYPE' => 'E',  // Привязка к элементам
    'LINK_IBLOCK_ID' => 1,   // К тому же инфоблоку
    'MULTIPLE' => 'Y',       // Множественное
    'ACTIVE' => 'Y'
]);
```

## Использование

### Базовый пример

```php
<?php
// На детальной странице товара
$APPLICATION->IncludeComponent(
    "custom:related.products",
    ".default",
    [
        "IBLOCK_ID" => 1,                      // ID инфоблока товаров
        "ELEMENT_ID" => $arResult['ID'],       // ID текущего товара
        "PROPERTY_CODE" => "RELATED_PRODUCTS", // Код свойства со связями
        "ELEMENT_COUNT" => 4,                  // Количество товаров
        "SHOW_PRICE" => "Y",                   // Показывать цену
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => 3600
    ],
    false
);
?>
```

### С большим количеством товаров

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:related.products",
    ".default",
    [
        "IBLOCK_ID" => 1,
        "ELEMENT_ID" => $arResult['ID'],
        "PROPERTY_CODE" => "RELATED_PRODUCTS",
        "ELEMENT_COUNT" => 8,                  // 8 товаров
        "SHOW_PRICE" => "Y",
        "CACHE_TIME" => 7200
    ]
);
?>
```

### Без показа цены

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:related.products",
    ".default",
    [
        "IBLOCK_ID" => 1,
        "ELEMENT_ID" => $arResult['ID'],
        "PROPERTY_CODE" => "RELATED_PRODUCTS",
        "ELEMENT_COUNT" => 6,
        "SHOW_PRICE" => "N",                   // Цена скрыта
        "CACHE_TIME" => 3600
    ]
);
?>
```

### С другим свойством

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:related.products",
    ".default",
    [
        "IBLOCK_ID" => 1,
        "ELEMENT_ID" => $arResult['ID'],
        "PROPERTY_CODE" => "ACCESSORIES",      // Аксессуары
        "ELEMENT_COUNT" => 4,
        "SHOW_PRICE" => "Y",
        "CACHE_TIME" => 3600
    ]
);
?>
```

## Параметры компонента

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `IBLOCK_ID` | int | - | ID инфоблока товаров (обязательный) |
| `ELEMENT_ID` | int | `$_REQUEST['ELEMENT_ID']` | ID текущего товара |
| `PROPERTY_CODE` | string | RELATED_PRODUCTS | Код свойства со связанными товарами |
| `ELEMENT_COUNT` | int | 4 | Количество выводимых товаров |
| `SHOW_PRICE` | Y/N | Y | Показывать цену товаров |
| `CACHE_TIME` | int | 3600 | Время кеширования в секундах |

## Логика работы

### 1. Связанные товары указаны

Если в свойстве `RELATED_PRODUCTS` текущего товара есть значения:
- Выводятся товары из этого свойства
- Заголовок: **"С этим товаром покупают"**
- `$arResult['TYPE']` = `'RELATED'`

### 2. Связанные товары НЕ указаны (фолбек)

Если свойство пустое, компонент автоматически показывает похожие товары:
- Выбираются товары из того же раздела
- Исключается текущий товар
- Сортировка случайная
- Заголовок: **"Похожие товары"**
- `$arResult['TYPE']` = `'SIMILAR'`

## Ключевые особенности кода

### 1. Получение связанных товаров через D7 ORM

```php
private function getRelatedIds()
{
    $iblock = Iblock::wakeUp($this->arParams['IBLOCK_ID']);
    $entityDataClass = $iblock->getEntityDataClass();

    // Получаем текущий элемент со свойством
    $element = $entityDataClass::getList([
        'select' => [
            'ID',
            'RELATED_IDS' => 'RELATED_PRODUCTS.VALUE'  // Обращение к свойству
        ],
        'filter' => ['=ID' => $this->arParams['ELEMENT_ID']]
    ])->fetch();

    return $element['RELATED_IDS'] ?? [];
}
```

### 2. Множественное свойство-привязка

```php
// Если свойство одиночное - приводим к массиву
$relatedIds = is_array($element['RELATED_IDS'])
    ? $element['RELATED_IDS']
    : [$element['RELATED_IDS']];
```

### 3. Получение похожих товаров (фолбек)

```php
private function getSimilarProducts()
{
    // Получаем раздел текущего товара
    $currentElement = $entityDataClass::getList([
        'select' => ['ID', 'IBLOCK_SECTION_ID'],
        'filter' => ['=ID' => $this->arParams['ELEMENT_ID']]
    ])->fetch();

    // Товары из того же раздела, кроме текущего
    $products = $entityDataClass::getList([
        'filter' => [
            '=IBLOCK_SECTION_ID' => $currentElement['IBLOCK_SECTION_ID'],
            '!=ID' => $this->arParams['ELEMENT_ID'],
            '=ACTIVE' => 'Y'
        ],
        'order' => ['RAND' => 'ASC'],  // Случайная сортировка
        'limit' => $this->arParams['ELEMENT_COUNT']
    ])->fetchAll();
}
```

### 4. Тегированное кеширование

```php
// Уникальный кеш для каждого товара
$cacheId = md5(serialize([
    $this->arParams['IBLOCK_ID'],
    $this->arParams['ELEMENT_ID'],
    $this->arParams['PROPERTY_CODE'],
    $this->arParams['ELEMENT_COUNT']
]));

$taggedCache = Application::getInstance()->getTaggedCache();
$taggedCache->startTagCache($cacheDir);

// Теги для очистки
$taggedCache->registerTag('iblock_id_' . $iblockId);
$taggedCache->registerTag('iblock_element_' . $elementId);
$taggedCache->endTagCache();
```

### 5. Обработка изображений с плейсхолдером

```php
<?php if (!empty($item['PREVIEW_PICTURE_SRC'])): ?>
    <img src="<?= $item['PREVIEW_PICTURE_SRC'] ?>" alt="...">
<?php else: ?>
    <div class="related-product-card__image--placeholder">
        <svg width="100" height="100">
            <rect width="100" height="100" fill="#f0f0f0"/>
            <text x="50" y="50" text-anchor="middle" fill="#999">
                Нет фото
            </text>
        </svg>
    </div>
<?php endif; ?>
```

## Настройка связей между товарами

### Через админку

1. Откройте элемент инфоблока
2. Найдите свойство "Связанные товары"
3. Выберите товары для связи
4. Сохраните

### Программно при создании товара

```php
$el = new CIBlockElement;

$fields = [
    'IBLOCK_ID' => 1,
    'NAME' => 'Ноутбук ASUS',
    'PROPERTY_VALUES' => [
        'RELATED_PRODUCTS' => [10, 15, 20]  // ID связанных товаров
    ]
];

$el->Add($fields);
```

### Программно обновление связей

```php
CIBlockElement::SetPropertyValuesEx(
    123,  // ID элемента
    1,    // ID инфоблока
    [
        'RELATED_PRODUCTS' => [10, 15, 20, 25]
    ]
);
```

## Расширение функционала

### Добавление количества отзывов

1. **Добавьте в select:**

```php
'select' => [
    'ID',
    'NAME',
    'REVIEWS_COUNT' => 'REVIEWS_COUNT.VALUE'
]
```

2. **В шаблоне:**

```php
<?php if (!empty($item['REVIEWS_COUNT'])): ?>
<div class="product-reviews">
    ⭐ <?= $item['REVIEWS_COUNT'] ?> отзывов
</div>
<?php endif; ?>
```

### Автоматическая генерация связей

Создайте агент для автоматической привязки товаров из одного раздела:

```php
function autoGenerateRelatedProducts()
{
    $iblock = Iblock::wakeUp(1);
    $class = $iblock->getEntityDataClass();

    // Получаем товары без связанных
    $elements = $class::getList([
        'select' => ['ID', 'IBLOCK_SECTION_ID', 'RELATED_IDS' => 'RELATED_PRODUCTS.VALUE'],
        'filter' => ['=ACTIVE' => 'Y']
    ]);

    while ($element = $elements->fetch()) {
        if (!empty($element['RELATED_IDS'])) {
            continue; // Уже есть связи
        }

        // Получаем 4 случайных товара из того же раздела
        $similar = $class::getList([
            'select' => ['ID'],
            'filter' => [
                '=IBLOCK_SECTION_ID' => $element['IBLOCK_SECTION_ID'],
                '!=ID' => $element['ID']
            ],
            'order' => ['RAND' => 'ASC'],
            'limit' => 4
        ])->fetchAll();

        $relatedIds = array_column($similar, 'ID');

        // Устанавливаем связи
        CIBlockElement::SetPropertyValuesEx(
            $element['ID'],
            1,
            ['RELATED_PRODUCTS' => $relatedIds]
        );
    }

    return "autoGenerateRelatedProducts();";
}
```

### Фильтрация по наличию на складе

```php
$elements = $entityDataClass::getList([
    'select' => [
        'ID',
        'NAME',
        'IN_STOCK' => 'IN_STOCK.VALUE'
    ],
    'filter' => [
        '@ID' => $relatedIds,
        '=ACTIVE' => 'Y',
        '=IN_STOCK.VALUE' => 'Y'  // Только в наличии
    ]
]);
```

## Оптимизация

### 1. Уменьшение количества запросов

Компонент делает всего **2 запроса**:
1. Получение ID связанных товаров
2. Получение данных связанных товаров

Альтернативный подход (один запрос, но сложнее):

```php
$element = $entityDataClass::getList([
    'select' => [
        'ID',
        'NAME',
        'RELATED_1_ID' => 'RELATED_PRODUCTS.ITEM.ID',
        'RELATED_1_NAME' => 'RELATED_PRODUCTS.ITEM.NAME',
        'RELATED_1_PRICE' => 'RELATED_PRODUCTS.ITEM.PRICE.VALUE'
    ],
    'filter' => ['=ID' => $elementId]
])->fetch();
```

### 2. Предзагрузка изображений

```html
<link rel="preload" as="image" href="<?= $item['PREVIEW_PICTURE_SRC'] ?>">
```

### 3. Lazy loading для изображений

```html
<img src="..." loading="lazy" decoding="async">
```

## Частые ошибки

### ❌ Свойство не найдено

**Проблема:** `Property RELATED_PRODUCTS not found`

**Решение:** Проверьте код свойства в параметрах компонента:

```php
'PROPERTY_CODE' => 'RELATED_PRODUCTS'  // Точное совпадение с кодом в админке
```

### ❌ Не работает фолбек на похожие товары

**Проблема:** Не выводятся похожие товары, если связанные не указаны

**Решение:** Проверьте, что товары находятся в разделах:

```php
$currentElement['IBLOCK_SECTION_ID']  // Должно быть > 0
```

### ❌ Связанные товары дублируются

**Проблема:** Один товар показывается несколько раз

**Решение:** Используйте `array_unique()`:

```php
$relatedIds = array_unique(array_filter($relatedIds));
```

### ❌ Кеш не сбрасывается при изменении связей

**Проблема:** Изменили связанные товары, но компонент показывает старые

**Решение:** Очистите тегированный кеш:

```php
use Bitrix\Main\Application;

$taggedCache = Application::getInstance()->getTaggedCache();
$taggedCache->clearByTag('iblock_element_' . $elementId);
```

## Примеры использования

### На детальной странице товара

```php
<?php
// /catalog/detail.php

$APPLICATION->IncludeComponent(
    "bitrix:catalog.element",
    ".default",
    [
        "IBLOCK_ID" => 2,
        "ELEMENT_ID" => $_REQUEST['ELEMENT_ID'],
        // ... другие параметры
    ]
);

// Связанные товары под описанием
$APPLICATION->IncludeComponent(
    "custom:related.products",
    ".default",
    [
        "IBLOCK_ID" => 2,
        "ELEMENT_ID" => $_REQUEST['ELEMENT_ID'],
        "PROPERTY_CODE" => "RELATED_PRODUCTS",
        "ELEMENT_COUNT" => 4,
        "SHOW_PRICE" => "Y",
        "CACHE_TIME" => 3600
    ]
);
?>
```

### С разными блоками

```php
<!-- Связанные товары -->
<?php $APPLICATION->IncludeComponent(
    "custom:related.products",
    ".default",
    [
        "IBLOCK_ID" => 2,
        "ELEMENT_ID" => $arResult['ID'],
        "PROPERTY_CODE" => "RELATED_PRODUCTS",
        "ELEMENT_COUNT" => 4,
        "SHOW_PRICE" => "Y"
    ]
); ?>

<!-- Аксессуары -->
<?php $APPLICATION->IncludeComponent(
    "custom:related.products",
    ".default",
    [
        "IBLOCK_ID" => 2,
        "ELEMENT_ID" => $arResult['ID'],
        "PROPERTY_CODE" => "ACCESSORIES",
        "ELEMENT_COUNT" => 6,
        "SHOW_PRICE" => "Y"
    ]
); ?>
```

## Что изучается в этом примере

1. ✅ Работа со свойствами-привязками к элементам
2. ✅ Получение связанных элементов через D7 ORM
3. ✅ Фолбек-логика (запасной вариант)
4. ✅ Случайная сортировка товаров
5. ✅ Работа с множественными свойствами
6. ✅ Плейсхолдеры для отсутствующих изображений
7. ✅ Адаптивная верстка grid
8. ✅ Тегированное кеширование с учетом элемента

## Полезные ссылки

- [Свойства инфоблоков](https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/setpropertyvaluesex.php)
- [Привязка к элементам](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2116)
- [D7 ORM для инфоблоков](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=11770)

---

**Автор:** Учебный курс подготовки Bitrix-разработчика  
**Модуль:** 2.3 — Инфоблоки и их API  
**Сложность:** Средняя