# Интеграция с CRM через REST API Bitrix24

## Описание

Практический пример работы со сделками (deals) через REST API Bitrix24. Демонстрирует основные операции CRUD, фильтрацию, статистику и batch-запросы.

## Файлы

- `CRestClient.php` — базовый клиент для работы с REST API
- `DealManager.php` — менеджер для операций со сделками
- `example.php` — примеры использования

## Возможности

### CRestClient
- Выполнение REST-запросов к Bitrix24
- Автоматическая обработка ошибок
- Batch-запросы (до 50 команд за раз)
- Автоматическая pagination для получения всех данных

### DealManager
- **CRUD операции:** создание, чтение, обновление, удаление сделок
- **Фильтрация:** поиск по стадиям, сумме, датам
- **Массовые операции:** создание нескольких сделок через batch
- **Статистика:** подсчет количества и суммы по стадиям
- **Комментарии:** добавление комментариев к сделкам
- **Изменение стадии:** перевод сделок между стадиями

## Установка

### 1. Создайте входящий webhook в Bitrix24

1. Откройте ваш портал Bitrix24
2. Перейдите: **Настройки → Разработчикам → Входящий вебхук**
3. Нажмите **Создать webhook**
4. Выберите права доступа (минимум: CRM - чтение и запись)
5. Скопируйте URL вида: `https://ваш-портал.bitrix24.ru/rest/1/код_вебхука/`

### 2. Настройте подключение

Откройте `example.php` и замените URL вебхука:

```php
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/ваш_webhook_код/';
```

### 3. Запустите пример

```bash
php example.php
```

## Примеры использования

### Базовая инициализация

```php
require_once 'CRestClient.php';
require_once 'DealManager.php';

$client = new CRestClient($WEBHOOK_URL);
$dealManager = new DealManager($client);
```

### Создание сделки

```php
$dealId = $dealManager->createDeal([
    'TITLE' => 'Продажа CRM-системы',
    'STAGE_ID' => 'NEW',
    'OPPORTUNITY' => 150000,
    'CURRENCY_ID' => 'RUB',
    'ASSIGNED_BY_ID' => 1
]);

if ($dealId) {
    echo "Сделка создана, ID: $dealId";
}
```

### Получение сделки

```php
$deal = $dealManager->getDeal($dealId);

echo "Название: {$deal['TITLE']}\n";
echo "Сумма: {$deal['OPPORTUNITY']} {$deal['CURRENCY_ID']}\n";
```

### Обновление сделки

```php
$dealManager->updateDeal($dealId, [
    'OPPORTUNITY' => 180000,
    'COMMENTS' => 'Новая сумма после переговоров'
]);
```

### Фильтрация сделок

```php
// Получить новые сделки
$newDeals = $dealManager->getDeals(
    ['STAGE_ID' => 'NEW'],
    ['DATE_CREATE' => 'DESC']
);

// Сделки по диапазону суммы
$deals = $dealManager->getDealsByAmountRange(100000, 500000);
```

### Перевод на другую стадию

```php
$dealManager->moveToStage($dealId, 'PREPARATION');
```

### Добавление комментария

```php
$dealManager->addComment($dealId, 'Клиент запросил дополнительную информацию');
```

### Статистика по сделкам

```php
$stats = $dealManager->getStatistics();

echo "Всего сделок: {$stats['total_count']}\n";
echo "Общая сумма: {$stats['total_amount']} руб.\n";

foreach ($stats['by_stage'] as $stageId => $data) {
    echo "$stageId: {$data['count']} шт., {$data['amount']} руб.\n";
}
```

### Массовое создание (batch)

```php
$newDeals = [
    ['TITLE' => 'Сделка 1', 'OPPORTUNITY' => 50000],
    ['TITLE' => 'Сделка 2', 'OPPORTUNITY' => 75000],
    ['TITLE' => 'Сделка 3', 'OPPORTUNITY' => 100000]
];

$result = $dealManager->createBatch($newDeals);
// Создано 3 сделки за один HTTP-запрос
```

## Основные поля сделки

