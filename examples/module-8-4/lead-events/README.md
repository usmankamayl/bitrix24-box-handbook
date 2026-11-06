# Задача 1: Обработчик событий лидов

## 📋 Описание

Система обработки вебхуков для отслеживания событий лидов в Bitrix24 CRM. Получает уведомления о создании, обновлении и удалении лидов, логирует их и отправляет уведомления ответственным менеджерам.

## 🎯 Что реализовано

### Основной функционал
- ✅ Обработка событий `onCrmLeadAdd`, `onCrmLeadUpdate`, `onCrmLeadDelete`
- ✅ Защита от несанкционированного доступа (токен + подпись)
- ✅ Предотвращение повторной обработки (webhook_id)
- ✅ Быстрый ответ Bitrix24 (200 OK) с фоновой обработкой
- ✅ Логирование в файлы и базу данных
- ✅ Отправка уведомлений (Email, Bitrix24, Telegram)
- ✅ Проверка на дубли лидов

### Безопасность
- 🔒 Проверка секретного токена в URL
- 🔒 Проверка подписи X-Bitrix24-Signature
- 🔒 Защита от повторной обработки одного события
- 🔒 Логирование подозрительной активности

### Уведомления
- 📧 Email уведомления
- 💬 Уведомления в Bitrix24 (im.notify)
- 📱 Telegram уведомления (опционально)

## 📁 Структура файлов

```
lead-events/
├── webhook-handler.php          # Основной обработчик вебхуков
├── LeadEventLogger.php          # Класс для логирования событий
├── NotificationService.php      # Сервис отправки уведомлений
├── config.example.php           # Пример конфигурации
├── schema.sql                   # SQL схема базы данных
├── register-webhooks.php        # Скрипт регистрации вебхуков
├── list-webhooks.php           # Просмотр зарегистрированных вебхуков
├── unregister-webhooks.php     # Удаление вебхуков
├── logs/                       # Папка для логов (создается автоматически)
│   ├── webhook.log            # Общий лог
│   ├── info.log               # Информационные сообщения
│   ├── warning.log            # Предупреждения
│   └── error.log              # Ошибки
└── README.md                   # Эта инструкция
```

## 🚀 Установка

### Шаг 1: Подготовка конфигурации

```bash
# Скопируйте пример конфигурации
cp config.example.php config.php

# Отредактируйте config.php и укажите свои данные
nano config.php
```

**Обязательные параметры в config.php:**
```php
// Секретный токен для защиты
define('WEBHOOK_SECRET_TOKEN', 'ваш-секретный-токен-12345');

// База данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'bitrix_webhooks');
define('DB_USER', 'root');
define('DB_PASSWORD', 'пароль');

// Bitrix24
define('BITRIX24_WEBHOOK_URL', 'https://ваш-портал.bitrix24.ru/rest/1/ключ/');
define('BITRIX24_DOMAIN', 'https://ваш-портал.bitrix24.ru');
```

### Шаг 2: Создание базы данных

```bash
# Создайте базу данных
mysql -u root -p -e "CREATE DATABASE bitrix_webhooks CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Импортируйте схему
mysql -u root -p bitrix_webhooks < schema.sql
```

### Шаг 3: Настройка веб-сервера

**Nginx:**
```nginx
server {
    listen 80;
    server_name ваш-сайт.com;
    root /var/www/webhook;
    
    location /webhook-handler.php {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

**Apache (.htaccess):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^webhook-handler\.php$ - [L]
</IfModule>
```

### Шаг 4: Регистрация вебхуков в Bitrix24

```bash
# Отредактируйте register-webhooks.php (укажите URL обработчика)
nano register-webhooks.php

# Запустите регистрацию
php register-webhooks.php
```

**Вывод:**
```
Регистрация вебхуков в Bitrix24...
Handler URL: https://ваш-сайт.com/webhook-handler.php?token=ВАШ_ТОКЕН

Регистрация события: ONCRMLEADADD... ✅ Успешно
Регистрация события: ONCRMLEADUPDATE... ✅ Успешно
Регистрация события: ONCRMLEADDELETE... ✅ Успешно

✅ Регистрация завершена!
```

### Шаг 5: Проверка

```bash
# Просмотр зарегистрированных вебхуков
php list-webhooks.php

# Проверка логов
tail -f logs/webhook.log
```

## 💡 Использование

