# Компонент "Список с фильтром"

Продвинутый компонент для вывода списка элементов инфоблока с фильтрацией, Ajax-обновлением и умным кешированием.

## Функционал

### Основной функционал
- **Вывод элементов инфоблока** с настройкой сортировки и количества
- **Фильтрация по разделам** с поддержкой подразделов
- **Фильтрация по свойствам** (списочные, строковые, числовые)
- **Ajax-обновление** списка без перезагрузки страницы
- **Постраничная навигация** с Ajax-переключением
- **Сохранение фильтра в сессии** для удобства пользователя

### Продвинутые возможности
- **Умное кеширование** с учетом фильтра и прав доступа
- **Автоматическая фильтрация** при изменении полей
- **History API** для поддержки кнопки "Назад" браузера
- **Debouncing запросов** для производительности
- **Адаптивная вёрстка** для мобильных устройств
- **Lazy loading изображений** для оптимизации загрузки

## Установка

1. Скопируйте папку компонента в `/local/components/custom/filtered.list/`
2. Убедитесь, что модуль `iblock` подключен

## Использование

### Базовый вызов

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:filtered.list",
    ".default",
    [
        "IBLOCK_ID" => 1,
        "SECTION_ID" => 0,
        "INCLUDE_SUBSECTIONS" => "Y",
        
        // Сортировка
        "SORT_BY" => "SORT",
        "SORT_ORDER" => "ASC",
        
        // Фильтр
        "SHOW_SECTIONS_FILTER" => "Y",
        "SHOW_PROPERTIES_FILTER" => "Y",
        "FILTER_PROPERTIES" => "BRAND,COLOR,SIZE",
        "SAVE_FILTER_IN_SESSION" => "Y",
        
        // Пагинация
        "PAGE_ELEMENT_COUNT" => 12,
        "SHOW_PAGINATION" => "Y",
        
        // Ajax
        "USE_AJAX" => "Y",
        
        // Кеширование
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => 3600,
        "CACHE_FILTER" => "N",
        "CACHE_GROUPS" => "Y",
    ]
);
?>
```

### Расширенный вызов с внешним фильтром

```php
<?php
// Внешний фильтр (например, по цене)
$arrFilter = [
    ">=PROPERTY_PRICE" => 1000,
    "<=PROPERTY_PRICE" => 5000,
];

