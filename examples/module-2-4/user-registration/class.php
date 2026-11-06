<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

/**
 * Компонент регистрации пользователя
 * Демонстрирует работу с CUser, валидацию полей и авторизацию
 */
class UserRegistrationComponent extends CBitrixComponent
{
    /**
     * Проверка подключения необходимых модулей
     */
    protected function checkModules()
    {
        if (!Loader::includeModule('main')) {
            throw new \Exception('Не удалось загрузить модуль main');
        }

        return true;
    }

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams)
    {
        // Автоматическая авторизация после регистрации
        $arParams['AUTO_AUTH'] = $arParams['AUTO_AUTH'] ?? 'Y';

        // Страница редиректа после регистрации
        $arParams['SUCCESS_PAGE'] = $arParams['SUCCESS_PAGE'] ?? '/';

        // Группы пользователя (по умолчанию группа 2 - авторизованные)
        $arParams['USER_GROUPS'] = !empty($arParams['USER_GROUPS'])
            ? (is_array($arParams['USER_GROUPS']) ? $arParams['USER_GROUPS'] : [$arParams['USER_GROUPS']])
            : [2];

        return $arParams;
    }

    /**
     * Валидация данных формы
     */
    protected function validateForm($arFields)
    {
        $arErrors = [];

        // Проверка логина
        if (empty($arFields['LOGIN'])) {
            $arErrors[] = 'Не указан логин';
        } elseif (strlen($arFields['LOGIN']) < 3) {
            $arErrors[] = 'Логин должен быть не менее 3 символов';
        }

        // Проверка email
        if (empty($arFields['EMAIL'])) {
            $arErrors[] = 'Не указан email';
        } elseif (!check_email($arFields['EMAIL'])) {
            $arErrors[] = 'Некорректный формат email';
        }

        // Проверка имени
        if (empty($arFields['NAME'])) {
            $arErrors[] = 'Не указано имя';
        }

        // Проверка пароля
        if (empty($arFields['PASSWORD'])) {
            $arErrors[] = 'Не указан пароль';
        } elseif (strlen($arFields['PASSWORD']) < 6) {
            $arErrors[] = 'Пароль должен быть не менее 6 символов';
        }

        // Проверка подтверждения пароля
        if ($arFields['PASSWORD'] !== $arFields['PASSWORD_CONFIRM']) {
            $arErrors[] = 'Пароли не совпадают';
        }

        return $arErrors;
    }

    /**
     * Регистрация пользователя
     */
    protected function registerUser($arFields)
    {
        global $USER;

        $user = new CUser;

        // Подготовка полей для регистрации
        $arUserFields = [
            'LOGIN' => $arFields['LOGIN'],
            'EMAIL' => $arFields['EMAIL'],
            'NAME' => $arFields['NAME'],
            'LAST_NAME' => $arFields['LAST_NAME'] ?? '',
            'PASSWORD' => $arFields['PASSWORD'],
            'CONFIRM_PASSWORD' => $arFields['PASSWORD_CONFIRM'],
            'ACTIVE' => 'Y',
            'GROUP_ID' => $this->arParams['USER_GROUPS'],
            'PERSONAL_PHONE' => $arFields['PHONE'] ?? '',
        ];

        // Создание пользователя
        $userId = $user->Add($arUserFields);

        if (!$userId) {
            return [
                'success' => false,
                'error' => $user->LAST_ERROR,
            ];
        }

        // Автоматическая авторизация, если включена
        if ($this->arParams['AUTO_AUTH'] === 'Y') {
            $USER->Authorize($userId);
        }

        return [
            'success' => true,
            'user_id' => $userId,
        ];
    }

    /**
     * Обработка POST-запроса
     */
    protected function processPost()
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if (!$request->isPost() || !check_bitrix_sessid()) {
            return false;
        }

        $arFields = [
            'LOGIN' => $request->getPost('LOGIN'),
            'EMAIL' => $request->getPost('EMAIL'),
            'NAME' => $request->getPost('NAME'),
            'LAST_NAME' => $request->getPost('LAST_NAME'),
            'PASSWORD' => $request->getPost('PASSWORD'),
            'PASSWORD_CONFIRM' => $request->getPost('PASSWORD_CONFIRM'),
            'PHONE' => $request->getPost('PHONE'),
        ];

        // Валидация
        $arErrors = $this->validateForm($arFields);

        if (!empty($arErrors)) {
            $this->arResult['ERRORS'] = $arErrors;
            $this->arResult['FORM_DATA'] = $arFields;
            return false;
        }

        // Регистрация
        $result = $this->registerUser($arFields);

        if ($result['success']) {
            $this->arResult['SUCCESS'] = true;
            $this->arResult['USER_ID'] = $result['user_id'];

            // Редирект на страницу успеха
            LocalRedirect($this->arParams['SUCCESS_PAGE']);
        } else {
            $this->arResult['ERRORS'] = [$result['error']];
            $this->arResult['FORM_DATA'] = $arFields;
        }

        return true;
    }

    /**
     * Точка входа в компонент
     */
    public function executeComponent()
    {
        try {
            $this->checkModules();

            // Инициализация результата
            $this->arResult['ERRORS'] = [];
            $this->arResult['SUCCESS'] = false;
            $this->arResult['FORM_DATA'] = [];

            // Обработка POST
            $this->processPost();

            // Подключение шаблона
            $this->includeComponentTemplate();

        } catch (\Exception $e) {
            ShowError($e->getMessage());
        }
    }
}
