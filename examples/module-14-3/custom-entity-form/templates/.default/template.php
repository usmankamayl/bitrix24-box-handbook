<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 * @var CMain $APPLICATION
 * @var CBitrixComponent $component
 */

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Page\Asset;

// Подключение UI расширений
Extension::load(['ui.notification', 'ui.buttons', 'ui.dialogs.messagebox']);

// Подключение стилей и скриптов
$APPLICATION->SetAdditionalCSS($templateFolder . '/style.css');
$APPLICATION->AddHeadScript($templateFolder . '/script.js');

$entity = $arResult['ENTITY'];
$draft = $arResult['DRAFT'];
$config = $arResult['FORM_CONFIG'];
$isEdit = $entity['ID'] > 0;

// Данные для формы (черновик приоритетнее)
$formData = $draft ?? $entity;
?>

<div class="entity-form-wrapper" id="entity-form-wrapper">

    <!-- Уведомление о черновике -->
    <?php if ($draft): ?>
        <div class="draft-notification">
            <span class="draft-icon">📝</span>
            <span class="draft-text">Восстановлен несохранённый черновик</span>
            <button type="button" class="draft-close" onclick="EntityForm.clearDraft()">×</button>
        </div>
    <?php endif; ?>

    <!-- Заголовок формы -->
    <div class="entity-form-header">
        <h2><?= $isEdit ? 'Редактирование: ' . htmlspecialcharsbx($entity['NAME']) : 'Создание новой записи' ?></h2>
    </div>

    <!-- Форма -->
    <form id="entity-form" class="entity-form" data-entity-id="<?= $entity['ID'] ?>">

        <input type="hidden" name="ID" value="<?= $entity['ID'] ?>">

        <!-- Табы -->
        <div class="entity-form-tabs">
            <div class="tabs-nav">
                <?php foreach ($config['sections'] as $index => $section): ?>
                    <button
                        type="button"
                        class="tab-button <?= $index === 0 ? 'active' : '' ?>"
                        data-tab="<?= $section['id'] ?>"
                        onclick="EntityForm.switchTab('<?= $section['id'] ?>')"
                    >
                        <?= htmlspecialcharsbx($section['title']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="tabs-content">
                <?php foreach ($config['sections'] as $index => $section): ?>
                    <div
                        class="tab-pane <?= $index === 0 ? 'active' : '' ?>"
                        id="tab-<?= $section['id'] ?>"
                    >
                        <?php foreach ($section['fields'] as $field): ?>
                            <div class="form-field" data-field="<?= $field['name'] ?>">

                                <label class="form-label">
                                    <?= htmlspecialcharsbx($field['title']) ?>
                                    <?php if ($field['required']): ?>
                                        <span class="required">*</span>
                                    <?php endif; ?>
                                </label>

                                <div class="form-input-wrapper">
                                    <?php
                                    $value = $formData[$field['name']] ?? '';

                                    switch ($field['type']) {
                                        case 'text':
                                        case 'email':
                                        case 'tel':
                                        case 'date':
                                            ?>
                                            <input
                                                type="<?= $field['type'] ?>"
                                                name="<?= $field['name'] ?>"
                                                value="<?= htmlspecialcharsbx($value) ?>"
                                                placeholder="<?= htmlspecialcharsbx($field['placeholder'] ?? '') ?>"
                                                <?= $field['required'] ? 'required' : '' ?>
                                                <?= isset($field['minLength']) ? 'minlength="' . $field['minLength'] . '"' : '' ?>
                                                class="form-input"
                                            >
                                            <?php
                                            break;

                                        case 'textarea':
                                            ?>
                                            <textarea
                                                name="<?= $field['name'] ?>"
                                                rows="<?= $field['rows'] ?? 3 ?>"
                                                placeholder="<?= htmlspecialcharsbx($field['placeholder'] ?? '') ?>"
                                                <?= $field['required'] ? 'required' : '' ?>
                                                class="form-input"
                                            ><?= htmlspecialcharsbx($value) ?></textarea>
                                            <?php
                                            break;

                                        case 'checkbox':
                                            ?>
                                            <label class="form-checkbox">
                                                <input
                                                    type="checkbox"
                                                    name="<?= $field['name'] ?>"
                                                    value="Y"
                                                    <?= $value ? 'checked' : '' ?>
                                                >
                                                <span class="checkbox-label">Да</span>
                                            </label>
                                            <?php
                                            break;

                                        case 'user':
                                            ?>
                                            <div class="user-selector-wrapper">
                                                <input
                                                    type="hidden"
                                                    name="<?= $field['name'] ?>"
                                                    value="<?= htmlspecialcharsbx($value) ?>"
                                                    id="<?= $field['name'] ?>"
                                                >
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary"
                                                    onclick="EntityForm.selectUser('<?= $field['name'] ?>')"
                                                >
                                                    Выбрать пользователя
                                                </button>
                                                <div id="selected-user-<?= $field['name'] ?>" class="selected-user">
                                                    <?php if ($value): ?>
                                                        <?php
                                                        $selectedUser = array_filter($arResult['USERS'], function($u) use ($value) {
                                                            return $u['id'] == $value;
                                                        });
                                                        $selectedUser = reset($selectedUser);
                                                        if ($selectedUser):
                                                        ?>
                                                            <span class="user-name"><?= htmlspecialcharsbx($selectedUser['name']) ?></span>
                                                            <button type="button" class="user-remove" onclick="EntityForm.clearUser('<?= $field['name'] ?>')">×</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php
                                            break;

                                        case 'file':
                                            ?>
                                            <div class="file-upload-wrapper">
                                                <input
                                                    type="file"
                                                    id="file-input-<?= $field['name'] ?>"
                                                    <?= $field['multiple'] ?? false ? 'multiple' : '' ?>
                                                    style="display: none;"
                                                    onchange="EntityForm.uploadFile('<?= $field['name'] ?>', this)"
                                                >
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary"
                                                    onclick="document.getElementById('file-input-<?= $field['name'] ?>').click()"
                                                >
                                                    📎 Выбрать файл
                                                </button>
                                                <div id="file-list-<?= $field['name'] ?>" class="file-list">
                                                    <!-- Список файлов будет здесь -->
                                                </div>
                                            </div>
                                            <?php
                                            break;
                                    }
                                    ?>
                                </div>

                                <div class="form-field-error"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Кнопки действий -->
        <div class="entity-form-footer">
            <button type="submit" class="btn btn-primary">
                <span class="btn-text"><?= $isEdit ? 'Сохранить' : 'Создать' ?></span>
                <span class="btn-loader" style="display: none;">⏳</span>
            </button>

            <button type="button" class="btn btn-secondary" onclick="EntityForm.clearForm()">
                Очистить
            </button>

            <button type="button" class="btn btn-link" onclick="history.back()">
                Отмена
            </button>

            <?php if ($arResult['ENABLE_AUTOSAVE']): ?>
                <span class="autosave-status" id="autosave-status">
                    Автосохранение включено
                </span>
            <?php endif; ?>
        </div>

    </form>

</div>

<script>
    // Передача данных в JavaScript
    BX.ready(function() {
        EntityForm.init({
            formId: 'entity-form',
            componentName: '<?= $component->getName() ?>',
            signedParameters: '<?= $component->getSignedParameters() ?>',
            enableAutosave: <?= $arResult['ENABLE_AUTOSAVE'] ? 'true' : 'false' ?>,
            autosaveInterval: <?= $arResult['AUTOSAVE_INTERVAL'] * 1000 ?>,
            users: <?= CUtil::PhpToJSObject($arResult['USERS']) ?>,
            formConfig: <?= CUtil::PhpToJSObject($config) ?>
        });
    });
</script>
