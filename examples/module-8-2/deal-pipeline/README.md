# Задача 2: Работа со сделками (воронка продаж)

## Описание

Класс для управления сделками в Bitrix24 CRM через REST API. Позволяет создавать сделки, перемещать по воронке продаж, привязывать контакты и получать статистику.

## 📂 Где разместить код в проекте

### Вариант 1: В `/local/php_interface/classes/` (рекомендуется)

```
bitrix-site/
├── local/
│   └── php_interface/
│       ├── init.php
│       └── classes/
│           └── DealManager.php    ← Основной класс
└── admin/
    └── deals_example.php          ← Пример использования
```

**Подключение в `/local/php_interface/init.php`:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/DealManager.php';
```

### Вариант 2: В модуле

```
bitrix-site/
├── local/
│   └── modules/
│       └── mycompany.crm/
│           ├── lib/
│           │   └── DealManager.php
│           └── include.php
```

### Вариант 3: Для внешних интеграций

```
integration/
├── classes/
│   └── DealManager.php
├── config.php
└── run.php
```

## Возможности

### ✅ Что умеет класс

- **Создание сделки** с привязкой к контакту и компании
- **Обновление сделки** (стадия, сумма, вероятность)
- **Получение сделки** по ID
- **Поиск сделок** с фильтрацией
- **Перемещение по воронке** (автоматический переход на следующую стадию)
- **Привязка контактов** (множественная)
- **Получение стадий воронки**
- **Статистика по воронке** (количество и сумма на каждой стадии)
- **Закрытие сделки** (выигрыш/проигрыш)
- **Удаление сделки**

### 📋 Основные поля сделки

- `TITLE` - название сделки
- `TYPE_ID` - тип (SALE, SERVICES)
- `STAGE_ID` - стадия воронки (NEW, PREPARATION, WON, LOSE)
- `OPPORTUNITY` - сумма сделки
- `CURRENCY_ID` - валюта (RUB, USD, EUR)
- `CONTACT_ID` - контакт
- `COMPANY_ID` - компания
- `ASSIGNED_BY_ID` - ответственный
- `PROBABILITY` - вероятность закрытия (%)
- `BEGINDATE` - дата начала
- `CLOSEDATE` - планируемая дата закрытия
- `CLOSED` - флаг закрытия (Y/N)

## Установка

### Шаг 1: Получить webhook

1. Зайти в Bitrix24 → **Разработчикам** → **Вебхуки**
2. Создать **входящий webhook**
3. Выбрать права: **CRM (crm)** - полный доступ
4. Скопировать URL

### Шаг 2: Разместить класс

Скопировать `DealManager.php` в `/local/php_interface/classes/`

### Шаг 3: Подключить в init.php

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/DealManager.php';
```

## Использование

### Базовый пример

```php
<?php
use Local\Bitrix24\DealManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new DealManager($webhookUrl);

// Создание сделки
$dealId = $manager->createDeal([
    'TITLE' => 'Продажа CRM',
    'STAGE_ID' => 'NEW',
    'OPPORTUNITY' => 150000,
    'CURRENCY_ID' => 'RUB',
    'CONTACT_ID' => 123,
    'COMPANY_ID' => 456,
    'ASSIGNED_BY_ID' => 1
]);

echo "Создана сделка: $dealId";
```

### Пример 1: Конвертация лида в сделку

**Расположение:** `/local/components/mycompany/lead.converter/ajax.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Local\Bitrix24\DealManager;
use Local\Bitrix24\LeadManager;

$leadId = (int)$_POST['lead_id'];

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';

// Получаем данные лида
$leadManager = new LeadManager($webhookUrl);
$lead = $leadManager->getLead($leadId);

if ($lead) {
    // Создаем сделку на основе лида
    $dealManager = new DealManager($webhookUrl);
    
    $dealId = $dealManager->createDeal([
        'TITLE' => $lead['TITLE'],
        'STAGE_ID' => 'NEW',
        'OPPORTUNITY' => $lead['OPPORTUNITY'] ?? 0,
        'CURRENCY_ID' => $lead['CURRENCY_ID'] ?? 'RUB',
        'CONTACT_ID' => $lead['CONTACT_ID'] ?? null,
        'ASSIGNED_BY_ID' => $lead['ASSIGNED_BY_ID'],
        'COMMENTS' => "Конвертирован из лида #{$leadId}"
    ]);
    
    if ($dealId) {
        // Обновляем статус лида
        $leadManager->updateLead($leadId, [
            'STATUS_ID' => 'CONVERTED'
        ]);
        
        echo json_encode(['success' => true, 'dealId' => $dealId]);
    }
}
```

### Пример 2: Автоматическое перемещение по воронке

**Расположение:** `/local/php_interface/classes/DealAutomation.php`