| Поле | Описание | Пример |
|------|----------|--------|
| `TITLE` | Название сделки | "Продажа услуг" |
| `STAGE_ID` | Стадия сделки | NEW, PREPARATION, WON, LOSE |
| `OPPORTUNITY` | Сумма сделки | 150000 |
| `CURRENCY_ID` | Валюта | RUB, USD, EUR |
| `ASSIGNED_BY_ID` | ID ответственного | 1 |
| `CONTACT_ID` | ID контакта | 123 |
| `COMPANY_ID` | ID компании | 456 |
| `PROBABILITY` | Вероятность (%) | 80 |
| `COMMENTS` | Комментарий | "Важный клиент" |

## Стандартные стадии сделок

- `NEW` — Новая сделка
- `PREPARATION` — Подготовка
- `PREPAYMENT_INVOICE` — Выставлен счет
- `EXECUTING` — Выполняется
- `FINAL_INVOICE` — Финальный счет
- `WON` — Успешно реализовано
- `LOSE` — Провалено

## Операторы фильтрации

```php
$deals = $dealManager->getDeals([
    'STAGE_ID' => 'NEW',              // Равно
    '>OPPORTUNITY' => 100000,         // Больше
    '>=OPPORTUNITY' => 100000,        // Больше или равно
    '<OPPORTUNITY' => 500000,         // Меньше
    '<=OPPORTUNITY' => 500000,        // Меньше или равно
    '!STAGE_ID' => 'LOSE',            // Не равно
    '%TITLE' => 'CRM',                // Содержит (LIKE)
]);
```

## Обработка ошибок

```php
$dealId = $dealManager->createDeal([...]);

if ($dealId === false) {
    echo "Ошибка: " . $client->getLastError();
}
```

### Типичные ошибки

- **ERROR_CORE**: Недостаточно прав → проверьте права webhook
- **INVALID_ARG_VALUE**: Неверное значение поля → проверьте данные
- **NOT_FOUND**: Сделка не найдена → проверьте ID

## Производительность

### Используйте batch для множественных операций

❌ **Плохо** (3 HTTP-запроса):
```php
$dealManager->createDeal(['TITLE' => 'Сделка 1']);
$dealManager->createDeal(['TITLE' => 'Сделка 2']);
$dealManager->createDeal(['TITLE' => 'Сделка 3']);
```

✅ **Хорошо** (1 HTTP-запрос):
```php
$dealManager->createBatch([
    ['TITLE' => 'Сделка 1'],
    ['TITLE' => 'Сделка 2'],
    ['TITLE' => 'Сделка 3']
]);
```

### Используйте select для выборки только нужных полей

```php
$deals = $dealManager->getDeals(
    [],
    [],
    ['ID', 'TITLE', 'OPPORTUNITY'] // Только эти поля
);
```

## Интеграция в реальный проект

### Копирование в Bitrix-проект

```bash
# Скопируйте файлы в папку вашего проекта
cp CRestClient.php /path/to/bitrix/local/php_interface/classes/
cp DealManager.php /path/to/bitrix/local/php_interface/classes/
```

### Использование в компоненте

```php
// В component.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/CRestClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/DealManager.php';

$client = new CRestClient(WEBHOOK_URL);
$dealManager = new DealManager($client);

$this->arResult['DEALS'] = $dealManager->getDeals(['STAGE_ID' => 'NEW']);
```

### Автозагрузка через composer

```json
{
    "autoload": {
        "psr-4": {
            "App\\Bitrix24\\": "src/"
        }
    }
}
```

## Полезные ссылки

- [CRM REST API документация](https://dev.1c-bitrix.ru/rest_help/crm/index.php)
- [Методы для работы со сделками](https://dev.1c-bitrix.ru/rest_help/crm/deals/index.php)
- [Batch-запросы](https://dev.1c-bitrix.ru/rest_help/general/batch.php)
- [Фильтрация данных](https://dev.1c-bitrix.ru/rest_help/general/start_rest.php)

## Что изучается в этом примере

✅ Создание REST-клиента с обработкой ошибок  
✅ CRUD-операции со сделками  
✅ Фильтрация и сортировка данных  
✅ Автоматическая pagination (получение всех данных)  
✅ Batch-запросы для оптимизации  
✅ Работа с комментариями и стадиями  
✅ Статистика и аналитика  
✅ Обработка ошибок API