$APPLICATION->IncludeComponent(
    "custom:filtered.list",
    ".default",
    [
        "IBLOCK_ID" => 1,
        "FILTER_NAME" => "arrFilter", // Имя внешнего фильтра
        "FILTER_PROPERTIES" => "BRAND,COLOR",
        "CACHE_FILTER" => "Y", // Кешировать даже с фильтром
    ]
);
?>
```

## Параметры компонента

### Основные параметры

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `IBLOCK_ID` | int | 0 | ID инфоблока |
| `SECTION_ID` | int | 0 | ID раздела (0 = все разделы) |
| `INCLUDE_SUBSECTIONS` | Y/N | Y | Показывать элементы подразделов |

### Сортировка

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `SORT_BY` | string | SORT | Поле сортировки (ID, NAME, SORT, ACTIVE_FROM) |
| `SORT_ORDER` | string | ASC | Порядок (ASC, DESC) |

### Фильтр

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `FILTER_NAME` | string | arrFilter | Имя глобального массива фильтра |
| `SHOW_SECTIONS_FILTER` | Y/N | Y | Показывать фильтр по разделам |
| `SHOW_PROPERTIES_FILTER` | Y/N | Y | Показывать фильтр по свойствам |
| `FILTER_PROPERTIES` | string | "" | Коды свойств через запятую (BRAND,COLOR,SIZE) |
| `SAVE_FILTER_IN_SESSION` | Y/N | Y | Сохранять выбранный фильтр в сессии |

### Пагинация

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `PAGE_ELEMENT_COUNT` | int | 12 | Количество элементов на странице |
| `SHOW_PAGINATION` | Y/N | Y | Показывать постраничную навигацию |

### Ajax

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `USE_AJAX` | Y/N | Y | Использовать Ajax для обновления списка |

### Кеширование

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `CACHE_TIME` | int | 3600 | Время кеширования (сек) |
| `CACHE_FILTER` | Y/N | N | Кешировать при установленном фильтре |
| `CACHE_GROUPS` | Y/N | Y | Учитывать права доступа при кешировании |

## Структура компонента

```
filtered-list/
├── .description.php         # Описание компонента
├── .parameters.php          # Настройка параметров
├── class.php                # Основная логика
├── component.php            # Точка входа
├── templates/
│   └── .default/
│       ├── template.php     # HTML шаблон
│       ├── style.css        # Стили
│       ├── script.js        # JavaScript
│       └── component_epilog.php (опционально)
└── README.md                # Документация
```

## Как работает компонент

### 1. Инициализация

```php
// class.php :: executeComponent()
public function executeComponent()
{
    // 1. Проверка модулей
    if (!$this->checkModules()) return;
    
    // 2. Подготовка фильтра
    $filter = $this->prepareFilter();
    
    // 3. Формирование ключа кеша
    $cacheId = $this->getCacheId();
    
    // 4. Запуск кеширования
    if ($this->startResultCache(false, $cacheId)) {
        // Получение данных
        $this->arResult["ITEMS"] = $this->getElements($filter);
        $this->includeComponentTemplate();
    }
}
```

### 2. Фильтрация

Компонент поддерживает **3 источника фильтрации**:

1. **Фильтр из запроса** (GET параметры)
2. **Фильтр из сессии** (если включено `SAVE_FILTER_IN_SESSION`)
3. **Внешний фильтр** (глобальный массив `$arrFilter`)

```php
// class.php :: prepareFilter()
protected function prepareFilter(): array
{
    $filter = ["IBLOCK_ID" => $this->arParams["IBLOCK_ID"]];
    
    // Из запроса
    $requestFilter = $this->getFilterFromRequest();
    
    // Из сессии (если запрос пустой)
    if (empty($requestFilter) && $this->arParams["SAVE_FILTER_IN_SESSION"]) {
        $requestFilter = $this->getFilterFromSession();
    }
    
    // Внешний фильтр
    global ${$this->arParams["FILTER_NAME"]};
    
    return array_merge($filter, $requestFilter, ${$this->arParams["FILTER_NAME"]} ?? []);
}
```

### 3. Ajax-обновление

JavaScript автоматически перехватывает отправку формы и обновляет контент через Ajax:

```javascript
// script.js :: applyFilter()
async applyFilter(updateHistory = true) {
    const formData = new FormData(this.form);
    const params = new URLSearchParams(formData);
    params.append('ajax', 'Y');
    
    const response = await fetch(url + '&' + params.toString());
    const data = await response.json();
    
    if (data.success) {
        this.updateContent(data.html);
        if (updateHistory) {
            window.history.pushState({}, '', newUrl);
        }
    }
}
```

### 4. Кеширование

Компонент использует **умное кеширование** с учётом:
- Фильтра (если `CACHE_FILTER` = Y)
- Групп пользователя (если `CACHE_GROUPS` = Y)
- Страницы пагинации

```php
// class.php :: getCacheId()
protected function getCacheId(): string
{
    $cacheId = $this->arParams["IBLOCK_ID"];
    
    // Учитываем фильтр
    if ($this->arParams["CACHE_FILTER"]) {
        $cacheId .= "_" . md5(serialize($this->filter));
    }
    
    // Учитываем права доступа
    if ($this->arParams["CACHE_GROUPS"]) {
        global $USER;
        $cacheId .= "_" . md5(serialize($USER->GetUserGroupArray()));
    }
    
    // Учитываем страницу
    $cacheId .= "_page_" . $page;
    
    return $cacheId;
}
```

## Типы свойств для фильтрации

### Списочный тип (L)

```php
<select name="property[BRAND]">
    <option value="">Не выбрано</option>
    <option value="123">Apple</option>
    <option value="124">Samsung</option>
</select>
```

### Строковый тип (S)

```php
<input 
    type="text" 
    name="property[ARTICLE]" 
    placeholder="Введите артикул"
