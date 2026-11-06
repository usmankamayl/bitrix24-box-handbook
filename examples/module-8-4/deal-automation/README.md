# Задача 2: Автоматизация сделок через вебхуки

## 📋 Описание

Система автоматизации работы со сделками, которая:
- Автоматически создает сделку при создании лида
- Перемещает сделки по воронке в зависимости от статусов
- Создает активности (звонки, встречи) и задачи
- Отправляет уведомления при важных изменениях
- Синхронизирует данные между лидами и сделками

## 🎯 Функционал

### Автоматическое создание сделок
- ✅ При создании лида → создается сделка
- ✅ При конвертации лида → обновляется связь
- ✅ Автоматическое назначение ответственного
- ✅ Установка начальной стадии

### Управление воронкой продаж
- ✅ Автоматические переходы между стадиями
- ✅ Создание задач в зависимости от стадии
- ✅ Напоминания об оплате
- ✅ Уведомления команде при выполнении

### Активности и задачи
- ✅ Создание активности "Позвонить"
- ✅ Создание задачи на подготовку КП
- ✅ Создание напоминания об оплате
- ✅ Создание задачи на анализ при проигрыше

### Уведомления
- ✅ Уведомление менеджеру при создании сделки
- ✅ Поздравление при выигрыше
- ✅ Уведомление руководителю о низкой вероятности
- ✅ Уведомление команды при переводе в производство

## 📁 Структура файлов

```
deal-automation/
├── webhook-handler.php          # Основной обработчик
├── Bitrix24Client.php          # Клиент для API
├── DealAutomationLogger.php    # Логирование
├── config.php                  # Конфигурация
├── schema.sql                  # SQL схема
├── logs/                       # Логи
└── README.md                   # Инструкция
```

## 🚀 Установка

### 1. Копируем конфигурацию

```bash
cp ../lead-events/config.example.php config.php
nano config.php

# Добавляем специфичные для автоматизации параметры
```

**Обновленный config.php:**
```php
// ... существующие параметры ...

// ID продажного менеджера (руководителя)
define('SALES_MANAGER_ID', 1);

// ID членов команды производства
define('PRODUCTION_TEAM_IDS', [2, 3, 4]);

// ID администраторов
define('ADMIN_USER_IDS', [1]);
```

### 2. Создание таблицы в БД

