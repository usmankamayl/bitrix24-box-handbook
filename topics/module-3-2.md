# Модуль 3.2: Работа с внешними API

## Теория

### HTTP-клиент в Bitrix

Bitrix Framework предоставляет класс `Bitrix\Main\Web\HttpClient` для работы с внешними API.

#### Основные возможности:
- Выполнение HTTP-запросов (GET, POST, PUT, DELETE)
- Поддержка различных методов аутентификации
- Работа с заголовками и cookies
- Обработка ответов (JSON, XML, HTML)
- Настройка таймаутов
- Поддержка SSL

### Создание HTTP-клиента

```php
use Bitrix\Main\Web\HttpClient;

$httpClient = new HttpClient([
    'waitResponse' => true,        // Ждать ответа от сервера
    'socketTimeout' => 30,         // Таймаут соединения (сек)
    'streamTimeout' => 60,         // Таймаут чтения данных (сек)
    'redirect' => true,            // Следовать редиректам
    'redirectMax' => 5,            // Максимум редиректов
    'version' => HttpClient::HTTP_1_1,  // Версия HTTP
]);
```

### Методы запросов

#### GET-запрос
```php
$url = 'https://api.example.com/data';
$response = $httpClient->get($url);

if ($httpClient->getStatus() === 200) {
    $data = \Bitrix\Main\Web\Json::decode($response);
}
```

#### POST-запрос
```php
$url = 'https://api.example.com/create';
$postData = [
    'name' => 'Test',
    'email' => 'test@example.com'
];

$response = $httpClient->post($url, $postData);
```

#### POST с JSON
```php
$httpClient->setHeader('Content-Type', 'application/json');
$jsonData = \Bitrix\Main\Web\Json::encode($postData);
$response = $httpClient->post($url, $jsonData);
```

### Работа с заголовками

#### Установка заголовков
```php
// Один заголовок
$httpClient->setHeader('Authorization', 'Bearer YOUR_TOKEN');

// Несколько заголовков
$httpClient->setHeaders([
    'Authorization' => 'Bearer YOUR_TOKEN',
    'Accept' => 'application/json',
    'User-Agent' => 'BitrixApp/1.0'
]);
```

#### Чтение заголовков ответа
```php
$response = $httpClient->get($url);
$headers = $httpClient->getHeaders();
$contentType = $headers->get('Content-Type');
```

### Типы аутентификации

#### 1. API Key в заголовке
```php
$httpClient->setHeader('X-API-Key', 'your_api_key');
$response = $httpClient->get($url);
```

#### 2. Bearer Token
```php
$httpClient->setHeader('Authorization', 'Bearer ' . $accessToken);
$response = $httpClient->get($url);
```

#### 3. Basic Authentication
```php
$httpClient->setAuthorization('username', 'password');
$response = $httpClient->get($url);
```

#### 4. API Key в параметрах URL
```php
$url = 'https://api.example.com/data?api_key=' . urlencode($apiKey);
$response = $httpClient->get($url);
```

### Обработка ответов

#### JSON
```php
use Bitrix\Main\Web\Json;

$response = $httpClient->get($url);

if ($httpClient->getStatus() === 200) {
    try {
        $data = Json::decode($response);
        // Работа с данными
    } catch (\Bitrix\Main\ArgumentException $e) {
        // Ошибка парсинга JSON
    }
}
```

#### XML
```php
$response = $httpClient->get($url);

if ($httpClient->getStatus() === 200) {
    $xml = simplexml_load_string($response);
    if ($xml !== false) {
        // Работа с XML
    }
}
```

### Обработка ошибок

