<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Context;
use Bitrix\Main\Application;

class SmsSenderComponent extends CBitrixComponent
{
    // API SMSC.ru
    private const API_URL = 'https://smsc.ru/sys/send.php';

    /**
     * Проверка параметров
     */
    private function checkParams()
    {
        if (empty($this->arParams['API_LOGIN'])) {
            return 'Не указан логин SMSC.ru';
        }

        if (empty($this->arParams['API_PASSWORD'])) {
            return 'Не указан пароль SMSC.ru';
        }

        if (empty($this->arParams['SUCCESS_MESSAGE'])) {
            $this->arParams['SUCCESS_MESSAGE'] = 'SMS успешно отправлено';
        }

        if (!isset($this->arParams['AJAX_MODE'])) {
            $this->arParams['AJAX_MODE'] = 'Y';
        }

        return true;
    }

    /**
     * Отправка SMS через SMSC.ru
     */
    private function sendSMS($phone, $message)
    {
        // Создаем HTTP-клиент
        $httpClient = new HttpClient([
            'waitResponse' => true,
            'socketTimeout' => 10,
            'streamTimeout' => 30,
        ]);

        // Параметры запроса
        $params = [
            'login' => $this->arParams['API_LOGIN'],
            'psw' => $this->arParams['API_PASSWORD'],
            'phones' => $this->formatPhone($phone),
            'mes' => $message,
            'charset' => 'utf-8',
            'fmt' => 3,  // Формат ответа: JSON
        ];

        // Добавляем имя отправителя, если указано
        if (!empty($this->arParams['SENDER_NAME'])) {
            $params['sender'] = $this->arParams['SENDER_NAME'];
        }

        // Формируем URL с параметрами
        $url = self::API_URL . '?' . http_build_query($params);

        // Логируем запрос (без пароля)
        $this->logRequest($phone, $message);

        try {
            // Выполняем запрос
            $startTime = microtime(true);
            $response = $httpClient->get($url);
            $duration = round((microtime(true) - $startTime) * 1000);
            $status = $httpClient->getStatus();

            // Проверяем HTTP статус
            if ($status !== 200) {
                throw new \Exception('SMSC API returned status ' . $status);
            }

            // Парсим JSON
            $data = Json::decode($response);

            // Проверяем ответ API
            if (isset($data['error'])) {
                throw new \Exception($this->getErrorMessage($data['error'], $data['error_code']));
            }

            // Логируем успех
            $this->logResponse($phone, $data['id'] ?? 'unknown', $duration);

            return [
                'success' => true,
                'id' => $data['id'] ?? null,
                'cnt' => $data['cnt'] ?? 1,
            ];

        } catch (\Exception $e) {
            // Логируем ошибку
            $this->logError($phone, $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Форматирование номера телефона
     */
    private function formatPhone($phone)
    {
        // Убираем все символы кроме цифр и +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Если начинается с 8, заменяем на +7
        if (strpos($phone, '8') === 0) {
            $phone = '+7' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Получение текста ошибки
     */
    private function getErrorMessage($error, $errorCode)
    {
        $errors = [
            1 => 'Ошибка в параметрах',
            2 => 'Неверный логин или пароль',
            3 => 'Недостаточно средств на счете',
            4 => 'IP-адрес временно заблокирован',
            5 => 'Неверный формат даты',
            6 => 'Сообщение запрещено',
            7 => 'Неверный формат номера телефона',
            8 => 'Сообщение на этот номер не может быть доставлено',
            9 => 'Отправка более одного одинакового запроса',
        ];

        if (isset($errors[$errorCode])) {
            return $errors[$errorCode] . ' (код: ' . $errorCode . ')';
        }

        return $error;
    }

    /**
     * Обработка POST-запроса
     */
    private function processForm()
    {
        $request = Context::getCurrent()->getRequest();

        if (!$request->isPost()) {
            return null;
        }

        // Проверка сессии
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error' => 'Ошибка проверки сессии',
            ];
        }

        // Получаем данные формы
        $phone = trim($request->getPost('phone'));
        $message = trim($request->getPost('message'));

        // Валидация
        $errors = [];

        if (empty($phone)) {
            $errors[] = 'Не указан номер телефона';
        }

        if (empty($message)) {
            $errors[] = 'Не указан текст сообщения';
        } elseif (mb_strlen($message) > 1000) {
            $errors[] = 'Текст сообщения слишком длинный (максимум 1000 символов)';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'error' => implode(', ', $errors),
            ];
        }

        // Отправляем SMS
        return $this->sendSMS($phone, $message);
    }

    /**
     * Логирование запроса
     */
    private function logRequest($phone, $message)
    {
        AddMessage2Log(
            'SMS Request: phone=' . $phone . ', message_length=' . mb_strlen($message),
            'sms_sender',
            \CEventLog::SEVERITY_INFO
        );
    }

    /**
     * Логирование ответа
     */
    private function logResponse($phone, $smsId, $duration)
    {
        AddMessage2Log(
            'SMS Sent: phone=' . $phone . ', sms_id=' . $smsId . ', duration=' . $duration . 'ms',
            'sms_sender',
            \CEventLog::SEVERITY_INFO
        );
    }

    /**
     * Логирование ошибки
     */
    private function logError($phone, $error)
    {
        AddMessage2Log(
            'SMS Error: phone=' . $phone . ', error=' . $error,
            'sms_sender',
            \CEventLog::SEVERITY_ERROR
        );
    }

    /**
     * Основной метод выполнения
     */
    public function executeComponent()
    {
        // Проверяем параметры
        $checkResult = $this->checkParams();
        if ($checkResult !== true) {
            $this->arResult['ERROR'] = $checkResult;
            $this->includeComponentTemplate();
            return;
        }

        // Обрабатываем POST-запрос
        $formResult = $this->processForm();

        if ($formResult !== null) {
            // Если AJAX режим, возвращаем JSON
            if ($this->arParams['AJAX_MODE'] === 'Y' &&
                Context::getCurrent()->getRequest()->isAjaxRequest()) {

                Application::getInstance()->end();
                header('Content-Type: application/json');
                echo Json::encode($formResult);
                die();
            }

            // Иначе сохраняем результат
            $this->arResult['FORM_RESULT'] = $formResult;
        }

        // Подключаем шаблон
        $this->includeComponentTemplate();
    }
}
