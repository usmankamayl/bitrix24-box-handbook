<?php
/**
 * Установщик модуля mycompany.hello.
 *
 * CModule вызывает методы в таком порядке (мы держим его сами в DoInstall):
 *   DoInstall:  InstallFiles → InstallDB → InstallEvents → registerModule
 *   DoUninstall: UnInstallFiles → UnInstallEvents → UnInstallDB → unRegisterModule
 *
 * registerModule — последней: если она пройдёт, а InstallDB() упадёт,
 * получится «модуль установлен, таблиц нет».
 */

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class mycompany_hello extends CModule
{
    public $MODULE_ID = 'mycompany.hello';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'Модуль приветствия';
        $this->MODULE_DESCRIPTION = 'Каркас D7-модуля: структура, автозагрузка, этапы установки';
        $this->PARTNER_NAME = 'MyCompany';
        $this->PARTNER_URI = 'https://example.com';
    }

    /**
     * Установка модуля — вызывается из админки.
     */
    public function DoInstall()
    {
        global $APPLICATION, $USER, $step;

        // Установка модуля — административное действие. Кнопка в админке не
        // защита: страницу установки можно вызвать запросом.
        if (!$USER->IsAdmin()) {
            $APPLICATION->ThrowException('Установка модуля доступна только администратору.');
            return false;
        }

        $step = (int)$step;

        // Этап 1: файлы. В реальном мастере — отдельный шаг мастера установки.
        if ($step < 2) {
            $this->InstallFiles();
        }

        // Этап 2: схема БД, события, регистрация модуля.
        if (!$this->InstallDB()) {
            return false;
        }
        $this->InstallEvents();
        ModuleManager::registerModule($this->MODULE_ID);

        return true;
    }

    /**
     * Удаление модуля — порядок обратный установке.
     */
    public function DoUninstall()
    {
        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    /**
     * Создание таблиц, инфоблоков, HL-блоков, демо-данных.
     */
    public function InstallDB()
    {
        // Пример: $connection = Application::getConnection();
        // $connection->queryExecute("CREATE TABLE IF NOT EXISTS ...");
        return true;
    }

    public function UnInstallDB()
    {
        // DROP TABLE / удалить инфоблоки. Спросить у пользователя, удалять ли данные.
        return true;
    }

    /**
     * Копирование компонентов, шаблонов, js в /local/ или /bitrix/.
     */
    public function InstallFiles()
    {
        // CopyDirFiles(__DIR__ . '/components', Application::getDocumentRoot() . '/local/components', true, true);
        // CopyDirFiles(__DIR__ . '/admin', Application::getDocumentRoot() . '/bitrix/admin', true);
        return true;
    }

    public function UnInstallFiles()
    {
        return true;
    }

    /**
     * Регистрация обработчиков событий модуля.
     */
    public function InstallEvents()
    {
        // $em = \Bitrix\Main\EventManager::getInstance();
        // $em->registerEventHandler('main', 'OnPageStart', $this->MODULE_ID, 'Mycompany\\Hello\\EventHandlers', 'onPageStart');
        return true;
    }

    public function UnInstallEvents()
    {
        // $em = \Bitrix\Main\EventManager::getInstance();
        // $em->unRegisterEventHandler('main', 'OnPageStart', $this->MODULE_ID, 'Mycompany\\Hello\\EventHandlers', 'onPageStart');
        return true;
    }
}
