# Модуль 1.2: Компоненты «1С-Битрикс» — устройство, кеширование, комплексные компоненты

От устройства компонента до кеширования и комплексных компонентов — с рабочими примерами.

Мы разберём и напишем:

1. Простой классовый компонент списка новостей.
2. Его настройки и шаблон.
3. Корректное кеширование с зависимостью от инфоблока.
4. Комплексный компонент с ЧПУ.
5. Обработку ошибок и вопросы безопасности.
6. Типовые вопросы на собеседовании.

**О версиях:** материал опирается на устойчивые механизмы компонентов «1С-Битрикс: Управление сайтом». Проверить изменения конкретной сборки на ноябрь 2026 года я не могу. Сигнатуры и поведение отдельных API при необходимости сверяй с документацией установленной версии.

**Откуда точные утверждения про механику.** Всё, что ниже сказано о порядке вызовов, составе ключа кеша и загрузке класса, проверено по исходникам ядра `main/classes/general/component.php`, `main/classes/general/component_template.php`, `main/classes/general/component_engine.php`, `iblock/classes/general/iblockresult.php` (снимок 20.200.300). Там, где поведение могло измениться в новых сборках, стоит пометка `[проверить]`.

**Практика** к этому подразделу лежит в `examples/module-1-2/`: четыре компонента (`practice-1-news-list`, `practice-2-news-filter`, `practice-3-feedback-form`, `practice-4-complex-news`) и контрольное задание с пятью дефектами.

---

# 1. Что нужно понимать до начала

Для изучения достаточно знать:

- основы PHP;
- массивы, функции, классы и наследование;
- `include`, `require`, пространства имён;
- основы HTTP: GET, POST, статус ответа;
- HTML и экранирование;
- назначение базы данных.

В самом Bitrix нужно ориентироваться в понятиях:

- инфоблок;
- элемент и раздел инфоблока;
- шаблон сайта;
- публичная и административная части;
- пролог и эпилог страницы.

## Учебный стенд

Для примеров понадобится установленный Bitrix с модулем `iblock`.

Создай инфоблок:

- тип: например, `content`;
- название: «Новости»;
- несколько активных элементов;
- у элементов заполни название, анонс, картинку анонса и дату начала активности.

ID инфоблока в примерах — `3`. **Замени его на фактический ID своего инфоблока.**

Для публичного просмотра настрой права чтения для нужной группы пользователей. На учебном публичном стенде это обычно группа «Все пользователи».

---

# 2. Что такое компонент

**Компонент — это переиспользуемый блок функциональности сайта, который принимает параметры, получает или обрабатывает данные и формирует результат.**

Примеры:

- список новостей;
- карточка товара;
- меню;
- форма обратной связи;
- каталог;
- личный кабинет.

Упрощённая схема:

```text
Параметры компонента
        ↓
Проверка и нормализация параметров
        ↓
Бизнес-логика и получение данных
        ↓
Результат
        ↓
Шаблон
        ↓
HTML
```

В Bitrix для этого используются:

```php
$arParams // входные параметры
$arResult // данные, подготовленные для шаблона
```

## 2.1. Разделение ответственности

| Часть | За что отвечает |
|---|---|
| Компонент | Проверки, права, получение данных, кеширование |
| Шаблон | HTML и отображение подготовленных данных |
| `.parameters.php` | Интерфейс настройки компонента |
| `.description.php` | Описание компонента для редактора |
| `result_modifier.php` | Подготовка данных для конкретного шаблона |
| `component_epilog.php` | Действия после вывода компонента, в том числе при попадании в кеш |

**Основная мысль для собеседования:**

> Компонент отвечает за функциональность, шаблон — за представление. Запросы к БД и бизнес-правила не стоит прятать в шаблон.

---

# 3. Простой и комплексный компонент

## Простой компонент

Решает отдельную задачу:

- выводит список;
- показывает детальную страницу;
- строит меню;
- обрабатывает форму.

У простого компонента может быть **несколько альтернативных шаблонов**:

```text
templates/
├── .default/
│   └── template.php
├── cards/
│   └── template.php
└── compact/
    └── template.php
```

При конкретном вызове выбирается один из них.

## Комплексный компонент

Организует многостраничный раздел:

- определяет текущую страницу по URL;
- извлекает переменные маршрута;
- подключает нужный шаблон страницы;
- координирует дочерние компоненты.

Например:

```text
/news/       → список
/news/15/    → новость с ID 15
```

**Комплексность определяется не количеством шаблонов, а ролью компонента в организации раздела.**

---

# 4. Где размещать собственные компоненты

Для собственных компонентов используй `/local/components/`.

Структура:

```text
/local/components/<пространство_имён>/<имя_компонента>/
```

Например:

```text
/local/components/study/news.list/
```

Полное имя при подключении:

```text
study:news.list
```

Здесь:

- `study` — пространство имён компонентов;
- `news.list` — имя компонента.

Не путай это пространство имён с PHP `namespace`: это разные механизмы.

## Почему не стоит редактировать стандартный компонент

Изменения в `/bitrix/components/bitrix/` могут быть потеряны при обновлении.

Выбирай подход по задаче:

| Задача | Подход |
|---|---|
| Изменить разметку стандартного компонента | Переопределить его шаблон |
| Немного преобразовать данные для своего шаблона | Использовать `result_modifier.php` |
| Реализовать самостоятельную бизнес-логику | Написать собственный компонент |
| Сильно изменить поведение стандартного компонента | Оценить собственный компонент или обоснованную копию с ответственностью за сопровождение |

---

# 5. Пишем простой компонент списка новостей

Наш компонент будет:

- принимать ID инфоблока и количество элементов;
- проверять параметры;
- учитывать активность по датам;
- проверять права чтения;
- получать новости;
- безопасно выводить текст;
- кешировать результат, включая пустую выборку;
- сбрасывать кеш при изменении инфоблока.

## 5.1. Структура

```text
/local/components/study/news.list/
├── .description.php
├── .parameters.php
├── class.php
└── templates/
    └── .default/
        ├── template.php
        └── style.css
```

В этом примере компонент реализован классом с методом `executeComponent()`. Отдельный `component.php` ему не нужен.

---

## 5.2. Описание: `.description.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'Учебный список новостей',
    'DESCRIPTION' => 'Выводит активные новости из инфоблока',
    'PATH' => [
        'ID' => 'study',
        'NAME' => 'Учебные компоненты',
    ],
];
```

Этот файл содержит метаданные для визуального редактора.

Он не получает новости и не управляет кешированием.

---

## 5.3. Настройки: `.parameters.php`

Для начала сделаем ID инфоблока обычным числовым полем. Выпадающий список инфоблоков удобнее, но для понимания механизма не обязателен.

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID инфоблока',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],

        'COUNT' => [
            'PARENT' => 'BASE',
            'NAME' => 'Количество новостей',
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
        ],

        'CACHE_TIME' => [
            'DEFAULT' => 3600,
        ],
    ],
];
```

`CACHE_TIME` — специальный параметр: редактор Bitrix использует его при формировании настроек кеширования.

**Важно:**

> `.parameters.php` описывает интерфейс настройки, но не заменяет проверку параметров во время выполнения.

Компонент можно вызвать вручную и передать ему некорректные значения.

---

## 5.4. Логика: `class.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

class StudyNewsListComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);

        $arParams['COUNT'] = max(
            1,
            min(100, (int)($arParams['COUNT'] ?? 10))
        );

        $cacheType = (string)($arParams['CACHE_TYPE'] ?? 'A');

        $arParams['CACHE_TYPE'] = in_array(
            $cacheType,
            ['A', 'Y', 'N'],
            true
        ) ? $cacheType : 'A';

        $arParams['CACHE_TIME'] = max(
            0,
            (int)($arParams['CACHE_TIME'] ?? 3600)
        );

        return $arParams;
    }

    public function executeComponent()
    {
        if ($this->arParams['IBLOCK_ID'] <= 0) {
            ShowError('Не указан корректный ID инфоблока.');
            return;
        }

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль инфоблоков недоступен.');
            return;
        }

        $additionalCacheId = $this->getAdditionalCacheId();

        if (!$this->startResultCache(false, $additionalCacheId)) {
            return;
        }

        try {
            $items = $this->loadItems();
        } catch (\Throwable $exception) {
            $this->abortResultCache();

            AddMessage2Log(
                $exception->getMessage(),
                'study:news.list'
            );

            ShowError('Не удалось загрузить новости.');
            return;
        }

        $this->arResult = [
            'ITEMS' => $items,
            'ITEMS_COUNT' => count($items),
        ];

        if (defined('BX_COMP_MANAGED_CACHE')) {
            global $CACHE_MANAGER;

            $CACHE_MANAGER->RegisterTag(
                'iblock_id_' . $this->arParams['IBLOCK_ID']
            );
        }

        $this->setResultCacheKeys([
            'ITEMS_COUNT',
        ]);

        $this->includeComponentTemplate();
    }

    protected function getAdditionalCacheId()
    {
        global $USER;

        $groups = array_map(
            'intval',
            $USER->GetUserGroupArray()
        );

        sort($groups, SORT_NUMERIC);

        return [
            'USER_ID' => (int)$USER->GetID(),
            'GROUPS' => $groups,
            'LANGUAGE_ID' => LANGUAGE_ID,
        ];
    }

    protected function loadItems()
    {
        $items = [];

        $result = CIBlockElement::GetList(
            [
                'ACTIVE_FROM' => 'DESC',
                'ID' => 'DESC',
            ],
            [
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'ACTIVE' => 'Y',
                'ACTIVE_DATE' => 'Y',
                'CHECK_PERMISSIONS' => 'Y',
                'MIN_PERMISSION' => 'R',
            ],
            false,
            [
                'nTopCount' => $this->arParams['COUNT'],
            ],
            [
                'ID',
                'IBLOCK_ID',
                'IBLOCK_SECTION_ID',
                'NAME',
                'DETAIL_PAGE_URL',
                'PREVIEW_TEXT',
                'PREVIEW_TEXT_TYPE',
                'PREVIEW_PICTURE',
                'ACTIVE_FROM',
            ]
        );

        // Ошибки БД ядро отдаёт исключением; перехват — в executeComponent().
        // Проверка "$result === false" здесь не нужна: GetList() возвращает
        // CIBlockResult, а не false. Разбор — в 5.5.

        while ($row = $result->GetNext(false, false)) {
            $previewText = (string)$row['PREVIEW_TEXT'];

            if ($row['PREVIEW_TEXT_TYPE'] === 'html') {
                $previewText = html_entity_decode(
                    strip_tags($previewText),
                    ENT_QUOTES | ENT_HTML5,
                    SITE_CHARSET
                );
            }

            $picture = false;

            if ((int)$row['PREVIEW_PICTURE'] > 0) {
                $picture = CFile::GetFileArray(
                    (int)$row['PREVIEW_PICTURE']
                );
            }

            $dateFormatted = '';

            if (!empty($row['ACTIVE_FROM'])) {
                $dateFormatted = FormatDate(
                    'd.m.Y',
                    MakeTimeStamp($row['ACTIVE_FROM'])
                );
            }

            $items[] = [
                'ID' => (int)$row['ID'],
                'NAME' => (string)$row['NAME'],
                'DETAIL_PAGE_URL' => (string)$row['DETAIL_PAGE_URL'],
                'PREVIEW_TEXT_PLAIN' => $previewText,
                'PREVIEW_PICTURE' => $picture,
                'DATE_FORMATTED' => $dateFormatted,
            ];
        }

        return $items;
    }
}
```

### Почему здесь старое API, а не ORM

`CIBlockElement::GetList()` остаётся полезным API для работы с инфоблоками.

Классовый компонент не обязан использовать исключительно D7 ORM.

Выбор зависит от:

- задачи;
- типа данных;
- настроек инфоблока;
- требований к выборке;
- используемых возможностей старого API;
- стандартов проекта.

**Плохой ответ на собеседовании:**

> Всё, что не D7, устарело и использовать нельзя.

**Хороший ответ:**

> D7 — современный слой API Bitrix, но в реальных проектах он сосуществует с историческим API. Выбирать нужно осознанно, а не по названию класса.

---

## 5.5. Что важно в этом коде

### Нормализация параметров

Количество ограничено диапазоном:

```php
1 <= COUNT <= 100
```

Это защищает от случайного запроса огромного количества элементов.

### Стабильная сортировка

```php
[
    'ACTIVE_FROM' => 'DESC',
    'ID' => 'DESC',
]
```

Если даты совпадают, порядок определяется по ID.

Для пагинации стабильная сортировка особенно важна.

### Активность

```php
'ACTIVE' => 'Y',
'ACTIVE_DATE' => 'Y',
```

Это разные условия:

- `ACTIVE` — флаг активности;
- `ACTIVE_DATE` — соответствие текущей даты периоду публикации.

### Права

```php
'CHECK_PERMISSIONS' => 'Y',
'MIN_PERMISSION' => 'R',
```

Нельзя считать, что любой собственный запрос автоматически реализует нужную модель доступа.

### Неэкранированные данные

```php
$result->GetNext(false, false)
```

В этом примере мы отключаем автоматическую HTML-подготовку значений и появление дублирующих полей с префиксом `~`.

Контракт такой:

> В `$arResult` передаются исходные текстовые значения. Экранирование для HTML выполняется в шаблоне.

При этом `GetNext()` используется и для обработки URL-шаблонов элементов.

### HTML-анонс превращается в текст

Компонент намеренно не выводит HTML из анонса.

Если анонс был HTML, из него формируется текст. Перед выводом этот текст всё равно экранируется.

`strip_tags()` здесь **не выступает самостоятельной защитой от XSS**. Безопасность вывода обеспечивается последующим HTML-экранированием.

### Почему проверка `$result === false` — мёртвая ветка

`CIBlockElement::GetList()` возвращает объект `CIBlockResult`. `false` там не появится никогда, поэтому `if ($result === false) { throw ... }` не выполняется ни при каких данных. Это не «защита на всякий случай», а строка, создающая ложное впечатление обработки ошибок.

Реальные отказы выглядят иначе:

| Ситуация | Что происходит |
|---|---|
| Ошибка SQL | ядро вызывает `$APPLICATION->ThrowException()` или бросает исключение БД |
| Инфоблок удалён или деактивирован | пустой результат (не ошибка) |
| Нет прав на инфоблок | пустой результат при `CHECK_PERMISSIONS => Y` |
| Неверный тип значения в фильтре | PHP-ошибка типа (`TypeError`), не `false` |

Отсюда две разные ветки в рабочем коде:

```php
if (!$this->loadIblock()) {          // инфоблока нет — это 404
    $this->abortResultCache();
    \Bitrix\Iblock\Component\Tools::process404('Раздел не найден.', true, true, false);
    return;
}

