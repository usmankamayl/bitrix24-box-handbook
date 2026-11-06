# Модуль 8.4: События и обработчики в Bitrix24 REST API

## Введение

События в Bitrix24 REST API — это асинхронные уведомления о изменении данных в системе. Вместо постоянного опроса (polling) данных, ты можешь подписаться на события и получить вебхук, когда происходит нужное событие.

**Основные понятия:**
- **События (Events)** — что произошло в системе (создана сделка, обновлен лид, и т.д.)
- **Вебхуки (Webhooks)** — URL, на который отправляется уведомление
- **Обработчики (Event Handlers)** — код, который обрабатывает полученное событие

## События в Bitrix24 CRM

### Основные события CRM

#### Лиды (Leads)
- **onCrmLeadAdd** — создан новый лид
- **onCrmLeadUpdate** — обновлен существующий лид
- **onCrmLeadDelete** — удален лид
- **onCrmLeadStatusChanged** — изменена стадия лида

Пример JSON события `onCrmLeadAdd`:
```json
{
  "event": "onCrmLeadAdd",
  "data": {
    "id": 42,
    "title": "ООО Новая компания",
    "name": "Иван",
    "last_name": "Петров",
    "status_id": "1",
    "created_time": "2024-01-15T10:30:00+00:00",
    "source_id": "WEB",
    "assigned_by_id": 1,
    "responsible_by_id": 1,
    "opened": "Y",
    "comments": "Интересуется продуктом"
  }
}
```

#### Сделки (Deals)
- **onCrmDealAdd** — создана новая сделка
- **onCrmDealUpdate** — обновлена сделка
- **onCrmDealDelete** — удалена сделка
- **onCrmDealStageChange** — сделка перемещена на другую стадию
- **onCrmDealStatusChange** — изменен статус сделки (выиграна/проиграна)

Пример JSON события `onCrmDealAdd`:
```json
{
  "event": "onCrmDealAdd",
  "data": {
    "id": 123,
    "title": "Контракт на разработку сайта",
    "stage_id": "C1:NEW",
    "assigned_by_id": 1,
    "responsible_by_id": 1,
    "opened": "Y",
    "sum": 50000,
    "currency_id": "RUB",
    "probability": 50,
    "created_time": "2024-01-15T10:30:00+00:00",
    "source_id": "SELF",
    "contact_ids": [15, 16]
  }
}
```

#### Контакты (Contacts)
- **onCrmContactAdd** — создан новый контакт
- **onCrmContactUpdate** — обновлен контакт
- **onCrmContactDelete** — удален контакт

#### Компании (Companies)
- **onCrmCompanyAdd** — создана новая компания
- **onCrmCompanyUpdate** — обновлена компания
- **onCrmCompanyDelete** — удалена компания

### Другие события CRM
- **onCrmActivityAdd** — добавлена активность (звонок, встреча, письмо)
- **onCrmActivityDelete** — удалена активность
- **onCrmActivityUpdate** — обновлена активность

## Работа с вебхуками

### Регистрация вебхука

Чтобы получать события, нужно зарегистрировать вебхук. Это делается через REST API:

```php
// Регистрируем вебхук на событие onCrmDealAdd
$response = \Bitrix24\Service\CrmApi::registerWebhook([
    'AUTH_ID' => 'your_auth_id',
    'EVENT_NAME' => 'onCrmDealAdd',
    'HANDLER_URL' => 'https://your-site.com/api/webhook-handler.php?event=onCrmDealAdd'
]);

// Результат
// {
//   "result": true,
//   "time": {
//       "start": 1421104621.29,
//       "finish": 1421104622.02,
//       "duration": 0.73
//   }
// }
```

### Список зарегистрированных вебхуков

```php
$webhooks = \Bitrix24\Service\CrmApi::getWebhooks([
    'AUTH_ID' => 'your_auth_id'
]);
```

### Удаление вебхука

```php
$response = \Bitrix24\Service\CrmApi::unregisterWebhook([
    'AUTH_ID' => 'your_auth_id',
    'EVENT_NAME' => 'onCrmDealAdd',
    'HANDLER_URL' => 'https://your-site.com/api/webhook-handler.php?event=onCrmDealAdd'
]);
```

## Обработка событий на сервере

### Структура обработчика

