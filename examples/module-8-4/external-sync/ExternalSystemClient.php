<?php
/**
 * Клиент для работы с внешними системами
 *
 * Поддерживает:
 * - 1C через REST API
 * - Внешние CRM
 * - Slack уведомления
 * - Telegram уведомления
 */

class ExternalSystemClient
{
    private $timeout = 30;
    private $maxRetries = 3;

    /**
     * Отправка данных в 1C
     */
    public function sendTo1C($endpoint, $data)
    {
        $url = ONE_C_API_URL . '/' . $endpoint;

        return $this->sendRequest($url, $data, [
            'Authorization: Basic ' . base64_encode(ONE_C_API_USER . ':' . ONE_C_API_PASSWORD),
            'Content-Type: application/json'
        ]);
    }

    /**
     * Отправка данных во внешнюю CRM
     */
    public function sendToExternalCRM($endpoint, $data)
    {
        $url = EXTERNAL_CRM_API_URL . '/' . $endpoint;

        return $this->sendRequest($url, $data, [
            'Authorization: Bearer ' . EXTERNAL_CRM_API_KEY,
            'Content-Type: application/json'
        ]);
    }

    /**
     * Отправка сообщения в Slack
     */
    public function sendToSlack($message, $channel = null)
    {
        if (!defined('SLACK_WEBHOOK_URL') || empty(SLACK_WEBHOOK_URL)) {
            return ['success' => false, 'error' => 'Slack not configured'];
        }

        $data = [
            'text' => $message,
            'channel' => $channel ?? SLACK_DEFAULT_CHANNEL ?? '#general',
            'username' => 'Bitrix24 Bot',
            'icon_emoji' => ':robot_face:'
        ];

        $ch = curl_init(SLACK_WEBHOOK_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 200,
            'response' => $response
        ];
    }

    /**
     * Отправка сообщения в Telegram
     */
    public function sendToTelegram($message)
    {
        if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
            return ['success' => false, 'error' => 'Telegram not configured'];
        }

        $chatIds = TELEGRAM_CHAT_IDS ?? [];
        if (empty($chatIds)) {
            return ['success' => false, 'error' => 'No Telegram chat IDs'];
        }

        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        $success = true;

        foreach ($chatIds as $chatId) {
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $success = false;
            }
        }

        return ['success' => $success];
    }

    /**
     * Универсальный метод отправки HTTP запроса
     */
    private function sendRequest($url, $data, $headers = [])
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Успешный ответ
            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($response, true);

                return [
                    'success' => true,
                    'response' => $result,
                    'external_id' => $result['id'] ?? $result['external_id'] ?? null
                ];
            }

            // Серверная ошибка - повторяем
            if ($httpCode >= 500 && $attempt < $this->maxRetries) {
                sleep(pow(2, $attempt)); // Экспоненциальная задержка: 2, 4, 8 секунд
                continue;
            }

            // Клиентская ошибка или последняя попытка
            return [
                'success' => false,
                'error' => "HTTP $httpCode: $error",
                'response' => $response,
                'attempt' => $attempt
            ];
        }

        return [
            'success' => false,
            'error' => 'Max retries exceeded'
        ];
    }

    /**
     * Получение данных из внешней системы (GET запрос)
     */
    public function getFrom1C($endpoint, $params = [])
    {
        $url = ONE_C_API_URL . '/' . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(ONE_C_API_USER . ':' . ONE_C_API_PASSWORD)
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return [
                'success' => true,
                'data' => json_decode($response, true)
            ];
        }

        return [
            'success' => false,
            'error' => "HTTP $httpCode"
        ];
    }

    /**
     * Проверка доступности внешней системы
     */
    public function checkConnection($system)
    {
        switch ($system) {
            case '1C':
                $url = ONE_C_API_URL . '/ping';
                break;

            case 'external_crm':
                $url = EXTERNAL_CRM_API_URL . '/health';
                break;

            default:
                return ['success' => false, 'error' => 'Unknown system'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true // HEAD request
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 200,
            'http_code' => $httpCode
        ];
    }
}
