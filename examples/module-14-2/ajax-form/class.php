<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Event;

Loc::loadMessages(__FILE__);

/**
 * Компонент AJAX формы с валидацией через BX.JS
 */
class AjaxFormComponent extends CBitrixComponent implements Controllerable
{
    /**
     * Настройка фильтров для AJAX действий
     */
    public function configureActions()
    {
        return [
            'submitForm' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST
                    ]),
                    new ActionFilter\Csrf(),
                ],
                'postfilters' => []
            ],
        ];
    }

    /**
     * Выполнение компонента
     */
    public function executeComponent()
    {
        $this->arResult['FORM_ID'] = 'ajax-contact-form';
        $this->arResult['EMAIL_TO'] = $this->arParams['EMAIL_TO'];
        $this->arResult['SUCCESS_MESSAGE'] = $this->arParams['SUCCESS_MESSAGE'];

        $this->includeComponentTemplate();
    }

    /**
     * AJAX метод: отправка формы
     *
     * @param string $name Имя отправителя
     * @param string $email Email отправителя
     * @param string $phone Телефон
     * @param string $message Сообщение
     * @return array
     */
    public function submitFormAction($name, $email, $phone, $message)
    {
        // Серверная валидация
        $errors = $this->validateFormData($name, $email, $phone, $message);

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        // Отправка email (используем почтовые события Bitrix)
        $sent = Event::send([
            'EVENT_NAME' => 'FEEDBACK_FORM',
            'LID' => SITE_ID,
            'C_FIELDS' => [
                'EMAIL_TO' => $this->arParams['EMAIL_TO'],
                'NAME' => $name,
                'EMAIL' => $email,
                'PHONE' => $phone,
                'MESSAGE' => $message,
            ],
        ]);

        if ($sent) {
            return [
                'success' => true,
                'message' => $this->arParams['SUCCESS_MESSAGE'],
                'timestamp' => time(),
            ];
        }

        return [
            'success' => false,
            'errors' => [Loc::getMessage('ERROR_EMAIL_SEND')],
        ];
    }

    /**
     * Валидация данных формы
     *
     * @param string $name
     * @param string $email
     * @param string $phone
     * @param string $message
     * @return array Массив ошибок
     */
    private function validateFormData($name, $email, $phone, $message)
    {
        $errors = [];

        // Проверка имени
        if (empty($name) || mb_strlen($name) < 2) {
            $errors['name'] = Loc::getMessage('ERROR_NAME_REQUIRED');
        }

        // Проверка email
        if (empty($email)) {
            $errors['email'] = Loc::getMessage('ERROR_EMAIL_REQUIRED');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = Loc::getMessage('ERROR_EMAIL_INVALID');
        }

        // Проверка телефона
        if (empty($phone)) {
            $errors['phone'] = Loc::getMessage('ERROR_PHONE_REQUIRED');
        } elseif (!preg_match('/^\+?[0-9]{10,15}$/', preg_replace('/[^0-9+]/', '', $phone))) {
            $errors['phone'] = Loc::getMessage('ERROR_PHONE_INVALID');
        }

        // Проверка сообщения
        if (empty($message) || mb_strlen($message) < 10) {
            $errors['message'] = Loc::getMessage('ERROR_MESSAGE_REQUIRED');
        }

        return $errors;
    }
}
