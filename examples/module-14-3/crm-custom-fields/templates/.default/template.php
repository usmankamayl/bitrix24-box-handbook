<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arResult
 * @var array $arParams
 * @var CMain $APPLICATION
 * @var CBitrixComponent $component
 */

use Bitrix\Main\UI\Extension;

// Подключение UI расширений
Extension::load(['ui.notification', 'ui.buttons', 'ui.dialogs.messagebox']);
?>

<div class="crm-custom-fields-wrapper">

    <h2>CRM форма с кастомными полями</h2>

    <div class="crm-custom-fields-info">
        <p>Этот компонент демонстрирует кастомизацию CRM формы через JavaScript события.</p>
        <p>Тип сущности: <strong><?= htmlspecialcharsbx($arResult['ENTITY_TYPE']) ?></strong></p>
        <?php if ($arResult['ENTITY_ID'] > 0): ?>
            <p>ID сущности: <strong><?= $arResult['ENTITY_ID'] ?></strong></p>
        <?php endif; ?>
    </div>

    <!-- Инструкции по кастомизации -->
    <div class="crm-customization-guide">
        <h3>Что будет добавлено в CRM форму:</h3>

        <div class="guide-section">
            <h4>📝 Кастомные поля:</h4>
            <ul>
                <?php if (!empty($arResult['CUSTOM_FIELDS'])): ?>
                    <?php foreach ($arResult['CUSTOM_FIELDS'] as $field): ?>
                        <li>
                            <strong><?= htmlspecialcharsbx($field['title']) ?></strong>
                            (<?= htmlspecialcharsbx($field['name']) ?>)
                            — <?= htmlspecialcharsbx($field['help'] ?? 'Кастомное поле') ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="guide-section">
            <h4>🔧 Модификации стандартных полей:</h4>
            <ul>
                <li>Изменение заголовка поля "Сумма" на "Бюджет проекта"</li>
                <li>Блокировка поля "Дата закрытия" для стадии "Новая"</li>
                <li>Добавление валидации для оценки качества (1-100)</li>
            </ul>
        </div>

        <div class="guide-section">
            <h4>✅ Кастомная валидация:</h4>
            <ul>
                <li>Проверка отрицательных сумм</li>
                <li>Обязательность проекта для высокоприоритетных сделок</li>
                <li>Ограничение количества тегов (макс 10)</li>
            </ul>
        </div>

        <div class="guide-section">
            <h4>🔄 Автоматизация:</h4>
            <ul>
                <li>Автозаполнение внешнего ID</li>
                <li>Обновление времени последней активности</li>
                <li>Нормализация тегов</li>
                <li>Автозапуск БП для критичных сделок</li>
            </ul>
        </div>
    </div>

    <!-- Кнопка для открытия CRM формы -->
    <div class="crm-open-form">
        <button class="ui-btn ui-btn-success" onclick="openCrmForm()">
            Открыть CRM форму
        </button>
        <p class="form-hint">
            После открытия формы все кастомизации будут применены автоматически
        </p>
    </div>

    <!-- Примеры использования -->
    <div class="crm-examples">
        <h3>Примеры использования API:</h3>

        <div class="example-block">
            <h4>Получить значение поля:</h4>
            <pre><code>var priority = CrmCustomFields.getFieldValue('UF_CRM_PRIORITY');
console.log('Приоритет:', priority);</code></pre>
        </div>

        <div class="example-block">
            <h4>Установить значение поля:</h4>
            <pre><code>CrmCustomFields.setFieldValue('UF_CRM_QUALITY_SCORE', 85);</code></pre>
        </div>

        <div class="example-block">
            <h4>Скрыть/показать поле:</h4>
            <pre><code>CrmCustomFields.toggleField('CURRENCY_ID', false); // скрыть
CrmCustomFields.toggleField('CURRENCY_ID', true);  // показать</code></pre>
        </div>

        <div class="example-block">
            <h4>Заблокировать/разблокировать поле:</h4>
            <pre><code>CrmCustomFields.toggleFieldReadOnly('CLOSEDATE', true);  // заблокировать
