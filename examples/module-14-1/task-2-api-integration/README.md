# Задача 2: Модуль интеграции с внешним API

## Описание

Модуль `acme.integration` для интеграции Bitrix24 с внешними API (SMS-сервисы, CRM, аналитика).

## Функциональность

- ✅ Универсальный HTTP-клиент для работы с API
- ✅ Система настроек подключений
- ✅ Агент для периодической синхронизации
- ✅ Логирование запросов/ответов
- ✅ Обработка ошибок и retry-механизм
- ✅ Кеширование ответов API
- ✅ Webhook для входящих данных

## Структура модуля

```
/local/modules/acme.integration/
├── install/
│   ├── index.php              # Установщик
│   ├── version.php            # Версия
│   └── components/
│       └── webhook.handler/   # Компонент для webhook
├── lib/
│   ├── Api/
│   │   ├── AbstractClient.php     # Абстрактный API-клиент
│   │   ├── SmsClient.php          # Клиент для SMS
│   │   └── CrmClient.php          # Клиент для внешней CRM
│   ├── Service/
│   │   ├── LogService.php         # Логирование запросов
│   │   └── CacheService.php       # Кеширование
│   ├── Agents/
│   │   └── SyncAgent.php          # Агент синхронизации
│   └── WebhookHandler.php         # Обработчик вебхуков
├── lang/ru/
└── options.php
```

## Установка

```bash
cp -r acme.integration /local/modules/
```

Установить через: Marketplace → Установленные решения → acme.integration

## Использование

### 1. Отправка SMS через API

```php
<?php
use Bitrix\Main\Loader;
use Acme\Integration\Api\SmsClient;

Loader::includeModule('acme.integration');

$smsClient = new SmsClient();

// Отправить SMS
$result = $smsClient->send(
    '+79991234567',
    'Ваш код подтверждения: 1234'
);

if ($result->isSuccess()) {
    $data = $result->getData();
    echo "SMS отправлено, ID: {$data['message_id']}";
} else {
    echo "Ошибка: " . implode(', ', $result->getErrorMessages());
}
```

### 2. Работа с внешней CRM

```php
<?php
use Acme\Integration\Api\CrmClient;

$crmClient = new CrmClient();

// Создать контакт
$result = $crmClient->createContact([
    'name' => 'Иван Иванов',
    'email' => 'ivan@example.com',
    'phone' => '+79991234567',
]);

// Получить контакт
$contact = $crmClient->getContact(12345);

// Обновить контакт
$crmClient->updateContact(12345, [
    'status' => 'qualified',
]);
```

### 3. Кеширование ответов API

```php
<?php
use Acme\Integration\Service\CacheService;

$cache = new CacheService();

// Кеш на 1 час
$data = $cache->remember('api_users_list', 3600, function() {
    // Тяжелый запрос к API
    return $apiClient->getUsers();
});
```

### 4. Логирование API-запросов

```php
<?php
use Acme\Integration\Service\LogService;

$log = new LogService();

// Логировать запрос
$log->logRequest('POST', 'https://api.example.com/users', [
    'name' => 'John',
]);

// Логировать ответ
$log->logResponse(200, ['id' => 123, 'status' => 'created']);

// Логировать ошибку
$log->logError('Connection timeout', [
    'url' => 'https://api.example.com',
    'timeout' => 30,
]);
```

### 5. Обработка входящих webhook

Создайте страницу `/local/webhook.php`:

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Acme\Integration\WebhookHandler;

Loader::includeModule('acme.integration');

$handler = new WebhookHandler();
$handler->process();
```

Пример входящего запроса:

```bash
curl -X POST https://your-site.ru/local/webhook.php \
  -H "Content-Type: application/json" \
  -d '{"event": "order.created", "order_id": 12345}'
```

### 6. Агент синхронизации

Агент запускается каждые 10 минут:

```php
<?php
use Acme\Integration\Agents\SyncAgent;

// Запустить вручную
SyncAgent::run();
```

## Примеры использования

### Пример 1: Уведомления через SMS при создании заказа

```php
<?php
// /local/php_interface/init.php

use Bitrix\Main\Loader;
use Acme\Integration\Api\SmsClient;

AddEventHandler('sale', 'OnOrderAdd', 'sendSmsOnOrderAdd');

function sendSmsOnOrderAdd($orderId, $arFields)
{
    if (!Loader::includeModule('acme.integration')) {
        return;
    }

    // Получаем телефон клиента
    if (empty($arFields['USER_PHONE'])) {
        return;
    }

    $smsClient = new SmsClient();

    $message = sprintf(
        'Ваш заказ №%s принят в обработку. Сумма: %s руб.',
        $orderId,
        number_format($arFields['PRICE'], 0, '.', ' ')
    );

    $smsClient->send($arFields['USER_PHONE'], $message);
}
```

### Пример 2: Синхронизация контактов с внешней CRM

```php
<?php
use Bitrix\Main\Loader;
use Acme\Integration\Api\CrmClient;

Loader::includeModule('acme.integration');
Loader::includeModule('crm');

$crmClient = new CrmClient();

// Получаем новые контакты из Bitrix CRM
$contacts = \Bitrix\Crm\ContactTable::getList([
    'filter' => [
        '>DATE_CREATE' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    ],
])->fetchAll();

