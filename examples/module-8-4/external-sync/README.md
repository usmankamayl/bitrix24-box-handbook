# Задача 3: Синхронизация с внешними системами

## 📋 Описание

Система двусторонней синхронизации данных между Bitrix24 и внешними системами (1C, внешние CRM, мессенджеры) через вебхуки. Обеспечивает надежную передачу данных с автоматическими повторными попытками при ошибках.

## 🎯 Функционал

### Интеграции
- ✅ **1C** — синхронизация сделок, контактов, компаний
- ✅ **Внешние CRM** — двусторонняя синхронизация данных
- ✅ **Slack** — уведомления о важных событиях
- ✅ **Telegram** — уведомления в мессенджер

### Возможности
- ✅ Автоматическая синхронизация при изменении данных
- ✅ Маппинг ID между системами
- ✅ Очередь повторных попыток при ошибках
- ✅ Экспоненциальная задержка (backoff)
- ✅ Детальное логирование операций
- ✅ Статистика синхронизации

## 📁 Структура файлов

```
external-sync/
├── webhook-handler.php          # Обработчик вебхуков
├── ExternalSystemClient.php     # Клиент для внешних API
├── ExternalSyncLogger.php       # Логирование синхронизации
├── config.php                   # Конфигурация интеграций
├── schema.sql                   # SQL схема
├── retry-queue-worker.php       # Обработчик очереди повторов
├── logs/                        # Логи
└── README.md                    # Эта инструкция
```

## 🚀 Установка

### 1. Настройка конфигурации

```bash
nano config.php
```

**Основные параметры:**

```php
// === 1C ===
define('ENABLE_1C_SYNC', true);
define('ONE_C_API_URL', 'http://1c-server.company.local:8080/api/v1');
define('ONE_C_API_USER', 'bitrix_user');
define('ONE_C_API_PASSWORD', 'password123');

// === Slack ===
define('ENABLE_SLACK_NOTIFICATIONS', true);
define('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL');
define('SLACK_CHANNEL_SALES', '#sales-wins');

// === Telegram ===
define('ENABLE_TELEGRAM_NOTIFICATIONS', true);
define('TELEGRAM_BOT_TOKEN', '123456:ABC-DEF...');
define('TELEGRAM_CHAT_IDS', [123456789]);
```

### 2. Создание таблиц

```bash
mysql -u root -p bitrix_webhooks < schema.sql
```

**Таблицы:**
- `external_sync_log` — лог событий синхронизации
- `sync_history` — история всех синхронизаций
- `external_id_mapping` — маппинг ID между системами
- `sync_retry_queue` — очередь повторных попыток

### 3. Регистрация вебхуков

```bash
php register-webhooks.php
```

**События для регистрации:**
- `ONCRMDEALUPDATE` — обновление сделки
- `ONCRMDEALSTAGECHANGE` — изменение стадии
- `ONCRMCONTACTADD` — создание контакта
- `ONCRMCONTACTUPDATE` — обновление контакта

### 4. Настройка обработчика очереди (cron)

```bash
# Добавить в crontab
crontab -e

# Каждые 5 минут обрабатывать очередь повторов
*/5 * * * * php /path/to/external-sync/retry-queue-worker.php >> /var/log/sync-queue.log 2>&1
```

## 💡 Как это работает

### Сценарий 1: Синхронизация сделки с 1C

```
Bitrix24: Сделка обновлена → стадия "Выставлен счет"
  ↓
Вебхук отправлен на ваш сервер
  ↓
webhook-handler.php получает событие
  ↓
ExternalSystemClient отправляет данные в 1C:
  POST http://1c-server/api/v1/deals/update
  {
    "bitrix_id": 123,
    "title": "Контракт",
    "stage": "PREPAYMENT_INVOICE",
    "amount": 150000,
    "currency": "RUB"
  }
  ↓
Успешно → сохраняем в sync_history со статусом "success"
  ↓
Сохраняем маппинг: Bitrix ID 123 ↔ 1C ID 456
```

**Лог:**
```
[2024-01-15 10:30:00] INFO: Sync event received: ONCRMDEALUPDATE
[2024-01-15 10:30:00] INFO: Syncing deal #123 to external system
[2024-01-15 10:30:01] INFO: Deal #123 synced to 1C successfully
```

### Сценарий 2: Обработка ошибки с повторными попытками

```
Попытка 1: Отправка в 1C
  ↓
❌ Ошибка: HTTP 500 (1C недоступна)
  ↓
Добавляем в sync_retry_queue:
  - retry_count = 0
  - next_retry_at = NOW() + 5 минут
  ↓
Cron запускает retry-queue-worker.php через 5 минут
  ↓
Попытка 2: Отправка в 1C
  ↓
❌ Ошибка: HTTP 500
  ↓
Обновляем в очереди:
  - retry_count = 1
  - next_retry_at = NOW() + 10 минут (экспоненциальная задержка)
  ↓
Попытка 3: Отправка в 1C
  ↓
✅ Успешно!
  ↓
Удаляем из sync_retry_queue
Сохраняем в sync_history
```

