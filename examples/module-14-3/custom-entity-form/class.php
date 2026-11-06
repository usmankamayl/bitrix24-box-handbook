<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Application;

/**
 * Компонент: Форма редактирования сущности
 *
 * Демонстрирует:
 * - Различные типы полей (text, email, phone, date, file, user)
 * - Клиентскую и серверную валидацию
 * - Автосохранение черновиков
 * - Работу с файлами и пользователями
 */
class CustomEntityFormComponent extends CBitrixComponent implements Controllerable
{
    /**
     * Подключение модулей
     */
    protected function checkModules()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль инфоблоков не установлен');
            return false;
        }

        return true;
    }

    /**
     * Конфигурация контроллера
     */
    public function configureActions()
    {
        return [
            'save' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST
                    ]),
                    new ActionFilter\Csrf(),
                ],
            ],
            'autoSave' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST
                    ]),
                ],
            ],
            'uploadFile' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                ],
            ],
        ];
    }

    /**
     * Основной метод компонента
     */
    public function executeComponent()
    {
        if (!$this->checkModules()) {
            return;
        }

        $this->arResult['ENTITY_ID'] = (int)$this->arParams['ENTITY_ID'];
        $this->arResult['ENABLE_AUTOSAVE'] = $this->arParams['ENABLE_AUTOSAVE'] === 'Y';
        $this->arResult['AUTOSAVE_INTERVAL'] = (int)$this->arParams['AUTOSAVE_INTERVAL'] ?: 30;

        // Загрузка данных сущности
        if ($this->arResult['ENTITY_ID'] > 0) {
            $this->arResult['ENTITY'] = $this->loadEntity($this->arResult['ENTITY_ID']);
        } else {
            $this->arResult['ENTITY'] = $this->getEmptyEntity();
        }

        // Загрузка черновика из сессии
        $this->arResult['DRAFT'] = $this->loadDraft();

        // Конфигурация полей формы
        $this->arResult['FORM_CONFIG'] = $this->getFormConfig();

        // Список пользователей для селектора
        $this->arResult['USERS'] = $this->getUsers();

        $this->includeComponentTemplate();
    }

    /**
     * Загрузка данных сущности
     */
    protected function loadEntity($id)
    {
        // Пример: загрузка из инфоблока
        // В реальном проекте это может быть любая ORM-сущность

        $entity = CIBlockElement::GetByID($id)->GetNextElement();

        if (!$entity) {
            return $this->getEmptyEntity();
        }

        $fields = $entity->GetFields();
        $props = $entity->GetProperties();

        return [
            'ID' => $fields['ID'],
            'NAME' => $fields['NAME'],
            'EMAIL' => $props['EMAIL']['VALUE'] ?? '',
            'PHONE' => $props['PHONE']['VALUE'] ?? '',
            'BIRTH_DATE' => $props['BIRTH_DATE']['VALUE'] ?? '',
            'DESCRIPTION' => $fields['PREVIEW_TEXT'] ?? '',
            'RESPONSIBLE_ID' => $props['RESPONSIBLE_ID']['VALUE'] ?? '',
            'FILES' => $props['FILES']['VALUE'] ?? [],
            'ACTIVE' => $fields['ACTIVE'] === 'Y',
        ];
    }

    /**
     * Пустая сущность для создания
     */
    protected function getEmptyEntity()
    {
        global $USER;

        return [
            'ID' => 0,
            'NAME' => '',
            'EMAIL' => '',
            'PHONE' => '',
            'BIRTH_DATE' => '',
            'DESCRIPTION' => '',
            'RESPONSIBLE_ID' => $USER->GetID(),
            'FILES' => [],
            'ACTIVE' => true,
        ];
    }

    /**
     * Загрузка черновика из сессии
     */
    protected function loadDraft()
    {
        $session = Application::getInstance()->getSession();
        $draftKey = 'entity_form_draft_' . $this->arResult['ENTITY_ID'];

        if ($session->has($draftKey)) {
            $draft = $session->get($draftKey);

            // Проверка давности черновика (24 часа)
            if (isset($draft['timestamp']) &&
                (time() - $draft['timestamp']) < 86400) {
                return $draft['data'];
            }
        }

        return null;
    }

    /**
     * Конфигурация полей формы
     */
    protected function getFormConfig()
    {
        return [
            'sections' => [
                [
                    'id' => 'main',
                    'title' => 'Основная информация',
                    'fields' => [
                        [
                            'name' => 'NAME',
                            'title' => 'Название',
                            'type' => 'text',
                            'required' => true,
                            'placeholder' => 'Введите название',
                            'minLength' => 3,
                        ],
                        [
                            'name' => 'EMAIL',
                            'title' => 'Email',
                            'type' => 'email',
                            'required' => true,
                            'placeholder' => 'example@domain.com',
                        ],
                        [
                            'name' => 'PHONE',
                            'title' => 'Телефон',
                            'type' => 'tel',
                            'required' => false,
                            'placeholder' => '+7 (999) 123-45-67',
                        ],
                        [
                            'name' => 'BIRTH_DATE',
                            'title' => 'Дата рождения',
                            'type' => 'date',
                            'required' => false,
                        ],
                    ],
                ],
                [
                    'id' => 'additional',
                    'title' => 'Дополнительная информация',
                    'fields' => [
                        [
                            'name' => 'DESCRIPTION',
                            'title' => 'Описание',
                            'type' => 'textarea',
                            'required' => false,
                            'rows' => 5,
                        ],
                        [
                            'name' => 'RESPONSIBLE_ID',
                            'title' => 'Ответственный',
                            'type' => 'user',
                            'required' => true,
                        ],
                        [
                            'name' => 'FILES',
                            'title' => 'Файлы',
                            'type' => 'file',
                            'required' => false,
                            'multiple' => true,
                        ],
                        [
                            'name' => 'ACTIVE',
                            'title' => 'Активность',
                            'type' => 'checkbox',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Получение списка пользователей
     */
    protected function getUsers()
    {
        $users = [];

        $rsUsers = CUser::GetList(
            ($by = "last_name"),
            ($order = "asc"),
            ['ACTIVE' => 'Y'],
            ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']]
        );

        while ($user = $rsUsers->Fetch()) {
            $users[] = [
                'id' => $user['ID'],
                'name' => trim($user['NAME'] . ' ' . $user['LAST_NAME']),
                'email' => $user['EMAIL'],
            ];
        }

        return $users;
    }

    /**
     * AJAX: Сохранение формы
     */
    public function saveAction($data)
    {
        // Валидация данных
        $errors = $this->validateData($data);

        if (!empty($errors)) {
            $this->addErrors($errors);
            return null;
        }

        // Сохранение данных
        try {
            $entityId = $this->saveEntity($data);

            // Очистка черновика
            $this->clearDraft();

            return [
                'success' => true,
                'entityId' => $entityId,
                'message' => 'Данные успешно сохранены',
                'redirectUrl' => $this->getSuccessUrl($entityId),
            ];

        } catch (\Exception $e) {
            $this->addError(new Error($e->getMessage()));
            return null;
        }
    }

    /**
     * Валидация данных
     */
    protected function validateData($data)
    {
        $errors = [];

        // Обязательное поле NAME
        if (empty($data['NAME']) || mb_strlen($data['NAME']) < 3) {
            $errors[] = new Error('Название должно содержать минимум 3 символа', 'NAME');
        }

        // Валидация email
        if (empty($data['EMAIL'])) {
            $errors[] = new Error('Email обязателен для заполнения', 'EMAIL');
        } elseif (!filter_var($data['EMAIL'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = new Error('Некорректный формат email', 'EMAIL');
        }

        // Валидация телефона (если указан)
        if (!empty($data['PHONE'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['PHONE']);
            if (strlen($phone) < 10) {
                $errors[] = new Error('Некорректный формат телефона', 'PHONE');
            }
        }

        // Проверка ответственного
        if (empty($data['RESPONSIBLE_ID'])) {
            $errors[] = new Error('Выберите ответственного', 'RESPONSIBLE_ID');
        }

        return $errors;
    }

    /**
     * Сохранение сущности
     */
    protected function saveEntity($data)
    {
        $el = new CIBlockElement();

        $fields = [
            'IBLOCK_ID' => 1, // ID инфоблока (настроить под свой проект)
            'NAME' => $data['NAME'],
            'ACTIVE' => isset($data['ACTIVE']) && $data['ACTIVE'] ? 'Y' : 'N',
            'PREVIEW_TEXT' => $data['DESCRIPTION'] ?? '',
            'PROPERTY_VALUES' => [
                'EMAIL' => $data['EMAIL'],
                'PHONE' => $data['PHONE'] ?? '',
                'BIRTH_DATE' => $data['BIRTH_DATE'] ?? '',
                'RESPONSIBLE_ID' => $data['RESPONSIBLE_ID'],
                'FILES' => $data['FILES'] ?? [],
            ],
        ];

        $entityId = (int)($data['ID'] ?? 0);

        if ($entityId > 0) {
            // Обновление
            if (!$el->Update($entityId, $fields)) {
                throw new \Exception($el->LAST_ERROR);
            }
        } else {
            // Создание
            $entityId = $el->Add($fields);
            if (!$entityId) {
                throw new \Exception($el->LAST_ERROR);
            }
        }

        return $entityId;
    }

    /**
     * AJAX: Автосохранение черновика
     */
    public function autoSaveAction($data)
    {
        $session = Application::getInstance()->getSession();
        $draftKey = 'entity_form_draft_' . ($data['ID'] ?? 0);

        $session->set($draftKey, [
            'data' => $data,
            'timestamp' => time(),
        ]);

        return [
            'success' => true,
            'message' => 'Черновик сохранён',
        ];
    }

    /**
     * AJAX: Загрузка файла
     */
    public function uploadFileAction()
    {
        if (!isset($_FILES['file'])) {
            $this->addError(new Error('Файл не выбран'));
            return null;
        }

        $file = $_FILES['file'];

        // Проверка размера (макс 10 МБ)
        if ($file['size'] > 10 * 1024 * 1024) {
            $this->addError(new Error('Файл слишком большой (макс 10 МБ)'));
            return null;
        }

        // Сохранение файла
        $fileId = CFile::SaveFile($file, 'custom_entity');

        if (!$fileId) {
            $this->addError(new Error('Ошибка при загрузке файла'));
            return null;
        }

        $fileInfo = CFile::GetFileArray($fileId);

        return [
            'success' => true,
            'fileId' => $fileId,
            'fileName' => $fileInfo['FILE_NAME'],
            'fileSize' => $fileInfo['FILE_SIZE'],
            'filePath' => $fileInfo['SRC'],
        ];
    }

    /**
     * Очистка черновика
     */
    protected function clearDraft()
    {
        $session = Application::getInstance()->getSession();
        $draftKey = 'entity_form_draft_' . $this->arResult['ENTITY_ID'];
        $session->remove($draftKey);
    }

    /**
     * URL успешного сохранения
     */
    protected function getSuccessUrl($entityId)
    {
        if (!empty($this->arParams['SUCCESS_URL'])) {
            return str_replace('#ID#', $entityId, $this->arParams['SUCCESS_URL']);
        }

        return '/local/entity/detail.php?ID=' . $entityId;
    }
}