```php
<?php
// api/webhook-handler.php

// 1. Получаем JSON из запроса
$input = file_get_contents('php://input');
$event = json_decode($input, true);

// 2. Проверяем, что данные получены
if (empty($event)) {
    http_response_code(400);
    exit('No data');
}

// 3. Логируем событие
error_log('Webhook received: ' . $event['event'] . ' for entity #' . $event['data']['id']);

// 4. Обрабатываем событие
switch ($event['event']) {
    case 'onCrmDealAdd':
        handleDealAdd($event['data']);
        break;
    case 'onCrmDealUpdate':
        handleDealUpdate($event['data']);
        break;
    case 'onCrmLeadAdd':
        handleLeadAdd($event['data']);
        break;
    default:
        error_log('Unknown event: ' . $event['event']);
}

// 5. Отправляем успешный ответ
http_response_code(200);
echo 'OK';

function handleDealAdd($data) {
    // Отправляем email
    $to = 'manager@company.com';
    $subject = 'Новая сделка: ' . $data['title'];
    $body = 'Сделка #' . $data['id'] . ' на сумму ' . $data['sum'];
    mail($to, $subject, $body);
}

function handleDealUpdate($data) {
    // Логируем обновление
    error_log('Deal updated: #' . $data['id']);
}

function handleLeadAdd($data) {
    // Автоматически создаем сделку из лида
    // ... код создания сделки
}
```

## Безопасность вебхуков

### Верификация подписи (X-Bitrix24-Signature)

Bitrix24 отправляет подпись запроса для верификации подлинности:

```php
<?php
// Получаем подпись из заголовка
$signature = $_SERVER['HTTP_X_BITRIX24_SIGNATURE'] ?? '';
$input = file_get_contents('php://input');

// Вычисляем ожидаемую подпись
$expected_signature = md5($input . $webhook_secret);

// Сравниваем подписи
if ($signature !== $expected_signature) {
    http_response_code(403);
    exit('Invalid signature');
}

// Обрабатываем событие
$event = json_decode($input, true);
```

### Защита от повторных обработок

```php
<?php
// webhook_id помогает идентифицировать уникальный вебхук
$webhook_id = $_SERVER['HTTP_X_BITRIX24_WEBHOOK_ID'] ?? '';
$event = json_decode(file_get_contents('php://input'), true);

// Сохраняем webhook_id в базе, чтобы не обработать дважды
$db = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'password');
$stmt = $db->prepare('SELECT * FROM webhook_log WHERE webhook_id = ?');
$stmt->execute([$webhook_id]);

if ($stmt->rowCount() > 0) {
    // Этот вебхук уже обработан
    http_response_code(200);
    echo 'Already processed';
    exit;
}

// Обрабатываем
// ...

// Логируем
$stmt = $db->prepare('INSERT INTO webhook_log (webhook_id, event) VALUES (?, ?)');
$stmt->execute([$webhook_id, $event['event']]);
```

## Примеры обработчиков

### Пример 1: Отправка email при создании сделки

```php
<?php
function handleCrmDealAdd($dealData) {
    $dealId = $dealData['id'];
    $dealTitle = $dealData['title'];
    $dealSum = $dealData['sum'] ?? 0;
    $responsible = $dealData['responsible_by_id'] ?? 1;
    
    // Получаем email ответственного
    // ... код получения email
    
    // Отправляем email
    $subject = 'Новая сделка: ' . $dealTitle;
    $message = "Создана новая сделка:\n" .
               "ID: $dealId\n" .
               "Название: $dealTitle\n" .
               "Сумма: $dealSum руб.";
    
    mail($email, $subject, $message);
}
```

### Пример 2: Создание активности при обновлении лида

```php
<?php
function handleCrmLeadUpdate($leadData) {
    $leadId = $leadData['id'];
    $leadName = $leadData['name'] ?? 'Unknown';
    
    // Если лид перемещен в статус "Квалифицирован"
    if ($leadData['status_id'] == 'QUALIFIED') {
        // Создаем задачу на звонок
        $taskData = [
            'fields' => [
                'TITLE' => 'Позвонить ' . $leadName,
                'DESCRIPTION' => 'Лид #' . $leadId . ' (' . $leadName . ') готов к звонку',
                'RESPONSIBLE_ID' => $leadData['responsible_by_id'],
                'GROUP_ID' => 0,
                'PRIORITY' => 2
            ]
        ];
        
        // API вызов для создания задачи
        // ...
    }
}
```

### Пример 3: Синхронизация с внешней системой

```php
<?php
function handleCrmDealStatusChange($dealData) {
    $dealId = $dealData['id'];
    $stageId = $dealData['stage_id'];
    
    // Если сделка выиграна
    if ($stageId === 'C1:WON') {
        // Отправляем данные в систему 1C
        $curl = curl_init('https://1c-system.com/api/deal');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'bitrix_id' => $dealId,
                'title' => $dealData['title'],
                'sum' => $dealData['sum'],
                'status' => 'COMPLETED'
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        curl_exec($curl);
        curl_close($curl);
    }
}
```

## Обработка ошибок

### Повторные попытки (Retry Logic)

Bitrix24 автоматически повторяет вебхук, если обработчик вернул ошибку:
- 1-я попытка: сразу же
- 2-я попытка: через 1 минуту
- 3-я попытка: через 10 минут
- 4-я попытка: через 1 час