```php
$response = $httpClient->get($url);
$status = $httpClient->getStatus();

switch ($status) {
    case 200:
        // Успех
        $data = Json::decode($response);
        break;
        
    case 401:
        // Ошибка аутентификации
        throw new \Exception('Unauthorized: проверьте токен');
        
    case 403:
        // Доступ запрещен
        throw new \Exception('Forbidden: недостаточно прав');
        
    case 404:
        // Не найдено
        throw new \Exception('Not Found: ресурс не существует');
        
    case 429:
        // Превышен лимит запросов
        throw new \Exception('Too Many Requests: попробуйте позже');
        
    case 500:
    case 502:
    case 503:
        // Ошибки сервера
        throw new \Exception('Server Error: попробуйте позже');
        
    default:
        // Другие ошибки
        $errors = $httpClient->getError();
        throw new \Exception('HTTP Error ' . $status . ': ' . implode(', ', $errors));
}
```

### Таймауты и повторные попытки

#### Настройка таймаутов
```php
$httpClient = new HttpClient([
    'socketTimeout' => 10,   // Таймаут подключения
    'streamTimeout' => 30,   // Таймаут чтения
]);
```

#### Повторные попытки
```php
function makeRequestWithRetry($url, $maxAttempts = 3, $delay = 1) {
    $httpClient = new HttpClient();
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        $response = $httpClient->get($url);
        $status = $httpClient->getStatus();
        
        if ($status === 200) {
            return $response;
        }
        
        // Повторяем только при временных ошибках
        if (in_array($status, [429, 500, 502, 503, 504])) {
            $attempt++;
            if ($attempt < $maxAttempts) {
                sleep($delay * $attempt); // Экспоненциальная задержка
                continue;
            }
        }
        
        throw new \Exception('Request failed with status ' . $status);
    }
    
    throw new \Exception('Max retry attempts reached');
}
```

### Логирование запросов

```php
use Bitrix\Main\Diag\Logger;

class ApiLogger {
    private static function log($message, $context = []) {
        $logger = Logger::create('api_requests');
        $logger->info($message, $context);
    }
    
    public static function logRequest($url, $method, $params = []) {
        self::log('API Request', [
            'url' => $url,
            'method' => $method,
            'params' => $params,
            'time' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function logResponse($url, $status, $duration) {
        self::log('API Response', [
            'url' => $url,
            'status' => $status,
            'duration' => $duration . 'ms',
            'time' => date('Y-m-d H:i:s')
        ]);
    }
    
    public static function logError($url, $error) {
        $logger = Logger::create('api_errors');
        $logger->error('API Error', [
            'url' => $url,
            'error' => $error,
            'time' => date('Y-m-d H:i:s')
        ]);
    }
}

// Использование
$startTime = microtime(true);
ApiLogger::logRequest($url, 'GET', $params);

try {
    $response = $httpClient->get($url);
    $duration = round((microtime(true) - $startTime) * 1000);
    ApiLogger::logResponse($url, $httpClient->getStatus(), $duration);
} catch (\Exception $e) {
    ApiLogger::logError($url, $e->getMessage());
}
```

### Работа с SSL

```php
// Проверка SSL-сертификата (по умолчанию включена)
$httpClient->setOption(CURLOPT_SSL_VERIFYPEER, true);
$httpClient->setOption(CURLOPT_SSL_VERIFYHOST, 2);

// Отключение проверки (только для разработки!)
$httpClient->setOption(CURLOPT_SSL_VERIFYPEER, false);
$httpClient->setOption(CURLOPT_SSL_VERIFYHOST, 0);
```

### Proxy

```php
$httpClient->setProxy(
    'proxy.example.com',  // Хост
    8080,                 // Порт
    'username',           // Логин (опционально)
    'password'            // Пароль (опционально)
);
```

## Вопросы для собеседования

### 1. Как создать HTTP-клиент в Bitrix?
**Ответ:** Используется класс `Bitrix\Main\Web\HttpClient`:
```php
use Bitrix\Main\Web\HttpClient;

$httpClient = new HttpClient([
    'waitResponse' => true,
    'socketTimeout' => 30,
    'streamTimeout' => 60,
]);
```

### 2. Какие методы HTTP поддерживает HttpClient?
**Ответ:**
- `get($url)` - GET-запрос
- `post($url, $postData)` - POST-запрос
- `put($url, $data)` - PUT-запрос (через setOption)
- `delete($url)` - DELETE-запрос (через setOption)
- Любой метод через `query($method, $url, $data)`

