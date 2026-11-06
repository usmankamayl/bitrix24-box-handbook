# Обработчик Webhook событий от Bitrix24

## Описание

Система обработки событий (webhooks) от Bitrix24. Позволяет получать уведомления о событиях в реальном времени и реагировать на них автоматически.

## Файлы

- `WebhookHandler.php` — базовый класс для обработки webhooks и регистрации событий
- `handler.php` — обработчик событий (размещается на доступном из интернета сервере)
- `register.php` — скрипт для регистрации событий в Bitrix24
- `unregister.php` — скрипт для отмены регистрации событий
- `test_local.php` — локальное тестирование обработчика

## Как работают webhooks в Bitrix24?

**Webhook (исходящий)** — это механизм, при котором Bitrix24 отправляет HTTP-запрос на ваш сервер при возникновении определенного события (создание сделки, задачи и т.д.).

### Принцип работы:
1. Вы регистрируете обработчик в Bitrix24 (URL вашего сервера)
2. При возникновении события Bitrix24 отправляет POST-запрос на ваш URL
3. Ваш скрипт обрабатывает событие и возвращает ответ

## Установка

### 1. Разместите обработчик на сервере

Загрузите файлы на веб-сервер, доступный из интернета:

```
https://ваш-домен.ru/
├── WebhookHandler.php
├── handler.php
└── webhook.log (создастся автоматически)
```

**Требования:**
- PHP 7.0+
- Доступ из интернета (Bitrix24 должен достучаться до вашего сервера)
- Права на запись для создания log-файлов

### 2. Создайте входящий webhook в Bitrix24

Для регистрации событий нужен webhook:

1. **Настройки → Разработчикам → Входящий вебхук**
2. Права: **Основные (user)**, **CRM**, **Задачи**
3. Скопируйте URL

### 3. Настройте конфигурацию

В `register.php`:

```php
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/код/';
$HANDLER_URL = 'https://ваш-домен.ru/handler.php';
```

### 4. Зарегистрируйте события

Запустите скрипт регистрации:

```bash
php register.php
```

Это зарегистрирует обработчик для всех событий в скрипте.

## Основные события Bitrix24

### CRM - Сделки
- `ONCRMDEALADD` — создание сделки
- `ONCRMDEALUPDATE` — обновление сделки
- `ONCRMDEALDELETE` — удаление сделки

### CRM - Лиды
- `ONCRMLЕADADD` — создание лида
- `ONCRMLEADUPDATE` — обновление лида
- `ONCRMLEADDELETE` — удаление лида

### CRM - Контакты
- `ONCRMCONTACTADD` — создание контакта
- `ONCRMCONTACTUPDATE` — обновление контакта
- `ONCRMCONTACTDELETE` — удаление контакта

### CRM - Компании
- `ONCRMCOMPANYADD` — создание компании
- `ONCRMCOMPANYUPDATE` — обновление компании
- `ONCRMCOMPANYDELETE` — удаление компании

### Задачи
- `ONTASKADD` — создание задачи
- `ONTASKUPDATE` — обновление задачи
- `ONTASKDELETE` — удаление задачи
- `ONTASKCOMMENTADD` — добавление комментария

### Пользователи
- `ONUSERADD` — добавление пользователя
- `ONUSERUPDATE` — обновление пользователя
- `ONUSERDELETE` — удаление пользователя

## Примеры использования

### Обработка создания сделки

```php
$handler->on('ONCRMDEALADD', function($data) {
    $dealId = $data['FIELDS']['ID'];
    $title = $data['FIELDS']['TITLE'];
    $amount = $data['FIELDS']['OPPORTUNITY'];
    
    // Уведомление о крупной сделке
    if ($amount > 100000) {
        mail(
            'manager@example.com',
            'Крупная сделка!',
            "Создана сделка \"{$title}\" на {$amount} руб."
        );
    }
    
    // Автоматическое создание задачи
    if ($amount > 500000) {
        // Создать задачу для руководителя
    }
    
    return ['processed' => true];
});
```

### Обработка изменения стадии сделки