>
```

### Числовой тип (N)

```php
<input type="number" name="property[PRICE][MIN]" placeholder="От">
<input type="number" name="property[PRICE][MAX]" placeholder="До">
```

## Примеры использования

### Пример 1: Каталог товаров

```php
$APPLICATION->IncludeComponent(
    "custom:filtered.list",
    "catalog",
    [
        "IBLOCK_ID" => 2,
        "FILTER_PROPERTIES" => "BRAND,COLOR,SIZE,PRICE",
        "SORT_BY" => "SHOWS",
        "SORT_ORDER" => "DESC",
        "PAGE_ELEMENT_COUNT" => 24,
    ]
);
```

### Пример 2: Список вакансий

```php
$APPLICATION->IncludeComponent(
    "custom:filtered.list",
    "vacancies",
    [
        "IBLOCK_ID" => 5,
        "FILTER_PROPERTIES" => "CITY,EXPERIENCE,SALARY",
        "SHOW_SECTIONS_FILTER" => "Y",
        "SAVE_FILTER_IN_SESSION" => "Y",
    ]
);
```

### Пример 3: Новости с тегами

```php
$APPLICATION->IncludeComponent(
    "custom:filtered.list",
    "news",
    [
        "IBLOCK_ID" => 1,
        "FILTER_PROPERTIES" => "TAGS",
        "SORT_BY" => "ACTIVE_FROM",
        "SORT_ORDER" => "DESC",
        "CACHE_FILTER" => "Y",
    ]
);
```

## Кастомизация шаблона

### Создание собственного шаблона

1. Создайте папку `templates/my-template/`
2. Скопируйте файлы из `.default`
3. Измените HTML структуру в `template.php`
4. Адаптируйте стили в `style.css`
5. При необходимости измените логику в `script.js`

### Пример кастомной карточки товара

```php
<!-- template.php -->
<article class="product-card">
    <div class="product-card__badge">
        <?php if ($arItem["PROPERTY_NEW_VALUE"]): ?>
            <span class="badge badge--new">Новинка</span>
        <?php endif; ?>
    </div>
    
    <div class="product-card__image">
        <img src="<?= $arItem["PREVIEW_PICTURE"]["SRC"] ?>" alt="">
    </div>
    
    <h3><?= $arItem["NAME"] ?></h3>
    
    <div class="product-card__price">
        <?= number_format($arItem["PROPERTY_PRICE_VALUE"], 0, '', ' ') ?> ₽
    </div>
    
    <button class="btn" data-product-id="<?= $arItem["ID"] ?>">
        В корзину
    </button>
</article>
```

## Производительность

### Рекомендации по оптимизации

1. **Используйте кеширование**
   ```php
   "CACHE_TYPE" => "A",
   "CACHE_TIME" => 3600,
   ```

2. **Кешируйте фильтр для статичных данных**
   ```php
   "CACHE_FILTER" => "Y", // Только если фильтр редко меняется
   ```

3. **Ограничьте количество элементов**
   ```php
   "PAGE_ELEMENT_COUNT" => 12, // Не больше 24
   ```

4. **Используйте индексы в базе данных** для свойств фильтрации

5. **Оптимизируйте изображения** и включите `loading="lazy"`

## Возможные проблемы и решения

### Проблема: Ajax не работает

**Решение:**
1. Проверьте параметр `USE_AJAX` = "Y"
2. Убедитесь, что JavaScript загружается
3. Проверьте консоль браузера на ошибки

### Проблема: Фильтр не применяется

**Решение:**
1. Проверьте правильность кодов свойств в `FILTER_PROPERTIES`
2. Убедитесь, что свойства активны
3. Проверьте типы свойств (L, S, N)

### Проблема: Кеш не сбрасывается

**Решение:**
```php
// Временно отключите кеш для проверки
"CACHE_TYPE" => "N",

// Или очистите кеш вручную
BXClearCache(true, "/components/filtered.list/");
```

## Вопросы для самопроверки

1. Как компонент формирует ключ кеша с учётом фильтра?
2. Почему фильтр сохраняется в сессии?
3. Как работает Ajax-обновление с History API?
4. В чём разница между `CACHE_FILTER` и `CACHE_GROUPS`?
5. Как компонент обрабатывает числовые свойства (диапазон)?

## Ссылки на документацию

- [CIBlockElement::GetList](https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/getlist.php)
- [Bitrix D7 ORM](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5753)
- [Кеширование компонентов](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2830)
- [Ajax в компонентах](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3140)

## Автор

Практическая задача для изучения Bitrix Framework
Модуль 1.2: Компоненты (простые и сложные)
