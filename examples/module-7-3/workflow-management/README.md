# Задача 3: Запуск и управление бизнес-процессом

## Описание

Нужно создать систему для запуска, мониторинга и управления бизнес-процессами через REST API.

## Требования

- Запуск бизнес-процесса для сделки с параметрами
- Отслеживание статуса выполнения БП
- Получение списка активных бизнес-процессов
- Остановка/завершение бизнес-процесса
- Получение истории выполнения

## Решение

### Шаг 1: Класс управления бизнес-процессами

```php
<?php
// file: /local/lib/WorkflowManager.php

namespace Local\CRM;

use Bitrix\Main\Loader;

/**
 * Класс для управления бизнес-процессами (Workflow)
 */
class WorkflowManager {
    
    private $logFile;
    
    public function __construct() {
        $this->logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/workflows.log';
        Loader::includeModule('crm');
        Loader::includeModule('bizproc');
    }
    
    /**
     * Запустить бизнес-процесс для сделки
     * 
     * @param int $templateId ID шаблона бизнес-процесса
     * @param int $dealId ID сделки
     * @param array $parameters Параметры для запуска
     * @return string ID запущенного процесса
     */
    public function startWorkflow($templateId, $dealId, $parameters = []) {
        try {
            $this->log('Запуск бизнес-процесса', [
                'template_id' => $templateId,
                'deal_id' => $dealId,
                'parameters' => $parameters
            ]);
            
            $result = \CRest::call('bizproc.workflow.start', [
                'TEMPLATE_ID' => $templateId,
                'DOCUMENT_ID' => ['crm', 'CCrmDocumentDeal', $dealId],
                'PARAMETERS' => $parameters
            ]);
            
            if (isset($result['result'])) {
                $workflowId = $result['result'];
                
                $this->log('Бизнес-процесс запущен', [
                    'workflow_id' => $workflowId,
                    'deal_id' => $dealId
                ]);
                
                return $workflowId;
            }
            
            throw new \Exception('Ошибка запуска: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка запуска бизнес-процесса', [
                'template_id' => $templateId,
                'deal_id' => $dealId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Получить список запущенных бизнес-процессов для сделки
     * 
     * @param int $dealId ID сделки
     * @return array Список процессов
     */
    public function getWorkflowInstances($dealId) {
        try {
            $result = \CRest::call('bizproc.workflow.instances', [
                'DOCUMENT_ID' => ['crm', 'CCrmDocumentDeal', $dealId]
            ]);
            
            if (isset($result['result'])) {
                return $result['result'];
            }
            
            throw new \Exception('Ошибка получения процессов: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка получения процессов', [
                'deal_id' => $dealId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Получить информацию о бизнес-процессе
     * 
     * @param string $workflowId ID процесса
     * @return array Информация о процессе
     */
    public function getWorkflowInfo($workflowId) {
        try {
            $result = \CRest::call('bizproc.workflow.instance', [
                'ID' => $workflowId
            ]);
            
            if (isset($result['result'])) {
                return $result['result'];
            }
            
            throw new \Exception('Процесс не найден: ' . $workflowId);
            
        } catch (\Exception $e) {
            $this->log('Ошибка получения информации о процессе', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Остановить бизнес-процесс
     * 
     * @param string $workflowId ID процесса
     * @param string $status Статус завершения (Terminated, Completed, Errors)
     * @param string $message Причина остановки
     * @return bool
     */
    public function terminateWorkflow($workflowId, $status = 'Terminated', $message = '') {
        try {
            $this->log('Остановка бизнес-процесса', [
                'workflow_id' => $workflowId,
                'status' => $status,
                'message' => $message
            ]);
            
            $result = \CRest::call('bizproc.workflow.terminate', [
                'WORKFLOW_ID' => $workflowId,
                'STATUS' => $status,
                'MESSAGE' => $message
            ]);
            
            if ($result['result']) {
                $this->log('Бизнес-процесс остановлен', [
                    'workflow_id' => $workflowId
                ]);
                
                return true;
            }
            
            throw new \Exception('Ошибка остановки: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка остановки процесса', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Получить список всех шаблонов бизнес-процессов
     * 
     * @return array Список шаблонов
     */
    public function getTemplates() {
        try {
            $result = \CRest::call('bizproc.workflow.template.list', [
                'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL']
            ]);
            
            if (isset($result['result'])) {
                return $result['result'];
            }
            
            throw new \Exception('Ошибка получения шаблонов: ' . json_encode($result));
            
        } catch (\Exception $e) {
            $this->log('Ошибка получения шаблонов', [
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Проверить статус выполнения бизнес-процесса
     * 
     * @param string $workflowId ID процесса
     * @return string Статус (Running, Completed, Terminated, etc.)
     */
    public function getWorkflowStatus($workflowId) {
        try {
            $info = $this->getWorkflowInfo($workflowId);
            return $info['STATE'] ?? 'Unknown';
            
        } catch (\Exception $e) {
            $this->log('Ошибка получения статуса', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Получить историю выполнения бизнес-процесса
     * 
     * @param string $workflowId ID процесса
     * @return array История выполнения
     */
    public function getWorkflowHistory($workflowId) {
        try {
            // Получаем детальную информацию о процессе
            $info = $this->getWorkflowInfo($workflowId);
            
            // Формируем историю
            $history = [
                'workflow_id' => $workflowId,
                'template_id' => $info['TEMPLATE_ID'] ?? null,
                'document_id' => $info['DOCUMENT_ID'] ?? null,
                'started' => $info['STARTED'] ?? null,
                'finished' => $info['FINISHED'] ?? null,
                'status' => $info['STATE'] ?? 'Unknown',
                'started_by' => $info['STARTED_BY'] ?? null,
                'events' => []
            ];
            
            // Если процесс завершен, получаем время выполнения
            if (isset($info['STARTED']) && isset($info['FINISHED'])) {
                $start = strtotime($info['STARTED']);
                $end = strtotime($info['FINISHED']);
                $history['duration_seconds'] = $end - $start;
            }
            
            return $history;
            
        } catch (\Exception $e) {
            $this->log('Ошибка получения истории', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Запустить бизнес-процесс согласования сделки
     * 
     * @param int $dealId ID сделки
     * @param int $approverId ID согласующего
     * @param string $comment Комментарий
     * @return string ID процесса
     */
    public function startApprovalWorkflow($dealId, $approverId, $comment = '') {
        // Находим шаблон процесса согласования
        $templates = $this->getTemplates();
        $approvalTemplateId = null;
        
        foreach ($templates as $template) {
            // Ищем шаблон с названием "Согласование сделки"
            if (stripos($template['NAME'], 'Согласование') !== false) {
                $approvalTemplateId = $template['ID'];
                break;
            }
        }
        
        if (!$approvalTemplateId) {
            throw new \Exception('Шаблон согласования не найден');
        }
        
        // Запускаем процесс
        return $this->startWorkflow($approvalTemplateId, $dealId, [
            'approver_id' => $approverId,
            'comment' => $comment
        ]);
    }
    
    /**
     * Получить статистику по бизнес-процессам
     * 
     * @return array Статистика
     */
    public function getStatistics() {
        try {
            // Получаем все шаблоны
            $templates = $this->getTemplates();
            
            $stats = [
                'total_templates' => count($templates),
                'templates' => []
            ];
            
            foreach ($templates as $template) {
                $stats['templates'][] = [
                    'id' => $template['ID'],
                    'name' => $template['NAME'],
                    'active' => $template['ACTIVE'] ?? 'N',
                    'auto_execute' => $template['AUTO_EXECUTE'] ?? '0'
                ];
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            $this->log('Ошибка получения статистики', [
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
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

### Шаг 2: Скрипт запуска бизнес-процесса

```php
<?php
// file: /local/scripts/start-workflow.php
/**
 * Скрипт для запуска бизнес-процесса
 * 
 * Запускает БП для указанной сделки с параметрами
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/WorkflowManager.php');

use Local\CRM\WorkflowManager;
use Bitrix\Main\Loader;

Loader::includeModule('crm');

// Параметры запуска (можно передать через CLI)
$dealId = isset($argv[1]) ? (int)$argv[1] : null;
$templateId = isset($argv[2]) ? (int)$argv[2] : null;

if (!$dealId) {
    echo "Использование: php start-workflow.php <deal_id> [template_id]\n";
    exit(1);
}

$workflowManager = new WorkflowManager();

echo "=== Запуск бизнес-процесса ===\n\n";

try {
    // Если не указан ID шаблона, показываем список
    if (!$templateId) {
        echo "Доступные шаблоны:\n\n";
        
        $templates = $workflowManager->getTemplates();
        
        if (empty($templates)) {
            echo "⚠ Шаблоны не найдены\n";
            exit(1);
        }
        
        foreach ($templates as $template) {
            echo sprintf(
                "ID: %s | Название: %s | Активен: %s\n",
                $template['ID'],
                $template['NAME'],
                $template['ACTIVE'] === 'Y' ? '✅' : '❌'
            );
        }
        
        echo "\nВыберите ID шаблона: ";
        $handle = fopen("php://stdin", "r");
        $templateId = (int)trim(fgets($handle));
        fclose($handle);
    }
    
    // Получаем информацию о сделке
    echo "\nПолучение данных сделки #{$dealId}...\n";
    
    $dealData = \CRest::call('crm.deal.get', ['ID' => $dealId]);
    
    if (!$dealData['result']) {
        throw new \Exception("Сделка #{$dealId} не найдена");
    }
    
    $deal = $dealData['result'];
    
    echo "Сделка: {$deal['TITLE']}\n";
    echo "Сумма: {$deal['OPPORTUNITY']} руб.\n";
    echo "Стадия: {$deal['STAGE_ID']}\n\n";
    
    // Параметры для бизнес-процесса
    $parameters = [
        'deal_title' => $deal['TITLE'],
        'deal_amount' => $deal['OPPORTUNITY'],
        'responsible_id' => $deal['ASSIGNED_BY_ID']
    ];
    
    echo "Запуск бизнес-процесса...\n";
    
    $workflowId = $workflowManager->startWorkflow($templateId, $dealId, $parameters);
    
    echo "✅ Бизнес-процесс запущен!\n\n";
    echo "ID процесса: {$workflowId}\n";
    echo "Сделка: #{$dealId}\n";
    echo "Шаблон: #{$templateId}\n\n";
    
    // Получаем начальный статус
    $status = $workflowManager->getWorkflowStatus($workflowId);
    echo "Статус: {$status}\n";
    
    echo "\n🔍 Для мониторинга выполните:\n";
    echo "php local/scripts/monitor-workflow.php {$workflowId}\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

?>
```

### Шаг 3: Скрипт мониторинга бизнес-процесса

```php
<?php
// file: /local/scripts/monitor-workflow.php
/**
 * Скрипт для мониторинга выполнения бизнес-процесса
 * 
 * Показывает текущий статус и историю выполнения
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/WorkflowManager.php');

use Local\CRM\WorkflowManager;
use Bitrix\Main\Loader;

Loader::includeModule('crm');

$workflowId = isset($argv[1]) ? $argv[1] : null;

if (!$workflowId) {
    echo "Использование: php monitor-workflow.php <workflow_id>\n";
    exit(1);
}

$workflowManager = new WorkflowManager();

echo "=== Мониторинг бизнес-процесса ===\n\n";

try {
    // Получаем информацию о процессе
    $info = $workflowManager->getWorkflowInfo($workflowId);
    
    echo "ID процесса: {$workflowId}\n";
    echo "Шаблон: #{$info['TEMPLATE_ID']}\n";
    echo "Запущен: {$info['STARTED']}\n";
    echo "Статус: {$info['STATE']}\n";
    
    if (isset($info['FINISHED'])) {
        echo "Завершен: {$info['FINISHED']}\n";
        
        $start = strtotime($info['STARTED']);
        $end = strtotime($info['FINISHED']);
        $duration = $end - $start;
        
        echo "Длительность: {$duration} сек.\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Получаем историю
    $history = $workflowManager->getWorkflowHistory($workflowId);
    
    echo "История выполнения:\n\n";
    echo "Запущен: {$history['started']}\n";
    
    if ($history['finished']) {
        echo "Завершен: {$history['finished']}\n";
        echo "Продолжительность: {$history['duration_seconds']} сек.\n";
    } else {
        echo "Статус: В процессе выполнения\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Если процесс еще выполняется, предлагаем остановить
    if ($info['STATE'] === 'Running') {
        echo "⚠️  Процесс выполняется. Остановить? (yes/no): ";
        
        $handle = fopen("php://stdin", "r");
        $confirmation = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($confirmation) === 'yes') {
            echo "Причина остановки: ";
            $reason = trim(fgets(STDIN));
            
            $workflowManager->terminateWorkflow($workflowId, 'Terminated', $reason);
            echo "✅ Процесс остановлен\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

?>
```

### Шаг 4: Скрипт получения списка активных процессов

```php
<?php
// file: /local/scripts/list-workflows.php
/**
 * Скрипт для получения списка всех активных бизнес-процессов
 * 
 * Показывает процессы по всем сделкам или для конкретной сделки
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/WorkflowManager.php');

use Local\CRM\WorkflowManager;
use Bitrix\Main\Loader;

Loader::includeModule('crm');

$dealId = isset($argv[1]) ? (int)$argv[1] : null;

$workflowManager = new WorkflowManager();

echo "=== Активные бизнес-процессы ===\n\n";

try {
    if ($dealId) {
        // Процессы для конкретной сделки
        echo "Сделка: #{$dealId}\n\n";
        
        $instances = $workflowManager->getWorkflowInstances($dealId);
        
        if (empty($instances)) {
            echo "⚠ Процессы не найдены\n";
            exit(0);
        }
        
        foreach ($instances as $instance) {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "ID: {$instance['ID']}\n";
            echo "Шаблон: #{$instance['TEMPLATE_ID']}\n";
            echo "Статус: {$instance['STATE']}\n";
            echo "Запущен: {$instance['STARTED']}\n";
            
            if (isset($instance['FINISHED'])) {
                echo "Завершен: {$instance['FINISHED']}\n";
            }
            
            echo "\n";
        }
        
    } else {
        // Статистика по всем процессам
        echo "Статистика по бизнес-процессам:\n\n";
        
        $stats = $workflowManager->getStatistics();
        
        echo "Всего шаблонов: {$stats['total_templates']}\n\n";
        
        if (!empty($stats['templates'])) {
            echo "Шаблоны:\n";
            foreach ($stats['templates'] as $template) {
                echo sprintf(
                    "  - ID: %s | %s | Активен: %s | Автозапуск: %s\n",
                    $template['id'],
                    $template['name'],
                    $template['active'] === 'Y' ? 'Да' : 'Нет',
                    $template['auto_execute'] === '1' ? 'Да' : 'Нет'
                );
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

?>
```

### Шаг 5: API endpoint для внешних систем

```php
<?php
// file: /local/api/workflow-api.php
/**
 * REST API endpoint для управления бизнес-процессами
 * 
 * Позволяет внешним системам запускать и контролировать БП
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/WorkflowManager.php');

use Local\CRM\WorkflowManager;
use Bitrix\Main\Loader;

header('Content-Type: application/json');

Loader::includeModule('crm');

$workflowManager = new WorkflowManager();

// Получаем метод и данные
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'POST':
            // Запуск нового процесса
            if (!isset($input['deal_id']) || !isset($input['template_id'])) {
                throw new \Exception('Missing required fields: deal_id, template_id');
            }
            
            $workflowId = $workflowManager->startWorkflow(
                $input['template_id'],
                $input['deal_id'],
                $input['parameters'] ?? []
            );
            
            echo json_encode([
                'success' => true,
                'workflow_id' => $workflowId,
                'message' => 'Workflow started successfully'
            ]);
            break;
            
        case 'GET':
            // Получение информации о процессе
            if (!isset($_GET['workflow_id'])) {
                throw new \Exception('Missing workflow_id parameter');
            }
            
            $info = $workflowManager->getWorkflowInfo($_GET['workflow_id']);
            
            echo json_encode([
                'success' => true,
                'data' => $info
            ]);
            break;
            
        case 'DELETE':
            // Остановка процесса
            if (!isset($input['workflow_id'])) {
                throw new \Exception('Missing workflow_id');
            }
            
            $workflowManager->terminateWorkflow(
                $input['workflow_id'],
                $input['status'] ?? 'Terminated',
                $input['message'] ?? 'Stopped via API'
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Workflow terminated successfully'
            ]);
            break;
            
        default:
            throw new \Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
```

## Как использовать

### 1. Запустить бизнес-процесс для сделки

```bash
# Запуск с выбором шаблона
php local/scripts/start-workflow.php 123

# Запуск с указанием шаблона
php local/scripts/start-workflow.php 123 456
```

### 2. Мониторить выполнение процесса

```bash
php local/scripts/monitor-workflow.php abc123xyz
```

### 3. Получить список активных процессов

```bash
# Для конкретной сделки
php local/scripts/list-workflows.php 123

# Общая статистика
php local/scripts/list-workflows.php
```

### 4. Использовать API из внешней системы

#### Запустить процесс
```bash
curl -X POST https://yourdomain.com/local/api/workflow-api.php \
  -H "Content-Type: application/json" \
  -d '{
    "deal_id": 123,
    "template_id": 456,
    "parameters": {
      "approver_id": 10,
      "comment": "Требуется согласование"
    }
  }'
```

**Ответ:**
```json
{
  "success": true,
  "workflow_id": "abc123xyz",
  "message": "Workflow started successfully"
}
```

#### Получить статус
```bash
curl -X GET "https://yourdomain.com/local/api/workflow-api.php?workflow_id=abc123xyz"
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "ID": "abc123xyz",
    "TEMPLATE_ID": "456",
    "STATE": "Running",
    "STARTED": "2024-01-15 10:30:00",
    "DOCUMENT_ID": ["crm", "CCrmDocumentDeal", "123"]
  }
}
```

#### Остановить процесс
```bash
curl -X DELETE https://yourdomain.com/local/api/workflow-api.php \
  -H "Content-Type: application/json" \
  -d '{
    "workflow_id": "abc123xyz",
    "status": "Terminated",
    "message": "Процесс больше не актуален"
  }'
```

## Примеры использования в коде

### Запуск процесса согласования

```php
<?php
use Local\CRM\WorkflowManager;

$workflowManager = new WorkflowManager();

// Запустить процесс согласования для сделки
$workflowId = $workflowManager->startApprovalWorkflow(
    123,  // ID сделки
    10,   // ID согласующего
    'Требуется согласование сделки на 500 000 руб.'
);

echo "Процесс согласования запущен: {$workflowId}";
?>
```

### Проверка статуса процесса

```php
<?php
use Local\CRM\WorkflowManager;

$workflowManager = new WorkflowManager();

$workflowId = 'abc123xyz';
$status = $workflowManager->getWorkflowStatus($workflowId);

if ($status === 'Completed') {
    echo "Процесс успешно завершен";
} elseif ($status === 'Running') {
    echo "Процесс еще выполняется";
} else {
    echo "Статус: {$status}";
}
?>
```

### Остановка всех процессов сделки

```php
<?php
use Local\CRM\WorkflowManager;

$workflowManager = new WorkflowManager();

$dealId = 123;
$instances = $workflowManager->getWorkflowInstances($dealId);

foreach ($instances as $instance) {
    if ($instance['STATE'] === 'Running') {
        $workflowManager->terminateWorkflow(
            $instance['ID'],
            'Terminated',
            'Автоматическая остановка при закрытии сделки'
        );
    }
}

echo "Остановлено процессов: " . count($instances);
?>
```

## Что происходит

1. **Скрипт start-workflow.php** запускает бизнес-процесс для сделки
2. **Скрипт monitor-workflow.php** показывает статус и историю выполнения
3. **API endpoint** позволяет внешним системам управлять процессами
4. Все операции логируются в `/local/logs/workflows.log`

## Статусы бизнес-процессов

- `Running` — выполняется
- `Completed` — успешно завершен
- `Terminated` — принудительно остановлен
- `Errors` — завершен с ошибками

## Обработка ошибок

Все ошибки логируются:
```bash
tail -f /local/logs/workflows.log
```

## Безопасность

- API endpoint должен быть защищен аутентификацией
- Проверяйте права пользователя перед запуском БП
- Логируйте все операции с процессами
- Используйте HTTPS для API запросов

## Расширения

Можно добавить:
- Dashboard для визуализации процессов
- Email уведомления о завершении процессов
- Retry логику для неудачных запусков
- Экспорт истории выполнения в CSV/Excel
- Webhooks при изменении статуса процесса