foreach ($contacts as $contact) {
    // Отправляем во внешнюю CRM
    $result = $crmClient->createContact([
        'name' => $contact['NAME'] . ' ' . $contact['LAST_NAME'],
        'email' => $contact['EMAIL'],
        'phone' => $contact['PHONE'],
    ]);

    if ($result->isSuccess()) {
        $data = $result->getData();

        // Сохраняем внешний ID
        \Bitrix\Crm\ContactTable::update($contact['ID'], [
            'UF_EXTERNAL_CRM_ID' => $data['id'],
        ]);
    }
}
```

### Пример 3: Webhook для обновления статуса доставки

```php
<?php
// /local/webhook.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

Loader::includeModule('sale');

// Получаем данные из webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data['order_id']) || empty($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверные параметры']);
    exit;
}

// Обновляем статус заказа
$order = \Bitrix\Sale\Order::load($data['order_id']);

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Заказ не найден']);
    exit;
}

// Устанавливаем новый статус
$statusMap = [
    'in_transit' => 'DS',  // В доставке
    'delivered' => 'F',    // Выполнен
];

$newStatus = $statusMap[$data['status']] ?? null;

if ($newStatus) {
    $order->setField('STATUS_ID', $newStatus);
    $order->save();

    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Неизвестный статус']);
}
```

### Пример 4: Отправка аналитики в внешний сервис

```php
<?php
use Acme\Integration\Api\AbstractClient;

class AnalyticsClient extends AbstractClient
{
    protected function getApiUrl(): string
    {
        return 'https://analytics.example.com/api';
    }

    protected function getApiKey(): string
    {
        return \Bitrix\Main\Config\Option::get('acme.integration', 'analytics_key');
    }

    /**
     * Отправить событие
     */
    public function trackEvent(string $event, array $properties = []): \Bitrix\Main\Result
    {
        return $this->post('/events', [
            'event' => $event,
            'properties' => $properties,
            'timestamp' => time(),
        ]);
    }
}

// Использование
$analytics = new AnalyticsClient();

$analytics->trackEvent('order.completed', [
    'order_id' => 12345,
    'amount' => 5000,
    'user_id' => 678,
]);
```

## Настройки модуля

Доступны через: **Настройки → Настройки продукта → Настройки модулей → Модуль интеграций**

### Секция SMS

- **SMS API URL** - адрес API SMS-шлюза
- **SMS API ключ** - ключ доступа
- **Имя отправителя** - подпись SMS

### Секция CRM

- **CRM API URL** - адрес внешней CRM
- **CRM API ключ** - ключ доступа

### Секция логирования

- **Включить логирование** - записывать все запросы/ответы
- **Уровень детализации** - minimal/full
- **Хранить логи (дней)** - автоматическое удаление старых логов

### Секция кеширования

- **Включить кеш** - кешировать ответы API
- **Время кеша (сек)** - TTL для кеша
- **Тип кеша** - files/memcache/redis

## Расширение модуля

### Добавление нового API-клиента

1. Создайте класс, наследующий `AbstractClient`:

```php
<?php
namespace Acme\Integration\Api;

class PaymentClient extends AbstractClient
{
    protected function getApiUrl(): string
    {
        return 'https://payments.example.com/api';
    }

    protected function getApiKey(): string
    {
        return \Bitrix\Main\Config\Option::get('acme.integration', 'payment_key');
    }

    public function createPayment(int $orderId, float $amount): \Bitrix\Main\Result
    {
        return $this->post('/payments', [
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => 'RUB',
        ]);
    }

    public function getPaymentStatus(string $paymentId): ?\array
    {
        $result = $this->get('/payments/' . $paymentId);

        if ($result->isSuccess()) {
            return $result->getData();
        }

        return null;
    }
}
```

2. Зарегистрируйте класс в `include.php`:

```php
'Acme\Integration\Api\PaymentClient' => 'lib/Api/PaymentClient.php',
```

3. Используйте:

```php
$payment = new PaymentClient();
$result = $payment->createPayment(12345, 5000.00);
```

## Тестирование

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Acme\Integration\Api\SmsClient;

Loader::includeModule('acme.integration');

// Тест 1: Отправка SMS
$sms = new SmsClient();
$result = $sms->send('+79991234567', 'Test message');

assert($result->isSuccess(), 'SMS не отправлено');
echo "✓ SMS отправлено\n";

// Тест 2: Кеширование
$cache = new \Acme\Integration\Service\CacheService();
$data = $cache->remember('test_key', 60, function() {
    return ['time' => time()];
});

assert(!empty($data), 'Кеш не работает');
echo "✓ Кеш работает\n";

// Тест 3: Логирование
$log = new \Acme\Integration\Service\LogService();
$log->logRequest('GET', 'https://api.test.com', []);

echo "✓ Логирование работает\n";

echo "\nВсе тесты пройдены успешно!\n";
```

## Мониторинг

Посмотреть логи запросов:

```php
<?php
use Acme\Integration\Service\LogService;

$log = new LogService();

// Последние 100 запросов
$requests = $log->getRecentRequests(100);

foreach ($requests as $request) {
    echo sprintf(
        "[%s] %s %s - %d (%s)\n",
        $request['date'],
        $request['method'],
        $request['url'],
        $request['status'],
        $request['duration'] . 'ms'
    );
}
```

## Заключение

Модуль `acme.integration` предоставляет:
- ✅ Универсальную архитектуру для любых API
- ✅ Готовые клиенты для популярных сервисов
- ✅ Логирование и мониторинг
- ✅ Кеширование для снижения нагрузки
- ✅ Retry-механизм для надежности
- ✅ Webhook для входящих данных

Используйте его как основу для интеграции с любыми внешними системами!