try {                                 // ошибка выполнения — это 500 и лог
    $this->loadItems($navParams);
} catch (\Throwable $exception) {
    $this->abortResultCache();
    AddMessage2Log($exception->getMessage(), 'study.news.list');
    ShowError('Не удалось загрузить новости.');
    return;
}
```

Пустая выборка при существующем инфоблоке — нормальный результат, а не ошибка.

### Автоматическое экранирование параметров: `$arParams` и `~$arParams`

Для классового компонента `CBitrixComponent::includeComponent()` выполняет (`main/classes/general/component.php`):

```php
$component->arParams = $component->onPrepareComponentParams($arParams); // сырые значения
$component->__prepareComponentParams($component->arParams);             // экранирование
```

`__prepareComponentParams()` для каждого строкового параметра:

1. копирует исходное значение в `$arParams['~' . $NAME]`;
2. если строка содержит любой из символов `; & < > "`, заменяет `$arParams[$NAME]` на `htmlspecialcharsEx($value)`.

Следствия:

- `onPrepareComponentParams()` получает **сырые** значения — валидация и `(int)` работают как ожидается;
- в `executeComponent()` и в шаблоне `$arParams['NAME']` — экранированный вариант, а исходный лежит в `$arParams['~NAME']`;
- для массивов экранируются строковые значения первого уровня, глубже ядро не идёт;
- ключ кеша строится **без** `~`-ключей, то есть по экранированным значениям (`getCacheID()` пропускает ключи, начинающиеся с `~`);
- если параметр нужен для SQL, для лога, для `header()` или для сравнения с исходной строкой — берите `~`-версию и обрабатывайте её сами.

Практический вывод для шаблона: выводить `$arParams[...]` без `htmlspecialcharsbx()` технически можно, ядро уже экранировало. Но привычка «данные из БД экранирую, параметры — нет» ломается на первом же `~`-значении, и повторное экранирование даст видимый `&amp;lt;`. Держите одно правило: **экранировать в месте вывода всё, что не является числом или заранее сформированным HTML**.

---

## 5.6. Шаблон: `templates/.default/template.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 */
?>

<div class="study-news">
    <?php if (empty($arResult['ITEMS'])): ?>
        <p class="study-news__empty">
            Новостей пока нет.
        </p>
    <?php else: ?>
        <div class="study-news__list">
            <?php foreach ($arResult['ITEMS'] as $item): ?>
                <article class="study-news__item">
                    <?php if (!empty($item['PREVIEW_PICTURE']['SRC'])): ?>
                        <img
                            class="study-news__image"
                            src="<?= htmlspecialcharsbx(
                                $item['PREVIEW_PICTURE']['SRC']
                            ) ?>"
                            alt="<?= htmlspecialcharsbx($item['NAME']) ?>"
                            loading="lazy"
                        >
                    <?php endif; ?>

                    <?php if ($item['DATE_FORMATTED'] !== ''): ?>
                        <div class="study-news__date">
                            <?= htmlspecialcharsbx(
                                $item['DATE_FORMATTED']
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <h2 class="study-news__title">
                        <?php if ($item['DETAIL_PAGE_URL'] !== ''): ?>
                            <a href="<?= htmlspecialcharsbx(
                                $item['DETAIL_PAGE_URL']
                            ) ?>">
                                <?= htmlspecialcharsbx($item['NAME']) ?>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialcharsbx($item['NAME']) ?>
                        <?php endif; ?>
                    </h2>

                    <?php if ($item['PREVIEW_TEXT_PLAIN'] !== ''): ?>
                        <p class="study-news__preview">
                            <?= nl2br(
                                htmlspecialcharsbx(
                                    $item['PREVIEW_TEXT_PLAIN']
                                )
                            ) ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
```

В шаблоне:

- нет запросов к БД;
- нет проверки прав;
- нет чтения `$_REQUEST`;
- нет бизнес-логики;
- есть только отображение подготовленного результата.

Для URL мы предполагаем штатные адреса, сформированные из настроенного шаблона инфоблока. Если URL поступает от пользователя, одного HTML-экранирования недостаточно: требуется ещё проверять допустимую схему и назначение адреса.

---

## 5.7. Стили: `templates/.default/style.css`

```css
.study-news__list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 24px;
}

.study-news__item {
    padding: 20px;
    border: 1px solid #e2e5e9;
    border-radius: 12px;
}

.study-news__image {
    display: block;
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 16px;
}

.study-news__date {
    margin-bottom: 8px;
    color: #777;
    font-size: 14px;
}

.study-news__title {
    margin: 0 0 12px;
    font-size: 22px;
}

.study-news__preview {
    margin: 0;
    line-height: 1.5;
}

.study-news__empty {
    color: #777;
}
```

Файлы `style.css` и `script.js` в каталоге шаблона имеют специальное назначение и подключаются механизмом шаблонов компонента.

---

## 5.8. Подключение на страницу

Пример полной публичной страницы:

```php
<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Учебный список новостей');

