# Виджет погоды (Weather Widget)

Компонент для отображения текущей погоды через OpenWeatherMap API.

## Описание

Этот компонент демонстрирует:
- Работу с внешним API через HttpClient
- Обработку JSON-ответов
- Настройку таймаутов и обработку ошибок
- Кеширование данных от внешнего сервиса
- Логирование запросов к API
- Форматирование данных для шаблона

## Структура файлов

```
weather-widget/
├── .description.php      # Описание компонента
├── .parameters.php       # Параметры (API ключ, город, единицы)
├── class.php            # Класс с логикой запросов к API
├── component.php        # Точка входа
└── templates/
    └── .default/
        ├── template.php # Шаблон отображения
        └── style.css    # Стили виджета
```

## Установка

### 1. Получите API ключ

Зарегистрируйтесь на [OpenWeatherMap](https://openweathermap.org/api) и получите бесплатный API ключ.

### 2. Скопируйте компонент

```bash
# Скопируйте папку в директорию компонентов
cp -r weather-widget /path/to/bitrix/components/custom/weather.widget
```

### 3. Подключите на странице

```php
<?$APPLICATION->IncludeComponent(
    "custom:weather.widget",
    ".default",
    [
        "API_KEY" => "ваш_api_ключ",
        "CITY" => "Moscow",
        "UNITS" => "metric",      // metric (Цельсий) или imperial (Фаренгейт)
        "LANG" => "ru",           // ru или en
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => 3600,     // Кешируем на 1 час
    ]
);?>
```

## Параметры компонента

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `API_KEY` | string | API ключ OpenWeatherMap (обязательно) | - |
| `CITY` | string | Название города | Moscow |
| `UNITS` | string | Единицы измерения (metric/imperial) | metric |
| `LANG` | string | Язык (ru/en) | ru |
| `CACHE_TIME` | int | Время кеширования (сек) | 3600 |

## Основные возможности

### 1. HTTP-запрос к внешнему API

```php
use Bitrix\Main\Web\HttpClient;

$httpClient = new HttpClient([
    'waitResponse' => true,
    'socketTimeout' => 5,
    'streamTimeout' => 10,
]);

$response = $httpClient->get($url);
$status = $httpClient->getStatus();
```

### 2. Обработка JSON-ответа

```php
use Bitrix\Main\Web\Json;

if ($status === 200) {
    $data = Json::decode($response);
}
```

### 3. Обработка ошибок

```php
try {
    $response = $httpClient->get($url);
    if ($httpClient->getStatus() !== 200) {
        throw new \Exception('API error');
    }
} catch (\Exception $e) {
    AddMessage2Log('Error: ' . $e->getMessage());
}
```

### 4. Логирование

```php
// Логирование запросов
AddMessage2Log('Weather API Request: ' . $url, 'weather_widget');

// Логирование ошибок
AddMessage2Log('Weather API Error: ' . $e->getMessage(), 'weather_widget');
```

## Что показывает виджет

- Текущая температура и ощущаемая температура
- Описание погоды с иконкой
- Влажность и давление
- Скорость ветра и облачность
- Время восхода и заката солнца

## Примеры использования

### Виджет для Москвы в метрической системе

```php
<?$APPLICATION->IncludeComponent(
    "custom:weather.widget",
    ".default",
    [
        "API_KEY" => "your_api_key",
        "CITY" => "Moscow",
        "UNITS" => "metric",
        "LANG" => "ru",
        "CACHE_TIME" => 1800,
    ]
);?>
```

### Виджет для Нью-Йорка в имперской системе

```php
<?$APPLICATION->IncludeComponent(
    "custom:weather.widget",
    ".default",
    [
        "API_KEY" => "your_api_key",
        "CITY" => "New York",
        "UNITS" => "imperial",
        "LANG" => "en",
        "CACHE_TIME" => 3600,
    ]
);?>
```

## Обработка ошибок

Компонент обрабатывает следующие ситуации:

1. **Не указан API ключ** - выводит сообщение об ошибке
2. **Ошибка запроса к API** - логирует ошибку, не ломает страницу
3. **Таймаут запроса** - автоматически прерывается через 10 секунд
4. **Некорректный JSON** - обрабатывается через try-catch

## Кеширование

Данные кешируются на заданное время (по умолчанию 1 час), чтобы:
- Снизить количество запросов к API
- Ускорить загрузку страницы
- Не превысить лимиты бесплатного плана OpenWeatherMap

## Логи

Все запросы и ошибки записываются в логи Bitrix:

```
/bitrix/modules/main/logs/
```

Префикс логов: `weather_widget`

## Ограничения API

**Бесплатный план OpenWeatherMap:**
- 1000 запросов в день
- 60 запросов в минуту
- Обновление данных каждые 10 минут

**Рекомендуемое время кеша:** минимум 30 минут (1800 секунд)

## Возможные улучшения

1. **Прогноз на несколько дней** - использовать endpoint `/forecast`
2. **Геолокация** - определять город пользователя автоматически
3. **Несколько городов** - показывать погоду в нескольких местах
4. **Виджет карты** - показать погоду на карте
5. **Push-уведомления** - предупреждать об изменениях погоды

## Полезные ссылки

- [OpenWeatherMap API Documentation](https://openweathermap.org/api)
- [Bitrix HttpClient](https://dev.1c-bitrix.ru/api_d7/bitrix/main/web/httpclient/index.php)
- [Список иконок погоды](https://openweathermap.org/weather-conditions)

## Отладка

### Проверка API ключа

```bash
curl "https://api.openweathermap.org/data/2.5/weather?q=Moscow&appid=YOUR_API_KEY"
```

### Просмотр логов

```php
// В файле class.php раскомментируйте строки с AddMessage2Log
// Затем смотрите логи в /bitrix/admin/event_log.php
```

### Отключение кеша для отладки

```php
[
    "CACHE_TYPE" => "N",  // Отключить кеш
]
```
