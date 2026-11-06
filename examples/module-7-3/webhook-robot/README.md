# Задача 1: Webhook-робот для отправки данных сделки

## Описание

Нужно создать пользовательского робота, который будет отправлять данные сделки в смарт-систему (AmoCRM, своя CRM и т.д.) через webhook.

## Требования

- Робот должен срабатывать при входе на стадию "Переговоры"
- Отправлять данные: ID сделки, сумма, клиент, ответственный
- Получать ответ из внешней системы и сохранять ID интеграции
- Обрабатывать ошибки и логировать все операции

## Решение

### Шаг 1: Регистрация робота в Bitrix24

Код для регистрации пользовательского робота:

```php
<?php
// file: register-robot.php

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Подключаем SDK Bitrix24
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/RestAPI.php');

use Bitrix\Main\Loader;
use Bitrix\Rest;

Loader::includeModule('crm');
Loader::includeModule('rest');

// Параметры для регистрации робота
$params = [
    'CODE' => 'send_to_external_crm',
    'HANDLER' => 'https://' . $_SERVER['HTTP_HOST'] . '/local/robot-handler.php',
    'AUTH_USER_ID' => 1, // Администратор
    'NAME' => 'Отправить данные сделки в CRM',
    'DESCRIPTION' => 'Автоматически отправляет данные в интегрированную CRM',
    'PROPERTIES' => [
        'external_url' => [
            'Name' => 'URL внешней CRM',
            'Type' => 'text',
            'Required' => 'Y',
            'Default' => 'https://api.external-crm.com/v1/deals'
        ],
        'api_key' => [
            'Name' => 'API ключ',
            'Type' => 'text',
            'Required' => 'Y'
        ],
        'sync_contacts' => [
            'Name' => 'Синхронизировать контакты',
            'Type' => 'checkbox',
            'Default' => 'Y'
        ]
    ],
    'RETURN_PROPERTIES' => [
        'external_id' => [
            'Name' => 'ID в CRM',
            'Type' => 'string'
        ],
        'sync_status' => [
            'Name' => 'Статус синхронизации',
            'Type' => 'string'
        ],
        'sync_time' => [
            'Name' => 'Время синхронизации',
            'Type' => 'datetime'
        ]
    ]
];

// Отправляем запрос для регистрации
$response = CRest::call('bizproc.robot.add', $params);

if ($response['result']) {
    echo "Робот успешно зарегистрирован!";
    echo "<pre>";
    var_dump($response);
    echo "</pre>";
} else {
    echo "Ошибка регистрации:";
    var_dump($response);
}
?>
```

### Шаг 2: Обработчик webhook

Основной файл обработчика, который будет получать запросы от Bitrix24:

```php
<?php
// file: /local/robot-handler.php
/**
 * Webhook-обработчик для робота отправки данных в CRM
 * 
 * Получает данные сделки из Bitrix24 и отправляет их в интегрированную CRM
 */

// Логирование запроса
function logRequest($data, $type = 'request') {
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/robot-crm-sync.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Получаем данные от Bitrix24
$input = json_decode(file_get_contents('php://input'), true);
logRequest($input, 'incoming');

// Проверяем корректность запроса
if (empty($input['auth']) || empty($input['document_id'])) {
    logRequest(['error' => 'Invalid request structure'], 'error');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'INVALID_REQUEST',
        'error_description' => 'Некорректная структура запроса'
    ]);
    exit;
}

// Извлекаем данные
$auth = $input['auth'];
$dealId = $input['document_id'][2];
$properties = $input['properties'];

try {
    // Получаем полные данные сделки из Bitrix24
    $dealData = CRest::call('crm.deal.get', [
        'ID' => $dealId
    ]);
    
    if (!$dealData['result'] || empty($dealData['result']['ID'])) {
        throw new Exception('Deal not found: ' . $dealId);
    }
    
    $deal = $dealData['result'];
    
    // Получаем данные контакта (если нужны)
    $contact = [];
    if ($properties['sync_contacts'] === 'Y' && !empty($deal['CONTACT_ID'])) {
        $contactData = CRest::call('crm.contact.get', [
            'ID' => $deal['CONTACT_ID']
        ]);
        if ($contactData['result']) {
            $contact = $contactData['result'];
        }
    }
    
    // Получаем данные компании
    $company = [];
    if (!empty($deal['COMPANY_ID'])) {
        $companyData = CRest::call('crm.company.get', [
            'ID' => $deal['COMPANY_ID']
        ]);
        if ($companyData['result']) {
            $company = $companyData['result'];
        }
    }
    
    // Получаем ответственного пользователя
    $responsible = [];
    if (!empty($deal['ASSIGNED_BY_ID'])) {
        $userData = CRest::call('user.get', [
            'ID' => $deal['ASSIGNED_BY_ID']
        ]);
        if ($userData['result']) {
            $responsible = $userData['result'][0];
        }
    }
    
    // Подготавливаем данные для отправки во внешнюю CRM
    $payloadForExternalCRM = [
        'bitrix_deal_id' => (int)$dealId,
        'title' => $deal['TITLE'],
        'amount' => (float)$deal['OPPORTUNITY'],
        'stage' => $deal['STAGE_ID'],
        'probability' => (int)$deal['PROBABILITY'],
        'date_create' => $deal['DATE_CREATE'],
        'date_close_plan' => $deal['CLOSEDATE'],
        'description' => $deal['DESCRIPTION'],
        'contact' => [
            'id' => $contact['ID'] ?? null,
            'name' => $contact['NAME'] ?? '',
            'last_name' => $contact['LAST_NAME'] ?? '',
            'phone' => $contact['PHONE'][0]['VALUE'] ?? '',
            'email' => $contact['EMAIL'][0]['VALUE'] ?? ''
        ],
        'company' => [
            'id' => $company['ID'] ?? null,
            'name' => $company['TITLE'] ?? ''
        ],
        'responsible' => [
            'id' => $responsible['ID'] ?? null,
            'name' => $responsible['NAME'] ?? '',
            'last_name' => $responsible['LAST_NAME'] ?? '',
            'email' => $responsible['EMAIL'] ?? ''
        ]
    ];
    
    logRequest($payloadForExternalCRM, 'payload_prepared');
    
    // Отправляем в интегрированную CRM
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $properties['external_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payloadForExternalCRM),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $properties['api_key'],
            'User-Agent: Bitrix24-Robot/1.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('cURL Error: ' . $curlError);
    }
    
    $externalResponse = json_decode($response, true);
    logRequest(['http_code' => $httpCode, 'response' => $externalResponse], 'external_response');
    
    // Проверяем статус ответа
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('External API returned: ' . $httpCode . ' - ' . $response);
    }
    
    if (!isset($externalResponse['success']) || !$externalResponse['success']) {
        throw new Exception('External API error: ' . json_encode($externalResponse));
    }
    
    // Сохраняем ID из внешней CRM в пользовательское поле Bitrix24
    $externalId = $externalResponse['data']['id'] ?? null;
    
    if ($externalId) {
        // Обновляем поле с ID из внешней CRM
        CRest::call('crm.deal.update', [
            'ID' => $dealId,
            'UF_CRM_EXTERNAL_ID' => $externalId,
            'UF_CRM_SYNC_STATUS' => 'synced',
            'UF_CRM_SYNC_TIME' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Возвращаем успешный результат Bitrix24
    $response = [
        'success' => true,
        'return_values' => [
            'external_id' => (string)$externalId,
            'sync_status' => 'success',
            'sync_time' => date('Y-m-d H:i:s')
        ]
    ];
    
    logRequest($response, 'success_response');
    echo json_encode($response);
    
} catch (Exception $e) {
    $errorResponse = [
        'success' => false,
        'error' => 'SYNC_ERROR',
        'error_description' => $e->getMessage(),
        'return_values' => [
            'sync_status' => 'error',
            'sync_time' => date('Y-m-d H:i:s')
        ]
    ];
    
    logRequest($errorResponse, 'error');
    
    // Обновляем статус ошибки в Bitrix24
    try {
        CRest::call('crm.deal.update', [
            'ID' => $dealId,
            'UF_CRM_SYNC_STATUS' => 'error: ' . $e->getMessage(),
            'UF_CRM_SYNC_TIME' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        // Игнорируем ошибки обновления
    }
    
    http_response_code(500);
    echo json_encode($errorResponse);
}

?>
```

### Шаг 3: Класс для управления синхронизацией

Вспомогательный класс для работы с синхронизацией:

