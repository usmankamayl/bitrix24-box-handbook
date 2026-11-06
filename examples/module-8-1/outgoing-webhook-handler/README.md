# Задача 3: Обработчик исходящего Webhook (Outgoing Webhook)

## 📋 Описание

PHP-скрипт, который принимает **POST-запросы от Bitrix24** при наступлении событий (создание лида, обновление сделки, и т.д.).

**Исходящий webhook** — это способ получать уведомления от Bitrix24 в режиме реального времени.

## 🎯 Что демонстрирует

- ✅ Прием POST-запросов от Bitrix24
- ✅ Парсинг данных события
- ✅ Валидация структуры webhook
- ✅ Маршрутизация событий по типам
- ✅ Логирование событий в файл
- ✅ Сохранение данных в JSON
- ✅ Отправка уведомлений
- ✅ Обработка разных типов событий (лиды, сделки)

## 📦 Требования

- PHP 7.4+
- Сервер с **HTTPS** (обязательно для webhook)
- Доступ к Bitrix24 для настройки исходящего webhook

## 🔧 Настройка

### Шаг 1: Разверни скрипт на сервере

Загрузи файл `webhook-handler.php` на сервер с HTTPS:

```
https://your-domain.com/webhook-handler.php
```

**ВАЖНО:** Должен быть доступен по HTTPS (не HTTP).

### Шаг 2: Создай исходящий webhook в Bitrix24

1. Зайди в Bitrix24
2. Перейди: **Приложения** → **Вебхуки** → **Добавить исходящий вебхук**
3. Выбери событие:
   - **ONCRMLEADADD** — создание лида
   - **ONCRMLEADUPDATE** — обновление лида
   - **ONCRMLEADDELETE** — удаление лида
   - **ONCRMDEALDD** — создание сделки
   - И другие...
4. Укажи **URL обработчика**:
   ```
   https://your-domain.com/webhook-handler.php
   ```
5. Сохрани

### Шаг 3: Проверь права на запись

Скрипт создает файлы для логирования:

```bash
chmod 755 /path/to/outgoing-webhook-handler/
```

**Создаваемые файлы:**
- `webhook_events.log` — лог всех событий
- `events/*.json` — JSON с данными каждого события

## 🚀 Как это работает

### 1. Bitrix24 отправляет POST-запрос

Когда в Bitrix24 происходит событие (например, создается лид), Bitrix24 отправляет POST на твой URL:

```http
POST https://your-domain.com/webhook-handler.php
Content-Type: application/x-www-form-urlencoded

event=ONCRMLEADADD&data[FIELDS][ID]=123&data[FIELDS][TITLE]=Новый+лид&...
```

### 2. Скрипт принимает и парсит данные

```php
$webhookData = getWebhookData();
```

**Структура данных:**
```json
{
  "event": "ONCRMLEADADD",
  "data": {
    "FIELDS": {
      "ID": "123",
      "TITLE": "Заявка с сайта",
      "NAME": "Иван Петров",
      "PHONE": [{"VALUE": "+7 (900) 123-45-67"}],
      "EMAIL": [{"VALUE": "ivan@example.com"}],
      "STATUS_ID": "NEW"
    }
  },
  "ts": "1609459200",
  "auth": {
    "access_token": "...",
    "domain": "my-company.bitrix24.ru",
    "member_id": "abc123"
  }
}
```

### 3. Валидация данных

```php
if (!validateBitrix24Webhook($webhookData)) {
    http_response_code(400);
    die('Invalid webhook');
}
```

### 4. Маршрутизация по типу события

```php
switch ($eventType) {
    case 'ONCRMLEADADD':
        handleLeadAdd($data);
        break;
    
    case 'ONCRMLEADUPDATE':
        handleLeadUpdate($data);
        break;
    
    // ...
}
```

### 5. Обработка события

```php
function handleLeadAdd(array $data): void
{
    $leadId = $data['FIELDS']['ID'];
    $leadTitle = $data['FIELDS']['TITLE'];
    
    // Логируем
    writeLog("Создан лид #$leadId: $leadTitle");
    
    // Отправляем уведомление
    sendNotification('Новый лид', "Лид: $leadTitle");
    
    // Ваша логика (отправить в CRM, создать задачу, и т.д.)
}
```

### 6. Возврат ответа Bitrix24

```php
http_response_code(200);
echo json_encode(['success' => true]);
```

## 💡 Примеры использования

### Пример 1: Тест webhook (создание лида)

1. Создай лид в Bitrix24 вручную
2. Bitrix24 отправит POST на твой URL
3. Проверь логи:

```bash
cat webhook_events.log
```

