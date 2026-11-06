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

        if ($result === false) {
            throw new \RuntimeException(
                'Не удалось выполнить выборку элементов инфоблока.'
            );
        }

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

При построении шаблона на промахе кеша доступен весь текущий `$arResult`. Но на попадании в кеш он не восстанавливается целиком автоматически в том же смысле: нужные для дальнейших действий ключи следует сохранять явно.

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
$this->setFrameMode(true);
```

В шаблоне эта настройка относится к работе с композитной технологией.

Она:

- не заменяет `startResultCache()`;
- не делает персональные данные автоматически безопасными;
- сама по себе не превращает произвольный блок в корректную динамическую область.

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

Не стоит превращать этот файл в скрытый второй компонент с тяжёлыми запросами и бизнес-правилами.

## 9.2. `component_epilog.php`

Нужен для действий после вывода компонента, включая случаи использования кеша.

Например:

- установить свойства страницы;
- выполнить действия с данными, сохранёнными через `SetResultCacheKeys()`.

Не следует без необходимости выводить там основную разметку компонента: это усложняет понимание кеширования.

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
        true,
        true,
        true
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
            true
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

## 13.5. Формы и CSRF

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

## 13.6. Ошибки пользователю и разработчику

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

# 16. Локализация

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

---

# 17. Что могут попросить на собеседовании

Ниже — вопросы и ответы в форме, близкой к устной.

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

В классовом варианте основную логику обычно размещают в `executeComponent()`.

## 6. Для чего `onPrepareComponentParams()`?

Для проверки и нормализации параметров до основной логики компонента.

## 7. Что делает `.parameters.php`?

Описывает интерфейс настройки компонента в редакторе. Не заменяет runtime-валидацию.

## 8. Что делает `startResultCache()`?

Проверяет доступность действительного кеша и организует формирование нового результата при его отсутствии.

На попадании в кеш возвращает управление так, чтобы код получения данных внутри соответствующей ветки не выполнялся повторно.

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

---

# 18. Практические задачи для закрепления

## Задача 1. Повтори простой компонент

Не копируй сразу весь код.

Сначала напиши:

1. Структуру.
2. Описание.
3. Параметры.
4. Класс.
5. Выборку.
6. Шаблон.
7. Кеширование.

После этого сравни с примером.

**Критерий готовности:** можешь объяснить назначение каждой части без чтения комментариев.

---

## Задача 2. Проверь кеширование экспериментально

На учебном стенде:

1. Поставь логирование внутри `loadItems()`.
2. Открой страницу дважды.
3. Убедись, что при действительном кеше загрузка повторно не выполняется.
4. Измени новость через штатную административную часть.
5. Проверь обновление результата.
6. Удали или деактивируй все новости.
7. Убедись, что пустой результат кешируется.
8. Добавь первую новость.
9. Проверь актуализацию пустого списка.

Не оставляй подробное диагностическое логирование в рабочем проекте.

---

## Задача 3. Добавь выбор сортировки

Параметры:

```text
SORT_BY
SORT_ORDER
```

Обязательные условия:

- белый список полей;
- только `ASC` или `DESC`;
- корректные значения по умолчанию;
- стабильная дополнительная сортировка.

---

## Задача 4. Добавь фильтр

Например, фильтр по году публикации.

Условия:

- значение валидируется;
- используется при выборке;
- участвует в зависимостях кеша;
- при смене фильтра сбрасывается текущая страница пагинации;
- фильтр работает без JavaScript.

Только после этого добавляй AJAX.

---

## Задача 5. Повтори комплексный компонент

Проверь адреса:

```text
/study-news/
/study-news/15/
/study-news/999999999/
/study-news/abc/
/study-news/15/extra/
```

Ожидаемое поведение:

- корень — список;
- существующий доступный элемент — детальная страница;
- отсутствующий или недоступный элемент — поведение «не найдено»;
- неизвестный маршрут — 404;
- неизвестный путь не подменяется молча списком.

Проверяй не только текст «Страница не найдена», но и HTTP-статус ответа.

---

## Задача 6. Сделай второй шаблон

Создай `compact`:

- без картинки;
- только дата и заголовок;
- без изменения логики компонента.

Это проверит, действительно ли представление отделено от данных.

---

# 19. План подготовки на семь занятий

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

# 20. Итоговая шпаргалка

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
```

**Ты готов к базовым вопросам о компонентах, когда можешь самостоятельно написать список с кешированием, собрать раздел «список + детальная» и объяснить поведение каждого из них при пустых данных, ошибке, неизвестном URL и смене пользователя.**