```php
$handler->on('ONCRMDEALUPDATE', function($data) {
    $dealId = $data['FIELDS']['ID'];
    $stageId = $data['FIELDS']['STAGE_ID'];
    
    if ($stageId === 'WON') {
        // Сделка успешно закрыта
        // Начислить бонус менеджеру
        // Отправить благодарственное письмо клиенту
    }
    
    if ($stageId === 'LOSE') {
        // Сделка провалена
        // Создать задачу на анализ причин
    }
    
    return ['stage_processed' => $stageId];
});
```

### Автоматическая обработка лидов

```php
$handler->on('ONCRMLЕADADD', function($data) {
    $leadId = $data['FIELDS']['ID'];
    $phone = $data['FIELDS']['PHONE'][0]['VALUE'] ?? null;
    $email = $data['FIELDS']['EMAIL'][0]['VALUE'] ?? null;
    
    // Автоматическая отправка приветственного письма
    if ($email) {
        mail($email, 'Спасибо за обращение', '...');
    }
    
    // Отправка SMS
    if ($phone) {
        // sendSMS($phone, 'Мы получили вашу заявку');
    }
    
    // Создание задачи менеджеру
    // createTask("Обработать лид #$leadId", $responsibleId);
    
    return ['auto_processed' => true];
});
```

### Синхронизация задач

```php
$handler->on('ONTASKADD', function($data) {
    $taskId = $data['FIELDS']['ID'];
    $title = $data['FIELDS']['TITLE'];
    $responsibleId = $data['FIELDS']['RESPONSIBLE_ID'];
    
    // Дублирование в внешний таск-трекер (Jira, Trello и т.д.)
    // $externalTaskId = createExternalTask($title, $responsibleId);
    
    // Сохранить связь ID
    // saveMapping($taskId, $externalTaskId);
    
    return ['synced' => true];
});
```

### Логирование всех изменений

```php
$handler->on('ONCRMDEALUPDATE', function($data) {
    $dealId = $data['FIELDS']['ID'];
    
    // Сохранение истории изменений в БД
    $db = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
    $stmt = $db->prepare('
        INSERT INTO deal_history (deal_id, changes, created_at)
        VALUES (?, ?, NOW())
    ');
    $stmt->execute([$dealId, json_encode($data['FIELDS'])]);
    
    return ['logged' => true];
});
```

## Структура данных события

Bitrix24 отправляет POST-запрос в формате:

```json
{
    "event": "ONCRMDEALADD",
    "data": {
        "FIELDS": {
            "ID": "123",
            "TITLE": "Название сделки",
            "OPPORTUNITY": "150000",
            "CURRENCY_ID": "RUB",
            "STAGE_ID": "NEW",
            "ASSIGNED_BY_ID": "1"
        }
    },
    "ts": "1696780800",
    "auth": {
        "domain": "portal.bitrix24.ru",
        "application_token": "..."
    }
}
```

Доступ к данным:

```php
$dealId = $data['FIELDS']['ID'];
$title = $data['FIELDS']['TITLE'];
$amount = $data['FIELDS']['OPPORTUNITY'];
```

## Тестирование

### Локальное тестирование

```bash
php test_local.php
```

Симулирует отправку событий от Bitrix24.

### Тестирование на сервере

Используйте инструменты для отправки POST-запросов:

**curl:**
```bash
curl -X POST https://ваш-домен.ru/handler.php \
  -H "Content-Type: application/json" \
  -d '{
    "event": "ONCRMDEALADD",
    "data": {
      "FIELDS": {
        "ID": "123",
        "TITLE": "Тест",
        "OPPORTUNITY": "150000"
      }
    }
  }'
```

**Postman / Insomnia:**
- URL: `https://ваш-домен.ru/handler.php`
- Method: POST
- Body: JSON с данными события

### Проверка логов

```bash
tail -f webhook.log
```

или откройте файл в редакторе.

## Отладка

### Проблема: События не приходят

1. **Проверьте, зарегистрированы ли события:**
   ```bash
   php register.php
   ```

2. **Проверьте доступность URL:**
   - Откройте `https://ваш-домен.ru/handler.php` в браузере
   - Должен вернуть JSON-ответ

3. **Проверьте права webhook:**
   - Bitrix24 → Настройки → Разработчикам → Входящий вебхук
   - Убедитесь, что выбраны нужные права

