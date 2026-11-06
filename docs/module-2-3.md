# Модуль 2.3: Инфоблоки и их API

## Теория

### Что такое инфоблоки?

**Инфоблоки (информационные блоки)** — это универсальная система хранения и управления структурированным контентом в Bitrix Framework. Они используются для:
- Новостей, статей, каталогов товаров
- Фотогалерей, файловых архивов
- FAQ, отзывов, вакансий
- Любого структурированного контента

### Структура инфоблоков

```
Тип инфоблока (IBlockType)
└── Инфоблок (IBlock)
    └── Разделы (Section) - опционально
        └── Элементы (Element)
            ├── Свойства (Property)
            └── Файлы, изображения
```

### Основные понятия

#### 1. Тип инфоблока (IBlockType)
Группировка инфоблоков по назначению (новости, каталог, контент и т.д.)

```php
use Bitrix\Iblock\TypeTable;

// Получить все типы
$types = TypeTable::getList([
    'select' => ['ID', 'SECTIONS'],
    'filter' => ['=SECTIONS' => 'Y'] // Только с разделами
])->fetchAll();
```

#### 2. Инфоблок (IBlock)
Контейнер для однотипных элементов

```php
use Bitrix\Iblock\IblockTable;

// Получить инфоблок по коду
$iblock = IblockTable::getList([
    'select' => ['ID', 'NAME', 'CODE', 'IBLOCK_TYPE_ID'],
    'filter' => [
        '=CODE' => 'news',
        '=ACTIVE' => 'Y'
    ]
])->fetch();
```

#### 3. Раздел (Section)
Иерархическая структура для группировки элементов

```php
use Bitrix\Iblock\SectionTable;

// Получить разделы инфоблока
$sections = SectionTable::getList([
    'select' => ['ID', 'NAME', 'CODE', 'DEPTH_LEVEL'],
    'filter' => [
        '=IBLOCK_ID' => 1,
        '=ACTIVE' => 'Y'
    ],
    'order' => ['LEFT_MARGIN' => 'ASC'] // Сортировка по дереву
])->fetchAll();
```

#### 4. Элемент (Element)
Единица контента с набором свойств

```php
use Bitrix\Iblock\ElementTable;

// Получить элементы
$elements = ElementTable::getList([
    'select' => ['ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'PREVIEW_PICTURE'],
    'filter' => [
        '=IBLOCK_ID' => 1,
        '=ACTIVE' => 'Y'
    ],
    'order' => ['SORT' => 'ASC', 'ID' => 'DESC']
])->fetchAll();
```

#### 5. Свойство (Property)
Дополнительные поля элементов (строки, числа, списки, файлы, привязки)

```php
use Bitrix\Iblock\PropertyTable;

// Получить свойства инфоблока
$properties = PropertyTable::getList([
    'select' => ['ID', 'NAME', 'CODE', 'PROPERTY_TYPE'],
    'filter' => ['=IBLOCK_ID' => 1]
])->fetchAll();
```

### Типы свойств инфоблоков

| Тип | Код | Описание | Пример использования |
|-----|-----|----------|----------------------|
| Строка | `S` | Текстовое поле | Артикул, заголовок |
| Число | `N` | Числовое значение | Цена, вес, рейтинг |
| Список | `L` | Выпадающий список | Статус, категория |
| Файл | `F` | Загрузка файлов | Документы, изображения |
| Привязка к разделам | `G` | Связь с разделами | Категории товара |
| Привязка к элементам | `E` | Связь с элементами | Похожие товары |
| HTML/текст | `S` | Текстовый редактор | Описание, контент |
| Дата/Время | `S` | Дата | Срок годности |

---

## API для работы с инфоблоками

### 1. Получение списка элементов (D7)

```php
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

$elements = ElementTable::getList([
    'select' => ['ID', 'NAME', 'CODE', 'IBLOCK_ID', 'ACTIVE'],
    'filter' => [
        '=IBLOCK_ID' => 1,
        '=ACTIVE' => 'Y'
    ],
    'order' => ['SORT' => 'ASC'],
    'limit' => 10
])->fetchAll();
```