CrmCustomFields.toggleFieldReadOnly('CLOSEDATE', false); // разблокировать</code></pre>
        </div>
    </div>

</div>

<script>
    // Инициализация после загрузки DOM
    BX.ready(function() {
        CrmCustomFields.init({
            entityType: '<?= CUtil::JSEscape($arResult['ENTITY_TYPE']) ?>',
            entityId: <?= (int)$arResult['ENTITY_ID'] ?>,
            enableCustomFields: <?= $arParams['ENABLE_CUSTOM_FIELDS'] === 'Y' ? 'true' : 'false' ?>,
            modifyStandardFields: <?= $arParams['MODIFY_STANDARD_FIELDS'] === 'Y' ? 'true' : 'false' ?>,
            customFields: <?= CUtil::PhpToJSObject($arResult['CUSTOM_FIELDS'] ?? []) ?>
        });
    });

    // Открытие CRM формы
    function openCrmForm() {
        var entityType = '<?= $arResult['ENTITY_TYPE'] ?>';
        var entityId = <?= (int)$arResult['ENTITY_ID'] ?>;

        var url = '';

        switch (entityType) {
            case 'DEAL':
                url = entityId > 0
                    ? '/crm/deal/details/' + entityId + '/'
                    : '/crm/deal/details/0/';
                break;
            case 'LEAD':
                url = entityId > 0
                    ? '/crm/lead/details/' + entityId + '/'
                    : '/crm/lead/details/0/';
                break;
            case 'CONTACT':
                url = entityId > 0
                    ? '/crm/contact/details/' + entityId + '/'
                    : '/crm/contact/details/0/';
                break;
            case 'COMPANY':
                url = entityId > 0
                    ? '/crm/company/details/' + entityId + '/'
                    : '/crm/company/details/0/';
                break;
        }

        if (url) {
            window.location.href = url;
        } else {
            BX.UI.Notification.Center.notify({
                content: 'Неизвестный тип сущности',
                position: 'top-right',
                autoHideDelay: 3000
            });
        }
    }
</script>

<style>
    .crm-custom-fields-wrapper {
        max-width: 1000px;
        margin: 0 auto;
        padding: 24px;
    }

    .crm-custom-fields-wrapper h2 {
        margin-bottom: 16px;
        color: #333;
    }

    .crm-custom-fields-info {
        padding: 16px;
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        border-radius: 4px;
        margin-bottom: 24px;
    }

    .crm-custom-fields-info p {
        margin: 8px 0;
    }

    .crm-customization-guide {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .crm-customization-guide h3 {
        margin: 0 0 20px 0;
        color: #333;
    }

    .guide-section {
        margin-bottom: 20px;
    }

    .guide-section:last-child {
        margin-bottom: 0;
    }

    .guide-section h4 {
        margin: 0 0 12px 0;
        color: #555;
    }

    .guide-section ul {
        margin: 0;
        padding-left: 24px;
    }

    .guide-section li {
        margin: 8px 0;
        line-height: 1.6;
    }

    .crm-open-form {
        text-align: center;
        padding: 32px;
        background: #f5f5f5;
        border-radius: 8px;
        margin-bottom: 24px;
    }

    .crm-open-form .ui-btn {
        font-size: 16px;
        padding: 12px 32px;
    }

    .form-hint {
        margin-top: 12px;
        color: #666;
        font-size: 14px;
    }

    .crm-examples {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 24px;
    }

    .crm-examples h3 {
        margin: 0 0 20px 0;
        color: #333;
    }

    .example-block {
        margin-bottom: 20px;
    }

    .example-block:last-child {
        margin-bottom: 0;
    }

    .example-block h4 {
        margin: 0 0 8px 0;
        color: #555;
        font-size: 14px;
    }

    .example-block pre {
        margin: 0;
        background: #f5f5f5;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 12px;
        overflow-x: auto;
    }

    .example-block code {
        font-family: 'Courier New', monospace;
        font-size: 13px;
        color: #333;
    }
</style>