$APPLICATION->IncludeComponent(
    'study:news.list',
    '.default',
    [
        'IBLOCK_ID' => 3,
        'COUNT' => 10,
        'CACHE_TYPE' => 'A',
        'CACHE_TIME' => 3600,
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
```

Здесь:

1. Первый аргумент — имя компонента.
2. Второй — имя шаблона.
3. Третий — параметры.
4. Необязательный четвёртый — родительский компонент.

`DETAIL_PAGE_URL` формируется по настройкам инфоблока. Когда реализуешь комплексный компонент ниже, можно установить шаблон детального URL:

```text
/study-news/#ELEMENT_ID#/
```

---

# 6. Кеширование: что именно происходит

Это одна из главных тем на собеседованиях.

## 6.0. Из чего складывается ключ кеша

Прежде чем рассуждать «что добавить в ключ», надо знать, что в нём уже есть. Метод `CBitrixComponent::getCacheID()` (`main/classes/general/component.php`):

```php
public function getCacheID($additionalCacheID = false)
{
    $cacheID = $SITE_ID . "|" . $LANGUAGE_ID
        . ($SITE_TEMPLATE_ID != "" ? "|" . $SITE_TEMPLATE_ID : "")
        . "|" . $this->__name               // 'study:news.list'
        . "|" . $this->getTemplateName()    // '.default'
        . "|";

    foreach ($this->arParams as $k => $v)
        if (strncmp("~", $k, 1))            // ~-ключи пропускаются
            $cacheID .= "," . $k . "=" . serialize($v);

    if (($offset = CTimeZone::getOffset()) <> 0)
        $cacheID .= "|" . $offset;

    if ($additionalCacheID !== false)
        $cacheID .= "|" . serialize($additionalCacheID);

    if ($this->__currentCounter > 1)        // второй и далее вызов на странице
        $cacheID .= "|" . $this->__currentCounter;

    return $cacheID;
}
```

| Есть в ключе | Нет в ключе |
|---|---|
| `SITE_ID`, `LANGUAGE_ID`, шаблон сайта | `$_GET`, `$_POST`, `$_REQUEST` |
| имя компонента и имя шаблона | cookies, `$_SESSION` |
| **все** `$arParams`, кроме `~`-ключей | `$USER`, его группы и права |
| смещение таймзоны пользователя | текущий URL |
| `$additionalCacheID` (второй аргумент) | данные в БД |
| номер вызова компонента на странице | время, окружение, конфигурация |

Что из этого следует практически:

1. **Любой параметр уже в ключе.** Меняете `CACHE_GROUPS`, `NEWS_COUNT`, `SORT_BY` — получаете отдельную запись кеша. Дублировать их в `$additionalCacheID` бессмысленно: это только раздувает строку ключа.
2. **`$_GET` в ключ не входит.** Номер страницы, поисковый запрос, выбранный раздел — всё это надо передавать вторым аргументом, иначе страница 2 получит HTML страницы 1.
3. **`__currentCounter`** спасает от коллизии, когда один и тот же компонент вызван на странице дважды с одинаковыми параметрами: у второго вызова ключ отличается.
4. **Порядок ключей в `$arParams` важен** — ключ строится конкатенацией `serialize()` по порядку массива. Один и тот же набор параметров в разном порядке даст разные записи кеша. На практике параметры приходят из одного вызова, но в `$additionalCacheID` лучше передавать массив с фиксированным порядком элементов.
5. **Смена часового пояса пользователя меняет кеш** — это защита от того, чтобы даты в списке показывались в чужом поясе.
6. **`~`-ключи в ключ не входят**, потому что это зеркальные копии исходных значений, а не отдельные параметры.

Физически кеш компонента лежит в `/bitrix/cache/<SITE_ID>/<путь компонента>/<hash>/`. Удалять файлы руками не нужно — есть `$CACHE_MANAGER->CleanDir()` и штатная очистка кеша.

## 6.1. Базовая схема

```php
if ($this->startResultCache()) {
    // Получение данных.
    // Подготовка результата.
    // Подключение шаблона.
}
```

Поведение:

```text
Вызов компонента
        ↓
Есть действительный кеш?
        ├── Да → вывод кешированного результата
        └── Нет
             ↓
        Получение данных
             ↓
        Выполнение шаблона
             ↓
        Сохранение результата в кеш
```

На попадании в кеш блок внутри `if` не выполняется.

В нашем примере та же логика записана через ранний выход:

```php
if (!$this->startResultCache(false, $additionalCacheId)) {
    return;
}
```

## 6.2. Что означает `false` в первом аргументе

```php
$this->startResultCache(false, $additionalCacheId)
```

Первый аргумент позволяет явно задать время кеширования.

`false` означает использование времени из параметров компонента.

Второй аргумент добавляет зависимости к идентификатору кеша.

### `CACHE_TYPE`: `A`, `Y`, `N` — это не «включено/выключено»

Значения разбирает сам `startResultCache()`:

```php
if ($this->arParams["CACHE_TYPE"] == "N"
    || ($this->arParams["CACHE_TYPE"] == "A"
        && COption::getOptionString("main", "component_cache_on", "Y") == "N"))
    return true;   // кеша нет: сразу строим результат
```

| Значение | Поведение |
|---|---|
| `N` | кеш не используется и не создаётся |
| `A` | «авто»: кешировать, если в настройках модуля `main` («Автокеширование») не выключено глобально |
| `Y` | кешировать всегда, независимо от глобальной настройки |

`A` — значение по умолчанию, которое редактор предлагает в `.parameters.php`. `Y` нужен там, где кеш является частью контракта: на продакшене с выключенным автокешированием список новостей всё равно должен кешироваться.

Любое другое значение `CBitrixComponent::includeComponent()` приводит к `A`:

```php
if ($arParams["CACHE_TYPE"] != "Y" && $arParams["CACHE_TYPE"] != "N")
    $arParams["CACHE_TYPE"] = "A";
```

### `CACHE_TIME = 0` как способ выключить кеш точечно

`startResultCache()` с нулевым временем жизни возвращает `true` и не находит запись в кеше: данные получаются заново, кеш не создаётся. Именно так стандартные компоненты отключают кеширование, когда появился фильтр, который не учтён в ключе:

```php
$arParams["CACHE_FILTER"] = $arParams["CACHE_FILTER"] == "Y";
if (!$arParams["CACHE_FILTER"] && count($arrFilter) > 0)
    $arParams["CACHE_TIME"] = 0;
```

Это лучше, чем `CACHE_TYPE = 'N'`: код компонента не ветвится, ветка одна.

### Что `startResultCache()` делает на попадании

Кроме возврата `false` он ещё:

- восстанавливает `$this->arResult` из кеша (только те ключи, что были сохранены, — см. 6.8);
- восстанавливает закешированные `additionalCSS` / `additionalJS` шаблона;
- восстанавливает данные динамических областей композита (`frames`);
- **вызывает `component_epilog.php`** — эпилог на попадании выполняется изнутри `startResultCache()`, уже после печати кешированного HTML.

Поэтому нельзя считать, что «на попадании в кеш ничего не выполняется». Выполняется эпилог и код после `if ($this->startResultCache(...))`.

---

## 6.3. Пустой результат тоже кешируется

Если новостей нет, это нормальный результат:

```php
$this->arResult['ITEMS'] = [];
```

Шаблон выведет:

```text
Новостей пока нет.
```

Такой результат обычно следует кешировать.

Иначе каждый посетитель будет выполнять одинаковый запрос к БД.

**Не путай:**

| Ситуация | Действие |
|---|---|
| Новостей нет | Обычно кешировать |
| Ошибка получения данных | Прервать формирование кеша |
| Не удалось подключить необходимый модуль | Не формировать обычный результат |
| Исключительная ситуация | Не сохранять ошибочный результат |

---

## 6.4. Для чего `abortResultCache()`

```php
$this->abortResultCache();
```

Останавливает формирование текущего кеша.

В нашем примере он вызывается, если при получении данных произошло исключение.

Он не нужен просто потому, что массив элементов оказался пустым.

---

## 6.5. Зачем тег инфоблока

```php
$CACHE_MANAGER->RegisterTag('iblock_id_3');
```

Он связывает кеш с инфоблоком.

Когда штатные механизмы Bitrix изменяют данные инфоблока, соответствующий тег инвалидируется.

Это позволяет не ждать полного истечения TTL.

**Важно:** пример регистрации тега выше используется внутри стандартного кеширования компонента. Для самостоятельного тегированного кеша вне этого механизма нужен полноценный жизненный цикл регистрации тегов.

### Кто на самом деле регистрирует `iblock_id_N`

Итерирование результата `CIBlockElement::GetList()` регистрирует тег **само**. В `CIBlockResult::Fetch()` (`iblock/classes/general/iblockresult.php`):

```php
if (isset($res["IBLOCK_ID"]) && $res["IBLOCK_ID"] != $this->_LAST_IBLOCK_ID
    && defined("BX_COMP_MANAGED_CACHE"))
{
    CIBlock::registerWithTagCache($res["IBLOCK_ID"]);
    $this->_LAST_IBLOCK_ID = $res["IBLOCK_ID"];
}
```

и там же — ветка для пустого результата:

```php
elseif (defined("BX_COMP_MANAGED_CACHE")
    && $this->_LAST_IBLOCK_ID == ""
    && count($this->_FILTER_IBLOCK_ID))
{
    foreach ($this->_FILTER_IBLOCK_ID as $iblock_id => $t)
        CIBlock::registerWithTagCache($iblock_id);
}
```

Практические выводы:

1. Явный `RegisterTag('iblock_id_' . $id)` в компоненте, который читает данные через `CIBlockElement`/`CIBlockSection`, **избыточен** — тег уже зарегистрирован. Вреда нет, читаемость выше, поэтому в учебных примерах он оставлен.
2. Если данные получены **не** через API инфоблока (ORM-сущности, свой SQL, кеш в модуле), тег нужно регистрировать руками, иначе кеш не сбросится никогда.
3. Тег регистрируется при `Fetch()`. Если результат не итерировали вообще (`GetList()` без `while`), тега не будет — это редкий, но реальный источник «залипшего» кеша.
4. `RegisterTag()` работает, только когда управляемый кеш запущен. Внутри компонента это обеспечивает `startResultCache()` (`$CACHE_MANAGER->startTagCache($this->__cachePath)`), закрывает — `endResultCache()`. Вызов `RegisterTag()` вне этого окна ничего не запишет.

### Почему тег регистрируется даже для пустого списка

Сегодня в инфоблоке нет элементов, а завтра появился первый.

Кеш пустого списка тоже должен стать неактуальным.

---

## 6.6. TTL и теги не заменяют друг друга

У кеша могут быть:

- ограничение по времени;
- событийная инвалидация по тегам.

Это разные механизмы.

Например, новость должна стать активной завтра в 10:00.

Само наступление времени — не редактирование инфоблока. Тег не обязательно будет сброшен в этот момент.

Поэтому при публикации по расписанию нужно учитывать TTL либо отдельно организовывать обновление.

---

## 6.7. Что должно входить в зависимости кеша

Всё, что влияет на результат и не учтено стандартным механизмом.

Например:

- фильтр;
- номер страницы;
- права пользователя;
- персональные настройки;
- язык;
- внешний контекст.

Нельзя делать так:

```php
$filter = $_SESSION['NEWS_FILTER'];

// Далее общий кеш, в котором фильтр не учитывается.
```

Иначе один посетитель может получить результат, сформированный для другого фильтра.

### `CACHE_GROUPS`: штатный механизм разделения по правам

Стандартные компоненты не изобретают свой ключ, а используют параметр `CACHE_GROUPS`. Каноническая строка из `bitrix:news.list`:

```php
$this->startResultCache(false, [
    $arParams['CACHE_GROUPS'] === 'N' ? false : $USER->GetGroups(),
    $bUSER_HAVE_ACCESS,
    $arNavigation,
    $arrFilter,
    $pagerParameters,
]);
```

Что здесь важно:

- `$USER->GetGroups()` возвращает строку групп, а не массив — она стабильна при одинаковом наборе;
- `CACHE_GROUPS = N` означает «данные одинаковы для всех групп» — так можно делать только после проверки, что выборка не зависит от прав;
- **ID пользователя при этом не входит** в ключ. Если в списке есть персональные данные («мои заявки», «избранное»), `CACHE_GROUPS` недостаточно: два пользователя одной группы получат один кеш. Персональные данные либо не кешируют, либо добавляют в ключ ID пользователя (и получают запись кеша на каждого).

### Почему в нашем примере в ключе есть пользователь

Мы применили консервативный вариант:

```php
[
    'USER_ID' => ...,
    'GROUPS' => ...,
]
```

Это разделяет кеш авторизованных пользователей даже при совпадающих группах.

Цена — больше отдельных записей кеша.

Для обычной публичной ленты часто достаточно общего кеша или разделения по группам. Но такую оптимизацию нужно делать после анализа модели прав.

**Правильный принцип:**

> Сначала определить, от чего зависят данные и доступ, затем проектировать кеш. Не наоборот.

---

## 6.8. Для чего `setResultCacheKeys()`

```php
$this->setResultCacheKeys([
    'ITEMS_COUNT',
]);
```

Позволяет сохранить выбранные ключи результата так, чтобы они были доступны при восстановлении компонента из кеша.

Это полезно для:

- `component_epilog.php`;
- действий после блока кеширования;
- результатов, которые нужны вызывающему коду.

**Это не перечисление всех полей, доступных шаблону.**

При построении шаблона на промахе кеша доступен весь текущий `$arResult`. Но на попадании в кеш он не восстанавливается целиком: в кеш попадает то, что осталось после урезания.

Точное место урезания — `showComponentTemplate()` (`main/classes/general/component.php`):

```php
final public function showComponentTemplate()
{
    if ($this->__template)
        $this->__template->includeTemplate($this->arResult);   // шаблон видит ВЕСЬ результат

    if (is_array($this->arResultCacheKeys))
    {
        $arNewResult = [];
        foreach ($this->arResultCacheKeys as $key)
            if (array_key_exists($key, $this->arResult))
                $arNewResult[$key] = $this->arResult[$key];
        $this->arResult = $arNewResult;                        // <- урезание
    }

    $this->__template->endViewTarget();
    $this->endResultCache();                                   // <- запись в кеш
}
```

Порядок жёсткий:

1. `template.php` получает полный `$arResult`;
2. сразу после шаблона `$arResult` урезается до `arResultCacheKeys`;
3. только затем результат пишется в кеш;
4. `component_epilog.php` вызывается **после** этого (из `includeComponentTemplate()`), поэтому эпилог тоже видит только перечисленные ключи.

Отсюда три правила, которые спрашивают на собеседовании:

- `setResultCacheKeys()` после `includeComponentTemplate()` — бесполезный вызов: урезание и запись уже произошли;
- ключ, добавленный в `$arResult` после `includeComponentTemplate()`, не попадёт ни в кеш, ни в эпилог;
- повторный вызов `setResultCacheKeys()` **дополняет** список (`array_merge`), а не заменяет.

Канонический паттерн стандартных компонентов — код после блока кеша, который работает и на попадании, потому что нужные ему поля перечислены явно (`bitrix:news.list`):

```php
if ($this->startResultCache(false, $cacheId))
{
    // ... выборка, $arResult["ITEMS"], $arResult["NAME"], $arResult["SECTION"] ...

    $this->setResultCacheKeys(['ID', 'NAME', 'SECTION', 'ELEMENTS', 'IPROPERTY_VALUES']);
    $this->includeComponentTemplate();
}

// Выполняется и на холодном кеше, и на горячем:
if (isset($arResult['ID']))
{
    $APPLICATION->SetTitle($arResult['NAME']);
    $APPLICATION->AddChainItem($arResult['NAME']);
}
```

На попадании в кеш `$arResult['ITEMS']` здесь уже нет — и это нормально: шаблон отрисован в кешированный HTML, а для заголовка и цепочки навигации достаточно `NAME`.

---

# 7. Кеш компонента и композит — разные вещи

Не смешивай:

1. кеш результата компонента;
2. управляемый кеш;
3. композитный кеш страницы;
4. динамические области.

| Механизм | Назначение |
|---|---|
| `startResultCache()` | Кеширование результата выполнения компонента |
| Теги управляемого кеша | Инвалидация по зависимостям |
| Композит | Быстрая отдача статической части HTML страницы |
| Динамические области | Обновление отдельных частей поверх статической страницы |

```php
$this->setFrameMode(true);   // вызов НЕ обязателен и НЕ включает кеш
```

`setFrameMode()` — про композит, а не про кеш результата. Точная семантика (`main/classes/general/component.php`):

```php
public function setFrameMode($mode)          // принимает true, false или null
{
    if (in_array($mode, [true, false, null], true))
        $this->frameMode = $mode;
}

public function getFrameMode()
{
    return $this->frameMode !== null ? $this->frameMode : true;   // по умолчанию true
}
```

| Значение | Что означает |
|---|---|
| не вызван (`null` → `getFrameMode()` = `true`) | компонент считается «динамическим»: ядро само создаёт вокруг него автоматическую область композита |
| `setFrameMode(true)` | компонент **сам** управляет динамическими областями через `createFrame()`; автоматическая область ему не нужна |
| `setFrameMode(false)` | компонент в композит не пускать: `CBitrixComponent::includeComponent()` отдаёт странице отрицательный голос (`giveNegativeComponentVote`), и страница не будет закеширована как композитная |

Отсюда:

- `setFrameMode(true)` **не** включает кеш и не имеет отношения к `startResultCache()`;
- ставить его «на всякий случай» в каждый компонент — ошибка: он отключает автоматическую динамическую область, которую ядро сделало бы само, и требует, чтобы вы действительно создавали `createFrame()`;
- `setFrameMode(false)` уместен для компонента с персональными данными, который не должен попасть в статический HTML композита;
- ни одна из веток не делает персональные данные безопасными: композит — отдельный механизм, а не замена продуманному кешу.

В нашем учебном компоненте композит специально не настраивается: сначала нужно уверенно понять обычное кеширование.

---

# 8. Жизненный цикл компонента и шаблона

Для нашего классового компонента:

```text
Подключение компонента
        ↓
Создание объекта
        ↓
onPrepareComponentParams()
        ↓
executeComponent()
        ↓
Проверки
        ↓
startResultCache()
        ├── Попадание в кеш
        │      ↓
        │  Вывод кешированного HTML
        │
        └── Промах кеша
               ↓
           Получение данных
               ↓
           includeComponentTemplate()
               ↓
           result_modifier.php
               ↓
           template.php
               ↓
           Завершение формирования кеша

После вывода — component_epilog.php, если он предусмотрен
```

Это учебная схема стандартного сценария. Внутренний механизм имеет больше этапов, но для практики важны именно эти границы.

## Как ядро выбирает между `class.php` и `component.php`

`CBitrixComponent::initComponent()` (`main/classes/general/component.php`):

```php
$path2Comp = CComponentEngine::MakeComponentPath($componentName);  // 'study:news.list' → '/study/news.list'
$componentPath = getLocalPath("components" . $path2Comp);          // /local/... раньше /bitrix/...
$this->classOfComponent = self::__getClassForPath($componentPath);

if ($this->classOfComponent === "")
{
    $componentFile = $_SERVER["DOCUMENT_ROOT"] . $componentPath . "/component.php";
    if (!file_exists($componentFile) || !is_file($componentFile))
    {
        $this->__ShowError(sprintf("'%s' is not a component", $componentName));
        return false;
    }
}
```

`__getClassForPath()` подключает `class.php` через `include_once` и ищет среди **новых** объявленных классов наследника `CBitrixComponent`; если наследников несколько, остаётся самый производный (`is_subclass_of(candidate, уже найденный)`).

Следствия:

1. **Есть `class.php` с наследником — `component.php` не подключается вообще.** Держать оба файла (даже с пустым `component.php`) — значит иметь мёртвый код и путаницу «что же исполняется».
2. **Есть `class.php`, но без наследника `CBitrixComponent`** (забыли `extends`, опечатка в имени) — ядро молча пойдёт искать `component.php`. Если его нет: `'study:news.list' is not a component` и белый экран. Это самая частая ошибка при переносе компонента.
3. **Класс ищется по факту наследования, а не по имени.** Ядро примет любое имя. Соглашение `study:news.list` → `StudyNewsListComponent` нужно не ядру, а людям, `includeComponentClass()` и наследованию компонентов: без него невозможно предсказать, какой класс подключать в своём коде.
4. **`getLocalPath()`** отдаёт `/local/components/...`, если там есть такой же путь. Копия штатного компонента в `/local/components/bitrix/news.list/` полностью перекрывает `/bitrix/components/bitrix/news.list/` — обновление ядра её не тронет, но и исправления из обновления вы не получите.

## Порядок поиска шаблона

`CBitrixComponentTemplate::generatePossibleTemplatePath()` перебирает папки строго в этом порядке (первый найденный побеждает):

| # | Путь | Когда важен |
|---|---|---|
| 1 | `/local/templates/<шаблон сайта>/components/<родитель>/<шаблон родителя>/<компонент>/<шаблон>` | шаблон дочернего компонента внутри шаблона родителя |
| 2 | `/local/templates/.default/components/<родитель>/…` | то же для шаблона сайта `.default` |
| 3 | `/local/components/<родитель>/templates/<шаблон родителя>/<компонент>/<шаблон>` | шаблон дочернего внутри самого компонента-родителя |
| 4 | `/local/templates/<шаблон сайта>/components/<компонент>/<шаблон>` | **переопределение в шаблоне сайта** |
| 5 | `/local/templates/.default/components/<компонент>/<шаблон>` | переопределение для `.default` |
| 6 | `/local/components/<компонент>/templates/<шаблон>` | шаблон в поставке компонента |
| 7–12 | те же шесть путей в `/bitrix/` | поставка ядра |

Практические выводы:

- `/local` всегда раньше `/bitrix`. Шаблон, поправленный в `/bitrix/templates/...`, будет молча перекрыт своим двойником в `/local`;
- «шаблон дочернего компонента внутри шаблона родителя» — это не магия, а строки 1–3: путь собирается из `GetRelativePath()` родителя и имени его шаблона. Именно поэтому четвёртый аргумент `IncludeComponent()` (объект родителя) обязателен;
- если шаблон не найден, `includeComponentTemplate()` вызывает `abortResultCache()` и печатает `Cannot find '#NAME#' template with page '#PAGE#'` — то есть ошибка шаблона не кешируется.

## Где какой `$this`

| Место | `$this` |
|---|---|
| `component.php` | Объект компонента |
| Метод класса из `class.php` | Объект компонента |
| `template.php` | Объект шаблона |

В шаблоне получить компонент можно так:

```php
$component = $this->getComponent();
```

## Переменные, доступные в шаблоне

`CBitrixComponentTemplate::__IncludePHPTemplate()` подключает `template.php` внутри функции и объявляет там:

| Переменная | Что это |
|---|---|
| `$arResult` | результат компонента, передаётся **по ссылке** |
| `$arParams` | параметры компонента (экранированные, см. 5.5) |
| `$templateName` | имя шаблона, например `.default` |
| `$templateFile` | абсолютный путь к `template.php` |
| `$templateFolder` | папка шаблона, например `/local/components/study/news.list/templates/.default` |
| `$componentPath` | папка компонента |
| `$component` | объект компонента (по ссылке) |
| `$this` | объект шаблона `CBitrixComponentTemplate` |
| `$APPLICATION`, `$USER`, `$DB` | объявлены `global` внутри функции, поэтому доступны |

Из этого — два практических правила:

- `$templateFolder` нужен для `$this->addExternalJs($templateFolder . '/ext.js')` и путей к картинкам шаблона;
- `$arResult` в шаблоне — тот же массив, что в компоненте (`по ссылке`), поэтому `unset($arResult['ITEMS'])` в шаблоне сломает урезание и кеширование. Шаблон должен только читать.

---

# 9. `result_modifier.php` и `component_epilog.php`

## 9.1. `result_modifier.php`

Находится рядом с `template.php`.

Применяется для преобразований, специфичных для шаблона:

- сгруппировать элементы по годам;
- сформировать дополнительные подписи;
- подготовить данные для конкретной визуальной структуры.

Например:

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arResult['HAS_ITEMS'] = !empty($arResult['ITEMS']);
```

При стандартном кешировании выполняется при построении шаблона, а не на каждом попадании в кеш.

Что доступно внутри `result_modifier.php` (`__IncludeMutatorFile()` в `component_template.php`):

| Переменная | Что это |
|---|---|
| `$arResult` | результат шаблона, **по ссылке** — изменения видны `template.php` |
| `$arParams` | параметры компонента |
| `$templateFolder`, `$templateName`, `$component` | как в `template.php` |
| `$this` | объект шаблона |
| `$APPLICATION`, `$USER`, `$DB` | глобальные |

Ключевая деталь: `result_modifier.php` **не входит** в цепочку кеширования отдельно. Он выполняется внутри `IncludeTemplate()`, то есть только тогда, когда шаблон действительно рендерится. На попадании в кеш шаблон не рендерится — файл не выполняется. Всё, что он меняет, уже лежит в кешированном HTML.

Поэтому `result_modifier.php` не подходит для:

- данных, которые должны быть в `$arResult` для `component_epilog.php` (эпилог увидит `$arResult` после урезания, а не после модификатора);
- действий «на каждом запросе» (счётчики, установка заголовка, `AddChainItem`).

Не стоит превращать этот файл в скрытый второй компонент с тяжёлыми запросами и бизнес-правилами.

## 9.2. `component_epilog.php`

Нужен для действий после вывода компонента, включая случаи использования кеша.

Например:

- установить свойства страницы;
- выполнить действия с данными, сохранёнными через `SetResultCacheKeys()`.

Не следует без необходимости выводить там основную разметку компонента: это усложняет понимание кеширования.

Где ищется файл и что в нём доступно (`__IncludePHPTemplate()` и `includeComponentEpilog()`):

```php
$component_epilog = $this->__folder . "/component_epilog.php";   // только папка шаблона
if (file_exists($_SERVER["DOCUMENT_ROOT"] . $component_epilog))
{
    $component->SetTemplateEpilog([
        "epilogFile" => $component_epilog,
        "templateName" => $this->__name,
        "templateFile" => $this->__file,
        "templateFolder" => $this->__folder,
        "templateData" => $templateData,   // то, что шаблон положил в $templateData
    ]);
}
```

| Факт | Следствие |
|---|---|
| Ищется **только** в папке шаблона | `component_epilog.php` в корне компонента не выполнится (в отличие от `class.php`) |
| Доступны `$arParams`, `$arResult`, `$component`, `$componentPath` плюс `$templateName`, `$templateFile`, `$templateFolder`, `$templateData` | `$arResult` — уже урезанный, только ключи из `setResultCacheKeys()` |
| `$templateData` — переменная из `template.php` | так шаблон передаёт данные в эпилог `[проверить]` поведение в конкретной сборке, но механизм стабилен |
| На попадании в кеш эпилог вызывается изнутри `startResultCache()` | он выполняется **до** кода после `if (startResultCache())` в компоненте; `SetTitle()` из компонента перезапишет заголовок, поставленный эпилогом |
| Эпилог может выполниться дважды | если компонент рендерит шаблон ещё и на попадании в кеш — эпилог должен быть идемпотентным (без безусловных счётчиков и отправок писем) |

### `component_epilog.php` в учебной практике

`examples/module-1-2/practice-2-news-filter/component/templates/.default/component_epilog.php` добавляет элемент цепочки навигации с количеством найденных элементов. Это демонстрация порядка вызовов: элемент появляется и на холодном, и на горячем кеше — значит эпилог действительно выполняется, а `ITEMS_COUNT` действительно сохранён через `setResultCacheKeys()`.

## Важный вывод

Если действие должно происходить при каждом вызове, нельзя бездумно помещать его в `template.php` или `result_modifier.php`.

На попадании в кеш они обычно не выполняются заново.

---

# 10. Переопределение шаблона

Предположим, есть компонент:

```text
study:news.list
```

И шаблон сайта:

```text
main
```

Переопределённый шаблон можно разместить здесь:

```text
/local/templates/main/components/study/news.list/cards/
```

Структура:

```text
cards/
├── template.php
└── style.css
```

Подключение:

```php
$APPLICATION->IncludeComponent(
    'study:news.list',
    'cards',
    [
        'IBLOCK_ID' => 3,
        'COUNT' => 6,
        'CACHE_TYPE' => 'A',
        'CACHE_TIME' => 3600,
    ]
);
```

Преимущество:

- логика компонента остаётся общей;
- внешний вид принадлежит шаблону сайта;
- обновление логики не требует копирования разметки в компонент.

**Частый вопрос на собеседовании:**

> Нужно изменить HTML стандартного `bitrix:news.list`. Что делать?

Ответ:

> Переопределить шаблон компонента в шаблоне сайта. Не редактировать стандартный компонент в `/bitrix/components/bitrix/`.

---

# 11. Пишем комплексный компонент

Теперь сделаем раздел:

```text
/study-news/       → список
/study-news/15/    → детальная страница
```

Чтобы сосредоточиться на маршрутизации, используем стандартные дочерние компоненты:

- `bitrix:news.list`;
- `bitrix:news.detail`.

Это нормальный подход: комплексный компонент не обязан заново реализовывать получение всех данных.

**Для учебной ясности наш комплексный компонент поддерживает только ЧПУ.** Режим с query-параметрами в нём намеренно не реализован.

---

## 11.1. Структура

```text
/local/components/study/news/
├── .description.php
├── .parameters.php
├── component.php
└── templates/
    └── .default/
        ├── list.php
        └── detail.php
```

Здесь используется процедурная реализация через `component.php`.

Это отдельный компонент, не продолжение класса из предыдущего примера.

---

## 11.2. `.description.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'Учебный раздел новостей',
    'DESCRIPTION' => 'Список и детальная страница с ЧПУ',
    'COMPLEX' => 'Y',
    'PATH' => [
        'ID' => 'study',
        'NAME' => 'Учебные компоненты',
    ],
];
```

`COMPLEX => Y` — признак комплексного компонента в метаданных.

Он не запускает маршрутизацию автоматически.

---

## 11.3. `.parameters.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'PARAMETERS' => [
        'IBLOCK_TYPE' => [
            'PARENT' => 'BASE',
            'NAME' => 'Тип инфоблока',
            'TYPE' => 'STRING',
            'DEFAULT' => 'content',
        ],

        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID инфоблока',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],

        'SEF_FOLDER' => [
            'PARENT' => 'BASE',
            'NAME' => 'Корневая папка раздела',
            'TYPE' => 'STRING',
            'DEFAULT' => '/study-news/',
        ],

        'NEWS_COUNT' => [
            'PARENT' => 'BASE',
            'NAME' => 'Количество новостей на странице',
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
        ],

        'CACHE_TIME' => [
            'DEFAULT' => 3600,
        ],
    ],
];
```

---

## 11.4. Маршрутизация: `component.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

$arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
$arParams['IBLOCK_TYPE'] = (string)(
    $arParams['IBLOCK_TYPE'] ?? 'content'
);

$arParams['NEWS_COUNT'] = max(
    1,
    min(100, (int)($arParams['NEWS_COUNT'] ?? 10))
);

$arParams['CACHE_TIME'] = max(
    0,
    (int)($arParams['CACHE_TIME'] ?? 3600)
);

$cacheType = (string)($arParams['CACHE_TYPE'] ?? 'A');

$arParams['CACHE_TYPE'] = in_array(
    $cacheType,
    ['A', 'Y', 'N'],
    true
) ? $cacheType : 'A';

$sefFolder = trim(
    (string)($arParams['SEF_FOLDER'] ?? '/study-news/'),
    '/'
);

$arParams['SEF_FOLDER'] = $sefFolder === ''
    ? '/'
    : '/' . $sefFolder . '/';

if ($arParams['IBLOCK_ID'] <= 0) {
    ShowError('Не указан корректный ID инфоблока.');
    return;
}

if (!Loader::includeModule('iblock')) {
    ShowError('Модуль инфоблоков недоступен.');
    return;
}

$urlTemplates = [
    'list' => '',
    'detail' => '#ELEMENT_ID#/',
];

$variables = [];