### Автоматическая работа

После установки система работает автоматически:

1. **Создается лид в Bitrix24** → вебхук отправляется на ваш сервер
2. **Обработчик получает событие** → проверяет безопасность
3. **Логирование** → сохраняет в БД и файлы
4. **Уведомление** → отправляет ответственному менеджеру

### Пример события при создании лида

```json
{
  "event": "ONCRMLEADADD",
  "data": {
    "FIELDS": {
      "ID": "42",
      "TITLE": "Заявка с сайта",
      "NAME": "Иван",
      "LAST_NAME": "Петров",
      "STATUS_ID": "NEW",
      "PHONE": [{"VALUE": "+79991234567", "VALUE_TYPE": "WORK"}],
      "EMAIL": [{"VALUE": "ivan@example.com", "VALUE_TYPE": "WORK"}],
      "SOURCE_ID": "WEB",
      "ASSIGNED_BY_ID": "1"
    }
  }
}
```

### Что происходит при обработке

1. **Логирование события**
   ```
   [2024-01-15 10:30:00] INFO: Event received: ONCRMLEADADD {"webhook_id":"abc123","entity_id":42}
   [2024-01-15 10:30:00] INFO: New lead created: #42 - Заявка с сайта
   ```

2. **Уведомление менеджеру**
   - Email: "Новый лид #42"
   - Bitrix24: Всплывающее уведомление
   - Telegram: Сообщение в чат (если настроен)

3. **Запись в БД**
   ```sql
   INSERT INTO webhook_log (webhook_id, event_name, event_data, status)
   VALUES ('abc123', 'ONCRMLEADADD', '{"FIELDS":{...}}', 'processed');
   ```

### Ручная проверка

```bash
# Отправить тестовый вебхук
curl -X POST "https://ваш-сайт.com/webhook-handler.php?token=ВАШ_ТОКЕН" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "ONCRMLEADADD",
    "data": {
      "FIELDS": {
        "ID": "999",
        "TITLE": "Тестовый лид",
        "NAME": "Тест",
        "ASSIGNED_BY_ID": "1"
      }
    }
  }'

# Проверить лог
tail -f logs/webhook.log
```

## 📊 Мониторинг

### Просмотр логов

```bash
# Общий лог
tail -f logs/webhook.log

# Только ошибки
tail -f logs/error.log

# Только информация
tail -f logs/info.log
```

### Статистика из базы данных

```sql
-- События за последние 7 дней
SELECT 
    event_name,
    COUNT(*) as count,
    DATE(created_at) as date
FROM webhook_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY event_name, DATE(created_at)
ORDER BY date DESC, count DESC;

-- Последние 10 событий
SELECT 
    id,
    event_name,
    JSON_EXTRACT(event_data, '$.FIELDS.ID') as lead_id,
    JSON_EXTRACT(event_data, '$.FIELDS.TITLE') as title,
    created_at,
    status
FROM webhook_log
ORDER BY created_at DESC
LIMIT 10;

-- События с ошибками
SELECT 
    id,
    event_name,
    error_message,
    retry_count,
    created_at
FROM webhook_log
WHERE status = 'failed'
ORDER BY created_at DESC;
```

## 🔧 Настройка уведомлений

### Email уведомления

Автоматически отправляются на email пользователя из Bitrix24.

### Bitrix24 уведомления

Используют метод `im.notify` для отправки всплывающих уведомлений.

### Telegram уведомления

**Настройка:**