### 3. Как передать Bearer Token в запросе?
**Ответ:**
```php
$httpClient->setHeader('Authorization', 'Bearer ' . $accessToken);
$response = $httpClient->get($url);
```

### 4. Как обработать JSON-ответ?
**Ответ:**
```php
use Bitrix\Main\Web\Json;

$response = $httpClient->get($url);
if ($httpClient->getStatus() === 200) {
    $data = Json::decode($response);
}
```

### 5. Как узнать статус ответа?
**Ответ:**
```php
$httpClient->get($url);
$status = $httpClient->getStatus();  // Например, 200, 404, 500
```

### 6. Как настроить таймауты?
**Ответ:**
```php
$httpClient = new HttpClient([
    'socketTimeout' => 10,   // Таймаут подключения (сек)
    'streamTimeout' => 30,   // Таймаут чтения данных (сек)
]);
```

### 7. Как отправить POST-запрос с JSON?
**Ответ:**
```php
$httpClient->setHeader('Content-Type', 'application/json');
$jsonData = Json::encode(['key' => 'value']);
$response = $httpClient->post($url, $jsonData);
```

### 8. Как получить заголовки ответа?
**Ответ:**
```php
$response = $httpClient->get($url);
$headers = $httpClient->getHeaders();
$contentType = $headers->get('Content-Type');
```

### 9. Как обработать ошибки запроса?
**Ответ:**
```php
$response = $httpClient->get($url);
$status = $httpClient->getStatus();

if ($status !== 200) {
    $errors = $httpClient->getError();
    // Обработка ошибки
}
```

### 10. Как реализовать повторные попытки при ошибке?
**Ответ:** Создать функцию с циклом:
```php
$attempt = 0;
while ($attempt < 3) {
    $response = $httpClient->get($url);
    if ($httpClient->getStatus() === 200) {
        break;
    }
    $attempt++;
    sleep(1);
}
```

## Частые ошибки

### 1. ❌ Не проверяется статус ответа
```php
// Неправильно
$data = Json::decode($httpClient->get($url));

// Правильно
$response = $httpClient->get($url);
if ($httpClient->getStatus() === 200) {
    $data = Json::decode($response);
}
```

### 2. ❌ Не обрабатываются исключения JSON
```php
// Неправильно
$data = Json::decode($response);

// Правильно
try {
    $data = Json::decode($response);
} catch (\Bitrix\Main\ArgumentException $e) {
    // Обработка ошибки парсинга
}
```

### 3. ❌ Не устанавливается Content-Type для JSON
```php
// Неправильно
$httpClient->post($url, Json::encode($data));

// Правильно
$httpClient->setHeader('Content-Type', 'application/json');
$httpClient->post($url, Json::encode($data));
```

### 4. ❌ Отсутствуют таймауты
```php
// Неправильно
$httpClient = new HttpClient();

// Правильно
$httpClient = new HttpClient([
    'socketTimeout' => 10,
    'streamTimeout' => 30,
]);
```

### 5. ❌ Не логируются запросы к API
```php
// Неправильно
$httpClient->get($url);

// Правильно
try {
    $response = $httpClient->get($url);
    Logger::create('api')->info('Request success', ['url' => $url]);
} catch (\Exception $e) {
    Logger::create('api')->error('Request failed', [
        'url' => $url,
        'error' => $e->getMessage()
    ]);
}
```

## Практические задачи

Все примеры кода находятся в `examples/module-3-2/`:

1. **weather-widget** - Виджет погоды с интеграцией OpenWeatherMap API
2. **currency-rates** - Получение и кеширование курсов валют
3. **sms-sender** - Отправка SMS через внешний сервис

## Полезные ссылки

- [Документация HttpClient](https://dev.1c-bitrix.ru/api_d7/bitrix/main/web/httpclient/index.php)
- [Работа с JSON](https://dev.1c-bitrix.ru/api_d7/bitrix/main/web/json/index.php)
- [HTTP Status Codes](https://httpstatuses.com/)