### 2. Получение элемента со свойствами

```php
use Bitrix\Iblock\Iblock;

// Современный подход через фабрику
$iblock = Iblock::wakeUp(1); // ID инфоблока
$entityDataClass = $iblock->getEntityDataClass();

$element = $entityDataClass::getList([
    'select' => [
        'ID', 
        'NAME', 
        'PROPERTY_PRICE' => 'PRICE.VALUE', // свойство PRICE
        'PROPERTY_ARTICLE' => 'ARTICLE.VALUE'
    ],
    'filter' => ['=ID' => 123]
])->fetch();
```

### 3. Добавление элемента (CIBlockElement)

```php
use CIBlockElement;

$el = new CIBlockElement;

$arFields = [
    'IBLOCK_ID' => 1,
    'NAME' => 'Новый товар',
    'CODE' => 'new-product',
    'ACTIVE' => 'Y',
    'DETAIL_TEXT' => 'Описание товара',
    'PROPERTY_VALUES' => [
        'PRICE' => 1000,
        'ARTICLE' => 'ART-001',
        'PHOTOS' => [
            ['VALUE' => CFile::MakeFileArray('/path/to/photo.jpg')]
        ]
    ]
];

$elementId = $el->Add($arFields);

if (!$elementId) {
    echo 'Ошибка: ' . $el->LAST_ERROR;
}
```

### 4. Обновление элемента

```php
$el = new CIBlockElement;

$arFields = [
    'NAME' => 'Обновленное название',
    'PROPERTY_VALUES' => [
        'PRICE' => 1500
    ]
];

$success = $el->Update(123, $arFields);

if (!$success) {
    echo 'Ошибка: ' . $el->LAST_ERROR;
}
```

### 5. Удаление элемента

```php
// Обычное удаление
CIBlockElement::Delete(123);

// С удалением всех связанных данных
global $DB;
$DB->StartTransaction();
try {
    if (CIBlockElement::Delete(123)) {
        $DB->Commit();
    } else {
        $DB->Rollback();
    }
} catch (Exception $e) {
    $DB->Rollback();
}
```

### 6. Работа с разделами

```php
use Bitrix\Iblock\SectionTable;

// Получить разделы с подсчетом элементов
$sections = SectionTable::getList([
    'select' => [
        'ID', 
        'NAME', 
        'CODE',
        'DEPTH_LEVEL',
        'CNT' => 'ELEMENT_CNT'
    ],
    'filter' => [
        '=IBLOCK_ID' => 1,
        '=ACTIVE' => 'Y'
    ],
    'runtime' => [
        new \Bitrix\Main\Entity\ExpressionField(
            'ELEMENT_CNT',
            'COUNT(%s)',
            'IBLOCK_ELEMENT.ID'
        )
    ]
])->fetchAll();
```

### 7. Добавление раздела

```php
use CIBlockSection;

$bs = new CIBlockSection;

$arFields = [
    'IBLOCK_ID' => 1,
    'NAME' => 'Новая категория',
    'CODE' => 'new-category',
    'ACTIVE' => 'Y',
    'SORT' => 500,
    'IBLOCK_SECTION_ID' => 0 // ID родительского раздела, 0 = корень
];

$sectionId = $bs->Add($arFields);

if (!$sectionId) {
    echo 'Ошибка: ' . $bs->LAST_ERROR;
}
```

### 8. Массовые операции (более эффективно)

```php
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

// Массовое обновление через SQL
ElementTable::updateMulti(
    [123, 124, 125], // ID элементов
    ['ACTIVE' => 'N'] // Новые значения
);
```

---

## Кеширование результатов

### Пример с тегированным кешем

```php
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Application;

$cache = Cache::createInstance();
$cacheId = 'iblock_elements_1';
$cacheDir = '/iblock/elements/';
$cacheTime = 3600; // 1 час

if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
    $elements = $cache->getVars();
} elseif ($cache->startDataCache()) {
    $taggedCache = Application::getInstance()->getTaggedCache();
    $taggedCache->startTagCache($cacheDir);
    
    $elements = ElementTable::getList([
        'filter' => ['=IBLOCK_ID' => 1]
    ])->fetchAll();
    
    // Тег для очистки кеша при изменении инфоблока
    $taggedCache->registerTag('iblock_id_1');
    $taggedCache->endTagCache();
    
    $cache->endDataCache($elements);
}
```