```sql
CREATE TABLE IF NOT EXISTS `deal_automation_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `webhook_id` VARCHAR(255) NOT NULL,
    `event_name` VARCHAR(100) NOT NULL,
    `event_data` JSON NOT NULL,
    `created_at` DATETIME NOT NULL,
    `processed_at` DATETIME NOT NULL,
    `status` ENUM('pending', 'processed', 'failed') DEFAULT 'pending',

    INDEX `idx_webhook_id` (`webhook_id`),
    INDEX `idx_event_name` (`event_name`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. Регистрация вебхуков

```bash
# Отредактируйте URL в скрипте
nano register-webhooks.php

# Добавьте события для сделок
# ONCRMDEALUPDATE
# ONCRMDEALSTAGECHANGE

php register-webhooks.php
```

## 💡 Использование

### Сценарий 1: Создание лида → Создание сделки

```
1. Менеджер создает лид в Bitrix24
   ↓
2. Отправляется вебхук onCrmLeadAdd
   ↓
3. Система проверяет, есть ли уже сделка (защита от дубликатов)
   ↓
4. Создается новая сделка со статусом "Новая"
   ↓
5. К сделке добавляется активность "Позвонить клиенту"
   ↓
6. Менеджеру отправляется уведомление
```

**Журнал:**
```
[2024-01-15 10:30:00] INFO: Event received: ONCRMLEADADD
[2024-01-15 10:30:00] INFO: Processing lead #42 for deal creation
[2024-01-15 10:30:01] INFO: Deal #123 created from lead #42
[2024-01-15 10:30:01] INFO: Call activity created for deal #123
```

### Сценарий 2: Перемещение сделки по воронке

```
Сделка: "Контракт на разработку сайта"

NEW → Создан
  ↓
PREPARATION → Подготовка КП
  ├── Создаётся задача "Подготовить КП"
  ├── Отправляется уведомление
  ↓
PREPAYMENT_INVOICE → Счет выставлен
  ├── Создается напоминание об оплате
  ├── Отправляется задача
  ↓
EXECUTING → Выполняется
  ├── Уведомляется команда производства
  ├── Отправляется в систему управления проектами
  ↓
WON → Выиграна (успешно закрыта)
  ├── Поздравление менеджеру
  ├── Уведомление руководителю
  ├── Интеграция с бухгалтерией (опционально)
```

### Сценарий 3: Низкая вероятность сделки

```
Событие: onCrmDealUpdate с probability < 30%
  ↓
Отправляется уведомление руководителю:
  "⚠️ Сделка с низкой вероятностью
   Сделка #123 имеет вероятность 25%
   Возможно, требуется помощь руководителя"
```

## 🔧 Класс Bitrix24Client

Упрощает работу с REST API:

```php
$bitrix = new Bitrix24Client();

// Создание сделки
$result = $bitrix->createDeal([
    'TITLE' => 'Новая сделка',
    'STAGE_ID' => 'C1:NEW',
    'ASSIGNED_BY_ID' => 1
]);

// Обновление сделки
$bitrix->updateDeal(123, [
    'STAGE_ID' => 'C1:PREPARATION'
]);

// Получение сделки
$deal = $bitrix->getDeal(123);

// Создание активности
$bitrix->createActivity([
    'OWNER_TYPE_ID' => 2, // Сделка
    'OWNER_ID' => 123,
    'TYPE_ID' => 2, // Звонок
    'SUBJECT' => 'Позвонить',
    'RESPONSIBLE_ID' => 1
]);

// Отправка уведомления
$bitrix->sendNotification(1, 'Заголовок', 'Текст сообщения');

// Пакетный запрос (batch)
$results = $bitrix->batch([
    'crm.deal.get' => ['id' => 123],
    'crm.contact.get' => ['id' => 456]
]);
```

## 📊 Примеры использования

### Пример 1: Автоматическое создание задачи

```php
function handleLeadAddForDeal($data, Bitrix24Client $bitrix, $logger)
{
    // Создаем сделку
    $deal = $bitrix->createDeal([
        'TITLE' => $data['FIELDS']['TITLE'],
        'STAGE_ID' => 'C1:NEW'
    ]);

    // Создаем задачу
    $bitrix->createTask([
        'TITLE' => 'Обработать новую сделку',
        'DESCRIPTION' => 'Сделка #' . $deal['id'],
        'RESPONSIBLE_ID' => $data['FIELDS']['ASSIGNED_BY_ID'],
        'DEADLINE' => date('d.m.Y', strtotime('+1 day')),
        'PRIORITY' => 2
    ]);
}
```

### Пример 2: Интеграция с внешней системой

```php
function handleDealWon($dealId, $data, Bitrix24Client $bitrix, $logger)
{
    $dealSum = $data['FIELDS']['OPPORTUNITY'];
    
    // Отправляем в бухгалтерию
    $curl = curl_init('https://accounting.company.com/api/invoices');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'deal_id' => $dealId,
            'sum' => $dealSum,
            'status' => 'completed'
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    curl_exec($curl);
}
```

### Пример 3: Статистика по воронке

```php
// Получение всех активных сделок по стадиям
$stages = [
    'C1:NEW' => 'Новые',
    'C1:PREPARATION' => 'Подготовка',
    'C1:EXECUTING' => 'Выполнение',
    'C1:WON' => 'Выигранные',
    'C1:LOSE' => 'Проигранные'
];

foreach ($stages as $stageId => $stageName) {
    $deals = $bitrix->getDeals([
        'filter' => ['STAGE_ID' => $stageId],
        'select' => ['ID', 'TITLE', 'OPPORTUNITY']
    ]);
    
    $sum = array_sum(array_column($deals['items'], 'OPPORTUNITY'));
    
    echo "$stageName: {$deals['total']} сделок на сумму $sum руб.\n";
}
```

## 📈 Мониторинг

### Просмотр логов

```bash
tail -f logs/automation.log
tail -f logs/info.log
tail -f logs/error.log
```

### Проверка в БД

```sql
-- Последние события автоматизации
SELECT * FROM deal_automation_log ORDER BY created_at DESC LIMIT 10;

-- События по типам
SELECT event_name, COUNT(*) as count FROM deal_automation_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY event_name;

-- Сделки, созданные из лидов (за последний день)
SELECT 
    JSON_EXTRACT(event_data, '$.FIELDS.ID') as deal_id,
    JSON_EXTRACT(event_data, '$.FIELDS.TITLE') as title,
    created_at
FROM deal_automation_log
WHERE event_name = 'ONCRMLEADADD'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY);
```

## 🛠️ Расширение функционала

### Добавление нового сценария автоматизации

```php
// В webhook-handler.php добавляем в handleDealStageChange:

case 'C1:CUSTOM_STAGE':
    // Ваша логика
    doSomethingSpecial($dealId, $bitrix, $logger);
    break;
```

### Интеграция с другими системами

```php
// Пример: синхронизация с 1C

function syncTo1C($dealId, $deal)
{
    $curl = curl_init('https://1c-server.company.com/api/deals');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'bitrix_id' => $dealId,
            'sum' => $deal['OPPORTUNITY'],
            'status' => 'synced'
        ])
    ]);
    curl_exec($curl);
}
```

## 🔐 Безопасность

- ✅ Проверка токена в URL
- ✅ Логирование всех действий
- ✅ Защита от повторной обработки (webhook_id)
- ✅ Обработка ошибок и исключений
- ✅ Асинхронная обработка для сохранения производительности

## 📚 Дополнительно

- [Документация crm.deal.add](https://dev.1c-bitrix.ru/rest_help/crm/deals/crm_deal_add.php)
- [Документация crm.activity.add](https://dev.1c-bitrix.ru/rest_help/crm/activities/crm_activity_add.php)
- [Документация tasks.task.add](https://dev.1c-bitrix.ru/rest_help/tasks/tasks_task_add.php)
- [Документация im.notify](https://dev.1c-bitrix.ru/rest_help/im/im_notify.php)

---

**Часть курса подготовки к собеседованию Bitrix-разработчика**
