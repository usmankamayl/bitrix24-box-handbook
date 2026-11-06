<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Bitrix\Main\Application;

/**
 * Компонент профиля пользователя с редактированием
 * Демонстрирует работу с данными пользователя, обновление полей и UF-полей
 */
class UserProfileEditComponent extends CBitrixComponent
{
    private $currentUser;

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
        // ID пользователя (если не указан, берется текущий)
        $arParams['USER_ID'] = (int)($arParams['USER_ID'] ?? 0);

        // Разрешить редактирование только своего профиля
        $arParams['ONLY_OWN_PROFILE'] = $arParams['ONLY_OWN_PROFILE'] ?? 'Y';

        // Страница редиректа после сохранения
        $arParams['SUCCESS_PAGE'] = $arParams['SUCCESS_PAGE'] ?? '';

        // Редактируемые поля
        $arParams['EDITABLE_FIELDS'] = !empty($arParams['EDITABLE_FIELDS'])
            ? $arParams['EDITABLE_FIELDS']
            : ['NAME', 'LAST_NAME', 'EMAIL', 'PERSONAL_PHONE', 'PERSONAL_PHOTO'];

        return $arParams;
    }

    /**
     * Проверка прав доступа
     */
    protected function checkAccess()
    {
        global $USER;

        if (!$USER->IsAuthorized()) {
            throw new \Exception('Необходима авторизация');
        }

        $userId = $this->arParams['USER_ID'] ?: $USER->GetID();

        // Проверка прав (только свой профиль или администратор)
        if ($this->arParams['ONLY_OWN_PROFILE'] === 'Y' && !$USER->IsAdmin()) {
            if ($userId != $USER->GetID()) {
                throw new \Exception('Недостаточно прав для редактирования');
            }
        }

        return $userId;
    }

    /**
     * Получение данных пользователя
     */
    protected function getUserData($userId)
    {
        $result = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => array_merge(
                ['ID', 'LOGIN', 'ACTIVE', 'DATE_REGISTER'],
                $this->arParams['EDITABLE_FIELDS']
            ),
        ]);

        $user = $result->fetch();

        if (!$user) {
            throw new \Exception('Пользователь не найден');
        }

        // Получение групп пользователя
        $rsGroups = \CUser::GetUserGroup($userId);
        $user['GROUPS'] = [];
        while ($arGroup = $rsGroups->Fetch()) {
            $user['GROUPS'][] = (int)$arGroup['GROUP_ID'];
        }

        // Получение UF-полей
        $user['UF_FIELDS'] = $this->getUserFields($userId);

        return $user;
    }

    /**
     * Получение пользовательских полей
     */
    protected function getUserFields($userId)
    {
        global $USER_FIELD_MANAGER;

        $arUserFields = $USER_FIELD_MANAGER->GetUserFields('USER', $userId, LANGUAGE_ID);
        $arResult = [];

        foreach ($arUserFields as $fieldName => $arField) {
            // Получение значения поля
            $rsUser = \CUser::GetByID($userId);
            $arUser = $rsUser->Fetch();

            $arResult[$fieldName] = [
                'FIELD_NAME' => $fieldName,
                'EDIT_FORM_LABEL' => $arField['EDIT_FORM_LABEL'],
                'USER_TYPE_ID' => $arField['USER_TYPE_ID'],
                'VALUE' => $arUser[$fieldName] ?? null,
                'SETTINGS' => $arField['SETTINGS'],
                'MANDATORY' => $arField['MANDATORY'],
            ];
        }

        return $arResult;
    }

    /**
     * Валидация данных формы
     */
    protected function validateForm($arFields)
    {
        $arErrors = [];

        // Проверка email
        if (in_array('EMAIL', $this->arParams['EDITABLE_FIELDS'])) {
            if (empty($arFields['EMAIL'])) {
                $arErrors[] = 'Email обязателен для заполнения';
            } elseif (!check_email($arFields['EMAIL'])) {
                $arErrors[] = 'Некорректный формат email';
            }
        }

        // Проверка имени
        if (in_array('NAME', $this->arParams['EDITABLE_FIELDS'])) {
            if (empty($arFields['NAME'])) {
                $arErrors[] = 'Имя обязательно для заполнения';
            }
        }

        return $arErrors;
    }

    /**
     * Обновление данных пользователя
     */
    protected function updateUser($userId, $arFields)
    {
        $user = new \CUser;

        // Подготовка полей для обновления
        $arUpdateFields = [];

        foreach ($this->arParams['EDITABLE_FIELDS'] as $fieldName) {
            if (isset($arFields[$fieldName])) {
                $arUpdateFields[$fieldName] = $arFields[$fieldName];
            }
        }

        // Обработка загрузки файла (фото)
        if (in_array('PERSONAL_PHOTO', $this->arParams['EDITABLE_FIELDS'])) {
            if (!empty($_FILES['PERSONAL_PHOTO']['name'])) {
                $arUpdateFields['PERSONAL_PHOTO'] = $_FILES['PERSONAL_PHOTO'];
            }
        }

        // Обработка UF-полей
        foreach ($arFields as $key => $value) {
            if (strpos($key, 'UF_') === 0) {
                $arUpdateFields[$key] = $value;
            }
        }

        // Обновление
        $result = $user->Update($userId, $arUpdateFields);

        if (!$result) {
            return [
                'success' => false,
                'error' => $user->LAST_ERROR,
            ];
        }

        return [
            'success' => true,
        ];
    }

    /**
     * Обработка POST-запроса
     */
    protected function processPost($userId)
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if (!$request->isPost() || !check_bitrix_sessid()) {
            return false;
        }

        // Сбор полей из POST
        $arFields = [];
        foreach ($this->arParams['EDITABLE_FIELDS'] as $fieldName) {
            $arFields[$fieldName] = $request->getPost($fieldName);
        }

        // Сбор UF-полей
        foreach ($request->getPostList()->toArray() as $key => $value) {
            if (strpos($key, 'UF_') === 0) {
                $arFields[$key] = $value;
            }
        }

        // Валидация
        $arErrors = $this->validateForm($arFields);

        if (!empty($arErrors)) {
            $this->arResult['ERRORS'] = $arErrors;
            return false;
        }

        // Обновление
        $result = $this->updateUser($userId, $arFields);

        if ($result['success']) {
            $this->arResult['SUCCESS'] = true;

            // Редирект, если указана страница
            if (!empty($this->arParams['SUCCESS_PAGE'])) {
                LocalRedirect($this->arParams['SUCCESS_PAGE']);
            }
        } else {
            $this->arResult['ERRORS'] = [$result['error']];
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

            // Проверка прав доступа
            $userId = $this->checkAccess();

            // Инициализация результата
            $this->arResult['ERRORS'] = [];
            $this->arResult['SUCCESS'] = false;

            // Обработка POST
            $this->processPost($userId);

            // Получение данных пользователя
            $this->arResult['USER'] = $this->getUserData($userId);
            $this->arResult['EDITABLE_FIELDS'] = $this->arParams['EDITABLE_FIELDS'];

            // Подключение шаблона
            $this->includeComponentTemplate();

        } catch (\Exception $e) {
            ShowError($e->getMessage());
        }
    }
}
