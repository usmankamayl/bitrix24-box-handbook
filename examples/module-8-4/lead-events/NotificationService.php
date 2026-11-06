<?php
/**
 * Сервис для отправки уведомлений
 *
 * Поддерживает:
 * - Email уведомления
 * - Telegram уведомления
 * - Уведомления в Bitrix24 (через REST API)
 * - SMS уведомления (опционально)
 */

class NotificationService
{
    private $bitrix24Token;
    private $telegramBotToken;
    private $telegramChatIds;

    public function __construct()
    {
        $this->bitrix24Token = BITRIX24_WEBHOOK_URL ?? null;
        $this->telegramBotToken = TELEGRAM_BOT_TOKEN ?? null;
        $this->telegramChatIds = TELEGRAM_CHAT_IDS ?? [];
    }

    /**
     * Отправка уведомления пользователю Bitrix24
     */
    public function sendToUser($userId, $title, $message)
    {
        // 1. Email уведомление
        $this->sendEmail($userId, $title, $message);

        // 2. Уведомление в Bitrix24
        $this->sendBitrix24Notification($userId, $title, $message);

        // 3. Telegram (если настроен)
        if ($this->telegramBotToken) {
            $this->sendTelegram($title, $message);
        }
    }

    /**
     * Отправка email
     */
    private function sendEmail($userId, $title, $message)
    {
        // Получаем email пользователя из Bitrix24
        $email = $this->getUserEmail($userId);

        if (!$email) {
            return false;
        }

        $headers = [
            'From: noreply@yourcompany.com',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];

        $htmlMessage = nl2br(htmlspecialchars($message));

        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0b66c3; color: white; padding: 15px; border-radius: 5px; }
                .content { padding: 20px; background: #f5f5f5; margin-top: 10px; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>$title</h2>
                </div>
                <div class='content'>
                    $htmlMessage
                </div>
                <div class='footer'>
                    Это автоматическое уведомление из Bitrix24.
                </div>
            </div>
        </body>
        </html>
        ";

        return mail($email, $title, $body, implode("\r\n", $headers));
    }

    /**
     * Отправка уведомления в Bitrix24
     */
    private function sendBitrix24Notification($userId, $title, $message)
    {
        if (!$this->bitrix24Token) {
            return false;
        }

        try {
            $url = $this->bitrix24Token . 'im.notify.json';

            $data = [
                'to' => $userId,
                'message' => "**$title**\n\n$message",
                'type' => 'SYSTEM', // или 'USER' для личного сообщения
                'notify_type' => 1, // 1 - всплывающее уведомление
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                return $result['result'] ?? false;
            }

            return false;
        } catch (Exception $e) {
            error_log('Bitrix24 notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Отправка в Telegram
     */
    private function sendTelegram($title, $message)
    {
        if (!$this->telegramBotToken || empty($this->telegramChatIds)) {
            return false;
        }

        $text = "🔔 *$title*\n\n$message";

        foreach ($this->telegramChatIds as $chatId) {
            $this->sendTelegramMessage($chatId, $text);
        }

        return true;
    }

    /**
     * Отправка сообщения в Telegram
     */
    private function sendTelegramMessage($chatId, $text)
    {
        $url = "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    /**
     * Получение email пользователя из Bitrix24
     */
    private function getUserEmail($userId)
    {
        if (!$this->bitrix24Token) {
            return null;
        }

        try {
            $url = $this->bitrix24Token . 'user.get.json?ID=' . $userId;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            return $result['result'][0]['EMAIL'] ?? null;
        } catch (Exception $e) {
            error_log('Error getting user email: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Массовая рассылка уведомлений
     */
    public function sendBulk($userIds, $title, $message)
    {
        foreach ($userIds as $userId) {
            $this->sendToUser($userId, $title, $message);
        }
    }

    /**
     * Отправка уведомления администраторам
     */
    public function sendToAdmins($title, $message)
    {
        $adminIds = ADMIN_USER_IDS ?? [1];

        foreach ($adminIds as $adminId) {
            $this->sendToUser($adminId, $title, $message);
        }
    }
}
