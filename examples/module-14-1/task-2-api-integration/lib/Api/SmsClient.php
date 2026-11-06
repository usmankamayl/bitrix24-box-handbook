<?php
/**
 * Клиент для отправки SMS через внешний API
 *
 * Пример использования:
 *
 * $sms = new SmsClient();
 * $result = $sms->send('+79991234567', 'Ваш код: 1234');
 *
 * if ($result->isSuccess()) {
 *     echo "SMS отправлено";
 * }
 */

namespace Acme\Integration\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Result;

class SmsClient extends AbstractClient
{
    /**
     * URL базового адреса SMS API
     *
     * @return string
     */
    protected function getApiUrl(): string
    {
        return Option::get('acme.integration', 'sms_api_url', 'https://sms.ru/sms');
    }

    /**
     * API ключ для авторизации
     *
     * @return string
     */
    protected function getApiKey(): string
    {
        return Option::get('acme.integration', 'sms_api_key', '');
    }

    /**
     * Отправить SMS сообщение
     *
     * @param string $phone Номер телефона в международном формате
     * @param string $message Текст сообщения
     * @param string|null $sender Имя отправителя (необязательно)
     * @return Result
     */
    public function send(string $phone, string $message, ?string $sender = null): Result
    {
        // Нормализация номера телефона
        $phone = $this->normalizePhone($phone);

        if (empty($phone)) {
            return $this->createErrorResult('Некорректный номер телефона');
        }

        // Проверка длины сообщения
        if (mb_strlen($message) > 1000) {
            return $this->createErrorResult('Сообщение слишком длинное (максимум 1000 символов)');
        }

        // Получаем имя отправителя из настроек
        if ($sender === null) {
            $sender = Option::get('acme.integration', 'sms_sender_name', 'Info');
        }

        // Отправляем запрос
        $result = $this->post('/send', [
            'to' => $phone,
            'text' => $message,
            'from' => $sender,
        ]);

        if ($result->isSuccess()) {
            $data = $result->getData();

            // Логируем успешную отправку
            AddMessage2Log(sprintf(
                'SMS отправлено: phone=%s, message_id=%s',
                $phone,
                $data['message_id'] ?? 'unknown'
            ), 'acme.integration');
        }

        return $result;
    }

    /**
     * Получить статус отправленного SMS
     *
     * @param string $messageId ID сообщения
     * @return Result
     */
    public function getStatus(string $messageId): Result
    {
        return $this->get('/status/' . $messageId);
    }

    /**
     * Получить баланс аккаунта
     *
     * @return Result
     */
    public function getBalance(): Result
    {
        return $this->get('/balance');
    }

    /**
     * Отправить массовую рассылку
     *
     * @param array $recipients Массив [['phone' => '+7...', 'message' => 'текст'], ...]
     * @return Result
     */
    public function sendBulk(array $recipients): Result
    {
        if (empty($recipients)) {
            return $this->createErrorResult('Список получателей пуст');
        }

        // Нормализуем номера
        foreach ($recipients as &$recipient) {
            $recipient['phone'] = $this->normalizePhone($recipient['phone']);
        }

        return $this->post('/send/bulk', [
            'messages' => $recipients,
        ]);
    }

    /**
     * Нормализация номера телефона
     *
     * Приводит номер к формату: +7XXXXXXXXXX
     *
     * @param string $phone
     * @return string|null
     */
    protected function normalizePhone(string $phone): ?string
    {
        // Убираем все кроме цифр и +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Если начинается с 8, заменяем на +7
        if (preg_match('/^8(\d{10})$/', $phone, $matches)) {
            return '+7' . $matches[1];
        }

        // Если начинается с 7, добавляем +
        if (preg_match('/^7(\d{10})$/', $phone, $matches)) {
            return '+7' . $matches[1];
        }

        // Если уже в формате +7XXXXXXXXXX
        if (preg_match('/^\+7\d{10}$/', $phone)) {
            return $phone;
        }

        return null;
    }
}
