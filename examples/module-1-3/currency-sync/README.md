# Задача 3: Агент синхронизации с внешним API

## 📝 Описание задачи

Создать агент для автоматической синхронизации курсов валют с внешним API (ЦБ РФ).

## 🎯 Что демонстрирует этот пример

- Работа с внешними API через HTTP
- HTTP-клиент Bitrix (`Bitrix\Main\Web\HttpClient`)
- Обработка JSON-данных
- Retry-логика (повторные попытки при ошибках)
- Сохранение данных в настройки
- Обнаружение значительных изменений данных
- Очистка кеша
- Работа с модулем Currency

## 📂 Файлы

- `agent.php` - класс агента с логикой синхронизации
- `install.php` - регистрация агента
- `example_usage.php` - пример использования полученных данных
- `README.md` - описание и инструкции (этот файл)

## 🚀 Установка

### Вариант 1: Через скрипт установки

Откройте в браузере: `http://ваш-сайт.ru/путь/к/currency-sync/install.php`

Агент будет запускаться каждый час.

### Вариант 2: Регистрация вручную

```php
use Bitrix\Main\Agent;
use Bitrix\Main\Type\DateTime;

require_once __DIR__ . '/agent.php';

Agent::add([
    'NAME' => '\\MyCompany\\Agents\\CurrencyRatesAgent::execute();',
    'ACTIVE' => 'Y',
    'AGENT_INTERVAL' => 3600,  // Каждый час
    'IS_PERIOD' => 'Y',
    'NEXT_EXEC' => new DateTime(),
]);
```

## ⚙️ Настройка параметров

В файле `agent.php` можно изменить:

```php
// URL API для получения курсов
const API_URL = "https://www.cbr-xml-daily.ru/daily_json.js";

// Количество попыток при ошибке
const MAX_RETRIES = 3;

// Задержка между попытками (секунды)
const RETRY_DELAY = 5;

// Список синхронизируемых валют
const CURRENCIES = ['USD', 'EUR', 'GBP', 'CNY', 'JPY'];
```

### Изменение порога уведомления

В методе `checkSignificantChanges()`:

```php
$threshold = 2.0; // Порог изменения в процентах
```

## 📊 Логирование

### Файлы логов:
- `/bitrix/logs/currency_agent.log` - успешные операции
- `/bitrix/logs/currency_agent_errors.log` - ошибки

### Журнал событий:
**Настройки → Журнал событий**

Тип: `CURRENCY_SYNC`

## ✅ Проверка работы

### Запустить агент вручную:

```php
\MyCompany\Agents\CurrencyRatesAgent::execute();
```

### Просмотреть курсы:

Откройте в браузере: `http://ваш-сайт.ru/путь/к/currency-sync/example_usage.php`

### Получить курс программно:

```php
use Bitrix\Main\Web\Json;

$usdJson = COption::GetOptionString('currency', 'rate_USD', '');
$usdRate = Json::decode($usdJson);

echo "1 USD = {$usdRate['RATE']} руб.";
```

### Конвертация валют:

```php
$amountUSD = 100;
$amountRUB = $amountUSD * $usdRate['RATE'];
echo "{$amountUSD} USD = " . round($amountRUB, 2) . " RUB";
```

## 🔍 Как это работает

1. **Агент запускается** каждый час
2. **Делает HTTP-запрос** к API ЦБ РФ
3. **При ошибке повторяет** запрос до 3 раз с задержкой
4. **Парсит JSON-ответ** и извлекает курсы валют
5. **Сохраняет данные** в настройки сайта
6. **Обновляет** стандартный справочник валют Bitrix
7. **Проверяет значительные изменения** (>2%)
8. **Отправляет уведомления** при резких скачках
9. **Очищает кеш** компонентов

## 💡 Ключевые моменты кода

### HTTP-запрос через D7

```php
use Bitrix\Main\Web\HttpClient;

$httpClient = new HttpClient([
    'socketTimeout' => 10,
    'streamTimeout' => 10,
    'redirect' => true,
]);

$httpClient->setHeader('User-Agent', 'Bitrix Currency Sync Agent');
$httpClient->setHeader('Accept', 'application/json');

$response = $httpClient->get($apiUrl);
$status = $httpClient->getStatus();
```

### Retry-логика

```php
$attempt = 0;
while ($attempt < self::MAX_RETRIES) {
    $attempt++;
    
    try {
        $result = self::fetchRates();
        if ($result !== null) {
            return $result;
        }
    } catch (\Exception $e) {
        self::logError("Попытка {$attempt} failed");
    }
    
    if ($attempt < self::MAX_RETRIES) {
        sleep(self::RETRY_DELAY);
    }
}
```

### Обработка JSON

```php
use Bitrix\Main\Web\Json;

try {
    $data = Json::decode($response);
} catch (\Exception $e) {
    throw new \Exception("Invalid JSON: " . $e->getMessage());
}
```

### Обнаружение изменений