### Сценарий 3: Уведомление в Slack при выигрыше сделки

```
Bitrix24: Сделка переведена в "Выиграна"
  ↓
Вебхук: onCrmDealStageChange
  ↓
webhook-handler.php проверяет стадию
  ↓
Если stage_id === "C1:WON" и ENABLE_SLACK_NOTIFICATIONS === true
  ↓
ExternalSystemClient.sendToSlack():
  POST https://hooks.slack.com/services/...
  {
    "text": "🎉 *Сделка выиграна!*\n\nСделка: Контракт\nСумма: 150 000 руб.",
    "channel": "#sales-wins"
  }
  ↓
Уведомление появляется в Slack
```

## 🔧 Класс ExternalSystemClient

### Отправка в 1C

```php
$client = new ExternalSystemClient();

$result = $client->sendTo1C('deals/update', [
    'bitrix_id' => 123,
    'title' => 'Новая сделка',
    'amount' => 100000
]);

if ($result['success']) {
    echo "Успешно! External ID: " . $result['external_id'];
} else {
    echo "Ошибка: " . $result['error'];
}
```

### Получение данных из 1C

```php
$result = $client->getFrom1C('deals/123');

if ($result['success']) {
    $deal = $result['data'];
    echo "Статус в 1C: " . $deal['status'];
}
```

### Отправка в Slack

```php
$client->sendToSlack(
    "🎉 Новая сделка на 150 000 руб!",
    "#sales"
);
```

### Отправка в Telegram

```php
$client->sendToTelegram(
    "✅ Сделка #123 закрыта успешно!"
);
```

### Проверка доступности системы

```php
$result = $client->checkConnection('1C');

if ($result['success']) {
    echo "1C доступна";
} else {
    echo "1C недоступна (HTTP " . $result['http_code'] . ")";
}
```

## 📊 Маппинг ID между системами

### Как это работает

Когда создается сущность в 1C из Bitrix24, нужно сохранить связь:

```php
// Bitrix24 Deal ID: 123
// После создания в 1C получаем: 1C ID: 456

saveExternalMapping('deal', 123, '1C', 456);

// Теперь можно найти 1C ID по Bitrix ID:
$externalId = getExternalMapping('deal', 123, '1C');
// Результат: "456"
```

### Запросы к БД

```sql
-- Найти 1C ID для сделки Bitrix
SELECT external_id FROM external_id_mapping
WHERE entity_type = 'deal'
  AND bitrix_id = 123
  AND external_system = '1C';

-- Все маппинги для сделки
SELECT * FROM external_id_mapping
WHERE entity_type = 'deal' AND bitrix_id = 123;
```

## 🔄 Обработчик очереди повторов

### Создание скрипта

```php
<?php
// retry-queue-worker.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ExternalSystemClient.php';
require_once __DIR__ . '/ExternalSyncLogger.php';

$logger = new ExternalSyncLogger();
$client = new ExternalSystemClient();

// Получаем элементы из очереди
$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
    DB_USER,
    DB_PASSWORD
);

$stmt = $db->query("
    SELECT * FROM sync_retry_queue
    WHERE next_retry_at <= NOW()
      AND retry_count < max_retries
    ORDER BY next_retry_at ASC
    LIMIT 50
");

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($items) . " items in retry queue\n";

foreach ($items as $item) {
    $data = json_decode($item['data'], true);
    
    echo "Retrying: {$item['system']} - {$item['entity_type']} #{$item['entity_id']}\n";
    
    // Пытаемся отправить
    $result = $client->sendTo1C('deals/update', $data);
    
    if ($result['success']) {
        // Успешно - удаляем из очереди
        $db->exec("DELETE FROM sync_retry_queue WHERE id = {$item['id']}");
        $logger->logInfo("Retry successful for {$item['entity_type']} #{$item['entity_id']}");
        echo "  ✅ Success\n";
    } else {
        // Ошибка - обновляем счетчик
        $newRetryCount = $item['retry_count'] + 1;
        $nextRetry = date('Y-m-d H:i:s', strtotime("+".pow(2, $newRetryCount)." minutes"));
        
        $db->exec("
            UPDATE sync_retry_queue
            SET retry_count = $newRetryCount,
                next_retry_at = '$nextRetry',
                last_error = '{$result['error']}'
            WHERE id = {$item['id']}
        ");
        
        echo "  ❌ Failed (attempt {$newRetryCount})\n";
    }
}
```

### Добавление в cron

```bash
# Каждые 5 минут
*/5 * * * * php /path/to/retry-queue-worker.php

# Или каждую минуту для более быстрой обработки
* * * * * php /path/to/retry-queue-worker.php
```

## 📈 Мониторинг и статистика

### Статистика синхронизации