**Вывод:**
```
[2024-01-15 10:30:15] [INFO] ===== Получен новый webhook от Bitrix24 =====
[2024-01-15 10:30:15] [INFO] Событие: ONCRMLEADADD
[2024-01-15 10:30:15] [INFO] Портал: my-company.bitrix24.ru
[2024-01-15 10:30:15] [INFO] Данные сохранены в файл: events/2024-01-15_10-30-15_ONCRMLEADADD_abc123.json
[2024-01-15 10:30:15] [EVENT] Создан новый лид #123: Заявка с сайта
[2024-01-15 10:30:15] [INFO] Контакты: Имя=Иван, Телефон=+7 (900) 123-45-67, Email=ivan@example.com
[2024-01-15 10:30:15] [NOTIFICATION] Уведомление: Новый лид #123 - Создан лид: Заявка с сайта...
[2024-01-15 10:30:15] [SUCCESS] Событие успешно обработано
[2024-01-15 10:30:15] [INFO] ===== Обработка завершена =====
```

### Пример 2: Просмотр сохраненных данных

```bash
cat events/2024-01-15_10-30-15_ONCRMLEADADD_abc123.json
```

**Вывод:**
```json
{
  "event": "ONCRMLEADADD",
  "data": {
    "FIELDS": {
      "ID": "123",
      "TITLE": "Заявка с сайта",
      "NAME": "Иван",
      "PHONE": [{"VALUE": "+7 (900) 123-45-67", "VALUE_TYPE": "WORK"}],
      "EMAIL": [{"VALUE": "ivan@example.com", "VALUE_TYPE": "WORK"}],
      "STATUS_ID": "NEW",
      "SOURCE_ID": "WEB"
    }
  },
  "auth": {
    "domain": "my-company.bitrix24.ru",
    "member_id": "abc123"
  }
}
```

### Пример 3: Добавление своей логики

Отредактируй функцию `handleLeadAdd()`:

```php
function handleLeadAdd(array $data): void
{
    $leadId = $data['FIELDS']['ID'];
    $leadTitle = $data['FIELDS']['TITLE'];
    $leadPhone = $data['FIELDS']['PHONE'][0]['VALUE'] ?? 'N/A';
    
    // 1. Отправить в вашу CRM
    sendToExternalCRM([
        'id' => $leadId,
        'title' => $leadTitle,
        'phone' => $leadPhone,
    ]);
    
    // 2. Создать задачу менеджеру
    createTaskForManager($leadId, $leadTitle);
    
    // 3. Отправить SMS клиенту
    sendSMS($leadPhone, 'Спасибо за заявку! Мы свяжемся с вами.');
    
    // 4. Записать в базу данных
    $pdo = new PDO('mysql:host=localhost;dbname=crm', 'user', 'pass');
    $stmt = $pdo->prepare('INSERT INTO leads (bitrix_id, title, phone) VALUES (?, ?, ?)');
    $stmt->execute([$leadId, $leadTitle, $leadPhone]);
    
    // 5. Отправить в Telegram
    sendTelegramNotification("Новый лид #$leadId: $leadTitle");
    
    writeLog("Лид #$leadId успешно обработан");
}
```

### Пример 4: Отправка в Telegram

```php
function sendTelegramNotification(string $message): void
{
    $botToken = 'YOUR_BOT_TOKEN';
    $chatId = 'YOUR_CHAT_ID';
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
    ];
    
    file_get_contents($url . '?' . http_build_query($data));
}
```

### Пример 5: Автоматическая задача при создании сделки

```php
function handleDealAdd(array $data): void
{
    $dealId = $data['FIELDS']['ID'];
    $dealTitle = $data['FIELDS']['TITLE'];
    $dealAmount = $data['FIELDS']['OPPORTUNITY'] ?? 0;
    
    // Если сделка крупная (> 100 000), создаем задачу директору
    if ($dealAmount > 100000) {
        $webhookUrl = 'https://your-portal.bitrix24.ru/rest/1/abc123/';
        
        // Создаем задачу
        file_get_contents($webhookUrl . 'tasks.task.add.json?' . http_build_query([
            'fields' => [
                'TITLE' => "Крупная сделка: $dealTitle",
                'DESCRIPTION' => "Сумма: $dealAmount руб.\nID сделки: $dealId",
                'RESPONSIBLE_ID' => 1, // ID директора
                'PRIORITY' => 2, // Высокий приоритет
            ],
        ]));
        
        writeLog("Создана задача для директора по сделке #$dealId");
    }
}
```

## 📋 Доступные события

