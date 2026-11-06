# Задача 2: Live-поиск с автодополнением

## Описание

Компонент **мгновенного поиска** с автодополнением через BX.ajax. Демонстрирует:
- **Debounce** - задержка запросов при вводе
- **Live поиск** - результаты в реальном времени
- **Подсветка найденного** текста
- **UX оптимизации** - loader, клавиатура (Escape)
- **Кастомные события** BX.onCustomEvent

## Структура компонента

```
live-search/
├── .description.php           # Описание компонента
├── .parameters.php            # Параметры компонента
├── class.php                  # Класс с методом search
└── templates/.default/
    └── template.php           # Шаблон с поиском и JS
```

## Установка

### 1. Копируем компонент

```bash
cp -r live-search /local/components/bitrix/module.14.2.live-search/
```

### 2. Подключаем на странице

```php
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->SetTitle('Поиск');
?>

<h1>Поиск по сайту</h1>

<?php
$APPLICATION->IncludeComponent(
    'bitrix:module.14.2.live-search',
    '.default',
    [
        'IBLOCK_TYPE' => 'content',
        'IBLOCK_ID' => 1,              // ID вашего инфоблока
        'MIN_QUERY_LENGTH' => 3,       // Минимум 3 символа для поиска
        'MAX_RESULTS' => 10,           // Максимум 10 результатов
        'CACHE_TIME' => 3600,
    ]
);
?>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
?>
```

## Как это работает

### 1. Debounce при вводе

```javascript
var searchTimeout = null;

BX.bind(input, 'input', function(e) {
    var query = e.target.value.trim();
    
    // Очищаем предыдущий таймер
    clearTimeout(searchTimeout);
    
    // Запускаем поиск с задержкой 300ms
    searchTimeout = setTimeout(function() {
        performSearch(query);
    }, 300);
});
```

**Зачем нужен debounce:**
- Пользователь печатает "битрикс" (7 символов)
- Без debounce: 7 AJAX запросов
- С debounce: 1-2 запроса (когда пользователь перестал печатать)

### 2. AJAX поиск

```javascript
function performSearch(query) {
    // Показываем loader
    loader.style.display = 'block';
    
    // AJAX запрос
    BX.ajax.runComponentAction(
        'bitrix:module.14.2.live-search',
        'search',
        {
            mode: 'class',
            data: { query: query, limit: 10 }
        }
    ).then(
        function(response) {
            loader.style.display = 'none';
            displayResults(response.data);
        }
    );
}
```

### 3. Серверный поиск (class.php)

```php
public function searchAction($query, $limit = null)
{
    // Валидация
    if (mb_strlen($query) < $this->arParams['MIN_QUERY_LENGTH']) {
        return ['success' => true, 'items' => [], 'message' => 'Введите минимум 3 символа'];
    }
    
    // Поиск в инфоблоке через D7
    $filter = [
        'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
        'ACTIVE' => 'Y',
        [
            'LOGIC' => 'OR',
            ['%NAME' => $query],
            ['%PREVIEW_TEXT' => $query],
            ['%DETAIL_TEXT' => $query],
        ]
    ];
    
    $result = ElementTable::getList([
        'filter' => $filter,
        'select' => ['ID', 'NAME', 'PREVIEW_TEXT', 'DETAIL_PAGE_URL'],
        'limit' => $limit,
    ]);
    
    // Возвращаем результаты
    return [
        'success' => true,
        'items' => $items,
        'count' => count($items),
    ];
}
```

### 4. Отображение результатов

```javascript
function displayResults(data) {
    if (data.items.length === 0) {
        // Нет результатов
        resultsList.innerHTML = '<div class="no-results">Ничего не найдено</div>';
    } else {
        // Выводим результаты
        data.items.forEach(function(item) {
            var element = createResultItem(item, data.query);
            resultsList.appendChild(element);
        });
    }
    
    resultsDiv.style.display = 'block';
}
```

### 5. Подсветка найденного текста

```javascript
function highlightQuery(text, query) {
    var escapedQuery = escapeRegex(query);
    var regex = new RegExp('(' + escapedQuery + ')', 'gi');
    
    return text.replace(regex, '<span class="highlight">$1</span>');
}

// Пример:
// text = "Битрикс - это CMS система"
// query = "битрикс"
// result = "<span class='highlight'>Битрикс</span> - это CMS система"
```

## Ключевые моменты

### 1. Debounce функция

