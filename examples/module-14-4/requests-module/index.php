<?php
/**
 * Описание модуля "Заявки"
 */

$arModuleVersion = [
    'VERSION' => '1.0.0',
    'VERSION_DATE' => '2024-01-15',
];

if (file_exists(__DIR__ . '/install/version.php')) {
    include __DIR__ . '/install/version.php';
}

$MODULE_ID = 'mycompany.requests';
$MODULE_NAME = 'Управление заявками';
$MODULE_DESCRIPTION = 'Модуль для работы с динамическим типом "Заявки" через Factory API';
$MODULE_GROUP_RIGHTS = 'Y';
$PARTNER_NAME = 'MyCompany';
$PARTNER_URI = 'https://mycompany.ru';

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\StatusTable;
use Bitrix\Crm\PhaseSemantics;

Loc::loadMessages(__FILE__);

class mycompany_requests extends CModule
{
    public $MODULE_ID = 'mycompany.requests';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'Y';
    public $PARTNER_NAME;
    public $PARTNER_URI;

    /** @var int ID динамического типа "Заявки" */
    private $entityTypeId;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'Управление заявками';
        $this->MODULE_DESCRIPTION = 'Модуль для работы с динамическим типом "Заявки" через Factory API';
        $this->PARTNER_NAME = 'MyCompany';
        $this->PARTNER_URI = 'https://mycompany.ru';
    }

    /**
     * Установка модуля
     */
    public function DoInstall()
    {
        global $APPLICATION;

        if (!Loader::includeModule('crm')) {
            $APPLICATION->ThrowException('Модуль CRM не установлен');
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();

        $APPLICATION->IncludeAdminFile(
            'Установка модуля ' . $this->MODULE_NAME,
            __DIR__ . '/install/step.php'
        );

        return true;
    }

    /**
     * Удаление модуля
     */
    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallDB();
        $this->UnInstallEvents();
        $this->UnInstallFiles();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            'Удаление модуля ' . $this->MODULE_NAME,
            __DIR__ . '/install/unstep.php'
        );

        return true;
    }

    /**
     * Создание динамического типа и полей
     */
    public function InstallDB()
    {
        Loader::includeModule('crm');

        // Создать динамический тип "Заявки"
        $result = TypeTable::add([
            'TITLE' => 'Заявки',
            'NAME' => 'Заявка',
            'CODE' => 'REQUEST',
            'ENTITY_TYPE_ID' => \CCrmOwnerType::getDynamicTypeBasedStaticId(
                \CCrmOwnerType::FirstDynamicTypeId
            ),
            'IS_STAGES_ENABLED' => 'Y',
            'IS_BEGIN_CLOSE_DATES_ENABLED' => 'Y',
            'IS_CLIENT_ENABLED' => 'Y',
            'IS_USE_IN_USERFIELD_ENABLED' => 'Y',
            'IS_CATEGORIES_ENABLED' => 'N',
            'IS_DOCUMENTS_ENABLED' => 'Y',
            'IS_SOURCE_ENABLED' => 'Y',
            'IS_RECYCLEBIN_ENABLED' => 'Y',
            'IS_AUTOMATION_ENABLED' => 'Y',
            'IS_BIZ_PROC_ENABLED' => 'Y',
            'IS_SET_OPEN_PERMISSIONS' => 'Y',
            'IS_LINK_WITH_PRODUCTS_ENABLED' => 'N',
        ]);

        if (!$result->isSuccess()) {
            throw new \Exception('Ошибка создания типа: ' . implode(', ', $result->getErrorMessages()));
        }

        $typeId = $result->getId();
        $type = TypeTable::getById($typeId)->fetch();
        $this->entityTypeId = $type['ENTITY_TYPE_ID'];

        // Сохранить ID в настройках модуля
        \Bitrix\Main\Config\Option::set($this->MODULE_ID, 'entity_type_id', $this->entityTypeId);

        // Инвалидировать кеш
        Container::getInstance()->getDynamicTypesMap()->invalidate();

        // Создать пользовательские поля
        $this->createFields();

        // Создать стадии
        $this->createStages();

        return true;
    }

    /**
     * Создание пользовательских полей
     */
    private function createFields()
    {
        $userFieldManager = Container::getInstance()->getUserFieldManager();
        $entityId = \CCrmOwnerType::ResolveUserFieldEntityID($this->entityTypeId);

        $fields = [
            // Тип заявки
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_REQUEST_TYPE',
                'USER_TYPE_ID' => 'enumeration',
                'EDIT_FORM_LABEL' => ['ru' => 'Тип заявки'],
                'LIST' => [
                    ['VALUE' => 'Техническая поддержка', 'SORT' => 100, 'XML_ID' => 'SUPPORT'],
                    ['VALUE' => 'Консультация', 'SORT' => 200, 'XML_ID' => 'CONSULTATION'],
                    ['VALUE' => 'Жалоба', 'SORT' => 300, 'XML_ID' => 'COMPLAINT'],
                    ['VALUE' => 'Предложение', 'SORT' => 400, 'XML_ID' => 'SUGGESTION'],
                ],
                'MANDATORY' => 'Y',
                'SHOW_FILTER' => 'Y',
                'SHOW_IN_LIST' => 'Y',
            ],
            // Приоритет
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_PRIORITY',
                'USER_TYPE_ID' => 'enumeration',
                'EDIT_FORM_LABEL' => ['ru' => 'Приоритет'],
                'LIST' => [
                    ['VALUE' => 'Низкий', 'SORT' => 100, 'XML_ID' => 'LOW'],
                    ['VALUE' => 'Средний', 'SORT' => 200, 'DEF' => 'Y', 'XML_ID' => 'MEDIUM'],
                    ['VALUE' => 'Высокий', 'SORT' => 300, 'XML_ID' => 'HIGH'],
                    ['VALUE' => 'Критический', 'SORT' => 400, 'XML_ID' => 'CRITICAL'],
                ],
                'MANDATORY' => 'Y',
                'SHOW_FILTER' => 'Y',
                'SHOW_IN_LIST' => 'Y',
            ],
            // Описание проблемы
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_DESCRIPTION',
                'USER_TYPE_ID' => 'string',
                'EDIT_FORM_LABEL' => ['ru' => 'Описание проблемы'],
                'MANDATORY' => 'Y',
                'SHOW_FILTER' => 'N',
                'SHOW_IN_LIST' => 'N',
                'SETTINGS' => ['ROWS' => 5],
            ],
            // Исполнитель
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_EXECUTOR',
                'USER_TYPE_ID' => 'employee',
                'EDIT_FORM_LABEL' => ['ru' => 'Исполнитель'],
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'Y',
                'SHOW_IN_LIST' => 'Y',
                'MULTIPLE' => 'N',
            ],
            // Комментарий исполнителя
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_EXECUTOR_COMMENT',
                'USER_TYPE_ID' => 'string',
                'EDIT_FORM_LABEL' => ['ru' => 'Комментарий исполнителя'],
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'N',
                'SHOW_IN_LIST' => 'N',
                'SETTINGS' => ['ROWS' => 3],
            ],
            // Файлы
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_FILES',
                'USER_TYPE_ID' => 'file',
                'EDIT_FORM_LABEL' => ['ru' => 'Прикреплённые файлы'],
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'N',
                'SHOW_IN_LIST' => 'N',
                'MULTIPLE' => 'Y',
            ],
            // Дата дедлайна
            [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_DEADLINE',
                'USER_TYPE_ID' => 'datetime',
                'EDIT_FORM_LABEL' => ['ru' => 'Срок выполнения'],
                'MANDATORY' => 'N',
                'SHOW_FILTER' => 'Y',
                'SHOW_IN_LIST' => 'Y',
            ],
        ];

        foreach ($fields as $field) {
            $userFieldManager->addField($field);
        }
    }

    /**
     * Создание стадий
     */
    private function createStages()
    {
        $statusEntityId = 'DYNAMIC_' . $this->entityTypeId . '_STAGE_0';

        $stages = [
            [
                'STATUS_ID' => 'NEW',
                'NAME' => 'Новая',
                'SORT' => 100,
                'SEMANTICS' => PhaseSemantics::PROCESS,
                'COLOR' => '#39A8EF',
            ],
            [
                'STATUS_ID' => 'IN_WORK',
                'NAME' => 'В работе',
                'SORT' => 200,
                'SEMANTICS' => PhaseSemantics::PROCESS,
                'COLOR' => '#FFA900',
            ],
            [
                'STATUS_ID' => 'PENDING',
                'NAME' => 'Ожидание ответа',
                'SORT' => 300,
                'SEMANTICS' => PhaseSemantics::PROCESS,
                'COLOR' => '#55D0E0',
            ],
            [
                'STATUS_ID' => 'RESOLVED',
                'NAME' => 'Решена',
                'SORT' => 400,
                'SEMANTICS' => PhaseSemantics::SUCCESS,
                'COLOR' => '#7BD500',
            ],
            [
                'STATUS_ID' => 'REJECTED',
                'NAME' => 'Отклонена',
                'SORT' => 500,
                'SEMANTICS' => PhaseSemantics::FAILURE,
                'COLOR' => '#FF5752',
            ],
        ];

        foreach ($stages as $stage) {
            StatusTable::add([
                'ENTITY_ID' => $statusEntityId,
                'STATUS_ID' => $stage['STATUS_ID'],
                'NAME' => $stage['NAME'],
                'SORT' => $stage['SORT'],
                'SEMANTICS' => $stage['SEMANTICS'],
                'COLOR' => $stage['COLOR'],
            ]);
        }
    }

    /**
     * Удаление динамического типа
     */
    public function UnInstallDB()
    {
        Loader::includeModule('crm');

        $entityTypeId = (int)\Bitrix\Main\Config\Option::get($this->MODULE_ID, 'entity_type_id');

        if ($entityTypeId > 0) {
            // Найти тип по entityTypeId
            $type = TypeTable::getList([
                'filter' => ['=ENTITY_TYPE_ID' => $entityTypeId],
            ])->fetch();

            if ($type) {
                // Удалить тип (автоматически удалятся поля и стадии)
                TypeTable::delete($type['ID']);
            }

            // Удалить настройку
            \Bitrix\Main\Config\Option::delete($this->MODULE_ID, ['name' => 'entity_type_id']);
        }

        return true;
    }

    /**
     * Регистрация обработчиков событий
     */
    public function InstallEvents()
    {
        $eventManager = EventManager::getInstance();

        // Перед добавлением заявки
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmDynamicItemAdd',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onBeforeItemAdd'
        );

        // После добавления заявки
        $eventManager->registerEventHandler(
            'crm',
            'OnAfterCrmDynamicItemAdd',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onAfterItemAdd'
        );

        // Перед обновлением заявки
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmDynamicItemUpdate',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onBeforeItemUpdate'
        );

        // После обновления заявки
        $eventManager->registerEventHandler(
            'crm',
            'OnAfterCrmDynamicItemUpdate',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onAfterItemUpdate'
        );

        return true;
    }

    /**
     * Удаление обработчиков событий
     */
    public function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmDynamicItemAdd',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onBeforeItemAdd'
        );

        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterCrmDynamicItemAdd',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onAfterItemAdd'
        );

        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmDynamicItemUpdate',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onBeforeItemUpdate'
        );

        $eventManager->unRegisterEventHandler(
            'crm',
            'OnAfterCrmDynamicItemUpdate',
            $this->MODULE_ID,
            '\\MyCompany\\Requests\\EventHandlers',
            'onAfterItemUpdate'
        );

        return true;
    }

    /**
     * Копирование файлов
     */
    public function InstallFiles()
    {
        // Копировать компоненты в /local/components/
        CopyDirFiles(
            __DIR__ . '/install/components',
            Application::getDocumentRoot() . '/local/components',
            true,
            true
        );

        return true;
    }

    /**
     * Удаление файлов
     */
    public function UnInstallFiles()
    {
        // Удалить компоненты
        DeleteDirFilesEx('/local/components/mycompany/requests.list');
        DeleteDirFilesEx('/local/components/mycompany/requests.detail');

        return true;
    }
}