```php
<?php
// file: /local/lib/ExternalCRMSync.php

namespace Local\CRM;

use Bitrix\Main\Loader;

/**
 * Класс для управления синхронизацией с внешней CRM
 */
class ExternalCRMSync {
    
    private $apiUrl;
    private $apiKey;
    private $logFile;
    
    public function __construct($apiUrl, $apiKey) {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/crm-sync.log';
    }
    
    /**
     * Отправить сделку во внешнюю CRM
     */
    public function sendDeal($dealId, $syncContacts = true) {
        Loader::includeModule('crm');
        
        try {
            // Получаем данные сделки
            $dealData = \CRest::call('crm.deal.get', ['ID' => $dealId]);
            if (!$dealData['result']) {
                throw new \Exception("Deal {$dealId} not found");
            }
            
            $deal = $dealData['result'];
            
            // Подготавливаем payload
            $payload = $this->prepareDealPayload($deal, $syncContacts);
            
            // Отправляем запрос
            $response = $this->sendRequest($payload);
            
            // Сохраняем результат в Bitrix24
            $this->saveSyncResult($dealId, $response);
            
            $this->log('Сделка успешно синхронизирована', [
                'deal_id' => $dealId,
                'external_id' => $response['data']['id'] ?? null
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->log('Ошибка синхронизации сделки', [
                'deal_id' => $dealId,
                'error' => $e->getMessage()
            ], 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Подготовить данные сделки для отправки
     */
    private function prepareDealPayload($deal, $syncContacts = true) {
        Loader::includeModule('crm');
        
        $payload = [
            'bitrix_deal_id' => (int)$deal['ID'],
            'title' => $deal['TITLE'],
            'amount' => (float)$deal['OPPORTUNITY'],
            'stage' => $deal['STAGE_ID'],
            'probability' => (int)$deal['PROBABILITY'],
            'date_create' => $deal['DATE_CREATE'],
            'date_close' => $deal['CLOSEDATE'],
            'description' => $deal['DESCRIPTION']
        ];
        
        // Получаем контакт
        if ($syncContacts && $deal['CONTACT_ID']) {
            $contactData = \CRest::call('crm.contact.get', [
                'ID' => $deal['CONTACT_ID']
            ]);
            
            if ($contactData['result']) {
                $contact = $contactData['result'];
                $payload['contact'] = [
                    'id' => $contact['ID'],
                    'name' => $contact['NAME'],
                    'last_name' => $contact['LAST_NAME'],
                    'phone' => $contact['PHONE'][0]['VALUE'] ?? '',
                    'email' => $contact['EMAIL'][0]['VALUE'] ?? ''
                ];
            }
        }
        
        // Получаем компанию
        if ($deal['COMPANY_ID']) {
            $companyData = \CRest::call('crm.company.get', [
                'ID' => $deal['COMPANY_ID']
            ]);
            
            if ($companyData['result']) {
                $company = $companyData['result'];
                $payload['company'] = [
                    'id' => $company['ID'],
                    'name' => $company['TITLE']
                ];
            }
        }
        
        return $payload;
    }
    
    /**
     * Отправить HTTP запрос во внешнюю CRM
     */
    private function sendRequest($payload) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('cURL Error: ' . $error);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception("HTTP {$httpCode}: {$response}");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        if (!isset($data['success']) || !$data['success']) {
            throw new \Exception('API Error: ' . ($data['message'] ?? 'Unknown error'));
        }
        
        return $data;
    }
    
    /**
     * Сохранить результат синхронизации в Bitrix24
     */
    private function saveSyncResult($dealId, $response) {
        Loader::includeModule('crm');
        
        $externalId = $response['data']['id'] ?? null;
        
        \CRest::call('crm.deal.update', [
            'ID' => $dealId,
            'UF_CRM_EXTERNAL_ID' => (string)$externalId,
            'UF_CRM_SYNC_STATUS' => 'synced',
            'UF_CRM_SYNC_TIME' => date('Y-m-d H:i:s')
        ]);
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

## Как использовать

### 1. Зарегистрировать робота (один раз)

```bash
# Вызвать регистрацию робота
php /local/register-robot.php
```

### 2. Убедиться, что обработчик доступен

- Проверить, что файл `/local/robot-handler.php` доступен по HTTP(S)
- Убедиться, что URL правильный: `https://yourdomain.com/local/robot-handler.php`

### 3. Настроить робота в Bitrix24

- Перейти в CRM → Настройки → Автоматизация → Роботы
- Найти робота "Отправить данные сделки в CRM"
- Добавить его на стадию "Переговоры"
- Заполнить параметры:
  - URL внешней CRM: `https://api.external-crm.com/v1/deals`
  - API ключ: ваш ключ от внешней CRM
  - Синхронизировать контакты: Да

### 4. Проверить логи

```bash
tail -f /local/logs/robot-crm-sync.log
```

## Что происходит

1. Сделка переместилась на стадию "Переговоры"
2. Робот автоматически сработал
3. Данные сделки отправлены во внешнюю CRM
4. ID из внешней CRM сохранен в поле `UF_CRM_EXTERNAL_ID`
5. Статус синхронизации установлен в "synced"

## Обработка ошибок

Если робот не сработал:
- Проверить логи: `/local/logs/robot-crm-sync.log`
- Проверить доступность webhook URL
- Проверить параметры робота (URL, API ключ)
- Проверить лог ошибок CRM

## Безопасность

- API ключ хранится в параметрах робота (защищено Bitrix24)
- Все запросы логируются
- Используется HTTPS для связи с внешней CRM
- Timeout установлен на 10 секунд для защиты от зависаний

## Расширения

Можно добавить:
- Синхронизацию активностей (звонки, встречи, письма)
- Двусторонний синк (обновление из внешней CRM)
- Обработка разных статусов ошибок
- Retry логика для неудачных синхронизаций