```php
<?php
// Правильный обработчик
try {
    $event = json_decode(file_get_contents('php://input'), true);
    processEvent($event);
    http_response_code(200);
    echo 'OK';
} catch (Exception $e) {
    // Возвращаем ошибку, Bitrix24 повторит попытку
    http_response_code(500);
    error_log('Error: ' . $e->getMessage());
    echo 'Error: ' . $e->getMessage();
}
```

## Лучшие практики

### ✅ ДЕЛАЙ

1. **Логируй все события**
   ```php
   error_log(json_encode([
       'timestamp' => date('Y-m-d H:i:s'),
       'event' => $event['event'],
       'entity_id' => $event['data']['id']
   ]));
   ```

2. **Используй try-catch**
   ```php
   try {
       processEvent($event);
   } catch (Exception $e) {
       http_response_code(500);
       error_log('Error: ' . $e->getMessage());
   }
   ```

3. **Проверяй подпись вебхука**
   ```php
   if (!verifyWebhookSignature($_SERVER['HTTP_X_BITRIX24_SIGNATURE'])) {
       http_response_code(403);
       exit;
   }
   ```

4. **Отправляй быстрый ответ (200 OK)**
   ```php
   http_response_code(200);
   echo 'OK';
   flush();
   // Долгие операции после этого
   ```

5. **Логируй во внешнюю систему**
   - Используй отдельный лог-файл для вебхуков
   - Сохраняй данные события в БД для аудита

### ❌ НЕ ДЕЛАЙ

1. ❌ Не выполняй долгие операции в обработчике
   - Используй очереди (queue) для асинхронной обработки

2. ❌ Не игнорируй ошибки
   - Всегда логируй исключения

3. ❌ Не забывай о безопасности
   - Проверяй подпись вебхука
   - Валидируй входящие данные

4. ❌ Не обрабатывай одно событие дважды
   - Сохраняй webhook_id в БД

5. ❌ Не забывай про timeout
   - Обработчик должен выполняться быстро (< 30 секунд)

## Вопросы для собеседования

### 1. Что такое события в Bitrix24 и как они отличаются от API polling?

**Ответ:**
События (Webhooks) — это асинхронные уведомления о изменении данных, которые Bitrix24 отправляет на твой сервер. 

**Различия:**
- **Polling** — постоянный опрос: "Есть ли новые данные?" (неэффективно)
- **Webhooks** — пассивное ожидание: Bitrix24 сам уведомит, когда что-то изменилось (эффективно)

**Пример polling (плохо):**
```php
// Каждую минуту проверяем новые сделки
while (true) {
    $deals = $crm->getDealsSince($lastCheck);
    sleep(60);
}
```

**Пример webhook (хорошо):**
```php
// Bitrix24 отправит вебхук, когда создана новая сделка
function handleCrmDealAdd($data) {
    // Сразу же обрабатываем
}
```

### 2. Какие основные события CRM ты знаешь?

**Ответ:**
Основные события разделены по сущностям:

**Лиды:**
- `onCrmLeadAdd` — новый лид
- `onCrmLeadUpdate` — обновление лида
- `onCrmLeadDelete` — удаление лида
- `onCrmLeadStatusChanged` — смена статуса

**Сделки:**
- `onCrmDealAdd` — новая сделка
- `onCrmDealUpdate` — обновление сделки
- `onCrmDealDelete` — удаление сделки
- `onCrmDealStageChange` — перемещение по стадиям
- `onCrmDealStatusChange` — смена статуса (выигрыш/проигрыш)

**Контакты, Компании, Активности:**
- Аналогичные события (Add, Update, Delete)

### 3. Как защитить вебхук от несанкционированного доступа?

**Ответ:**
1. **Проверка подписи (X-Bitrix24-Signature)**
   ```php
   $signature = $_SERVER['HTTP_X_BITRIX24_SIGNATURE'];
   $expected = md5(file_get_contents('php://input') . $secret);
   if ($signature !== $expected) {
       http_response_code(403);
       exit;
   }
   ```

2. **Белый список IP**
   ```php
   $allowed_ips = ['195.154.231.0/24']; // Bitrix24 IP
   if (!isIpAllowed($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
       http_response_code(403);
       exit;
   }
   ```

3. **Token в URL**
   ```php
   if ($_GET['token'] !== 'your-secret-token') {
       http_response_code(403);
       exit;
   }
   ```

### 4. Как избежать повторной обработки одного события?