$componentPage = CComponentEngine::ParseComponentPath(
    $arParams['SEF_FOLDER'],
    $urlTemplates,
    $variables
);

if (!in_array($componentPage, ['list', 'detail'], true)) {
    \Bitrix\Iblock\Component\Tools::process404(
        'Страница не найдена.',
        true,   // ERROR_404
        true,   // HTTP-статус 404
        false   // НЕ подменять всю страницу файлом /404.php и не вызывать die()
    );

    return;
}

if ($componentPage === 'detail') {
    $elementId = (string)($variables['ELEMENT_ID'] ?? '');

    if (!ctype_digit($elementId) || (int)$elementId <= 0) {
        \Bitrix\Iblock\Component\Tools::process404(
            'Страница не найдена.',
            true,
            true,
            false
        );

        return;
    }

    $variables['ELEMENT_ID'] = (int)$elementId;
}

$arResult = [
    'FOLDER' => $arParams['SEF_FOLDER'],
    'URL_TEMPLATES' => $urlTemplates,
    'VARIABLES' => $variables,
];

$this->includeComponentTemplate($componentPage);
```

### Что делает код

Для:

```text
/study-news/
```

выбирается:

```php
$componentPage = 'list';
```

Для:

```text
/study-news/15/
```

получаем:

```php
$componentPage = 'detail';

$variables = [
    'ELEMENT_ID' => 15,
];
```

После этого подключается соответствующий файл:

```text
list.php
detail.php
```

### Почему здесь нет `complex.php`

Потому что файл с таким именем не запускается ядром автоматически.

Если разработчик хочет вынести маршрутизацию в отдельный файл, он должен подключить его явно.

В учебном примере это только добавило бы лишнюю косвенность.

### SEF_MODE, VARIABLES и шаблоны URL — как у штатных компонентов

Учебный `component.php` выше разбирает маршрут «руками», потому что так видно механику. В боевом компоненте то же самое делается через настройки SEF, которые описываются в `.parameters.php`:

```php
$arComponentParameters = [
    'PARAMETERS' => [
        // Структуру SEF редактор превращает в поля «SEF_MODE» (Y/N) и «SEF_FOLDER»
        'SEF_MODE' => [
            'list' => [
                'NAME' => 'Страница списка',
                'DEFAULT' => '',
                'VARIABLES' => [],
            ],
            'detail' => [
                'NAME' => 'Страница элемента',
                'DEFAULT' => '#ELEMENT_ID#/',
                'VARIABLES' => ['ELEMENT_ID'],
            ],
        ],
        // Только для SEF_MODE = N: имена переменных в query-строке
        'VARIABLE_ALIASES' => [
            'ELEMENT_ID' => ['NAME' => 'ID элемента'],
        ],
        // ... остальные параметры
    ],
];
```

В компоненте это превращается в такой каркас (упрощённый `bitrix:news`):

```php
$arDefaultUrlTemplates404 = [
    'list' => '',
    'detail' => '#ELEMENT_ID#/',
];

// Пользовательские шаблоны поверх значений по умолчанию
$urlTemplates = CComponentEngine::makeComponentUrlTemplates(
    $arDefaultUrlTemplates404,
    $arParams['SEF_URL_TEMPLATES'] ?? []
);

$variables = [];
$engine = new CComponentEngine($this);

if ($arParams['SEF_MODE'] === 'Y') {
    $componentPage = $engine->guessComponentPath(
        $arParams['SEF_FOLDER'],
        $urlTemplates,
        $variables
    );

    if ($componentPage === false) {
        \Bitrix\Iblock\Component\Tools::process404('Страница не найдена', true, true, false);
        return;
    }
} else {
    // Режим без ЧПУ: те же переменные берутся из $_REQUEST по алиасам
    CComponentEngine::initComponentVariables(
        false,
        ['ELEMENT_ID'],
        $arParams['VARIABLE_ALIASES'] ?? [],
        $variables
    );

    $componentPage = isset($variables['ELEMENT_ID']) ? 'detail' : 'list';
}
```

Правила сопоставления адреса (`guessComponentPath()`):

1. Запрошенный адрес должен начинаться с `SEF_FOLDER`, иначе `false`. Компонент, включённый на странице вне своей папки, не найдёт ни одного маршрута — это не баг.
2. `SEF_FOLDER` нормализуется: `\` → `/`, дубли `//` схлопываются, крайние слэши добавляются.
3. `#ИМЯ#` в шаблоне превращается в `([^/]+?)` — одна часть пути без слэшей.
4. Шаблон, оканчивающийся на `/`, дополнительно матчит `index.php` (запрос `/study-news/index.php` = `/study-news/`).
5. Шаблоны сортируются: сначала без переменных, потом с переменными, потом «жадные» (`addGreedyPart`). Поэтому пустой шаблон `''` для списка не перехватывает детальные адреса.
6. Переменные возвращаются **строками** из URL — тип, диапазон и существование объекта проверяет ваш код.

Для вложенных разделов (`/news/section/code/`) нужны:

```php
$engine->addGreedyPart('#SECTION_CODE_PATH#');
$engine->setResolveCallback(['CIBlockFindTools', 'resolveComponentEngine']);
```

`addGreedyPart` разрешает переменной содержать `/`, а колбэк `CIBlockFindTools::resolveComponentEngine` доопределяет страницу по данным инфоблока (так `bitrix:news` отличает раздел от элемента).

Для сборки ссылок из шаблона используется обратная операция:

```php
$detailUrl = CComponentEngine::makePathFromTemplate(
    $arResult['FOLDER'] . $arResult['URL_TEMPLATES']['detail'],
    ['ELEMENT_ID' => $item['ID']]
);
```

`makePathFromTemplate()` дополнительно подставляет `#SITE_DIR#`, `#SITE#`, `#SERVER_NAME#`. Своя `str_replace('#ELEMENT_ID#', ...)` в шаблоне работает, но теряет эти подстановки и не экранирует значение.

---

## 11.5. Страница списка: `templates/.default/list.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 */

$component = $this->getComponent();

$APPLICATION->IncludeComponent(
    'bitrix:news.list',
    '.default',
    [
        'IBLOCK_TYPE' => $arParams['IBLOCK_TYPE'],
        'IBLOCK_ID' => $arParams['IBLOCK_ID'],

        'NEWS_COUNT' => $arParams['NEWS_COUNT'],

        'SORT_BY1' => 'ACTIVE_FROM',
        'SORT_ORDER1' => 'DESC',
        'SORT_BY2' => 'ID',
        'SORT_ORDER2' => 'DESC',

        'CHECK_DATES' => 'Y',

        'FIELD_CODE' => [
            'ID',
            'NAME',
            'PREVIEW_TEXT',
            'PREVIEW_PICTURE',
            'DATE_ACTIVE_FROM',
        ],

        'PROPERTY_CODE' => [],

        'DETAIL_URL' => $arResult['FOLDER']
            . $arResult['URL_TEMPLATES']['detail'],

        'DISPLAY_DATE' => 'Y',
        'DISPLAY_NAME' => 'Y',
        'DISPLAY_PICTURE' => 'Y',
        'DISPLAY_PREVIEW_TEXT' => 'Y',

        'SET_TITLE' => 'N',
        'INCLUDE_IBLOCK_INTO_CHAIN' => 'N',
        'ADD_SECTIONS_CHAIN' => 'N',

        'CACHE_TYPE' => $arParams['CACHE_TYPE'],
        'CACHE_TIME' => $arParams['CACHE_TIME'],
        'CACHE_GROUPS' => 'Y',

        'DISPLAY_TOP_PAGER' => 'N',
        'DISPLAY_BOTTOM_PAGER' => 'Y',
        'PAGER_TITLE' => 'Новости',
        'PAGER_SHOW_ALWAYS' => 'N',
        'PAGER_TEMPLATE' => '.default',
    ],
    $component
);
```

### Зачем четвёртый аргумент

```php
$component
```

Он передаёт дочернему компоненту ссылку на родительский.

Это участвует в механизмах вложенности и поиска шаблонов. Поэтому внутри комплексного компонента четвёртый аргумент не следует бездумно опускать.

---

## 11.6. Детальная страница: `templates/.default/detail.php`

```php
<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 */

$component = $this->getComponent();