```sql
-- За последние 7 дней
SELECT
    system,
    entity_type,
    status,
    COUNT(*) as count,
    DATE(synced_at) as date
FROM sync_history
WHERE synced_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY system, entity_type, status, DATE(synced_at)
ORDER BY date DESC;

-- Результат:
-- system | entity_type | status  | count | date
-- 1C     | deal        | success | 45    | 2024-01-15
-- 1C     | deal        | error   | 3     | 2024-01-15
-- 1C     | contact     | success | 12    | 2024-01-15
```

### Последние ошибки

```sql
SELECT
    system,
    entity_type,
    entity_id,
    error_message,
    synced_at
FROM sync_history
WHERE status = 'error'
ORDER BY synced_at DESC
LIMIT 20;
```

### Элементы в очереди

```sql
SELECT
    system,
    entity_type,
    entity_id,
    retry_count,
    max_retries,
    next_retry_at,
    last_error
FROM sync_retry_queue
ORDER BY next_retry_at ASC;
```

### Через API логгера

```php
$logger = new ExternalSyncLogger();

// Статистика за 7 дней
$stats = $logger->getSyncStats(7);
print_r($stats);

// Последние ошибки
$errors = $logger->getRecentErrors(50);
foreach ($errors as $error) {
    echo "{$error['system']} - {$error['entity_type']} #{$error['entity_id']}: {$error['error_message']}\n";
}
```

## 🛠️ Примеры использования

### Пример 1: Интеграция с системой складского учета

```php
function syncDealToWarehouse($dealId, $dealData)
{
    $client = new ExternalSystemClient();
    
    // Отправляем заказ на склад
    $result = $client->sendTo1C('warehouse/orders', [
        'order_id' => $dealId,
        'items' => [
            ['sku' => 'PROD-001', 'quantity' => 5],
            ['sku' => 'PROD-002', 'quantity' => 2]
        ],
        'delivery_date' => date('Y-m-d', strtotime('+7 days'))
    ]);
    
    return $result;
}
```

### Пример 2: Уведомление в Slack о проблемных сделках

```php
function notifyLowProbabilityDeal($dealId, $probability)
{
    if ($probability < 30) {
        $client = new ExternalSystemClient();
        
        $message = "⚠️ *Внимание!*\n\n" .
                   "Сделка #$dealId имеет низкую вероятность ($probability%)\n" .
                   "Требуется помощь руководителя.";
        
        $client->sendToSlack($message, '#alerts');
    }
}
```

### Пример 3: Двусторонняя синхронизация

```php
// Обновление из 1C в Bitrix24
function syncFrom1CToBitrix($external1CId)
{
    $client = new ExternalSystemClient();
    
    // Получаем данные из 1C
    $result = $client->getFrom1C("deals/$external1CId");
    
    if ($result['success']) {
        $dealData = $result['data'];
        
        // Находим соответствующую сделку в Bitrix
        $bitrixId = getExternalMapping('deal', null, '1C', $external1CId, true);
        
        // Обновляем в Bitrix24
        $bitrix = new Bitrix24Client();
        $bitrix->updateDeal($bitrixId, [
            'STAGE_ID' => mapStageFrom1C($dealData['stage']),
            'COMMENTS' => "Обновлено из 1C: " . date('Y-m-d H:i:s')
        ]);
    }
}
```

## 🔐 Безопасность

### Рекомендации

1. **HTTPS для всех внешних запросов**
2. **Базовая авторизация для 1C** (логин/пароль)
3. **Bearer токены для REST API**
4. **Логирование всех операций**
5. **Ограничение IP адресов** (если возможно)

### Хранение credentials

```php
// ❌ Плохо: в коде
$password = 'hardcoded_password';

// ✅ Хорошо: в config.php (в .gitignore)
define('ONE_C_API_PASSWORD', getenv('ONE_C_PASSWORD'));

// ✅ Еще лучше: в переменных окружения
// .env файл:
// ONE_C_PASSWORD=secret123
```

## 🐛 Отладка

### Проблема: Запросы не доходят до 1C

```bash
# Проверить доступность
curl -I http://1c-server:8080/api/v1/ping

# Проверить авторизацию
curl -u bitrix_user:password http://1c-server:8080/api/v1/deals
```

### Проблема: Данные не синхронизируются

```bash
# Проверить логи
tail -f logs/sync.log
tail -f logs/error.log

# Проверить очередь
mysql -u root -p bitrix_webhooks -e "SELECT * FROM sync_retry_queue;"

# Вручную запустить обработчик очереди
php retry-queue-worker.php
```

### Включить детальное логирование

```php
// config.php
define('DETAILED_SYNC_LOGGING', true);
```

## 📚 Дополнительные ресурсы

- [1C REST API документация](https://its.1c.ru/db/metod8dev)
- [Slack Incoming Webhooks](https://api.slack.com/messaging/webhooks)
- [Telegram Bot API](https://core.telegram.org/bots/api)

---

**Часть курса подготовки к собеседованию Bitrix-разработчика**
