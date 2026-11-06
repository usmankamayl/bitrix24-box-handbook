<?php
/**
 * Класс установки модуля складского учета
 *
 * Отвечает за установку/удаление модуля:
 * - Создание таблиц БД
 * - Копирование файлов
 * - Регистрация событий
 * - Установка агентов
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class acme_warehouse extends CModule
{
    public $MODULE_ID = 'acme.warehouse';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('ACME_WAREHOUSE_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ACME_WAREHOUSE_MODULE_DESC');

        $this->PARTNER_NAME = 'ACME Corp';
        $this->PARTNER_URI = 'https://acme.com';
    }

    /**
     * Установка модуля
     *
     * @return bool
     */
    public function DoInstall()
    {
        global $APPLICATION;

        // Проверка требований
        if (!$this->checkRequirements()) {
            $APPLICATION->ThrowException(
                Loc::getMessage('ACME_WAREHOUSE_REQUIREMENTS_ERROR')
            );
            return false;
        }

        // Регистрация модуля
        ModuleManager::registerModule($this->MODULE_ID);

        // Создание таблиц БД
        $this->InstallDB();

        // Копирование файлов
        $this->InstallFiles();

        // Регистрация событий
        $this->InstallEvents();

        // Установка агентов
        $this->InstallAgents();

        // Установка значений по умолчанию
        $this->setDefaultOptions();

        return true;
    }

    /**
     * Удаление модуля
     *
     * @return bool
     */
    public function DoUninstall()
    {
        global $APPLICATION;

        // Удаление агентов
        $this->UnInstallAgents();

        // Удаление событий
        $this->UnInstallEvents();

        // Удаление файлов
        $this->UnInstallFiles();

        // Удаление таблиц БД
        $this->UnInstallDB();

        // Удаление настроек
        Option::delete($this->MODULE_ID);

        // Снятие регистрации модуля
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    /**
     * Проверка требований для установки
     *
     * @return bool
     */
    protected function checkRequirements(): bool
    {
        // Проверка версии PHP
        if (version_compare(PHP_VERSION, '7.4.0') < 0) {
            return false;
        }

        // Проверка наличия необходимых модулей
        if (!ModuleManager::isModuleInstalled('iblock')) {
            return false;
        }

        return true;
    }

    /**
     * Установка таблиц БД
     *
     * @return bool
     */
    public function InstallDB()
    {
        $connection = Application::getConnection();

        // Создание таблицы товаров
        if (!$connection->isTableExists('acme_warehouse_products')) {
            $connection->query("
                CREATE TABLE IF NOT EXISTS `acme_warehouse_products` (
                    `ID` INT(11) NOT NULL AUTO_INCREMENT,
                    `NAME` VARCHAR(255) NOT NULL COMMENT 'Название товара',
                    `SKU` VARCHAR(50) NOT NULL COMMENT 'Артикул',
                    `QUANTITY` INT(11) DEFAULT 0 COMMENT 'Количество на складе',
                    `PRICE` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Цена',
                    `CREATED_AT` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания',
                    `UPDATED_AT` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата обновления',
                    PRIMARY KEY (`ID`),
                    UNIQUE KEY `UX_SKU` (`SKU`),
                    KEY `IX_NAME` (`NAME`),
                    KEY `IX_QUANTITY` (`QUANTITY`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Товары на складе';
            ");
        }

        return true;
    }

    /**
     * Удаление таблиц БД
     *
     * @return bool
     */
    public function UnInstallDB()
    {
        $connection = Application::getConnection();

        // Удаление таблиц
        $connection->dropTable('acme_warehouse_products');

        return true;
    }

    /**
     * Копирование файлов
     *
     * @return bool
     */
    public function InstallFiles()
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'];

        // Копирование компонентов (если есть)
        $componentsSource = __DIR__ . '/components';
        if (is_dir($componentsSource)) {
            CopyDirFiles(
                $componentsSource,
                $docRoot . '/local/components',
                true,
                true
            );
        }

        // Копирование JS/CSS (если есть)
        $jsSource = __DIR__ . '/js';
        if (is_dir($jsSource)) {
            CopyDirFiles(
                $jsSource,
                $docRoot . '/local/js/' . $this->MODULE_ID,
                true,
                true
            );
        }

        $cssSource = __DIR__ . '/css';
        if (is_dir($cssSource)) {
            CopyDirFiles(
                $cssSource,
                $docRoot . '/local/css/' . $this->MODULE_ID,
                true,
                true
            );
        }

        return true;
    }

    /**
     * Удаление файлов
     *
     * @return bool
     */
    public function UnInstallFiles()
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'];

        // Удаление компонентов
        $componentsDir = $docRoot . '/local/components/acme';
        if (is_dir($componentsDir)) {
            Directory::deleteDirectory($componentsDir);
        }

        // Удаление JS/CSS
        $jsDir = $docRoot . '/local/js/' . $this->MODULE_ID;
        if (is_dir($jsDir)) {
            Directory::deleteDirectory($jsDir);
        }

        $cssDir = $docRoot . '/local/css/' . $this->MODULE_ID;
        if (is_dir($cssDir)) {
            Directory::deleteDirectory($cssDir);
        }

        return true;
    }

    /**
     * Регистрация обработчиков событий
     *
     * @return bool
     */
    public function InstallEvents()
    {
        $eventManager = EventManager::getInstance();

        // Регистрация события при добавлении элемента инфоблока
        $eventManager->registerEventHandler(
            'iblock',
            'OnAfterIBlockElementAdd',
            $this->MODULE_ID,
            '\Acme\Warehouse\EventHandlers',
            'onIBlockElementAdd'
        );

        // Регистрация события при обновлении элемента инфоблока
        $eventManager->registerEventHandler(
            'iblock',
            'OnAfterIBlockElementUpdate',
            $this->MODULE_ID,
            '\Acme\Warehouse\EventHandlers',
            'onIBlockElementUpdate'
        );

        return true;
    }

    /**
     * Удаление обработчиков событий
     *
     * @return bool
     */
    public function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->unRegisterEventHandler(
            'iblock',
            'OnAfterIBlockElementAdd',
            $this->MODULE_ID,
            '\Acme\Warehouse\EventHandlers',
            'onIBlockElementAdd'
        );

        $eventManager->unRegisterEventHandler(
            'iblock',
            'OnAfterIBlockElementUpdate',
            $this->MODULE_ID,
            '\Acme\Warehouse\EventHandlers',
            'onIBlockElementUpdate'
        );

        return true;
    }

    /**
     * Установка агентов
     *
     * @return bool
     */
    public function InstallAgents()
    {
        // Агент синхронизации остатков (запуск каждые 24 часа)
        CAgent::AddAgent(
            '\Acme\Warehouse\Agents\SyncAgent::run();',
            $this->MODULE_ID,
            'N',      // не проверять на дубли
            86400,    // интервал 24 часа
            '',       // дата первой проверки
            'Y',      // активность
            '',       // дата первого запуска
            100       // сортировка
        );

        return true;
    }

    /**
     * Удаление агентов
     *
     * @return bool
     */
    public function UnInstallAgents()
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);

        return true;
    }

    /**
     * Установка значений по умолчанию
     */
    protected function setDefaultOptions()
    {
        Option::set($this->MODULE_ID, 'api_url', '');
        Option::set($this->MODULE_ID, 'api_key', '');
        Option::set($this->MODULE_ID, 'sync_enabled', 'N');
        Option::set($this->MODULE_ID, 'low_stock_threshold', '5');
    }
}