1. Создайте бота через [@BotFather](https://t.me/BotFather)
2. Получите токен бота
3. Узнайте chat_id (отправьте `/start` боту, затем проверьте: `https://api.telegram.org/bot<TOKEN>/getUpdates`)
4. Укажите в `config.php`:

```php
define('TELEGRAM_BOT_TOKEN', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
define('TELEGRAM_CHAT_IDS', [123456789]);
```

## 🛠️ Управление вебхуками

### Просмотр зарегистрированных

```bash
php list-webhooks.php
```

**Вывод:**
```
Найдено вебхуков: 3
================================================================================

1. Событие: ONCRMLEADADD
   Handler: https://ваш-сайт.com/webhook-handler.php?token=***
   User ID: 1

2. Событие: ONCRMLEADUPDATE
   Handler: https://ваш-сайт.com/webhook-handler.php?token=***
   User ID: 1

3. Событие: ONCRMLEADDELETE
   Handler: https://ваш-сайт.com/webhook-handler.php?token=***
   User ID: 1
```

### Удаление вебхуков

```bash
# Удалить один вебхук
php unregister-webhooks.php ONCRMLEADADD

# Удалить все вебхуки
php unregister-webhooks.php all
```

## 🐛 Отладка

### Проблема: Вебхуки не приходят

**Проверьте:**

1. **URL доступен извне**
   ```bash
   curl -I https://ваш-сайт.com/webhook-handler.php
   # Должен вернуть 403 (без токена)
   ```

2. **Вебхуки зарегистрированы**
   ```bash
   php list-webhooks.php
   ```

3. **Логи веб-сервера**
   ```bash
   tail -f /var/log/nginx/error.log
   ```

### Проблема: Ошибки в логах

**Проверьте:**

1. **Права на папку logs/**
   ```bash
   chmod 755 logs/
   chmod 644 logs/*.log
   ```

2. **Подключение к БД**
   ```bash
   mysql -h localhost -u root -p bitrix_webhooks -e "SHOW TABLES;"
   ```

3. **PHP ошибки**
   ```bash
   php -l webhook-handler.php
   ```

### Включение отладки

В `config.php`:
```php
define('DEBUG_MODE', true);
```

Это добавит подробные логи в `logs/webhook.log`.

## 📚 Примеры использования

### Пример 1: Автоматическое создание задачи

Добавьте в `webhook-handler.php`:

```php
function handleLeadAdd($data, LeadEventLogger $logger)
{
    // ... существующий код ...
    
    // Создаем задачу для нового лида
    if ($responsibleId) {
        createTask([
            'TITLE' => 'Обработать лид: ' . $leadTitle,
            'RESPONSIBLE_ID' => $responsibleId,
            'DESCRIPTION' => 'Новый лид #' . $leadId
        ]);
    }
}

function createTask($fields)
{
    $url = BITRIX24_WEBHOOK_URL . 'tasks.task.add.json';
    
    $data = ['fields' => $fields];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}
```

### Пример 2: Интеграция с внешней CRM

```php
function handleLeadAdd($data, LeadEventLogger $logger)
{
    // ... существующий код ...
    
    // Отправляем в внешнюю систему
    syncToExternalCRM($data);
}

function syncToExternalCRM($leadData)
{
    $curl = curl_init('https://external-crm.com/api/leads');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'name' => $leadData['FIELDS']['NAME'],
            'phone' => $leadData['FIELDS']['PHONE'][0]['VALUE'] ?? null,
            'source' => 'bitrix24'
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer YOUR_API_KEY'
        ]
    ]);
    
    curl_exec($curl);
    curl_close($curl);
}
```

## ⚡ Производительность

### Оптимизация для больших нагрузок

1. **Используйте очередь (queue)**
   - Redis Queue
   - RabbitMQ
   - Database Queue

2. **Асинхронная обработка**
   ```php
   // Быстро ответить
   http_response_code(200);
   echo 'OK';
   flush();
   
   // Обработать в фоне
   if (function_exists('fastcgi_finish_request')) {
       fastcgi_finish_request();
   }
   ```

3. **Индексы в БД**
   ```sql
   CREATE INDEX idx_entity_id ON webhook_log(entity_id);
   CREATE INDEX idx_created_at ON webhook_log(created_at);
   ```

## 🔐 Безопасность

### Рекомендации

1. **Используйте HTTPS** для webhook URL
2. **Сложный секретный токен** (минимум 32 символа)
3. **Проверяйте подпись** X-Bitrix24-Signature
4. **Ограничьте доступ** по IP (белый список Bitrix24)
5. **Логируйте подозрительную активность**
6. **Регулярно обновляйте токены**

### Белый список IP Bitrix24

```php
$allowed_ips = [
    '195.154.231.0/24',
    '195.154.230.0/24'
];

if (!isIpInRange($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    exit;
}
```

## 📝 Лицензия

Этот код создан для обучения и может свободно использоваться в ваших проектах.

## 🤝 Поддержка

При возникновении проблем:
1. Проверьте логи: `logs/error.log`
2. Включите режим отладки: `DEBUG_MODE = true`
3. Проверьте документацию Bitrix24: https://dev.1c-bitrix.ru/rest_help/

---

**Создано для курса подготовки к собеседованию Bitrix-разработчика**