### Сброс кеша при изменении

```php
use Bitrix\Main\Application;

// При изменении элемента
$taggedCache = Application::getInstance()->getTaggedCache();
$taggedCache->clearByTag('iblock_id_1');
```

---

## Вопросы для собеседования

### 1. Что такое инфоблоки и для чего они используются?

**Ответ:**
Инфоблоки — универсальная система хранения структурированного контента в Bitrix. Используются для новостей, каталогов, галерей, любого контента с повторяющейся структурой. Состоят из типа инфоблока, самого инфоблока, разделов (опционально) и элементов со свойствами.

### 2. Какие основные типы свойств элементов инфоблоков существуют?

**Ответ:**
- `S` — строка/текст
- `N` — число
- `L` — список (выпадающий)
- `F` — файл
- `E` — привязка к элементам
- `G` — привязка к разделам

Каждый тип имеет свои особенности хранения и получения значений.

### 3. В чем разница между CIBlockElement и ElementTable?

**Ответ:**
- **CIBlockElement** — старое API (до D7), использует классические методы `Add()`, `Update()`, `GetList()`. Работает с массивами, автоматически вызывает события, поддерживает workflow.
- **ElementTable** — ORM D7, работает через QueryBuilder, возвращает объекты Entity, более производительный, но не вызывает события автоматически.

Для добавления/изменения лучше использовать `CIBlockElement` (события, проверки), для чтения — `ElementTable` (скорость).

### 4. Как правильно получить элемент со всеми его свойствами через D7?

**Ответ:**
```php
use Bitrix\Iblock\Iblock;

$iblock = Iblock::wakeUp($iblockId);
$entityDataClass = $iblock->getEntityDataClass();

$element = $entityDataClass::getList([
    'select' => [
        'ID', 
        'NAME',
        'PRICE_VALUE' => 'PRICE.VALUE',
        'ARTICLE_VALUE' => 'ARTICLE.VALUE'
    ],
    'filter' => ['=ID' => $elementId]
])->fetch();
```

Через фабрику `Iblock::wakeUp()` получаем ORM-класс для работы с элементами конкретного инфоблока.

### 5. Как работает кеширование инфоблоков?

**Ответ:**
Три уровня кеширования:
1. **Управляемый кеш** — автоматический, очищается при изменении данных
2. **Тегированный кеш** — с метками `iblock_id_X`, можно сбрасывать группами
3. **Ручной кеш** — через `Cache::createInstance()`

Рекомендуется тегированный кеш с автоматической очисткой по событиям.

### 6. Что такое PROPERTY_VALUES и как с ним работать?

**Ответ:**
`PROPERTY_VALUES` — массив значений свойств при добавлении/обновлении элемента:

```php
'PROPERTY_VALUES' => [
    'PRICE' => 1000,                    // Простое свойство
    'COLORS' => [1, 5, 7],              // Множественное (список)
    'PHOTOS' => [                       // Файлы
        ['VALUE' => CFile::MakeFileArray('photo.jpg')],
        ['VALUE' => CFile::MakeFileArray('photo2.jpg')]
    ],
    'RELATED' => [10, 15, 20]          // Привязка к элементам
]
```

### 7. Как правильно удалять элементы с учетом транзакций?

**Ответ:**
```php
global $DB;
$DB->StartTransaction();
try {
    if (CIBlockElement::Delete($elementId)) {
        $DB->Commit();
    } else {
        $DB->Rollback();
        throw new Exception('Ошибка удаления');
    }
} catch (Exception $e) {
    $DB->Rollback();
    // Логирование ошибки
}
```

Транзакции гарантируют целостность данных при удалении связанных записей.

### 8. Чем отличается GetList от getList в новом D7 API?

