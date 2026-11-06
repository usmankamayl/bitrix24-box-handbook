# Счетчик просмотров (View Counter)

## Описание

Компонент для подсчета и отображения просмотров элементов с использованием Highload-блока. Демонстрирует работу с уникальными просмотрами, cookie, агрегацией данных и визуализацией статистики.

## Что демонстрирует компонент

1. **Уникальные просмотры** с использованием cookie
2. **Агрегатные функции** (COUNT, GROUP BY, DATE)
3. **Кеширование** результатов выборки
4. **Визуализация данных** (графики по дням)
5. **Runtime-поля** для вычислений на лету
6. **Разные режимы работы** (показ, подсчет, оба)

## Структура Highload-блока

### Создание HL-блока

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$result = HighloadBlockTable::add([
    'NAME' => 'ViewCounter',
    'TABLE_NAME' => 'b_hlbd_view_counter',
]);

$hlblockId = $result->getId();
```

### Поля HL-блока

```php
use Bitrix\Main\UserFieldTable;

$fields = [
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_ENTITY_TYPE',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Тип сущности'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_ENTITY_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID сущности'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_USER_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID пользователя'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_IP_ADDRESS',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'IP адрес'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_VIEW_DATE',
        'USER_TYPE_ID' => 'datetime',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Дата просмотра'],
    ],
];

foreach ($fields as $field) {
    UserFieldTable::add($field);
}
```

### Индексы

```php
$connection = \Bitrix\Main\Application::getConnection();