4. **Проверьте логи:**
   ```bash
   cat webhook.log
   ```

### Проблема: Обработчик не срабатывает

1. **Проверьте синтаксис PHP:**
   ```bash
   php -l handler.php
   ```

2. **Включите отображение ошибок:**
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

3. **Добавьте отладочный вывод:**
   ```php
   file_put_contents('debug.log', print_r($_POST, true), FILE_APPEND);
   ```

### Проблема: Таймаут обработки

Обработчик должен отвечать быстро (< 5 секунд). Для долгих операций:

```php
$handler->on('ONCRMDEALADD', function($data) {
    // Быстро сохраняем в очередь
    $queue[] = $data;
    saveQueue($queue);
    
    // Возвращаем быстрый ответ
    return ['queued' => true];
});

// Отдельный процесс обрабатывает очередь
```

## Безопасность

### 1. Проверка источника запроса

```php
// Проверяем, что запрос пришел от Bitrix24
$allowedDomains = ['bitrix24.ru', 'bitrix24.com'];
$referer = $_SERVER['HTTP_REFERER'] ?? '';

$isValidSource = false;
foreach ($allowedDomains as $domain) {
    if (strpos($referer, $domain) !== false) {
        $isValidSource = true;
        break;
    }
}

if (!$isValidSource) {
    http_response_code(403);
    exit('Forbidden');
}
```

### 2. Ограничение по IP

```php
// Если известны IP Bitrix24
$allowedIPs = ['xxx.xxx.xxx.xxx'];
$clientIP = $_SERVER['REMOTE_ADDR'];

if (!in_array($clientIP, $allowedIPs)) {
    http_response_code(403);
    exit('Forbidden');
}
```

### 3. Токен авторизации

```php
$authToken = $_POST['auth']['application_token'] ?? '';
$expectedToken = 'ваш_секретный_токен';

if ($authToken !== $expectedToken) {
    http_response_code(403);
    exit('Invalid token');
}
```

## Управление событиями

### Список зарегистрированных событий

```php
$eventManager = new EventManager($WEBHOOK_URL);
$events = $eventManager->getList();

foreach ($events as $event) {
    echo "{$event['event']} → {$event['handler']}\n";
}
```

### Отмена регистрации

```bash
php unregister.php
```

или для конкретного события:

```php
$eventManager->unbind('ONCRMDEALADD', $HANDLER_URL);
```

## Практические сценарии

### 1. Автоматизация продаж

```php
// При создании крупной сделки - создать задачу руководителю
$handler->on('ONCRMDEALADD', function($data) {
    if ($data['FIELDS']['OPPORTUNITY'] > 500000) {
        // API-запрос на создание задачи
    }
});
```

### 2. Интеграция с CRM/ERP

```php
// Синхронизация сделок с внешней системой
$handler->on('ONCRMDEALUPDATE', function($data) {
    $externalAPI->updateDeal($data['FIELDS']);
});
```

### 3. Уведомления в Telegram/Slack

```php
$handler->on('ONCRMDEALADD', function($data) {
    $telegram->sendMessage(
        'Новая сделка: ' . $data['FIELDS']['TITLE']
    );
});
```

### 4. Аналитика и отчеты

```php
$handler->on('ONCRMDEALUPDATE', function($data) {
    if ($data['FIELDS']['STAGE_ID'] === 'WON') {
        // Обновить статистику продаж
        updateSalesMetrics();
    }
});
```

## Полезные ссылки

- [События REST API](https://dev.1c-bitrix.ru/rest_help/general/event_bind.php)
- [Список событий CRM](https://dev.1c-bitrix.ru/rest_help/crm/events/index.php)
- [События задач](https://dev.1c-bitrix.ru/rest_help/tasks/events/index.php)

## Что изучается в этом примере

✅ Регистрация обработчиков событий  
✅ Прием и обработка webhooks от Bitrix24  
✅ Автоматическая реакция на события  
✅ Логирование всех операций  
✅ Тестирование обработчиков  
✅ Обработка ошибок  
✅ Интеграция с внешними системами  
✅ Безопасность webhook обработчиков