**Ответ:**
- **GetList()** (старый API) — возвращает CIBlockResult, работает с `->Fetch()`, поддерживает события, менее производительный
- **getList()** (D7) — возвращает Result, работает с `->fetch()`, нет автоматических событий, более быстрый, поддерживает QueryBuilder

### 9. Как получить элементы с фильтром по свойствам через D7?

**Ответ:**
```php
$iblock = Iblock::wakeUp(1);
$class = $iblock->getEntityDataClass();

$elements = $class::getList([
    'select' => ['ID', 'NAME', 'PRICE_VALUE' => 'PRICE.VALUE'],
    'filter' => [
        '>=PRICE.VALUE' => 1000,
        '<=PRICE.VALUE' => 5000,
        '=ACTIVE.VALUE' => 'Y'
    ]
])->fetchAll();
```

Обращение к свойствам через точку: `PROPERTY_CODE.VALUE`.

### 10. Какие события инфоблоков существуют и когда они вызываются?

**Ответ:**
Основные события:
- `OnBeforeIBlockElementAdd` / `OnAfterIBlockElementAdd` — до/после добавления
- `OnBeforeIBlockElementUpdate` / `OnAfterIBlockElementUpdate` — до/после изменения
- `OnBeforeIBlockElementDelete` / `OnAfterIBlockElementDelete` — до/после удаления
- `OnIBlockElementSetPropertyValues` / `OnIBlockElementSetPropertyValuesEx` — установка свойств

События `OnBefore*` позволяют изменить данные или отменить операцию.

---

## Частые ошибки

### ❌ Ошибка 1: Забыли подключить модуль

```php
// Неправильно
$elements = ElementTable::getList(...);

// Правильно
Loader::includeModule('iblock');
$elements = ElementTable::getList(...);
```

### ❌ Ошибка 2: Не проверили результат операции

```php
// Неправильно
$el = new CIBlockElement;
$el->Add($arFields);

// Правильно
$el = new CIBlockElement;
if (!$elementId = $el->Add($arFields)) {
    throw new Exception($el->LAST_ERROR);
}
```

### ❌ Ошибка 3: Неэффективная выборка в цикле

```php
// Неправильно - N+1 запросов
foreach ($elementIds as $id) {
    $element = ElementTable::getByPrimary($id)->fetch();
}

// Правильно - один запрос
$elements = ElementTable::getList([
    'filter' => ['@ID' => $elementIds]
])->fetchAll();
```

### ❌ Ошибка 4: Не используют кеширование

```php
// Неправильно - запрос к БД на каждый хит
$elements = ElementTable::getList([...])->fetchAll();

// Правильно - с кешированием
$cache = Cache::createInstance();
if ($cache->initCache(3600, 'elements', '/iblock/')) {
    $elements = $cache->getVars();
} else {
    // Получение данных
    $cache->endDataCache($elements);
}
```

---

## Практические задачи

Для закрепления материала выполните три задачи:

1. **Каталог товаров с фильтром** — компонент вывода товаров с фильтрацией по свойствам (цена, категория, бренд)
2. **Импорт элементов из CSV** — обработчик загрузки товаров из файла с валидацией
3. **Связанные товары** — компонент "Похожие товары" с привязкой через свойства

**Решения задач находятся в `examples/module-2-3/`**

---

## Полезные ссылки

- [Документация по инфоблокам](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2028)
- [ORM для инфоблоков](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=11770)
- [API CIBlockElement](https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/index.php)
- [Работа со свойствами](https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/setpropertyvalues.php)

---

## Вопросы для самопроверки

1. Какая структура данных у инфоблоков?
2. Как получить элемент со свойствами через D7?
3. Когда использовать CIBlockElement, а когда ElementTable?
4. Как правильно добавить элемент с файлом в свойство?
5. Как работает кеширование инфоблоков?
6. Какие события инфоблоков вы знаете?
7. Как фильтровать элементы по значениям свойств?
8. Чем отличается работа с множественными и одиночными свойствами?
9. Как сделать массовое обновление элементов?
10. Как правильно обрабатывать ошибки при работе с инфоблоками?