```php
$changePercent = abs(($newValue - $oldValue) / $oldValue * 100);

if ($changePercent >= $threshold) {
    self::notifySignificantChange($currency, $oldValue, $newValue, $changePercent);
}
```

### Очистка кеша

```php
\Bitrix\Main\Application::getInstance()
    ->getTaggedCache()
    ->clearByTag('currency_rates');
```

## 🎓 Что можно доработать

1. **Другие источники:** добавить поддержку других API (exchangerate-api.com, fixer.io)
2. **История курсов:** сохранять изменения в отдельную таблицу для построения графиков
3. **Highload-блок:** использовать HL-блок вместо настроек для больших объемов
4. **Fallback:** переключение на резервный API при недоступности основного
5. **Webhooks:** отправка данных в Telegram/Slack при изменениях
6. **Криптовалюты:** добавить синхронизацию курсов BTC, ETH и др.
7. **Прогнозирование:** ML-модель для предсказания изменений

## 🔧 Настройка для работы через cron

Для гарантированного выполнения:

**1. Создайте `/bitrix/php_interface/cron_agents.php`:**

```php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/path/to/site";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('BX_CRONTAB', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
CAgent::CheckAgents();
```

**2. Добавьте в crontab:**

```bash
# Каждый час
0 * * * * /usr/bin/php /path/to/site/bitrix/php_interface/cron_agents.php
```

**3. Обновите агент:**

```php
Agent::update($agentId, ['IS_CRON' => 'Y']);
```

## 📚 Связанные темы

- HTTP-клиент: `Bitrix\Main\Web\HttpClient`
- JSON: `Bitrix\Main\Web\Json`
- Кеширование: `Bitrix\Main\Application::getTaggedCache()`
- Модуль валют: `CCurrency`, `CCurrencyRates`
- Настройки: `COption`

## 🐛 Решение проблем

### API недоступен

- Проверьте доступность API: `curl https://www.cbr-xml-daily.ru/daily_json.js`
- Убедитесь, что сервер может делать исходящие HTTP-запросы
- Проверьте firewall и прокси-настройки

### Ошибка "Call to undefined method HttpClient"

- Убедитесь, что используется D7 API: `use Bitrix\Main\Web\HttpClient;`
- Проверьте версию Bitrix (D7 доступен с версии 12.5)

### JSON не парсится

- Проверьте формат ответа API (может измениться)
- Добавьте логирование: `Debug::writeToFile($response, '', 'api_response.log');`
- Проверьте кодировку ответа

### Курсы не обновляются

- Проверьте, активен ли агент в админке
- Посмотрите логи ошибок
- Запустите агент вручную для отладки

### Ошибка timeout

- Увеличьте таймауты в HttpClient
- Проверьте скорость соединения с API
- Используйте более быстрый API

## 💼 Применение на практике

Этот пример демонстрирует частые задачи в реальных проектах:

- **Интернет-магазины:** автообновление цен в разных валютах
- **Финансовые сервисы:** актуальные курсы для конвертации
- **Мониторинг:** отслеживание изменений внешних данных
- **Интеграции:** синхронизация с CRM, ERP, складскими системами

### Вопросы на собеседовании:

**Q: Как обрабатывать ошибки при работе с внешним API?**
A: Использовать try-catch, retry-логику, логировать ошибки, иметь fallback-решения

**Q: Как защититься от слишком частых запросов к API?**
A: Кешировать результаты, использовать rate limiting, контролировать интервал агента

**Q: Что делать, если API изменил формат ответа?**
A: Валидировать структуру ответа, логировать несоответствия, иметь версионирование парсеров

**Q: Как хранить исторические данные?**
A: Использовать отдельную таблицу или HL-блок, сохранять timestamp каждого изменения

## 📖 Альтернативные решения

### Использование Highload-блока

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$hlbl = HighloadBlockTable::getById($hlblockId)->fetch();
$entity = HighloadBlockTable::compileEntity($hlbl);
$entityClass = $entity->getDataClass();

$entityClass::add([
    'UF_CURRENCY_CODE' => 'USD',
    'UF_RATE' => 75.50,
    'UF_DATE' => new DateTime(),
]);
```

### Использование отдельной таблицы

```php
use Bitrix\Main\Entity\DataManager;

class CurrencyRateTable extends DataManager
{
    public static function getTableName()
    {
        return 'b_currency_rates';
    }

    public static function getMap()
    {
        return [
            'ID' => ['data_type' => 'integer', 'primary' => true, 'autocomplete' => true],
            'CURRENCY_CODE' => ['data_type' => 'string', 'required' => true],
            'RATE' => ['data_type' => 'float', 'required' => true],
            'DATE_CREATE' => ['data_type' => 'datetime', 'default_value' => function() {
                return new DateTime();
            }],
        ];
    }
}
```

## 🎬 Заключение

Этот агент демонстрирует полный цикл интеграции с внешним API:
- Выполнение запросов
- Обработка ошибок
- Сохранение данных
- Уведомления
- Мониторинг изменений

Код готов к использованию в продакшене и покрывает основные edge cases.
