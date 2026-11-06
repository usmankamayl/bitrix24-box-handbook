# Лог запросов пользователей (Request Log)

## Описание

Компонент для логирования HTTP-запросов пользователей с использованием Highload-блока. Демонстрирует работу с HL-блоками: создание, добавление данных, выборку с фильтрами и агрегатными функциями.

## Что демонстрирует компонент

1. **Получение DataClass** из Highload-блока
2. **Добавление записей** в HL-блок
3. **Выборка с фильтрами** (по пользователю, по дате)
4. **Агрегатные функции** (COUNT, GROUP BY)
5. **Форматирование данных** из HL-блока
6. **Кеширование** результатов

## Структура Highload-блока

### Создание HL-блока

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$result = HighloadBlockTable::add([
    'NAME' => 'RequestLog',
    'TABLE_NAME' => 'b_hlbd_request_log',
]);

$hlblockId = $result->getId();
```

### Поля HL-блока

```php
use Bitrix\Main\UserFieldTable;

$fields = [
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_USER_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID пользователя'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_REQUEST_URL',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'URL запроса'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_REQUEST_METHOD',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'HTTP метод'],
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
        'FIELD_NAME' => 'UF_USER_AGENT',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'User Agent'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_REQUEST_DATE',
        'USER_TYPE_ID' => 'datetime',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Дата запроса'],
    ],
];

foreach ($fields as $field) {
    UserFieldTable::add($field);
}
```

### Создание индексов для оптимизации

```php
$connection = \Bitrix\Main\Application::getConnection();

// Индекс на пользователя
$connection->query("
    CREATE INDEX ix_user_id 
    ON b_hlbd_request_log(UF_USER_ID)
");

// Индекс на дату
$connection->query("
    CREATE INDEX ix_request_date 
    ON b_hlbd_request_log(UF_REQUEST_DATE)
");

// Составной индекс для частых запросов
$connection->query("
    CREATE INDEX ix_user_date 
    ON b_hlbd_request_log(UF_USER_ID, UF_REQUEST_DATE)
");
```

## Установка и использование

### 1. Создайте Highload-блок

Выполните SQL-скрипт или создайте через админку:

```
Настройки → Настройки продукта → Настройки модулей → 
Highload-блоки → Добавить Highload-блок
```

**Параметры:**
- Название: `RequestLog`
- Имя таблицы: `b_hlbd_request_log`

### 2. Добавьте поля

Добавьте пользовательские поля согласно структуре выше.

### 3. Скопируйте компонент

```bash
cp -r request-log /local/components/custom/request.log
```

### 4. Подключите на странице

```php
<?php
$APPLICATION->IncludeComponent(
    "custom:request.log",
    ".default",
    [
        "HLBLOCK_ID" => 1,           // ID вашего HL-блока
        "PAGE_SIZE" => 20,           // Записей на странице
        "FILTER_USER_ID" => 0,       // 0 = все пользователи
        "LOG_PERIOD_DAYS" => 7,      // Логи за последние 7 дней
        "CACHE_TIME" => 3600,        // Время кеширования
    ]
);
?>
```

## Ключевые моменты кода

### Получение DataClass

```php
protected function getDataClass()
{
    $hlblockId = (int)$this->arParams['HLBLOCK_ID'];
    
    // Получаем HL-блок
    $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();
    
    // Компилируем Entity
    $entity = HighloadBlockTable::compileEntity($hlblock);
    
    // Получаем класс для работы с данными
    return $entity->getDataClass();
}
```

### Добавление записи

```php
protected function logCurrentRequest()
{
    $dataClass = $this->getDataClass();
    $request = Application::getInstance()->getContext()->getRequest();
    
    $result = $dataClass::add([
        'UF_USER_ID' => $USER->GetID() ?: 0,
        'UF_REQUEST_URL' => $request->getRequestUri(),
        'UF_REQUEST_METHOD' => $request->getRequestMethod(),
        'UF_IP_ADDRESS' => $request->getRemoteAddress(),
        'UF_USER_AGENT' => $request->getUserAgent(),
        'UF_REQUEST_DATE' => new DateTime(),
    ]);
    
    if (!$result->isSuccess()) {
        // Обработка ошибок
        $errors = $result->getErrorMessages();
    }
}
```

### Выборка с фильтрами

```php
protected function getLogList()
{
    $dataClass = $this->getDataClass();
    
    $filter = [];
    
    // Фильтр по пользователю
    if ($this->arParams['FILTER_USER_ID'] > 0) {
        $filter['=UF_USER_ID'] = (int)$this->arParams['FILTER_USER_ID'];
    }
    
    // Фильтр по периоду
    if ($this->arParams['LOG_PERIOD_DAYS'] > 0) {
        $dateFrom = new DateTime();
        $dateFrom->add('-' . $this->arParams['LOG_PERIOD_DAYS'] . ' days');
        $filter['>=UF_REQUEST_DATE'] = $dateFrom;
    }
    
    // Выборка
    $result = $dataClass::getList([
        'select' => ['*'],
        'filter' => $filter,
        'order' => ['UF_REQUEST_DATE' => 'DESC'],
        'limit' => (int)$this->arParams['PAGE_SIZE'],
    ]);
    
    return $result->fetchAll();
}
```

### Агрегатные функции

```php
protected function getStatistics()
{
    $dataClass = $this->getDataClass();
    
    // Топ URL по количеству запросов
    $topUrls = $dataClass::getList([
        'select' => [
            'UF_REQUEST_URL',
            'REQUEST_COUNT'
        ],
        'runtime' => [
            new \Bitrix\Main\Entity\ExpressionField(
                'REQUEST_COUNT',
                'COUNT(*)'
            ),
        ],
        'group' => ['UF_REQUEST_URL'],
        'order' => ['REQUEST_COUNT' => 'DESC'],
        'limit' => 5,
    ])->fetchAll();
    
    return $topUrls;
}
```

## Что можно улучшить

1. **Пагинация** — добавить постраничную навигацию для больших объемов
2. **Дополнительные фильтры** — по IP, методу запроса, URL
3. **Экспорт данных** — выгрузка в CSV/Excel
4. **Графики** — визуализация статистики
5. **Автоматическая очистка** — удаление старых логов через агента
6. **Расширенная статистика** — уникальные IP, браузеры, часы пик

## Возможные проблемы

### Ошибка "Highload-блок не найден"

Проверьте правильность ID блока в параметрах компонента.

### Медленная выборка

Создайте индексы на поля `UF_USER_ID` и `UF_REQUEST_DATE`.

### Переполнение таблицы

Настройте агент для автоматической очистки старых записей:

```php
CAgent::AddAgent(
    "RequestLogCleaner::cleanOldLogs();",
    "",
    "N",
    86400, // Раз в сутки
    "",
    "Y",
    "",
    50
);
```

## Полезные ссылки

- [Документация по Highload-блокам](https://dev.1c-bitrix.ru/api_d7/bitrix/highloadblock/index.php)
- [Теория модуля 2.2](../../../topics/module-2-2.md)

## Лицензия

Пример создан в учебных целях для курса подготовки к собеседованию Bitrix-разработчика.