$APPLICATION->IncludeComponent(
    'bitrix:news.detail',
    '.default',
    [
        'IBLOCK_TYPE' => $arParams['IBLOCK_TYPE'],
        'IBLOCK_ID' => $arParams['IBLOCK_ID'],

        'ELEMENT_ID' => $arResult['VARIABLES']['ELEMENT_ID'],

        'CHECK_DATES' => 'Y',

        'FIELD_CODE' => [
            'ID',
            'NAME',
            'DETAIL_TEXT',
            'DETAIL_PICTURE',
            'DATE_ACTIVE_FROM',
        ],

        'PROPERTY_CODE' => [],

        'DISPLAY_DATE' => 'Y',
        'DISPLAY_NAME' => 'Y',
        'DISPLAY_PICTURE' => 'Y',

        'SET_TITLE' => 'Y',
        'INCLUDE_IBLOCK_INTO_CHAIN' => 'N',
        'ADD_SECTIONS_CHAIN' => 'N',
        'ADD_ELEMENT_CHAIN' => 'Y',

        'CACHE_TYPE' => $arParams['CACHE_TYPE'],
        'CACHE_TIME' => $arParams['CACHE_TIME'],
        'CACHE_GROUPS' => 'Y',

        'SET_STATUS_404' => 'Y',
        'SHOW_404' => 'Y',
        'MESSAGE_404' => 'Новость не найдена.',
    ],
    $component
);
```

Синтаксически корректный ID ещё не означает, что элемент существует.

Проверку существования, активности и доступности выполняет дочерний компонент.

**Маршрутизация и поиск записи в базе — разные этапы.**

### 404: что означают аргументы `Tools::process404()`

Сигнатура (`iblock/lib/component/tools.php`):

```php
public static function process404(
    $message = "",            // текст, выводится через bitrix:system.show_message
    $defineConstant = true,   // определить константу ERROR_404
    $setStatus = true,        // отдать HTTP-статус 404
    $showPage = false,        // подменить рабочую область файлом /404.php и вызвать die()
    $pageFile = ""            // свой файл вместо /404.php
) {
```

Практические следствия:

- `$showPage = true` вызывает `RestartWorkarea()`, подключает `/404.php` и делает `die()` — код после вызова не выполнится, подвал страницы тоже. Для компонента, вставленного в обычную страницу, чаще нужен `false`;
- `$setStatus = true` — единственный способ отдать реальный HTTP 404 из компонента. Без него поисковик получит 200 с текстом «не найдено» и проиндексирует мусорные адреса;
- константа `ERROR_404` используется шаблоном сайта и другими компонентами, чтобы не переопределять статус позже;
- перед вызовом обязателен `abortResultCache()`: 404 не должен попасть в кеш;
- если нужен свой файл — пятый аргумент, путь относительно корня сайта.

```php
if ($componentPage === false) {
    $this->abortResultCache();
    \Bitrix\Iblock\Component\Tools::process404(
        'Страница не найдена.',
        true,                 // ERROR_404
        $arParams['SET_STATUS_404'] === 'Y',
        $arParams['SHOW_404'] === 'Y',
        $arParams['FILE_404']
    );
    return;
}
```

Проверять надо не текст на странице, а статус:

```bash
curl -I http://site/study-news/15abc/
# HTTP/1.1 404 Not Found
```

---

## 11.7. Публичная страница раздела

На странице `/study-news/index.php`:

```php
<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Новости');

$APPLICATION->IncludeComponent(
    'study:news',
    '.default',
    [
        'IBLOCK_TYPE' => 'content',
        'IBLOCK_ID' => 3,
        'SEF_FOLDER' => '/study-news/',
        'NEWS_COUNT' => 10,
        'CACHE_TYPE' => 'A',
        'CACHE_TIME' => 3600,
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
```

---

## 11.8. Настройка URL rewriting

Чтобы запрос:

```text
/study-news/15/
```

попал на:

```text
/study-news/index.php
```

нужно настроить правило обработки адресов.

В конфигурации URL rewriting Bitrix соответствующая запись имеет вид:

```php
[
    'CONDITION' => '#^/study-news/#',
    'RULE' => '',
    'ID' => 'study:news',
    'PATH' => '/study-news/index.php',
    'SORT' => 100,
]
```

Это **одна запись** массива `$arUrlRewrite`, а не содержимое всего файла.

Не заменяй существующий `urlrewrite.php` одной этой записью: там могут быть правила остальных разделов сайта.

Также должна работать стандартная настройка веб-сервера, передающая несуществующие физические пути обработчику Bitrix.

### Важно понимать цепочку

```text
Запрос браузера
      ↓
Веб-сервер
      ↓
URL rewriting Bitrix
      ↓
/study-news/index.php
      ↓
Комплексный компонент
      ↓
ParseComponentPath()
      ↓
Шаблон list.php или detail.php
      ↓
Дочерний компонент
```

`ParseComponentPath()` не заменяет настройку веб-сервера и URL rewriting.

---

## 11.9. Почему родительский компонент мы не кешируем целиком

В этом примере родитель занимается маршрутизацией, а данные кешируют дочерние компоненты.

Если добавить внешний кеш без анализа, можно получить проблемы:

- пагинация не учтена во внешнем ключе;
- разные детальные страницы используют один внешний результат;
- внутренний кеш сброшен, а внешний продолжает отдавать старый HTML;
- действия дочерних компонентов не выполняются из-за внешнего попадания в кеш.

Вложенное кеширование допустимо, но требует понимания всех зависимостей.

Для учебного комплексного компонента кеширование дочерних блоков — более прозрачная модель.

---

# 12. Как переопределять шаблоны дочерних компонентов

Для комплексного компонента можно разместить шаблон дочернего компонента внутри его собственного шаблона.

Например:

```text
/local/templates/main/components/study/news/.default/
├── list.php
├── detail.php
└── bitrix/
    ├── news.list/
    │   └── .default/
    │       └── template.php
    └── news.detail/
        └── .default/
            └── template.php
```

Так внешний вид дочерних компонентов становится частью шаблона всего раздела.

Именно поэтому важна передача родителя четвёртым аргументом `IncludeComponent()`.

---

# 13. Ошибки и безопасность

## 13.1. Прямой доступ к служебным файлам

В файлах компонента используется:

```php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
```

Это проверка запуска в контексте Bitrix.

**Она не является:**

- проверкой прав пользователя;
- CSRF-защитой;
- валидацией параметров;
- защитой от SQL-инъекций.

Каждый механизм решает свою задачу.

---

## 13.2. XSS

Небезопасно:

```php
<h2><?= $item['NAME'] ?></h2>
```

Для исходного текста:

```php
<h2><?= htmlspecialcharsbx($item['NAME']) ?></h2>
```

Для HTML-контента нужен отдельный контракт доверия:

- кто может его редактировать;
- разрешён ли HTML;
- проходит ли он очистку;
- где именно будет выведен.

Не следует считать любой текст из БД безопасным только потому, что он хранится в инфоблоке.

---

## 13.3. Валидация и экранирование — не одно и то же

**Валидация** проверяет допустимость значения:

```php
$id > 0
```

**Нормализация** приводит его к ожидаемому виду:

```php
$id = (int)$value;
```

**HTML-экранирование** подготавливает значение для HTML:

```php
htmlspecialcharsbx($value);
```

**SQL-параметризация** или корректное использование API БД защищает SQL-контекст.

Нельзя защитить SQL-запрос вызовом `htmlspecialchars()`.

---

## 13.4. Параметры сортировки

Если разрешаешь настраивать сортировку, используй белый список:

```php
$allowedFields = [
    'ID',
    'NAME',
    'ACTIVE_FROM',
    'SORT',
];

$sortBy = (string)($arParams['SORT_BY'] ?? 'ACTIVE_FROM');

if (!in_array($sortBy, $allowedFields, true)) {
    $sortBy = 'ACTIVE_FROM';
}
```

Список в `.parameters.php` не является серверной гарантией корректности входных данных.

---

## 13.5. Кеш как канал утечки данных

Кеш компонента — самая частая причина «пользователь видит чужое», и это не SQL-инъекция, а логика.

| Что сделано | Что произойдёт | Как исправить |
|---|---|---|
| фильтр берётся из `$_GET`, а `startResultCache()` без доп. ключа | все посетители получают выборку первого | фильтр в `$additionalCacheID` или `CACHE_TIME = 0` при непустом фильтре |
| выборка с `CHECK_PERMISSIONS => Y`, но `CACHE_GROUPS = N` | пользователь без прав получает список с элементами, закешированный админом | `CACHE_GROUPS = Y` или группы в ключе |
| карточка «мои заявки» в общем кеше | заявки одного пользователя видны другому в той же группе | ID пользователя в ключе либо `CACHE_TYPE = N` |
| результат зависит от `$_SESSION` (город, валюта, язык) | второй посетитель видит чужой город | значение сессии в `$additionalCacheID` |
| `SetTitle()`/`SetPageProperty()` в `template.php` | на горячем кеше заголовок от предыдущей страницы | перенести в код после `startResultCache()` или в `component_epilog.php` |

Правило: **в ключ кеша входит только то, что вы положили руками.** Всё, что пришло из `$_GET`, `$_POST`, `$_SESSION`, `$USER` или контекста, надо либо добавить в ключ, либо выключить кеш.

### SEF_FOLDER и путь из настроек

`SEF_FOLDER` попадает в `DETAIL_PAGE_URL`, в ссылки навигации и в `guessComponentPath()`. Значение приходит из настроек компонента, но нормализовать его всё равно надо:

```php
$folder = str_replace('\\', '/', trim($folder));
$folder = preg_replace('#/+#', '/', $folder);
if (preg_match('#(^|/)\.\.(/|$)#', $folder)) {
    return '/';       // обход каталога в ссылках: '/../..' 
}
```

Последствие без проверки: ссылки в разметке уводят за пределы раздела, а если шаблон подставляет значение в `header('Location: ...')` — это уже open redirect.

### Что ещё проверять в компоненте

- **AJAX-ветка без проверки прав.** Компонент отдаёт JSON по `X-Requested-With` — данные при этом должны проверяться так же, как в обычном запросе. Часто «ajax» пишут как отдельный файл без `prolog`, и там теряются и права, и `check_bitrix_sessid()`.
- **Текст ошибки наружу.** `$exception->getMessage()` в JSON-ответе выдаёт пути, SQL и имена таблиц.
- **`print_r($_REQUEST)` в шаблоне** при отладке: уезжает в кеш и в публичный HTML.

## 13.6. Формы и CSRF

Для обычной POST-формы Bitrix:

```php
<form method="post">
    <?= bitrix_sessid_post() ?>

    <input type="text" name="MESSAGE">

    <button type="submit">
        Отправить
    </button>
</form>
```

Проверка на сервере:

```php
<?php

$request = \Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->isPost()) {
    if (!check_bitrix_sessid()) {
        ShowError('Сессия истекла. Обновите страницу.');
    } else {
        // Серверная валидация и обработка.
    }
}
```

Это иллюстрация CSRF-проверки, а не готовая форма обратной связи.

Полноценной форме также нужны:

- проверка обязательных полей;
- ограничения длины;
- проверка формата email;
- ограничение частоты отправки;
- защита от повторных отправок;
- корректная работа с почтовыми событиями;
- правила хранения персональных данных.

Проверка времени заполнения — только дополнительный антиспам-сигнал. Она не заменяет CSRF-защиту и ограничение частоты запросов.

---

## 13.7. Ошибки пользователю и разработчику

Пользователю:

```text
Не удалось загрузить новости.
```

В журнал:

```text
Технические подробности исключения.
```

Не стоит выводить посетителю:

- SQL-запросы;
- трассировки;
- внутренние пути;
- конфигурацию;
- токены и секреты.

При этом и в журнал нельзя бездумно записывать пароли, персональные данные и полное содержимое пользовательских запросов.

---

# 14. Производительность

## 14.1. Выбирай только нужные поля

Плохо:

```php
['*']
```

когда нужен только заголовок и дата.

Лучше:

```php
[
    'ID',
    'IBLOCK_ID',
    'NAME',
    'ACTIVE_FROM',
]
```

Но учитывай поля, необходимые для формирования URL и связанной логики.

---

## 14.2. Не путай ограничение количества и пагинацию

```php
['nTopCount' => 10]
```

означает «получить первые десять».

Это не полноценная постраничная навигация.

Для пагинации нужно:

- выбрать соответствующий режим выборки;
- получить навигационный результат;
- вывести навигацию;
- учитывать текущую страницу в зависимостях кеша.

---

## 14.3. Следи за N+1

Типичный антипаттерн:

```text
1 запрос списка
+
по 1 запросу связанных данных для каждого элемента
```

Итого для 100 элементов — потенциально 101 запрос.

Не каждый вызов API обязательно создаёт отдельный SQL-запрос: внутри могут работать кеши. Но структуру обращений всё равно нужно анализировать.

В нашем примере обработка картинок сделана максимально понятно. На больших списках нужно отдельно оценивать получение файлов, ресайзы и повторное использование результатов.

---

## 14.4. Не лечи всё кешем

Кеш не отменяет:

- неэффективные запросы;
- отсутствие ограничений;
- неверные права;
- ошибки фильтрации;
- чрезмерный объём результата.

Хорошая последовательность:

1. Корректная логика.
2. Разумная выборка.
3. Проверка прав.
4. Понятные зависимости.
5. Кеширование.
6. Измерение результата.

---

# 15. D7: что нужно знать для собеседования

**D7 — современная архитектурная часть ядра Bitrix**, включающая:

- пространства имён;
- загрузчик модулей;
- классы запросов и контекста;
- ORM;
- события;
- исключения;
- другие системные API.

Пример подключения модуля:

```php
use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    // Обработка ошибки.
}
```

Пример ORM для инфоблоков как сущностей настроек:

```php
use Bitrix\Iblock\IblockTable;

$result = IblockTable::getList([
    'select' => [
        'ID',
        'NAME',
        'CODE',
    ],
    'filter' => [
        '=ACTIVE' => 'Y',
    ],
    'order' => [
        'SORT' => 'ASC',
        'ID' => 'ASC',
    ],
]);

while ($row = $result->fetch()) {
    // Работа с данными.
}
```

Не путай:

- `IblockTable` — инфоблоки;
- элементы инфоблоков;
- свойства элементов;
- специализированные ORM-сущности конкретного инфоблока.

Класс вроде:

```php
Bitrix\Iblock\Elements\ElementNewsTable
```

не гарантирован в любом проекте. Его доступность зависит от настроек API инфоблока и соответствующего механизма установленной версии.

Также нельзя предполагать, что произвольный ORM-запрос автоматически повторяет проверки активности, прав и бизнес-ограничений стандартного компонента.

---

# 16. AJAX-компоненты и подписанные параметры

«AJAX-компонент» — не отдельный тип компонента. Это тот же компонент, который на запрос с заголовком `X-Requested-With: XMLHttpRequest` (или своим маркером) возвращает не HTML страницы, а JSON или фрагмент разметки.

## 16.1. Кто решает, что вернуть

Решение принимает сервер по признаку запроса, а не клиент по скрытому полю формы:

```php
$request = \Bitrix\Main\Context::getCurrent()->getRequest();

if ($request->isPost() && $request->getPost('study_feedback') === 'Y') {
    $this->processPost();

    if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
        $this->sendJson();
        return;
    }
}
```

Почему не скрытое поле `ajax=Y`:

- клиент может попросить JSON при обычной отправке и получить на экране `{"success":false}`;
- при падении JS форма обычным POST уйдёт с `ajax=Y` и пользователь останется с голым JSON;
- признак в заголовке выставляет сам браузер (`fetch`, `XMLHttpRequest`), и он не попадает в HTML-форму.

Ответ компонента:

```php
private function sendJson(): void
{
    global $APPLICATION;

    $APPLICATION->RestartBuffer();                       // выбросить накопленный HTML
    header('Content-Type: application/json; charset=UTF-8');

    echo \Bitrix\Main\Web\Json::encode([
        'success' => $this->arResult['SUCCESS'],
        'errors' => array_values($this->errors),
    ]);

    die();                                               // без эпилога и подвала
}
```

`RestartBuffer()` обязателен: без него в ответ попадут `<html>`, шапка сайта и предупреждения ядра, и `JSON.parse` упадёт.

## 16.2. Что нельзя терять в AJAX-ветке

| Проверка | Почему в AJAX её забывают |
|---|---|
| `check_bitrix_sessid()` | «это же не форма, а запрос из JS» — и любой сайт дёргает эндпоинт |
| права пользователя | `$USER->IsAuthorized()` и проверка прав на инфоблок/раздел |
| валидация типов | `$_POST` в JSON-ветке обрабатывают «как получится» |
| CSRF-токен в самом запросе | `fetch` без `credentials: 'same-origin'` теряет куки сессии |
| отсутствие кеша | `CACHE_TYPE = N` для формы; иначе ответ закешируется вместе с ошибками |

Отдельный `ajax.php` в корне сайта без `prolog_before.php` — типовая дыра: в нём нет ни сессий, ни `$USER`, ни проверок, зато есть доступ к `$_REQUEST` и `Loader`. Если очень нужен отдельный файл, он начинается с:

```php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
```

и дальше повторяет те же проверки, что компонент.

## 16.3. Подписанные параметры

Проблема: компоненту нужно отдать в JS часть своих параметров (`IBLOCK_ID`, `NEWS_COUNT`, `FILTER_NAME`), чтобы клиент мог запросить следующую страницу. Если передать их открытым текстом, клиент может подменить `IBLOCK_ID` и получить чужой инфоблок.

Механизм «подписанных параметров» (`CBitrixComponent`, начиная с 20.5.x — `[проверить]` в вашей сборке; в 20.200.300 методы уже есть):

```php
class StudyNewsListComponent extends CBitrixComponent
{
    // Какие параметры ядро подпишет и отдаст шаблону
    protected function listKeysSignedParameters(): array
    {
        return ['IBLOCK_ID', 'NEWS_COUNT', 'SORT_BY1', 'SORT_ORDER1'];
    }
}
```

`includeComponent()` при вызове компонента делает:

```php
$keysToExport = $component->listKeysSignedParameters();
if ($keysToExport)
{
    $component->storeSignedParameters(array_intersect_key($arParams, array_combine($keysToExport, $keysToExport)));
}
```

`storeSignedParameters()` вызывает `\Bitrix\Main\Component\ParameterSigner::signParameters($componentName, $params)`: сериализует массив, кодирует base64 и подписывает `\Bitrix\Main\Security\Sign\Signer` с солью из имени компонента.

В шаблоне строка забирается так:

```php
<div class="study-news"
     data-signed-params="<?= htmlspecialcharsbx((string)$this->getComponent()->getSignedParameters()) ?>">