// Составной индекс для быстрого поиска по типу и ID
$connection->query("
    CREATE INDEX ix_entity 
    ON b_hlbd_view_counter(UF_ENTITY_TYPE, UF_ENTITY_ID)
");

// Индекс на дату для статистики
$connection->query("
    CREATE INDEX ix_view_date 
    ON b_hlbd_view_counter(UF_VIEW_DATE)
");
```

## Установка и использование

### 1. Создайте Highload-блок

Создайте через админку или программно согласно структуре выше.

### 2. Скопируйте компонент

```bash
cp -r view-counter /local/components/custom/view.counter
```

### 3. Подключите на странице

**Простой вариант (только счетчик):**

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:view.counter",
    ".default",
    [
        "HLBLOCK_ID" => 2,
        "ENTITY_TYPE" => "article",
        "ENTITY_ID" => $arResult['ID'],
        "MODE" => "both",
        "UNIQUE_VIEWS" => "Y",
        "UNIQUE_PERIOD" => 3600,
        "CACHE_TIME" => 3600,
    ]
);
?>
```

**С детальной статистикой:**

```php
<?php
// Подсчет просмотра товара
$APPLICATION->IncludeComponent(
    "custom:view.counter",
    ".default",
    [
        "HLBLOCK_ID" => 2,
        "ENTITY_TYPE" => "product",
        "ENTITY_ID" => $arResult['ID'],
        "MODE" => "both",
        "UNIQUE_VIEWS" => "Y",
        "UNIQUE_PERIOD" => 3600,
        "CACHE_TIME" => 600,
    ]
);
?>
```

## Ключевые моменты кода

### Проверка уникальности через cookie

```php
protected function isUniqueView($entityType, $entityId)
{
    if ($this->arParams['UNIQUE_VIEWS'] !== 'Y') {
        return true;
    }

    $cookieKey = self::COOKIE_PREFIX . $entityType . '_' . $entityId;
    $request = Application::getInstance()->getContext()->getRequest();

    // Проверяем cookie
    if ($request->getCookie($cookieKey)) {
        return false;
    }

    // Устанавливаем cookie на период уникальности
    $cookie = new \Bitrix\Main\Web\Cookie(
        $cookieKey,
        '1',
        time() + (int)$this->arParams['UNIQUE_PERIOD']
    );
    $cookie->setDomain($request->getHttpHost());
    $cookie->setPath('/');

    Application::getInstance()->getContext()->getResponse()->addCookie($cookie);

    return true;
}
```

### Детальная статистика с агрегацией

```php
protected function getDetailedStats()
{
    $dataClass = $this->getDataClass();
    
    // Просмотры по дням за последнюю неделю
    $dateFrom = new DateTime();
    $dateFrom->add('-7 days');
    
    $viewsByDay = $dataClass::getList([
        'select' => [
            'VIEW_DATE',
            'VIEWS_COUNT'
        ],
        'filter' => [
            '=UF_ENTITY_TYPE' => $entityType,
            '=UF_ENTITY_ID' => $entityId,
            '>=UF_VIEW_DATE' => $dateFrom,
        ],
        'runtime' => [
            // Группировка по дате (без времени)
            new \Bitrix\Main\Entity\ExpressionField(
                'VIEW_DATE',
                'DATE(%s)',
                ['UF_VIEW_DATE']
            ),
            // Подсчет количества
            new \Bitrix\Main\Entity\ExpressionField(
                'VIEWS_COUNT',
                'COUNT(*)'
            ),
        ],
        'group' => ['VIEW_DATE'],
        'order' => ['VIEW_DATE' => 'ASC'],
    ])->fetchAll();
    
    return $viewsByDay;
}
```

### Кеширование статистики

```php
protected function getViewCount()
{
    $cache = Cache::createInstance();
    $cacheTime = $this->arParams['CACHE_TIME'];
    $cacheId = 'view_count_' . $entityType . '_' . $entityId;
    $cacheDir = '/hlblock/view_counter/';

    if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
        $viewCount = $cache->getVars();
    } else {
        $cache->startDataCache();
        
        $viewCount = $dataClass::getCount([
            '=UF_ENTITY_TYPE' => $entityType,
            '=UF_ENTITY_ID' => $entityId,
        ]);
        
        $cache->endDataCache($viewCount);
    }

    return $viewCount;
}
```

### Режимы работы

```php
public function executeComponent()
{
    $mode = $this->arParams['MODE'];

    // Только подсчет (без вывода)
    if ($mode === 'count' || $mode === 'both') {
        $this->incrementView();
    }

    // Только показ статистики (без подсчета)
    if ($mode === 'show' || $mode === 'both') {
        $this->arResult['VIEW_COUNT'] = $this->getViewCount();
        $this->arResult['STATISTICS'] = $this->getDetailedStats();
        $this->includeComponentTemplate();
    }
}
```

## Примеры использования

### Счетчик для статей блога

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:view.counter",
    ".default",
    [
        "HLBLOCK_ID" => 2,
        "ENTITY_TYPE" => "article",
        "ENTITY_ID" => $arResult['ID'],
        "UNIQUE_VIEWS" => "Y",
        "UNIQUE_PERIOD" => 86400, // 24 часа
    ]
);
?>
```

### Счетчик для товаров

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:view.counter",
    ".default",
    [
        "HLBLOCK_ID" => 2,
        "ENTITY_TYPE" => "product",
        "ENTITY_ID" => $arElement['ID'],
        "MODE" => "count", // Только считать
        "UNIQUE_VIEWS" => "Y",
        "UNIQUE_PERIOD" => 3600,
    ]
);
?>
```

### Показ топ просматриваемых товаров

```php
<?php
// Отдельный компонент для вывода топа
$dataClass = /* получаем DataClass */;

$topProducts = $dataClass::getList([
    'select' => [
        'UF_ENTITY_ID',
        'VIEWS_COUNT'
    ],
    'filter' => [
        '=UF_ENTITY_TYPE' => 'product',
    ],
    'runtime' => [
        new \Bitrix\Main\Entity\ExpressionField(
            'VIEWS_COUNT',
            'COUNT(*)'
        ),
    ],
    'group' => ['UF_ENTITY_ID'],
    'order' => ['VIEWS_COUNT' => 'DESC'],
    'limit' => 10,
])->fetchAll();
?>
```

## Что можно улучшить

1. **Аналитика браузеров/устройств** — парсинг User-Agent
2. **Геолокация** — определение страны/города по IP
3. **Время на странице** — отслеживание через AJAX
4. **Тепловые карты** — клики и скроллинг
5. **A/B тесты** — разные варианты контента
6. **Экспорт в аналитику** — Google Analytics, Яндекс.Метрика

## Оптимизация для высоких нагрузок

### Отложенная запись через агент

```php
// Вместо немедленной записи в БД, складываем в Memcached
protected function incrementView()
{
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    $key = 'pending_views_' . $entityType . '_' . $entityId;
    
    // Увеличиваем счетчик в кеше
    $count = (int)$cache->get($key);
    $cache->set($key, $count + 1, 300);
}

// Агент пишет накопленные просмотры раз в 5 минут
public static function flushPendingViews()
{
    // Получить все ключи pending_views_*
    // Записать в БД пакетом
    // Очистить кеш
    return "ViewCounter::flushPendingViews();";
}
```

### Партиционирование таблицы

```sql
-- Партиции по месяцам для больших объемов
ALTER TABLE b_hlbd_view_counter
PARTITION BY RANGE (MONTH(UF_VIEW_DATE)) (
    PARTITION p202401 VALUES LESS THAN (2),
    PARTITION p202402 VALUES LESS THAN (3),
    ...
);
```

## Возможные проблемы

### Cookie не работают

Проверьте, что домен и путь cookie корректны. Для поддоменов используйте `.example.com`.

### Медленная статистика

Создайте индексы на часто используемые поля или используйте материализованные представления для агрегированных данных.

### Переполнение таблицы

Настройте агент для архивации старых данных:

```php
CAgent::AddAgent(
    "ViewCounterCleaner::archiveOldViews();",
    "",
    "N",
    604800, // Раз в неделю
    "",
    "Y"
);
```

## Полезные ссылки

- [Документация по Cookie в Bitrix](https://dev.1c-bitrix.ru/api_d7/bitrix/main/web/cookie.php)
- [Runtime-поля в ORM](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5753)
- [Теория модуля 2.2](../../../docs/module-2-2.md)

## Лицензия

Пример создан в учебных целях для курса подготовки к собеседованию Bitrix-разработчика.
