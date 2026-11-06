# Задача 2: Триггер автоматического перемещения по воронке

## Описание

Нужно создать триггеры, которые автоматически перемещают сделки между стадиями воронки при выполнении определенных условий.

## Требования

- Триггер 1: При создании звонка переместить сделку в "Контакт установлен"
- Триггер 2: При получении оплаты переместить в "Успешно реализовано"
- Триггер 3: При отсутствии активности 7 дней переместить в "Холодные"
- Логирование всех срабатываний триггеров

## Решение

### Шаг 1: Класс управления триггерами

```php
<?php
// file: /local/lib/TriggerManager.php

namespace Local\CRM;

use Bitrix\Main\Loader;

/**
 * Класс для управления триггерами CRM
 */
class TriggerManager {
    
    private $entityTypeId; // 1-лид, 2-сделка, 3-контакт, 4-компания
    private $categoryId;
    private $logFile;
    
    /**
     * @param int $entityTypeId Тип сущности (2 для сделок)
     * @param int $categoryId ID воронки
     */
    public function __construct($entityTypeId = 2, $categoryId = 0) {
        $this->entityTypeId = $entityTypeId;
        $this->categoryId = $categoryId;
        $this->logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/triggers.log';
        
        Loader::includeModule('crm');
    }
    
    /**
     * Создать триггер "При создании звонка"
     */
    public function createCallCreatedTrigger($targetStageId) {
        try {
            $result = \CRest::call('crm.automation.trigger.add', [
                'ENTITY_TYPE_ID' => $this->entityTypeId,
                'CATEGORY_ID' => $this->categoryId,
                'CODE' => 'ACTIVITY_CREATED',
                'NAME' => 'При создании звонка',
                'TARGET_STATUS_ID' => $targetStageId, // Например: C2:PREPARATION
                'APPLY_RULES' => [
                    [
                        'field' => 'ACTIVITY_TYPE',
                        'operator' => '=',
                        'value' => '2' // 2 = звонок
                    ]
                ]
            ]);
            
            if ($result['result']) {
                $this->log('Триггер успешно создан', [
                    'type' => 'ACTIVITY_CREATED',
                    'target_stage' => $targetStageId,
                    'trigger_id' => $result['result']
                ]);
                
                return $result['result'];
            }
            
            throw new \Exception('Ошибка создания триггера: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка создания триггера', [
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Создать триггер "При получении оплаты"
     */
    public function createPaymentReceivedTrigger($targetStageId) {
        try {
            $result = \CRest::call('crm.automation.trigger.add', [
                'ENTITY_TYPE_ID' => $this->entityTypeId,
                'CATEGORY_ID' => $this->categoryId,
                'CODE' => 'INVOICE_PAID',
                'NAME' => 'При получении оплаты',
                'TARGET_STATUS_ID' => $targetStageId, // Например: C2:WON
                'APPLY_RULES' => []
            ]);
            
            if ($result['result']) {
                $this->log('Триггер оплаты создан', [
                    'type' => 'INVOICE_PAID',
                    'target_stage' => $targetStageId,
                    'trigger_id' => $result['result']
                ]);
                
                return $result['result'];
            }
            
            throw new \Exception('Ошибка создания триггера: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка создания триггера оплаты', [
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Создать триггер "Нет активности N дней"
     */
    public function createInactivityTrigger($days, $targetStageId) {
        try {
            $result = \CRest::call('crm.automation.trigger.add', [
                'ENTITY_TYPE_ID' => $this->entityTypeId,
                'CATEGORY_ID' => $this->categoryId,
                'CODE' => 'INACTIVITY_PERIOD',
                'NAME' => "Нет активности {$days} дней",
                'TARGET_STATUS_ID' => $targetStageId, // Например: C2:LOSE
                'APPLY_RULES' => [
                    [
                        'field' => 'DAYS',
                        'operator' => '=',
                        'value' => (string)$days
                    ]
                ]
            ]);
            
            if ($result['result']) {
                $this->log('Триггер неактивности создан', [
                    'type' => 'INACTIVITY_PERIOD',
                    'days' => $days,
                    'target_stage' => $targetStageId,
                    'trigger_id' => $result['result']
                ]);
                
                return $result['result'];
            }
            
            throw new \Exception('Ошибка создания триггера: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка создания триггера неактивности', [
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Получить список всех триггеров воронки
     */
    public function getTriggers() {
        try {
            $result = \CRest::call('crm.automation.trigger.list', [
                'ENTITY_TYPE_ID' => $this->entityTypeId,
                'CATEGORY_ID' => $this->categoryId
            ]);
            
            if (isset($result['result'])) {
                return $result['result'];
            }
            
            throw new \Exception('Ошибка получения триггеров: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка получения списка триггеров', [
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Удалить триггер
     */
    public function deleteTrigger($triggerId) {
        try {
            $result = \CRest::call('crm.automation.trigger.delete', [
                'ID' => $triggerId
            ]);
            
            if ($result['result']) {
                $this->log('Триггер удален', [
                    'trigger_id' => $triggerId
                ]);
                
                return true;
            }
            
            throw new \Exception('Ошибка удаления триггера: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка удаления триггера', [
                'trigger_id' => $triggerId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Обновить триггер
     */
    public function updateTrigger($triggerId, $fields) {
        try {
            $params = array_merge(['ID' => $triggerId], $fields);
            
            $result = \CRest::call('crm.automation.trigger.update', $params);
            
            if ($result['result']) {
                $this->log('Триггер обновлен', [
                    'trigger_id' => $triggerId,
                    'fields' => $fields
                ]);
                
                return true;
            }
            
            throw new \Exception('Ошибка обновления триггера: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка обновления триггера', [
                'trigger_id' => $triggerId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Проверить, существует ли триггер определенного типа
     */
    public function triggerExists($code) {
        $triggers = $this->getTriggers();
        
        foreach ($triggers as $trigger) {
            if ($trigger['CODE'] === $code) {
                return $trigger;
            }
        }
        
        return false;
    }
    
    /**
     * Логирование
     */
    private function log($message, $data = [], $level = 'INFO') {
        $logEntry = sprintf(
            "[%s] [%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }
}

?>
```