```

На сервере в AJAX-ветке параметры восстанавливаются и проверяются подписью:

```php
use Bitrix\Main\Component\ParameterSigner;
use Bitrix\Main\Security\Sign\BadSignatureException;

$signed = (string)$request->getPost('signed_params');

try {
    $params = ParameterSigner::unsignParameters('study:news.list', $signed);
} catch (BadSignatureException $exception) {
    // Подпись не сходится: параметры подменены или взяты из другого компонента
    return;
}

$iblockId = (int)($params['IBLOCK_ID'] ?? 0);
```

Что это даёт и чего не даёт:

- **даёт**: клиент не может изменить параметры, не сломав подпись; подпись привязана к имени компонента;
- **не даёт**: не заменяет проверку прав. Подписанный `IBLOCK_ID` — это тот же инфоблок, который был в настройках, но доступ к данным всё равно проверяется в выборке;
- **не даёт**: не защищает от повторного использования строки — она живёт, пока живёт ключ подписи. Для одноразовости нужен свой nonce в сессии.

## 16.4. Что проверить в своей сборке

- есть ли `ParameterSigner` (`/bitrix/modules/main/lib/component/parametersigner.php`);
- поддерживает ли `HttpRequest::getHeader('X-Requested-With')` — в старых сборках заголовок читался только из `$_SERVER['HTTP_X_REQUESTED_WITH']`;
- как ведёт себя `RestartBuffer()` в вашем шаблоне сайта (в некоторых шаблонах он не очищает буфер композита).

Проверка простая: отправьте форму без JS (отключите его в браузере) и с JS. Поведение должно быть одинаково корректным, отличаться только форматом ответа.

---

# 17. Локализация

В учебных примерах строки записаны прямо в коде, чтобы не увеличивать количество файлов.

В сопровождаемом компоненте обычно используют языковые сообщения.

Пример в `class.php`:

```php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

ShowError(Loc::getMessage('STUDY_NEWS_MODULE_ERROR'));
```

Соответствующий файл:

```text
/lang/ru/class.php
```

Содержимое:

```php
<?php

$MESS['STUDY_NEWS_MODULE_ERROR'] =
    'Модуль инфоблоков недоступен.';
```

Зачем это нужно:

- перевод интерфейса;
- единообразие;
- отделение сообщений от логики;
- удобство сопровождения.

## 17.1. Три места, где живут сообщения

| Файл | Что в нём | Кто загружает |
|---|---|---|
| `/lang/ru/class.php` | сообщения компонента (`ShowError`, ошибки) | `Loc::loadMessages(__FILE__)` в `class.php`, либо `$this->includeComponentLang()` |
| `/lang/ru/.parameters.php` | подписи в интерфейсе настроек | ядро при открытии настроек компонента |
| `/templates/<шаблон>/lang/ru/template.php` | тексты шаблона | `CBitrixComponentTemplate::IncludeLangFile()` автоматически перед `template.php` |

Файлы компонента подключаются не вручную, а методом `includeComponentLang()`:

```php
protected function onIncludeComponentLang(): void
{
    // вызывается ядром ДО onPrepareComponentParams()
    $this->includeComponentLang('class.php');
}
```

Если метод не переопределён, ядро грузит `lang/<язык>/component.php` — поэтому сообщения из `class.php` в классовых компонентах либо грузят явно, либо `Loc::loadMessages(__FILE__)` в начале файла.

## 17.2. Что проверять в языке

- Коды — заглавными, с префиксом компонента (`STUDY_NEWS_ERR_NO_IBLOCK`), иначе `GetMessage()` вернёт код вместо текста, и это вылезет в интерфейс.
- Подстановки — вторым аргументом `Loc::getMessage($code, ['#FIELD#' => $name])`; `str_replace` руками делать не нужно.
- В шаблоне работает `GetMessage()`, в классе — `Loc::getMessage()`; это одна и та же таблица сообщений, но разные точки входа.
- Файл `lang/ru/.parameters.php` подключается ядром, `Loc::loadMessages(__FILE__)` в `.parameters.php` не нужен.
- Пропущенный ключ не падает: `Loc::getMessage('НЕТ_ТАКОГО')` вернёт `НЕТ_ТАКОГО`. Поэтому тесты на тексты бесполезны, а на код — осмысленны.

---

# 18. Что могут попросить на собеседовании

Ниже — вопросы и ответы в форме, близкой к устной.

Уровни: на **Junior** ждут определения и понимание «зачем», на **Middle** — механику и типичные ошибки, на **Senior** — состав ключа кеша, порядок вызовов ядра и последствия решений.

| Уровень | Вопросы |
|---|---|
| Junior | 1–7, 9, 16, 21 |
| Middle | 8, 10–15, 19, 20, 22, 26, 27, 28, 33 |
| Senior | 17, 18, 23–25, 29–32, 34–36 |

Что реально спрашивают на вакансиях Bitrix24 Box: вопросы 8, 11, 14, 17, 23, 26, 29–31 — почти в каждой первой технической беседе.

## 1. Что такое компонент Bitrix?

Переиспользуемая единица функциональности, которая принимает параметры, выполняет логику, готовит результат и обычно выводит его через шаблон.

## 2. Чем `$arParams` отличается от `$arResult`?

`$arParams` — входные настройки компонента.

`$arResult` — подготовленные данные для представления и дальнейшей работы компонента.

## 3. Где размещать собственные компоненты?

Обычно в:

```text
/local/components/<vendor>/<component>/
```

Это отделяет собственный код от поставки продукта.

## 4. Чем простой компонент отличается от комплексного?

Простой решает отдельную задачу. Комплексный организует многостраничный раздел, маршрутизацию и работу дочерних компонентов.

Количество альтернативных шаблонов не является главным критерием.

## 5. Какие варианты реализации компонента есть?

Процедурный через `component.php` и классовый через наследование от `CBitrixComponent`.

Если в папке есть `class.php` с наследником `CBitrixComponent`, ядро использует его, а `component.php` не подключает вообще. Держать оба файла «на всякий случай» бессмысленно.

В классовом варианте основную логику обычно размещают в `executeComponent()`.

## 6. Для чего `onPrepareComponentParams()`?

Для проверки и нормализации параметров до основной логики компонента.

## 7. Что делает `.parameters.php`?

Описывает интерфейс настройки компонента в редакторе. Не заменяет runtime-валидацию.

## 8. Что делает `startResultCache()`?

Строит ключ кеша (`getCacheID()`), ищет запись, при попадании печатает кешированный HTML, восстанавливает `$arResult` по сохранённым ключам, регистрирует `additionalCSS`/`additionalJS` и динамические области, вызывает `component_epilog.php` — и возвращает `false`. При промахе возвращает `true` и запускает управляемый кеш (теги), чтобы результат можно было записать в `endResultCache()`.

## 9. Нужно ли кешировать пустую выборку?

Обычно да. Пустой список — корректный результат. Важно обеспечить его актуализацию при появлении данных.

## 10. Когда нужен `abortResultCache()`?

Когда начатый результат не должен быть сохранён: например, при ошибке получения данных.

## 11. Зачем теги кеша?

Чтобы инвалидировать кеш по зависимостям, не ожидая истечения времени хранения.

## 12. Что такое дополнительный идентификатор кеша?

Способ учесть контекст, влияющий на результат: фильтр, права, номер страницы и другие зависимости.

## 13. Для чего `SetResultCacheKeys()`?

Для сохранения выбранных ключей результата, необходимых после восстановления компонента из кеша, например в эпилоге.

## 14. Выполняется ли `result_modifier.php` на каждом запросе?

При стандартном кешировании — нет. Он выполняется при построении шаблона, а не при каждом попадании в кеш.

## 15. Для чего `component_epilog.php`?

Для действий после вывода компонента, в том числе когда использован кешированный результат.

## 16. Как изменить HTML стандартного компонента?

Переопределить его шаблон, обычно в каталоге шаблона сайта.

## 17. Что передаётся четвёртым аргументом `IncludeComponent()`?

Родительский компонент. Это важно для вложенных компонентов и механизмов поиска их шаблонов.

## 18. Что делает `ParseComponentPath()`?

Сопоставляет адрес с шаблонами маршрутов и извлекает переменные.

Он не получает элемент из БД и не заменяет URL rewriting.

## 19. Чем `ACTIVE` отличается от `ACTIVE_DATE`?

Первое — флаг активности, второе — проверка периода активности относительно текущей даты.

## 20. Почему нельзя делать запросы в шаблоне?

Это смешивает получение данных с представлением, затрудняет тестирование, переиспользование и анализ кеширования.

## 21. Является ли `CIBlockElement::GetList()` недопустимым устаревшим подходом?

Нет. Нужно учитывать задачу и возможности API. Использование D7 не является самоцелью.

## 22. Достаточно ли `htmlspecialchars()` для безопасности формы?

Нет. Нужны разные меры: валидация, CSRF-защита, проверка прав, безопасная работа с БД и экранирование для конкретного контекста вывода.

## 23. Почему данные могут попасть не тому пользователю?

Одна из причин — общий кеш результата, который на самом деле зависит от пользователя или его прав.

## 24. Чем кеш компонента отличается от композита?

Кеш компонента сокращает повторное выполнение его логики. Композит ускоряет выдачу страницы через статический HTML и динамические области.

## 25. Что проверять, если изменения не видны на сайте?

Последовательно:

1. Исполняется ли нужный компонент.
2. Выбран ли нужный шаблон.
3. Нет ли переопределения в шаблоне сайта.
4. Какой слой кеша отдаёт результат.
5. Работает ли инвалидация.
6. Не мешает ли композит, внешний кеш или CDN.
7. Не изменяются ли данные в другом окружении или инфоблоке.

Просто «очистить весь кеш» — полезный диагностический шаг, но не объяснение причины.

## 26. Что входит в ключ кеша компонента по умолчанию?

`SITE_ID`, `LANGUAGE_ID`, шаблон сайта, имя компонента, имя шаблона, все `$arParams` (кроме `~`-ключей), смещение таймзоны пользователя, значение второго аргумента `startResultCache()` и номер вызова компонента на странице (`__currentCounter`). Не входят: `$_GET`, `$_POST`, `$_SESSION`, `$USER`, текущий URL, данные БД.

## 27. Компонент вызван на странице дважды с одинаковыми параметрами — будет коллизия кеша?

Нет. В ключ добавляется `__currentCounter` — порядковый номер вызова этого компонента на странице. Второй вызов получит отдельную запись.

## 28. Чем `$arParams['X']` отличается от `$arParams['~X']`?

`X` — значение после `htmlspecialcharsEx()`, если строка содержала `; & < > "`. `~X` — исходное значение. Экранирование делает `__prepareComponentParams()` уже после `onPrepareComponentParams()`; в ключ кеша попадают только не-`~` значения.