```javascript
// Без debounce (плохо)
BX.bind(input, 'input', function(e) {
    performSearch(e.target.value); // Запрос при каждом символе!
});

// С debounce (хорошо)
var timeout = null;
BX.bind(input, 'input', function(e) {
    clearTimeout(timeout);
    timeout = setTimeout(function() {
        performSearch(e.target.value); // Запрос через 300ms после остановки ввода
    }, 300);
});
```

### 2. Показ loader'а

```javascript
function performSearch(query) {
    if (isLoading) return; // Предотвращаем множественные запросы
    
    isLoading = true;
    loader.style.display = 'block';
    
    BX.ajax.runComponentAction(...).then(function() {
        isLoading = false;
        loader.style.display = 'none';
    });
}
```

### 3. Закрытие по клику вне

```javascript
BX.bind(document, 'click', function(e) {
    if (!container.contains(e.target)) {
        hideResults();
    }
});
```

### 4. Клавиатурные события

```javascript
BX.bind(input, 'keydown', function(e) {
    if (e.key === 'Escape') {
        hideResults();
        input.blur();
    }
});
```

### 5. Кастомные события

```javascript
// Генерация события при выборе элемента
BX.bind(resultItem, 'click', function() {
    BX.onCustomEvent('LiveSearch:itemSelected', [item]);
});

// Подписка на событие (в другом компоненте)
BX.addCustomEvent('LiveSearch:itemSelected', function(item) {
    console.log('Выбран элемент:', item.name);
    // Отправка в аналитику
    ym(12345678, 'reachGoal', 'search-item-click');
});
```

## Расширение функционала

### 1. Поиск с категориями

```php
// В class.php
private function searchInIblock($query, $limit)
{
    $items = [];
    
    // Группируем по разделам
    $result = ElementTable::getList([
        'filter' => $filter,
        'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'SECTION.NAME'],
        'runtime' => [
            new \Bitrix\Main\Entity\ReferenceField(
                'SECTION',
                \Bitrix\Iblock\SectionTable::class,
                ['=this.IBLOCK_SECTION_ID' => 'ref.ID']
            )
        ],
    ]);
    
    while ($item = $result->fetch()) {
        $sectionName = $item['IBLOCK_SECTION_NAME'] ?: 'Без раздела';
        
        if (!isset($items[$sectionName])) {
            $items[$sectionName] = [];
        }
        
        $items[$sectionName][] = [
            'id' => $item['ID'],
            'name' => $item['NAME'],
        ];
    }
    
    return $items;
}
```

**JavaScript:**

```javascript
function displayResults(data) {
    for (var category in data.items) {
        // Заголовок категории
        var header = BX.create('div', {
            props: { className: 'results-category' },
            text: category
        });
        resultsList.appendChild(header);
        
        // Элементы категории
        data.items[category].forEach(function(item) {
            var element = createResultItem(item);
            resultsList.appendChild(element);
        });
    }
}
```

### 2. Поиск в нескольких инфоблоках

```php
public function searchAction($query, $limit = null)
{
    $results = [];
    
    // Поиск в новостях
    $results['news'] = $this->searchInIblock($query, 5, 10);
    
    // Поиск в товарах
    $results['products'] = $this->searchInIblock($query, 5, 11);
    
    // Поиск на страницах
    $results['pages'] = $this->searchInIblock($query, 5, 12);
    
    return [
        'success' => true,
        'results' => $results,
    ];
}
```

### 3. История поиска (LocalStorage)

```javascript
// Сохранение в историю
function saveToHistory(query) {
    var history = JSON.parse(localStorage.getItem('searchHistory') || '[]');
    
    // Добавляем в начало, убираем дубликаты
    history = history.filter(function(q) { return q !== query; });
    history.unshift(query);
    
    // Ограничиваем 10 элементами
    history = history.slice(0, 10);
    
    localStorage.setItem('searchHistory', JSON.stringify(history));
}

// Показ истории при фокусе
BX.bind(input, 'focus', function() {
    if (input.value === '') {
        showSearchHistory();
    }
});

function showSearchHistory() {
    var history = JSON.parse(localStorage.getItem('searchHistory') || '[]');
    
    if (history.length > 0) {
        resultsList.innerHTML = '<div class="history-title">Последние запросы</div>';
        
        history.forEach(function(query) {
            var item = BX.create('div', {
                props: { className: 'history-item' },
                text: query,
                events: {
                    click: function() {
                        input.value = query;
                        performSearch(query);
                    }
                }
            });
            resultsList.appendChild(item);
        });
        
        resultsDiv.style.display = 'block';
    }
}
```

### 4. Автокомплит с клавиатурной навигацией

