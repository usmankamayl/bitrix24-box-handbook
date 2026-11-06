<?php
/**
 * Установщик модуля mycompany.tools.
 *
 * install/index.php — legacy-часть контракта модуля: имя класса обязательно
 * совпадает с ID модуля, где точки заменены подчёркиваниями
 * (mycompany.tools → mycompany_tools). Здесь допустим старый API, потому что
 * этот файл вызывает ядро, а не наш код.
 */

use Bitrix\Main\ModuleManager;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (class_exists('mycompany_tools')) {
    return;
}

class mycompany_tools extends CModule
{
    public $MODULE_ID = 'mycompany.tools';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'N';
    public $PARTNER_NAME = 'MyCompany';
    public $PARTNER_URI = 'https://example.com';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.0.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? '';
        $this->MODULE_NAME = 'MyCompany: инструменты';
        $this->MODULE_DESCRIPTION = 'Учебный модуль: автозагрузка lib/ и карта legacy-классов.';
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUninstall(): void
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