```php
<?php
namespace Local\Bitrix24;

class DealAutomation
{
    private $manager;
    
    public function __construct(DealManager $manager)
    {
        $this->manager = $manager;
    }
    
    /**
     * Автоматически перемещает сделку после оплаты
     */
    public function onPaymentReceived(int $dealId, float $amount)
    {
        $deal = $this->manager->getDeal($dealId);
        
        if (!$deal) {
            return false;
        }
        
        // Если оплачена полная сумма - закрываем как выигранную
        if ($amount >= $deal['OPPORTUNITY']) {
            return $this->manager->markAsWon($dealId);
        }
        
        // Иначе просто переводим на следующую стадию
        return $this->manager->moveToNextStage($dealId);
    }
    
    /**
     * Перемещает старые сделки в проигранные
     */
    public function closeExpiredDeals()
    {
        // Находим сделки с истекшим сроком
        $expiredDeals = $this->manager->listDeals([
            'CLOSED' => 'N',
            '<CLOSEDATE' => date('Y-m-d')
        ]);
        
        foreach ($expiredDeals as $deal) {
            $this->manager->markAsLost(
                $deal['ID'],
                'Автоматически закрыто: истек срок'
            );
        }
        
        return count($expiredDeals);
    }
}
```

### Пример 3: Дашборд менеджера продаж

**Расположение:** `/local/components/mycompany/sales.dashboard/component.php`

```php
<?php
use Local\Bitrix24\DealManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new DealManager($webhookUrl);

// ID текущего менеджера
$userId = 1;

// Мои активные сделки
$myDeals = $manager->listDeals([
    'ASSIGNED_BY_ID' => $userId,
    'CLOSED' => 'N'
]);

// Сделки, требующие внимания (истекают через 3 дня)
$urgentDate = date('Y-m-d', strtotime('+3 days'));
$urgentDeals = $manager->listDeals([
    'ASSIGNED_BY_ID' => $userId,
    'CLOSED' => 'N',
    '<=CLOSEDATE' => $urgentDate
]);

// Статистика по воронке
$stats = $manager->getPipelineStats();

// Сумма всех активных сделок
$totalAmount = array_sum(array_column($myDeals, 'OPPORTUNITY'));

$arResult = [
    'DEALS_COUNT' => count($myDeals),
    'URGENT_COUNT' => count($urgentDeals),
    'TOTAL_AMOUNT' => $totalAmount,
    'PIPELINE_STATS' => $stats,
    'DEALS' => $myDeals
];

$this->includeComponentTemplate();
```

### Пример 4: Webhook-обработчик для внешних систем

**Расположение:** `/local/webhooks/deal_webhook.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Local\Bitrix24\DealManager;

// Получаем данные от внешней системы (например, из 1C)
$data = json_decode(file_get_contents('php://input'), true);

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new DealManager($webhookUrl);

switch ($data['action']) {
    case 'create_deal':
        $dealId = $manager->createDeal([
            'TITLE' => $data['title'],
            'OPPORTUNITY' => $data['amount'],
            'CURRENCY_ID' => 'RUB',
            'STAGE_ID' => 'NEW',
            'ASSIGNED_BY_ID' => $data['manager_id'] ?? 1
        ]);
        
        echo json_encode(['success' => true, 'deal_id' => $dealId]);
        break;
        
    case 'update_stage':
        $updated = $manager->updateDeal($data['deal_id'], [
            'STAGE_ID' => $data['stage_id']
        ]);
        
        echo json_encode(['success' => $updated]);
        break;
        
    case 'mark_won':
        $won = $manager->markAsWon($data['deal_id']);
        
        echo json_encode(['success' => $won]);
        break;
}
```

### Пример 5: Отчет по продажам

**Расположение:** `/admin/sales_report.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Local\Bitrix24\DealManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new DealManager($webhookUrl);

// Текущий месяц
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Выигранные сделки
$wonDeals = $manager->listDeals([
    'STAGE_ID' => 'WON',
    '>=CLOSEDATE' => $monthStart,
    '<=CLOSEDATE' => $monthEnd
]);

// Проигранные сделки
$lostDeals = $manager->listDeals([
    'STAGE_ID' => 'LOSE',
    '>=CLOSEDATE' => $monthStart,
    '<=CLOSEDATE' => $monthEnd
]);

$wonAmount = array_sum(array_column($wonDeals, 'OPPORTUNITY'));
$lostAmount = array_sum(array_column($lostDeals, 'OPPORTUNITY'));

$conversionRate = count($wonDeals) + count($lostDeals) > 0
    ? round(count($wonDeals) / (count($wonDeals) + count($lostDeals)) * 100, 2)
    : 0;

?>
<h2>Отчет по продажам за <?= date('F Y') ?></h2>

<div class="stats">
    <div class="stat-card">
        <h3>Выигранные сделки</h3>
        <p class="big-number"><?= count($wonDeals) ?></p>
        <p class="amount"><?= number_format($wonAmount, 0, '.', ' ') ?> ₽</p>
    </div>
    
    <div class="stat-card">
        <h3>Проигранные сделки</h3>
        <p class="big-number"><?= count($lostDeals) ?></p>
        <p class="amount"><?= number_format($lostAmount, 0, '.', ' ') ?> ₽</p>
    </div>
    
    <div class="stat-card">
        <h3>Конверсия</h3>
        <p class="big-number"><?= $conversionRate ?>%</p>
    </div>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
```