```javascript
var selectedIndex = -1;
var resultItems = [];

BX.bind(input, 'keydown', function(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, resultItems.length - 1);
        highlightSelected();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, -1);
        highlightSelected();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedIndex >= 0 && resultItems[selectedIndex]) {
            resultItems[selectedIndex].click();
        }
    }
});

function highlightSelected() {
    resultItems.forEach(function(item, index) {
        if (index === selectedIndex) {
            BX.addClass(item, 'selected');
        } else {
            BX.removeClass(item, 'selected');
        }
    });
}
```

### 5. Поиск с фильтрами

```javascript
// HTML с фильтрами
<div class="search-filters">
    <label><input type="checkbox" name="filter[]" value="news"> Новости</label>
    <label><input type="checkbox" name="filter[]" value="articles"> Статьи</label>
    <label><input type="checkbox" name="filter[]" value="products"> Товары</label>
</div>

// JavaScript
function performSearch(query) {
    var filters = [];
    document.querySelectorAll('input[name="filter[]"]:checked').forEach(function(checkbox) {
        filters.push(checkbox.value);
    });
    
    BX.ajax.runComponentAction(
        'bitrix:module.14.2.live-search',
        'search',
        {
            mode: 'class',
            data: {
                query: query,
                filters: filters
            }
        }
    ).then(displayResults);
}
```

## Тестирование

### 1. Проверка debounce

```javascript
// В консоли браузера
var input = document.querySelector('.search-input');
input.value = 'test';
input.dispatchEvent(new Event('input'));

// Должен быть один запрос через 300ms
```

### 2. Нагрузочное тестирование

```javascript
// Эмуляция быстрого ввода
var query = 'битрикс';
var input = document.querySelector('.search-input');

for (var i = 0; i < query.length; i++) {
    setTimeout(function(index) {
        input.value = query.substring(0, index + 1);
        input.dispatchEvent(new Event('input'));
    }, i * 50, i);
}

// Должен быть только 1 AJAX запрос после окончания ввода
```

### 3. Проверка подсветки

```javascript
var text = 'Битрикс - лучшая CMS';
var query = 'битрикс';
var result = highlightQuery(text, query);

console.log(result);
// "<span class='highlight'>Битрикс</span> - лучшая CMS"
```

## Performance оптимизации

### 1. Кеширование результатов на клиенте

```javascript
var searchCache = {};

function performSearch(query) {
    // Проверяем кеш
    if (searchCache[query]) {
        displayResults(searchCache[query]);
        return;
    }
    
    // Запрос к серверу
    BX.ajax.runComponentAction(...).then(function(response) {
        // Сохраняем в кеш
        searchCache[query] = response.data;
        displayResults(response.data);
    });
}
```

### 2. Отмена предыдущих запросов

```javascript
var currentRequest = null;

function performSearch(query) {
    // Отменяем предыдущий запрос
    if (currentRequest) {
        currentRequest.abort();
    }
    
    currentRequest = BX.ajax.runComponentAction(...);
}
```

### 3. Серверное кеширование

```php
public function searchAction($query, $limit = null)
{
    $cacheId = 'search_' . md5($query . '_' . $limit);
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    
    if ($cache->initCache(3600, $cacheId, '/live-search/')) {
        $items = $cache->getVars();
    } else {
        $items = $this->searchInIblock($query, $limit);
        
        $cache->startDataCache();
        $cache->endDataCache($items);
    }
    
    return ['success' => true, 'items' => $items];
}
```

## Частые ошибки

### 1. Слишком много запросов

**Проблема:** AJAX запрос при каждом нажатии клавиши

**Решение:** Используйте debounce с задержкой 300-500ms

### 2. Поиск не работает на кириллице

**Проблема:** SQL запрос не находит кириллицу

**Решение:** Проверьте кодировку БД и используйте `mb_strlen()`, `mb_substr()`

### 3. XSS уязвимость

**Проблема:** HTML теги в результатах выполняются

**Решение:** Всегда экранируйте HTML:
```javascript
function escapeHtml(text) {
    var map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
```

## Выводы

Эта задача демонстрирует:

✅ **Debounce** - оптимизация количества запросов  
✅ **Live поиск** - результаты в реальном времени  
✅ **BX.ajax.runComponentAction** - современный AJAX  
✅ **UX паттерны** - loader, клавиатура, закрытие  
✅ **Подсветка текста** - RegExp и highlight  
✅ **Кастомные события** - BX.onCustomEvent  
✅ **Performance** - кеширование, отмена запросов

---

**Следующая задача:** Задача 3: Админ-панель с уведомлениями и диалогами