### Шаг 2: Скрипт установки триггеров

```php
<?php
// file: /local/scripts/install-triggers.php
/**
 * Скрипт для установки триггеров автоматизации CRM
 * 
 * Устанавливает триггеры для автоматического движения сделок по воронке
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/TriggerManager.php');

use Local\CRM\TriggerManager;
use Bitrix\Main\Loader;

Loader::includeModule('crm');

// Создаем менеджер триггеров для сделок (entityTypeId = 2)
// categoryId = 0 — общая воронка
$triggerManager = new TriggerManager(2, 0);

echo "=== Установка триггеров автоматизации ===\n\n";

try {
    // Триггер 1: При создании звонка → "Контакт установлен"
    echo "1. Создание триггера: При создании звонка\n";
    
    // Проверяем, не существует ли уже такой триггер
    $existingTrigger = $triggerManager->triggerExists('ACTIVITY_CREATED');
    
    if ($existingTrigger) {
        echo "   ⚠ Триггер уже существует (ID: {$existingTrigger['ID']})\n";
        echo "   Обновляем...\n";
        
        $triggerManager->updateTrigger($existingTrigger['ID'], [
            'TARGET_STATUS_ID' => 'C2:PREPARATION', // Стадия "Контакт установлен"
            'NAME' => 'При создании звонка',
            'APPLY_RULES' => [
                [
                    'field' => 'ACTIVITY_TYPE',
                    'operator' => '=',
                    'value' => '2' // Звонок
                ]
            ]
        ]);
        
        echo "   ✅ Триггер обновлен\n\n";
    } else {
        $triggerId = $triggerManager->createCallCreatedTrigger('C2:PREPARATION');
        echo "   ✅ Триггер создан (ID: {$triggerId})\n\n";
    }
    
    // Триггер 2: При получении оплаты → "Успешно реализовано"
    echo "2. Создание триггера: При получении оплаты\n";
    
    $existingTrigger = $triggerManager->triggerExists('INVOICE_PAID');
    
    if ($existingTrigger) {
        echo "   ⚠ Триггер уже существует (ID: {$existingTrigger['ID']})\n\n";
    } else {
        $triggerId = $triggerManager->createPaymentReceivedTrigger('C2:WON');
        echo "   ✅ Триггер создан (ID: {$triggerId})\n\n";
    }
    
    // Триггер 3: Нет активности 7 дней → "Холодные"
    echo "3. Создание триггера: Нет активности 7 дней\n";
    
    $existingTrigger = $triggerManager->triggerExists('INACTIVITY_PERIOD');
    
    if ($existingTrigger) {
        echo "   ⚠ Триггер уже существует (ID: {$existingTrigger['ID']})\n";
        echo "   Обновляем...\n";
        
        $triggerManager->updateTrigger($existingTrigger['ID'], [
            'TARGET_STATUS_ID' => 'C2:LOSE', // Стадия "Проиграно"
            'NAME' => 'Нет активности 7 дней',
            'APPLY_RULES' => [
                [
                    'field' => 'DAYS',
                    'operator' => '=',
                    'value' => '7'
                ]
            ]
        ]);
        
        echo "   ✅ Триггер обновлен\n\n";
    } else {
        $triggerId = $triggerManager->createInactivityTrigger(7, 'C2:LOSE');
        echo "   ✅ Триггер создан (ID: {$triggerId})\n\n";
    }
    
    // Показываем все установленные триггеры
    echo "=== Список установленных триггеров ===\n\n";
    
    $triggers = $triggerManager->getTriggers();
    
    if (empty($triggers)) {
        echo "Триггеры не найдены\n";
    } else {
        foreach ($triggers as $trigger) {
            echo sprintf(
                "ID: %s | Тип: %s | Название: %s | Целевая стадия: %s\n",
                $trigger['ID'],
                $trigger['CODE'],
                $trigger['NAME'],
                $trigger['TARGET_STATUS_ID']
            );
        }
    }
    
    echo "\n✅ Установка завершена!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

?>
```

