# Курсы валют (Currency Rates)

Компонент для отображения актуальных курсов валют с API Центрального Банка РФ.

## Описание

Этот компонент демонстрирует:
- Работу с внешним API (ЦБ РФ) через HttpClient
- Обработку JSON-ответов без API ключа
- Кеширование данных на длительный срок
- Форматирование финансовых данных
- Отображение динамики изменения курсов
- Логирование запросов с метриками времени

## Структура файлов

```
currency-rates/
├── .description.php      # Описание компонента
├── .parameters.php       # Параметры (валюты, базовая валюта)
├── class.php            # Класс с логикой запросов
├── component.php        # Точка входа
└── templates/
    └── .default/
        ├── template.php # Шаблон отображения
        └── style.css    # Стили таблицы курсов
```

## Установка

### 1. Скопируйте компонент

```bash
cp -r currency-rates /path/to/bitrix/components/custom/currency.rates
```

### 2. Подключите на странице

```php
<?$APPLICATION->IncludeComponent(
    "custom:currency.rates",
    ".default",
    [
        "CURRENCIES" => "USD,EUR,CNY,GBP",
        "BASE_CURRENCY" => "RUB",
        "SHOW_DYNAMICS" => "Y",
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => 86400,  // Кешируем на 24 часа
    ]
);?>
```

## Параметры компонента

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `CURRENCIES` | string | Список валют через запятую | USD,EUR,CNY,GBP |
| `BASE_CURRENCY` | string | Базовая валюта (RUB/USD/EUR) | RUB |
| `SHOW_DYNAMICS` | string | Показывать изменение курса (Y/N) | Y |
| `CACHE_TIME` | int | Время кеширования (сек) | 86400 |

## Основные возможности

### 1. Запрос к API без авторизации

```php
use Bitrix\Main\Web\HttpClient;

$httpClient = new HttpClient([
    'waitResponse' => true,
    'socketTimeout' => 5,
    'streamTimeout' => 10,
]);

$httpClient->setHeader('Accept', 'application/json');
$response = $httpClient->get($url);
```

### 2. Обработка JSON без библиотеки

```php
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \Exception('JSON parse error: ' . json_last_error_msg());
}
```

### 3. Логирование с метриками

```php
$startTime = microtime(true);
$response = $httpClient->get($url);
$duration = round((microtime(true) - $startTime) * 1000);

AddMessage2Log('Response: status=' . $status . ', duration=' . $duration . 'ms');
```

### 4. Расчет изменения курса

```php
$change = $valute['Value'] - $valute['Previous'];
$changePercent = ($change / $valute['Previous']) * 100;
```

## Что показывает компонент

- Текущий курс валюты к рублю
- Номинал (для какого количества единиц)
- Изменение курса с предыдущего дня
- Процент изменения
- Цветовая индикация роста/падения

## Примеры использования

### Основные валюты

```php
<?$APPLICATION->IncludeComponent(
    "custom:currency.rates",
    ".default",
    [
        "CURRENCIES" => "USD,EUR",
        "SHOW_DYNAMICS" => "Y",
        "CACHE_TIME" => 43200,  // 12 часов
    ]
);?>
```

### Расширенный список

```php
<?$APPLICATION->IncludeComponent(
    "custom:currency.rates",
    ".default",
    [
        "CURRENCIES" => "USD,EUR,CNY,GBP,JPY,CHF,TRY",
        "SHOW_DYNAMICS" => "Y",
        "CACHE_TIME" => 86400,
    ]
);?>
```

### Без динамики

```php
<?$APPLICATION->IncludeComponent(
    "custom:currency.rates",
    ".default",
    [
        "CURRENCIES" => "USD,EUR",
        "SHOW_DYNAMICS" => "N",
        "CACHE_TIME" => 86400,
    ]
);?>
```

## Доступные валюты

API ЦБ РФ предоставляет курсы для следующих валют:

- **USD** - Доллар США
- **EUR** - Евро
- **GBP** - Фунт стерлингов
- **CNY** - Китайский юань
- **JPY** - Японская иена
- **CHF** - Швейцарский франк
- **TRY** - Турецкая лира
- **INR** - Индийская рупия
- **BYN** - Белорусский рубль
- **UAH** - Украинская гривна
- **KZT** - Казахстанский тенге
- И другие...

Полный список доступен по адресу: https://www.cbr-xml-daily.ru/daily_json.js

## Обработка ошибок

Компонент обрабатывает:

1. **Ошибка подключения** - логирует ошибку, не ломает страницу
2. **Таймаут запроса** - автоматически прерывается через 10 секунд
3. **Некорректный JSON** - обрабатывается через json_last_error()
4. **Отсутствие валюты** - пропускает без ошибки

## Кеширование

**Рекомендуемое время кеша:** 12-24 часа

**Почему:**
- ЦБ РФ обновляет курсы 1 раз в день
- Снижается нагрузка на внешний API
- Ускоряется загрузка страницы

## Логи

Все запросы и ошибки записываются в логи Bitrix:

```
/bitrix/modules/main/logs/
```

**Что логируется:**
- URL запроса
- HTTP статус ответа
- Время выполнения запроса (в миллисекундах)
- Ошибки парсинга или подключения

Префикс логов: `currency_rates`

## API Центрального Банка РФ

**Endpoint:** https://www.cbr-xml-daily.ru/daily_json.js

**Особенности:**
- Бесплатный, без регистрации
- Обновление 1 раз в день (рабочие дни)
- Формат ответа: JSON
- Не требует API ключа
- Лимиты не указаны

**Структура ответа:**
```json
{
  "Date": "2024-01-15T11:30:00+03:00",
  "PreviousDate": "2024-01-14T11:30:00+03:00",
  "Valute": {
    "USD": {
      "ID": "R01235",
      "NumCode": "840",
      "CharCode": "USD",
      "Nominal": 1,
      "Name": "Доллар США",
      "Value": 75.5678,
      "Previous": 75.1234
    }
  }
}
```

## Возможные улучшения

1. **Конвертер валют** - добавить калькулятор пересчета
2. **График курса** - показать изменения за период
3. **Уведомления** - предупреждать о резких изменениях
4. **Сравнение** - сравнивать курсы разных банков
5. **Экспорт** - выгружать данные в Excel/CSV

## Альтернативные API

Если нужны другие источники курсов:

1. **exchangerate-api.com** - бесплатный, 1500 запросов/месяц
2. **fixer.io** - популярный, есть бесплатный план
3. **currencylayer.com** - 100 запросов/месяц бесплатно
4. **openexchangerates.org** - 1000 запросов/месяц

## Отладка

### Проверка API

```bash
curl "https://www.cbr-xml-daily.ru/daily_json.js"
```

### Просмотр логов

```php
// В /bitrix/admin/event_log.php
// Фильтр по AUDIT_TYPE_ID: currency_rates
```

### Отключение кеша

```php
[
    "CACHE_TYPE" => "N",  // Для отладки
]
```

## Безопасность

✅ **Безопасно:**
- Данные от ЦБ РФ (официальный источник)
- Все выводы через htmlspecialchars()
- Таймауты настроены
- Обработка всех ошибок

⚠️ **Важно:**
- API ЦБ РФ работает только в РФ (может быть недоступен из других стран)
- Используйте VPN если работаете из-за границы

## Полезные ссылки

- [API ЦБ РФ](https://www.cbr.ru/development/SXML/)
- [Bitrix HttpClient](https://dev.1c-bitrix.ru/api_d7/bitrix/main/web/httpclient/index.php)
- [JSON в PHP](https://www.php.net/manual/ru/function.json-decode.php)