| Событие | Описание |
|---------|----------|
| **ONCRMLEADADD** | Создание лида |
| **ONCRMLEADUPDATE** | Обновление лида |
| **ONCRMLEADDELETE** | Удаление лида |
| **ONCRMDEALDD** | Создание сделки |
| **ONCRMDEALUPDATE** | Обновление сделки |
| **ONCRMDEALDELETE** | Удаление сделки |
| **ONCRMCONTACTADD** | Создание контакта |
| **ONCRMCONTACTUPDATE** | Обновление контакта |
| **ONCRMCOMPANYADD** | Создание компании |
| **ONTASKADD** | Создание задачи |
| **ONTASKUPDATE** | Обновление задачи |

**Полный список:** https://dev.1c-bitrix.ru/rest_help/general/event_bind.php

## ⚠️ Важные моменты

### 1. Обязателен HTTPS

Bitrix24 отправляет webhook **только на HTTPS**:

```
❌ Неправильно:
http://your-domain.com/webhook-handler.php

✅ Правильно:
https://your-domain.com/webhook-handler.php
```

**Решение:** Установи SSL-сертификат (Let's Encrypt бесплатно).

### 2. Быстрая обработка

Bitrix24 ждет ответ максимум **30 секунд**.

Если твоя обработка занимает дольше, Bitrix24 сочтет запрос неудачным.

**Решение:** Используй очереди (Redis, RabbitMQ) для долгих задач:

```php
function handleLeadAdd(array $data): void
{
    // Быстро добавляем в очередь
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->lpush('leads_queue', json_encode($data));
    
    writeLog("Лид добавлен в очередь");
    
    // Воркер обработает в фоне
}
```

### 3. Повторные отправки

Если твой скрипт вернет ошибку (код != 200), Bitrix24 **повторит запрос**:
- Через 1 минуту
- Через 5 минут
- Через 30 минут

**Решение:** Используй идемпотентность (проверяй, не обработан ли уже этот event):

```php
$eventId = $webhookData['event'] . '_' . $webhookData['ts'];

if (file_exists(EVENTS_DIR . $eventId . '.json')) {
    writeLog("Событие $eventId уже обработано, пропускаем");
    http_response_code(200);
    exit;
}
```

### 4. Безопасность

Проверяй, что запрос действительно от Bitrix24:

```php
function validateBitrix24Source(array $data): bool
{
    // Проверяем домен портала
    $allowedDomains = ['my-company.bitrix24.ru'];
    $domain = $data['auth']['domain'] ?? '';
    
    if (!in_array($domain, $allowedDomains)) {
        writeLog("Запрос от неизвестного домена: $domain", 'WARNING');
        return false;
    }
    
    return true;
}
```

## 🔍 Отладка

### Проверка логов

```bash
tail -f webhook_events.log
```

### Проверка последнего события

```bash
ls -lt events/ | head -n 5
```

### Тестирование webhook вручную

```bash
curl -X POST https://your-domain.com/webhook-handler.php \
  -d 'event=ONCRMLEADADD' \
  -d 'data[FIELDS][ID]=999' \
  -d 'data[FIELDS][TITLE]=Тестовый лид' \
  -d 'auth[domain]=test.bitrix24.ru'
```

## 🚀 Расширение

### Как добавить обработку нового события?

1. Добавь в `routeEvent()`:

```php
case 'ONTASKADD':
    handleTaskAdd($data);
    break;
```

2. Создай функцию:

```php
function handleTaskAdd(array $data): void
{
    $taskId = $data['FIELDS']['ID'];
    $taskTitle = $data['FIELDS']['TITLE'];
    
    writeLog("Создана задача #$taskId: $taskTitle");
    
    // Твоя логика
}
```

3. Настрой webhook в Bitrix24 на событие **ONTASKADD**

## 📚 Полезные ссылки

- [Исходящие вебхуки](https://dev.1c-bitrix.ru/rest_help/general/webhooks.php)
- [События для подписки](https://dev.1c-bitrix.ru/rest_help/general/event_bind.php)
- [Структура данных событий](https://dev.1c-bitrix.ru/rest_help/general/events_handler.php)

## 🎓 Что ты узнал

После выполнения этой задачи ты понимаешь:

1. ✅ Как работают исходящие webhook в Bitrix24
2. ✅ Как настроить webhook на события
3. ✅ Как принимать и парсить POST-запросы от Bitrix24
4. ✅ Как валидировать структуру webhook
5. ✅ Как маршрутизировать события по типам
6. ✅ Как логировать и сохранять данные событий
7. ✅ Как интегрировать Bitrix24 с внешними системами
8. ✅ Отличие исходящего webhook от входящего