## 29. Что происходит с `$arResult` после `includeComponentTemplate()`?

Он урезается до ключей из `setResultCacheKeys()`, и только потом пишется в кеш. `template.php` при этом видит полный результат, а `component_epilog.php` и код после `includeComponentTemplate()` — урезанный.

## 30. Что не так с `setResultCacheKeys()` после `includeComponentTemplate()`?

Ничего не произойдёт: урезание и запись в кеш уже выполнены внутри `showComponentTemplate()`. В кеш попадёт весь `$arResult`, эпилог получит все поля.

## 31. Сколько раз выполняется `component_epilog.php` и когда?

На промахе кеша — один раз, после шаблона. На попадании — изнутри `startResultCache()`, то есть до кода после блока кеша; если компонент рендерит шаблон и на попадании, эпилог выполнится второй раз. Писать его нужно идемпотентно.

## 32. В компоненте есть и `class.php`, и `component.php`. Что выполнится?

`class.php`: `__getClassForPath()` ищет наследника `CBitrixComponent`, и если нашёл — `component.php` не подключается. Если `class.php` есть, но наследника нет, ядро перейдёт к `component.php`; если нет и его — `'...' is not a component`.

## 33. `CACHE_TYPE = 'A'` и `'Y'` — в чём разница?

`A` — авто: кешировать, если в настройках модуля `main` не выключено глобальное автокеширование. `Y` — кешировать всегда. `N` — не кешировать. Любое другое значение `includeComponent()` приводит к `A`.

## 34. Почему в компоненте с кешем ломается пагинация и как это лечится?

Номер страницы читается из `$_GET`, а `$_GET` не входит в ключ кеша, поэтому все страницы получают HTML первой. Лечится передачей `CDBResult::GetNavParams($navStartParams)` вторым аргументом `startResultCache()` и `CPageOption::SetOptionString('main', 'nav_page_in_session', 'N')`, чтобы страница не запоминалась в сессии.

## 35. Чем `CACHE_FILTER` отличается от `CACHE_GROUPS`?

`CACHE_FILTER` определяет, попадает ли фильтр в ключ кеша: при `N` и непустом фильтре компонент вообще отключает кеш (`CACHE_TIME = 0`), при `Y` — создаёт отдельную запись на каждое сочетание. `CACHE_GROUPS` добавляет группы пользователя: `N` — один кеш для всех.

## 36. Чем опасен `Tools::process404($msg, true, true, true)`?

Четвёртый аргумент `$showPage = true` вызывает `RestartWorkarea()`, подключает `/404.php` и `die()`: остальная страница (включая подвал) не отрендерится, а код после вызова не выполнится. Для компонента внутри обычной страницы нужен `false`, а статус 404 обеспечивает третий аргумент.

---

# 19. Практические задачи

Все решения лежат в `examples/module-1-2/`. Каждая папка самодостаточна:
`component/` — готовая структура для `/local/components/study/<имя>/`, рядом
страница и README с порядком установки и таблицей экспериментов.

| Задача | Папка | Что проверяется |
|---|---|---|
| 1. Простой компонент списка | `practice-1-news-list/` | структура, параметры, выборка с правами, пагинация, кеш с тегом, два шаблона |
| 2. Фильтр и сортировка | `practice-2-news-filter/` | три источника условий, `CACHE_FILTER`, `component_epilog.php` |
| 3. Форма обратной связи | `practice-3-feedback-form/` | POST, `sessid`, капча, throttle, `Event::send`, AJAX-ветка |
| 4. Комплексный компонент | `practice-4-complex-news/` | ЧПУ, `SEF_URL_TEMPLATES`, 404, дочерние компоненты |
| 5. Пять дефектов | `control-task/` | code review по механизмам, а не по вкусу |

## Задача 1. Простой компонент (60–90 мин)

Не копируй код сразу. Порядок:

1. Структура папок и `.description.php`.
2. `.parameters.php`: какие параметры реально нужны, какие — «на будущее» (вторые не добавляй).
3. `class.php`: `onPrepareComponentParams()` → нормализация, `executeComponent()` → проверки, кеш, данные, шаблон.
4. Шаблон: только вывод и экранирование.
5. Кеш: тег инфоблока, `CACHE_GROUPS`, номер страницы в ключе.

Потом открой `practice-1-news-list/component/class.php` и сравни порядок
операций. Критерий: можешь объяснить, почему `startResultCache()` стоит именно
там, где стоит, и что произойдёт, если перенести его на строку выше.

## Задача 2. Фильтр и сортировка (60–90 мин)

Добавь к списку:

- белый список полей сортировки и направления (`ASC`/`DESC`), стабильная вторая сортировка;
- внешний фильтр через `FILTER_NAME` (`$GLOBALS['arrFilter']`);
- фильтр из `$_GET` по разделу и по названию;
- политику кеша: `CACHE_FILTER = Y` → фильтр в ключе; `N` → `CACHE_TIME = 0` при непустом фильтре;
- сброс `PAGEN_1` при смене фильтра.

Эталон: `practice-2-news-filter/`. Проверять по таблице экспериментов в README:
особенно `?q[]=1` и `?section=abc` — оба должны молча игнорироваться, без
warning и без мусора в фильтре.

## Задача 3. Форма обратной связи (90–120 мин)

Требования:

- `bitrix_sessid_post()` в форме, `check_bitrix_sessid()` до чтения POST;
- порядок проверок: sessid → honeypot → подпись времени → throttle → поля → капча;
- длины полей ограничены на сервере;
- отправка через `\Bitrix\Main\Mail\Event::send()`, ошибки — в лог;
- при ошибке данные формы сохраняются, капча обновляется;
- AJAX опционален и не ломает обычную отправку.

Эталон: `practice-3-feedback-form/`. Отдельно разбери, почему `mail()` с
`From: noreply@$_SERVER['HTTP_HOST']` — уязвимость (Host header) и почему
`Event::send()` не гарантирует доставку.

## Задача 4. Комплексный компонент (90–120 мин)

Раздел `/study-news/`: список + детальная, ЧПУ, 404.

Проверь адреса и **статусы**:

| URL | Ожидание |
|---|---|
| `/study-news/` | 200, список |
| `/study-news/15/` | 200, детальная |
| `/study-news/15abc/` | 404 |
| `/study-news/999999999/` | 404 |
| `/study-news/15/extra/` | 404 |

Дополнительно: нормализуй `SEF_FOLDER`, передай родителя четвёртым аргументом
`IncludeComponent()`, проверь, что шаблон дочернего компонента из
`.../study/news/.default/bitrix/news.list/` действительно применяется.

Эталон: `practice-4-complex-news/`.

## Задача 5. Code review: пять дефектов (30–40 мин)

`control-task/broken/` — компонент с пятью дефектами, `control-task/fixed/` —
эталон. Найденное формулируется как «ошибка → причина → последствие →
исправление», с указанием механизма ядра. Разбор — в
`control-task/README.md`; открывать его только после своей версии.

## Критерии приёмки подраздела

1. Написаны и запущены четыре компонента; по каждому пройдена таблица
   экспериментов из README.
2. Можешь без подсказок назвать: что входит в ключ кеша по умолчанию; что
   происходит с `$arResult` после `includeComponentTemplate()`; где ищется
   `component_epilog.php` и когда он выполняется; чем `class.php` отличается от
   `component.php` с точки зрения ядра.
3. Найдены все пять дефектов контрольного задания, у каждого назван механизм.
4. На любой вопрос из раздела «Вопросы для собеседования» отвечаешь своими
   словами, без чтения файла.


---

# 20. План подготовки на семь занятий

| Занятие | Что изучить | Что должно получиться |
|---|---|---|
| 1 | Устройство компонента, параметры, результат | Понимание структуры |
| 2 | Классовый компонент, выборка элементов | Собственный список |
| 3 | Шаблоны, экранирование, переопределение | Два варианта отображения |
| 4 | Кеш, теги, зависимости | Объяснение поведения на попадании и промахе |
| 5 | Комплексный компонент, ЧПУ, 404 | Раздел «список + детальная» |
| 6 | Права, формы, CSRF, производительность | Умение находить опасные решения |
| 7 | Устные вопросы и повторение без подсказки | Краткие уверенные ответы |

Для собеседования важнее не заучить все параметры стандартных компонентов, а понимать:

- где выполняется код;
- что происходит при попадании в кеш;
- кто проверяет права;
- откуда берётся URL;
- где экранируется значение;
- почему выбран именно такой механизм.

---

# 21. Итоговая шпаргалка

```text
Компонент:
    параметры → проверки → данные → результат

Шаблон:
    результат → безопасный HTML

Простой компонент:
    отдельная задача

Комплексный компонент:
    маршрутизация + страницы + дочерние компоненты

.parameters.php:
    интерфейс настроек, не runtime-валидация

onPrepareComponentParams():
    нормализация параметров

startResultCache():
    стандартное кеширование результата

abortResultCache():
    не сохранять некорректный результат

Пустой список:
    обычно кешировать

Тег инфоблока:
    инвалидация при изменении данных

Дополнительный ключ кеша:
    контекст, влияющий на результат

SetResultCacheKeys():
    данные, нужные после восстановления из кеша

result_modifier.php:
    подготовка данных под конкретный шаблон

component_epilog.php:
    действия после вывода, в том числе на кеше

ParseComponentPath():
    распознать маршрут, не получить запись из БД

HTML-экранирование:
    в контексте вывода, с понятным контрактом данных

Права:
    проверять в запросах и учитывать в кешировании

Композит:
    отдельный механизм, не синоним кеша компонента

Ключ кеша по умолчанию:
    сайт + язык + шаблон сайта + компонент + шаблон + все $arParams
    (+ tz, + доп. ключ, + номер вызова); $_GET/$USER — не входят

$arParams[X] / ~X:
    экранированное / исходное значение; в ключ идут только не-~

class.php vs component.php:
    есть наследник CBitrixComponent — component.php не выполняется

$arResult после includeComponentTemplate():
    урезан до setResultCacheKeys(); эпилог видит только эти ключи

component_epilog.php:
    только в папке шаблона; на попадании вызывается из startResultCache()

process404(msg, ERROR_404, HTTP 404, подменить страницу и die, файл):
    четвёртый аргумент = true убивает остальную страницу
```

**Ты готов к базовым вопросам о компонентах, когда можешь самостоятельно написать список с кешированием, собрать раздел «список + детальная» и объяснить поведение каждого из них при пустых данных, ошибке, неизвестном URL и смене пользователя.**