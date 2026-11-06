<?php
/**
 * Клиент для работы с Bitrix24 REST API
 *
 * Упрощает взаимодействие с API Bitrix24
 */

class Bitrix24Client
{
    private $webhookUrl;
    private $timeout = 30;

    public function __construct()
    {
        $this->webhookUrl = BITRIX24_WEBHOOK_URL;

        if (empty($this->webhookUrl)) {
            throw new Exception('BITRIX24_WEBHOOK_URL не настроен');
        }
    }

    /**
     * Создание сделки
     */
    public function createDeal($fields)
    {
        $response = $this->call('crm.deal.add', [
            'fields' => $fields
        ]);

        if (isset($response['result'])) {
            return [
                'success' => true,
                'id' => $response['result']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error_description'] ?? 'Unknown error'
        ];
    }

    /**
     * Обновление сделки
     */
    public function updateDeal($dealId, $fields)
    {
        $response = $this->call('crm.deal.update', [
            'id' => $dealId,
            'fields' => $fields
        ]);

        return [
            'success' => isset($response['result']) && $response['result'] === true,
            'error' => $response['error_description'] ?? null
        ];
    }

    /**
     * Получение сделки
     */
    public function getDeal($dealId)
    {
        $response = $this->call('crm.deal.get', [
            'id' => $dealId
        ]);

        if (isset($response['result'])) {
            return [
                'success' => true,
                'deal' => $response['result']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error_description'] ?? 'Deal not found'
        ];
    }

    /**
     * Получение списка сделок
     */
    public function getDeals($params = [])
    {
        $response = $this->call('crm.deal.list', $params);

        if (isset($response['result'])) {
            return [
                'success' => true,
                'items' => $response['result'],
                'total' => $response['total'] ?? count($response['result'])
            ];
        }

        return [
            'success' => false,
            'items' => [],
            'error' => $response['error_description'] ?? 'Error getting deals'
        ];
    }

    /**
     * Создание активности (звонок, встреча, и т.д.)
     */
    public function createActivity($fields)
    {
        $response = $this->call('crm.activity.add', [
            'fields' => $fields
        ]);

        if (isset($response['result'])) {
            return [
                'success' => true,
                'id' => $response['result']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error_description'] ?? 'Unknown error'
        ];
    }

    /**
     * Создание задачи
     */
    public function createTask($fields)
    {
        $response = $this->call('tasks.task.add', [
            'fields' => $fields
        ]);

        if (isset($response['result']['task']['id'])) {
            return [
                'success' => true,
                'id' => $response['result']['task']['id']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error_description'] ?? 'Unknown error'
        ];
    }

    /**
     * Отправка уведомления пользователю
     */
    public function sendNotification($userId, $title, $message)
    {
        $response = $this->call('im.notify', [
            'to' => $userId,
            'message' => "**$title**\n\n$message",
            'type' => 'SYSTEM'
        ]);

        return [
            'success' => isset($response['result']) && $response['result'] === true,
            'error' => $response['error_description'] ?? null
        ];
    }

    /**
     * Получение информации о пользователе
     */
    public function getUser($userId)
    {
        $response = $this->call('user.get', [
            'ID' => $userId
        ]);

        if (isset($response['result'][0])) {
            return [
                'success' => true,
                'user' => $response['result'][0]
            ];
        }

        return [
            'success' => false,
            'error' => 'User not found'
        ];
    }

    /**
     * Получение контакта
     */
    public function getContact($contactId)
    {
        $response = $this->call('crm.contact.get', [
            'id' => $contactId
        ]);

        if (isset($response['result'])) {
            return [
                'success' => true,
                'contact' => $response['result']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error_description'] ?? 'Contact not found'
        ];
    }

    /**
     * Получение компании
     */
    public function getCompany($companyId)
    {
        $response = $this->call('crm.company.get', [
            'id' => $companyId
        ]);

        if (isset($response['result'])) {
            return [
                'success' => true,
                'company' => $response['result']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error_description'] ?? 'Company not found'
        ];
    }

    /**
     * Пакетный запрос (batch)
     */
    public function batch($commands)
    {
        $response = $this->call('batch', [
            'halt' => 0,
            'cmd' => $commands
        ]);

        if (isset($response['result']['result'])) {
            return [
                'success' => true,
                'results' => $response['result']['result']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error_description'] ?? 'Batch error'
        ];
    }

    /**
     * Базовый метод для вызова API
     */
    private function call($method, $params = [])
    {
        $url = $this->webhookUrl . $method . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            return [
                'error' => "HTTP $httpCode",
                'error_description' => $error
            ];
        }

        return json_decode($response, true) ?: [
            'error' => 'Invalid JSON response',
            'error_description' => $response
        ];
    }

    /**
     * Установка таймаута для запросов
     */
    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
    }
}