## Работа с воронкой продаж

### Стандартные стадии

| ID | Название | Описание |
|----|----------|----------|
| `NEW` | Новая | Первичный контакт |
| `PREPARATION` | Подготовка КП | Готовим предложение |
| `PREPAYMENT_INVOICE` | Выставлен счет | Ожидаем оплату |
| `EXECUTING` | Выполняется | Работы в процессе |
| `FINAL_INVOICE` | Выставлен финальный счет | Финальная оплата |
| `WON` | Успешно реализовано | Сделка закрыта |
| `LOSE` | Провалена | Не удалось закрыть |

### Автоматическое перемещение

```php
// Получить текущую стадию
$deal = $manager->getDeal(123);
echo "Текущая стадия: {$deal['STAGE_ID']}";

// Переместить на следующую
$newStage = $manager->moveToNextStage(123);
echo "Новая стадия: $newStage";
```

### Получение всех стадий

```php
$stages = $manager->getStages();

foreach ($stages as $stage) {
    echo "{$stage['STATUS_ID']}: {$stage['NAME']}\n";
}
```

## Привязка контактов

### Основной контакт при создании

```php
$dealId = $manager->createDeal([
    'TITLE' => 'Продажа',
    'CONTACT_ID' => 123,    // Основной контакт
    'STAGE_ID' => 'NEW'
]);
```

### Добавление дополнительных контактов

```php
// Основной контакт
$manager->addContact($dealId, 123, true);

// Дополнительные
$manager->addContact($dealId, 456, false);
$manager->addContact($dealId, 789, false);

// Получить список всех контактов
$contacts = $manager->getContacts($dealId);
```

## Статистика и отчеты

### Статистика по воронке

```php
$stats = $manager->getPipelineStats();

foreach ($stats as $stat) {
    echo "{$stat['stage_name']}: ";
    echo "{$stat['deals_count']} сделок на ";
    echo number_format($stat['total_amount'], 0) . " руб.\n";
}
```

### Поиск с фильтрацией

```php
// Крупные активные сделки
$bigDeals = $manager->listDeals([
    'CLOSED' => 'N',
    '>OPPORTUNITY' => 500000
]);

// Сделки конкретного менеджера
$managerDeals = $manager->listDeals([
    'ASSIGNED_BY_ID' => 5,
    'STAGE_ID' => ['NEW', 'PREPARATION']
]);

// Сделки с истекающим сроком
$urgentDeals = $manager->listDeals([
    'CLOSED' => 'N',
    '<=CLOSEDATE' => date('Y-m-d', strtotime('+7 days'))
]);
```

## Обработка ошибок

### Проверка результата

```php
$dealId = $manager->createDeal([...]);

if ($dealId === false) {
    error_log('Не удалось создать сделку');
    // Проверить логи
} else {
    echo "Сделка создана: $dealId";
}
```

### Типичные ошибки

**1. NOT_FOUND**
```
Ошибка: Deal not found
Решение: Проверить существование сделки
```

**2. INVALID_STAGE**
```
Ошибка: Invalid stage ID
Решение: Получить список стадий через getStages()
```

**3. REQUIRED_FIELD**
```
Ошибка: Required fields: TITLE
Решение: Указать обязательное поле TITLE
```

## Лимиты и рекомендации

- Для webhook: **2 запроса/сек**
- Для массовых операций используйте **batch-запросы**
- Кешируйте список стадий воронки
- Добавляйте задержки при массовом обновлении

## Что изучается в этой задаче

✅ Методы `crm.deal.*`
✅ Создание и управление сделками
✅ Работа с воронкой продаж (стадии)
✅ Привязка контактов к сделкам
✅ Статистика по воронке
✅ Закрытие сделок (выигрыш/проигрыш)
✅ Фильтрация по стадиям и датам
✅ Работа с методом `crm.status.list`

## Следующие шаги

После освоения работы со сделками переходите к:
- **Задача 3:** Управление контактами (импорт из CSV)
- **Модуль 8.3:** Пакетные запросы (batch) для ускорения работы
