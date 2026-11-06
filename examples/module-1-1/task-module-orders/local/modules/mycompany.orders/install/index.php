<?php

/**
 * Установщик модуля mycompany.orders.
 *
 * Путь в проекте: /local/modules/mycompany.orders/install/index.php
 *
 * Класс CModule здесь — legacy, и это нормально: install/index.php модуля
 * исторически строится на нём, замены в D7 нет. Всё остальное в модуле — D7.
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class mycompany_orders extends CModule
{
    public $MODULE_ID = 'mycompany.orders';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'N';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('MYCOMPANY_ORDERS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MYCOMPANY_ORDERS_MODULE_DESCRIPTION');
    }

    public function DoInstall(): bool
    {
        global $APPLICATION, $USER;

        if (!$USER->IsAdmin()) {
            $APPLICATION->ThrowException(Loc::getMessage('MYCOMPANY_ORDERS_INSTALL_ERROR_RIGHTS'));
            return false;
        }

        if (!$this->InstallDB()) {
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        return true;
    }

    public function DoUninstall(): bool
    {
        global $APPLICATION, $USER;

        if (!$USER->IsAdmin()) {
            $APPLICATION->ThrowException(Loc::getMessage('MYCOMPANY_ORDERS_INSTALL_ERROR_RIGHTS'));
            return false;
        }

        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    public function InstallDB(): bool
    {
        global $APPLICATION, $DB;

        // RunSQLBatch возвращает false при успехе и массив ошибок при провале.
        $errors = $DB->RunSQLBatch(__DIR__ . '/db/install.sql');

        if ($errors !== false) {
            $APPLICATION->ThrowException(
                Loc::getMessage('MYCOMPANY_ORDERS_INSTALL_ERROR_DB', ['#ERROR#' => implode('; ', (array)$errors)])
            );
            return false;
        }

        return true;
    }

    public function UnInstallDB(): void
    {
        global $DB;

        // В реальном модуле здесь обычно спрашивают, удалять данные или нет.
        $DB->RunSQLBatch(__DIR__ . '/db/uninstall.sql');
    }
}