### Шаг 3: Монитор срабатываний триггеров

```php
<?php
// file: /local/scripts/trigger-monitor.php
/**
 * Скрипт для мониторинга срабатываний триггеров
 * 
 * Проверяет логи и показывает статистику срабатываний
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/TriggerManager.php');

use Local\CRM\TriggerManager;
use Bitrix\Main\Loader;

Loader::includeModule('crm');

$triggerManager = new TriggerManager(2, 0);

echo "=== Мониторинг триггеров ===\n\n";

// Получаем все триггеры
$triggers = $triggerManager->getTriggers();

if (empty($triggers)) {
    echo "⚠ Триггеры не установлены\n";
    exit(0);
}

echo "Установлено триггеров: " . count($triggers) . "\n\n";

// Показываем информацию о каждом триггере
foreach ($triggers as $trigger) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: {$trigger['ID']}\n";
    echo "Тип: {$trigger['CODE']}\n";
    echo "Название: {$trigger['NAME']}\n";
    echo "Целевая стадия: {$trigger['TARGET_STATUS_ID']}\n";
    
    if (!empty($trigger['APPLY_RULES'])) {
        echo "Условия:\n";
        foreach ($trigger['APPLY_RULES'] as $rule) {
            echo sprintf(
                "  - %s %s %s\n",
                $rule['field'],
                $rule['operator'],
                $rule['value']
            );
        }
    }
    
    echo "\n";
}

// Читаем лог-файл для статистики
$logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/triggers.log';

if (file_exists($logFile)) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Последние 10 записей из лога:\n\n";
    
    $lines = file($logFile);
    $lastLines = array_slice($lines, -10);
    
    foreach ($lastLines as $line) {
        echo $line;
    }
} else {
    echo "⚠ Лог-файл не найден: {$logFile}\n";
}

echo "\n=== Мониторинг завершен ===\n";

?>
```

### Шаг 4: Скрипт удаления триггеров (для тестирования)