**Ответ:**
Используй уникальный `webhook_id` для отслеживания:
```php
$webhook_id = $_SERVER['HTTP_X_BITRIX24_WEBHOOK_ID'];

// Проверяем, обработан ли уже
if ($db->query("SELECT * FROM webhook_log WHERE webhook_id = ?", [$webhook_id])->rowCount() > 0) {
    http_response_code(200); // Pretend success
    echo 'Already processed';
    exit;
}

// Обрабатываем
processEvent($event);

// Логируем
$db->query("INSERT INTO webhook_log (webhook_id, event) VALUES (?, ?)", 
    [$webhook_id, $event['event']]);
```

### 5. Какой самый быстрый способ ответить на вебхук?

**Ответ:**
Отправить `200 OK` как можно быстрее:
```php
http_response_code(200);
echo 'OK';
flush();
ob_flush();

// Долгие операции ПОСЛЕ отправки ответа
backgroundProcessing($event);
```

Это позволит:
- Bitrix24 считать, что обработка успешна
- Фоновому коду выполняться отдельно

### 6. Как интегрировать вебхук с внешней системой (1C, CRM, и т.д.)?

**Ответ:**
1. Получить данные из события
2. Преобразовать в формат внешней системы
3. Отправить HTTP запрос (в фоновом режиме)

```php
function syncWithExternalSystem($deal) {
    // Bitrix → External format
    $externalData = [
        'id' => $deal['id'],
        'sum' => $deal['sum'],
        'date' => date('Y-m-d')
    ];
    
    // Отправляем (асинхронно)
    $curl = curl_init('https://external-system.com/api/deals');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($externalData),
        CURLOPT_TIMEOUT => 5, // Быстро!
    ]);
    curl_exec($curl);
}
```

### 7. Почему вебхук должен отвечать быстро?

**Ответ:**
- **Timeout** — если ответить не можешь за 30 секунд, Bitrix24 считает ошибку
- **Повторные попытки** — ошибки = повторные отправки события
- **Нагрузка** — долгие операции могут заблокировать другие вебхуки

**Решение:**
```php
// Быстро ответить
http_response_code(200);
echo 'OK';
flush();

// Асинхронно обработать
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Долгие операции
slowDataProcessing();
```

### 8. Как обработать события в очередь (queue)?

**Ответ:**
Вместо обработки в вебхуке, сохраняем в очередь:

```php
// Быстро: добавить в очередь
$event = json_decode(file_get_contents('php://input'), true);
$db->query("INSERT INTO event_queue (event_data, created_at) VALUES (?, ?)",
    [json_encode($event), date('Y-m-d H:i:s')]);

http_response_code(200);
echo 'Queued';

// Отдельно: обработать из очереди
php artisan queue:work  // Laravel
// или custom cron script
```

### 9. Как регистрировать и управлять вебхуками программно?

**Ответ:**
```php
// Регистрация
$auth = new Bitrix24Auth($token);
$result = $auth->call('batch', [
    'halt' => 0,
    'batch' => [
        'webhooks.register' => ['EVENT_NAME' => 'onCrmDealAdd', 'HANDLER_URL' => 'https://...']
    ]
]);

// Получить список
$list = $auth->call('batch', [
    'batch' => [
        'webhooks.get' => []
    ]
]);

// Удалить
$auth->call('batch', [
    'batch' => [
        'webhooks.unregister' => ['EVENT_NAME' => 'onCrmDealAdd', 'HANDLER_URL' => 'https://...']
    ]
]);
```

### 10. Как отладить вебхук локально?

**Ответ:**
Используй ngrok для создания публичного URL:

```bash
# Установить ngrok
brew install ngrok

# Запустить локальный сервер
php -S localhost:8000

# Создать публичный URL
ngrok http 8000
# Output: https://abc123.ngrok.io

# Использовать URL в Bitrix24:
# https://abc123.ngrok.io/webhook-handler.php?event=onCrmDealAdd

# Смотреть логи в реальном времени
tail -f /var/log/webhook.log
```

**Логирование в файл:**
```php
error_log("Event: {$event['event']}, Data: " . json_encode($event['data']), 
    3, '/var/log/webhook.log');
```

## Практические задачи

Решения находятся в `/examples/module-8-4/`:

1. **Задача 1: Обработчик событий лидов**
   - Регистрация вебхука на `onCrmLeadAdd` и `onCrmLeadUpdate`
   - Логирование и уведомления
   - Обработка ошибок и повторных попыток

2. **Задача 2: Автоматизация сделок**
   - Создание сделки при создании лида
   - Перемещение по стадиям при обновлении
   - Уведомления ответственному

3. **Задача 3: Синхронизация с внешней системой**
   - Получение события о создании сделки
   - Отправка данных в 1C CRM
   - Обработка ошибок интеграции

## Дополнительные ресурсы

- [Официальная документация: Events](https://dev.1c-bitrix.ru/rest_help/crm/events/index.php)
- [Bitrix24 API: Webhooks](https://dev.1c-bitrix.ru/rest_help/index.php)
- [Примеры обработчиков](https://github.com/bitrix-samples)
