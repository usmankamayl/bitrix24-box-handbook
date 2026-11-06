<?php
/**
 * Страница настроек модуля складского учета
 *
 * Доступна через: Настройки → Настройки продукта → Настройки модулей
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

$moduleId = 'acme.warehouse';

// Загружаем модуль
Loader::includeModule($moduleId);

$request = \Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();

// Сохранение настроек
if ($request->isPost() && check_bitrix_sessid() && $request['save']) {
    Option::set($moduleId, 'api_url', $request['api_url']);
    Option::set($moduleId, 'api_key', $request['api_key']);
    Option::set($moduleId, 'sync_enabled', $request['sync_enabled'] ? 'Y' : 'N');
    Option::set($moduleId, 'low_stock_threshold', (int)$request['low_stock_threshold']);

    // Показываем сообщение об успешном сохранении
    \CAdminMessage::ShowNote(Loc::getMessage('ACME_WAREHOUSE_OPTIONS_SAVED'));
}

// Получение текущих значений
$apiUrl = Option::get($moduleId, 'api_url', '');
$apiKey = Option::get($moduleId, 'api_key', '');
$syncEnabled = Option::get($moduleId, 'sync_enabled', 'N');
$lowStockThreshold = Option::get($moduleId, 'low_stock_threshold', '5');

// Определение вкладок
$aTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('ACME_WAREHOUSE_TAB_SETTINGS'),
        'TITLE' => Loc::getMessage('ACME_WAREHOUSE_TAB_SETTINGS_TITLE'),
    ],
    [
        'DIV' => 'edit2',
        'TAB' => Loc::getMessage('ACME_WAREHOUSE_TAB_SYNC'),
        'TITLE' => Loc::getMessage('ACME_WAREHOUSE_TAB_SYNC_TITLE'),
    ],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialchars($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>

    <?php $tabControl->Begin(); ?>

    <!-- Вкладка 1: Основные настройки -->
    <?php $tabControl->BeginNextTab(); ?>

    <tr class="heading">
        <td colspan="2">
            <b><?= Loc::getMessage('ACME_WAREHOUSE_SECTION_API') ?></b>
        </td>
    </tr>

    <tr>
        <td width="40%" class="adm-detail-content-cell-l">
            <label for="api_url"><?= Loc::getMessage('ACME_WAREHOUSE_API_URL') ?>:</label>
        </td>
        <td width="60%" class="adm-detail-content-cell-r">
            <input type="text"
                   id="api_url"
                   name="api_url"
                   value="<?= htmlspecialchars($apiUrl) ?>"
                   size="50"
                   placeholder="https://api.example.com">
            <br>
            <small><?= Loc::getMessage('ACME_WAREHOUSE_API_URL_HINT') ?></small>
        </td>
    </tr>

    <tr>
        <td class="adm-detail-content-cell-l">
            <label for="api_key"><?= Loc::getMessage('ACME_WAREHOUSE_API_KEY') ?>:</label>
        </td>
        <td class="adm-detail-content-cell-r">
            <input type="text"
                   id="api_key"
                   name="api_key"
                   value="<?= htmlspecialchars($apiKey) ?>"
                   size="50"
                   placeholder="your-api-key-here">
            <br>
            <small><?= Loc::getMessage('ACME_WAREHOUSE_API_KEY_HINT') ?></small>
        </td>
    </tr>

    <tr class="heading">
        <td colspan="2">
            <b><?= Loc::getMessage('ACME_WAREHOUSE_SECTION_THRESHOLDS') ?></b>
        </td>
    </tr>

    <tr>
        <td class="adm-detail-content-cell-l">
            <label for="low_stock_threshold"><?= Loc::getMessage('ACME_WAREHOUSE_LOW_STOCK_THRESHOLD') ?>:</label>
        </td>
        <td class="adm-detail-content-cell-r">
            <input type="number"
                   id="low_stock_threshold"
                   name="low_stock_threshold"
                   value="<?= htmlspecialchars($lowStockThreshold) ?>"
                   min="1"
                   max="100"
                   size="10">
            <br>
            <small><?= Loc::getMessage('ACME_WAREHOUSE_LOW_STOCK_THRESHOLD_HINT') ?></small>
        </td>
    </tr>

    <!-- Вкладка 2: Синхронизация -->
    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td width="40%" class="adm-detail-content-cell-l">
            <label for="sync_enabled"><?= Loc::getMessage('ACME_WAREHOUSE_SYNC_ENABLED') ?>:</label>
        </td>
        <td width="60%" class="adm-detail-content-cell-r">
            <input type="checkbox"
                   id="sync_enabled"
                   name="sync_enabled"
                   value="Y"
                   <?= $syncEnabled === 'Y' ? 'checked' : '' ?>>
            <br>
            <small><?= Loc::getMessage('ACME_WAREHOUSE_SYNC_ENABLED_HINT') ?></small>
        </td>
    </tr>

    <tr>
        <td colspan="2">
            <div class="adm-info-message">
                <?= Loc::getMessage('ACME_WAREHOUSE_SYNC_INFO') ?>
            </div>
        </td>
    </tr>

    <!-- Кнопки -->
    <?php $tabControl->Buttons(); ?>

    <input type="submit"
           name="save"
           value="<?= Loc::getMessage('ACME_WAREHOUSE_SAVE') ?>"
           class="adm-btn-save">

    <input type="reset"
           name="reset"
           value="<?= Loc::getMessage('ACME_WAREHOUSE_RESET') ?>">

    <?php $tabControl->End(); ?>
</form>

<style>
    .adm-detail-content-cell-l {
        vertical-align: top;
        padding-top: 7px;
    }

    .adm-detail-content-cell-r small {
        color: #777;
    }

    .adm-info-message {
        background: #e3f7fc;
        border-left: 4px solid #0092d0;
        padding: 12px;
        margin: 10px 0;
    }
</style>