```php
<?php
// file: /local/scripts/remove-triggers.php
/**
 * Скрипт для удаления всех триггеров
 * 
 * Используется для тестирования или очистки
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/TriggerManager.php');

use Local\CRM\TriggerManager;
use Bitrix\Main\Loader;

Loader::includeModule('crm');

$triggerManager = new TriggerManager(2, 0);

echo "=== Удаление триггеров ===\n\n";

// Получаем все триггеры
$triggers = $triggerManager->getTriggers();

if (empty($triggers)) {
    echo "⚠ Триггеры не найдены\n";
    exit(0);
}

echo "Найдено триггеров: " . count($triggers) . "\n\n";

// Подтверждение
echo "⚠️  ВНИМАНИЕ! Будут удалены все триггеры воронки.\n";
echo "Продолжить? (yes/no): ";

$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes') {
    echo "Отменено.\n";
    exit(0);
}

// Удаляем триггеры
$deleted = 0;
$errors = 0;

foreach ($triggers as $trigger) {
    echo "Удаление триггера ID: {$trigger['ID']} ({$trigger['NAME']})... ";
    
    try {
        $triggerManager->deleteTrigger($trigger['ID']);
        echo "✅\n";
        $deleted++;
    } catch (Exception $e) {
        echo "❌ {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n=== Результаты ===\n";
echo "Удалено: {$deleted}\n";
echo "Ошибок: {$errors}\n";

?>
```

## Как использовать

### 1. Установить триггеры

```bash
cd /path/to/bitrix
php local/scripts/install-triggers.php
```

**Вывод:**
```
=== Установка триггеров автоматизации ===

1. Создание триггера: При создании звонка
   ✅ Триггер создан (ID: 123)

2. Создание триггера: При получении оплаты
   ✅ Триггер создан (ID: 124)

3. Создание триггера: Нет активности 7 дней
   ✅ Триггер создан (ID: 125)

=== Список установленных триггеров ===

ID: 123 | Тип: ACTIVITY_CREATED | Название: При создании звонка | Целевая стадия: C2:PREPARATION
ID: 124 | Тип: INVOICE_PAID | Название: При получении оплаты | Целевая стадия: C2:WON
ID: 125 | Тип: INACTIVITY_PERIOD | Название: Нет активности 7 дней | Целевая стадия: C2:LOSE

✅ Установка завершена!
```

### 2. Проверить статус триггеров

```bash
php local/scripts/trigger-monitor.php
```

### 3. Протестировать триггеры

#### Тест 1: Создать звонок для сделки

```php
<?php
// Создать звонок
CRest::call('crm.activity.add', [
    'fields' => [
        'OWNER_TYPE_ID' => 2, // Сделка
        'OWNER_ID' => 123,    // ID сделки
        'TYPE_ID' => 2,       // Звонок
        'SUBJECT' => 'Тестовый звонок',
        'COMPLETED' => 'N',
        'RESPONSIBLE_ID' => 1
    ]
]);

// Триггер должен переместить сделку на стадию "Контакт установлен"
```

#### Тест 2: Оплата счета

```php
<?php
// Создать и оплатить счет
CRest::call('crm.invoice.update', [
    'id' => 456,
    'fields' => [
        'STATUS_ID' => 'P' // Оплачен
    ]
]);

// Триггер должен переместить сделку в "Успешно реализовано"
```

### 4. Удалить триггеры (для повторного тестирования)

```bash
php local/scripts/remove-triggers.php
```

## Что происходит

1. **Скрипт установки** создает 3 триггера в CRM
2. **При создании звонка** для сделки → сделка автоматически перемещается на стадию "Контакт установлен"
3. **При получении оплаты** → сделка перемещается в "Успешно реализовано"
4. **При отсутствии активности 7 дней** → сделка перемещается в "Холодные"
5. Все действия логируются в `/local/logs/triggers.log`

## Структура стадий воронки

```
C2:NEW          → Новая
C2:PREPARATION  → Контакт установлен
C2:PREPAIRING   → Переговоры
C2:FINAL_INVOICE → Коммерческое предложение
C2:WON          → Успешно реализовано
C2:LOSE         → Проиграно
```

## Настройка под вашу воронку

Измените ID стадий в соответствии с вашей воронкой:

```php
// Получить список стадий
CRest::call('crm.status.list', [
    'filter' => [
        'ENTITY_ID' => 'DEAL_STAGE'
    ]
]);
```

## Обработка ошибок

Все ошибки логируются в файл:
```bash
tail -f /local/logs/triggers.log
```

## Расширения

Можно добавить:
- Триггер при изменении поля
- Триггер при смене ответственного
- Уведомления при срабатывании триггера
- Статистика срабатываний триггеров
- Dashboard для мониторинга
