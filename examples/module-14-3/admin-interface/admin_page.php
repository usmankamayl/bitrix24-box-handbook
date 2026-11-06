<?php
/**
 * Административная страница: Управление настройками модуля
 *
 * Демонстрирует:
 * - Многовкладочный интерфейс
 * - Drag-and-drop сортировку
 * - Массовые операции
 * - Экспорт/импорт данных
 * - Интеграцию с административным API Bitrix
 */

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\UI\Extension;

// Проверка прав
global $USER, $APPLICATION;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm("Доступ запрещен");
}

$APPLICATION->SetTitle("Управление модулем");

// Подключение UI расширений
Extension::load(['ui.notification', 'ui.buttons', 'ui.dialogs.messagebox']);

// Обработка действий
$request = Application::getInstance()->getContext()->getRequest();
$action = $request->get('action');

if ($action && check_bitrix_sessid()) {
    switch ($action) {
        case 'save_settings':
            handleSaveSettings($request);
            break;
        case 'export':
            handleExport();
            break;
        case 'import':
            handleImport($request);
            break;
        case 'clear_cache':
            handleClearCache();
            break;
    }
}

// Загрузка текущих настроек
$settings = loadSettings();
?>

<style>
/* Стили для админ-интерфейса */
.admin-tabs-wrapper {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.admin-tabs-nav {
    display: flex;
    background: #f5f5f5;
    border-bottom: 2px solid #e0e0e0;
}

.admin-tab-button {
    flex: 1;
    padding: 16px 24px;
    background: none;
    border: none;
    font-size: 15px;
    font-weight: 500;
    color: #666;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.admin-tab-button:hover {
    background: rgba(33, 150, 243, 0.05);
    color: #2196f3;
}

.admin-tab-button.active {
    color: #2196f3;
    background: #fff;
}

.admin-tab-button.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: #2196f3;
}

.admin-tabs-content {
    padding: 32px;
}

.admin-tab-pane {
    display: none;
}

.admin-tab-pane.active {
    display: block;
}

/* Форма настроек */
.settings-group {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 1px solid #e0e0e0;
}

.settings-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.settings-group-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 16px;
    color: #333;
}

.setting-item {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.setting-label {
    min-width: 200px;
    font-weight: 500;
    color: #555;
}

.setting-input {
    flex: 1;
}

.setting-input input[type="text"],
.setting-input input[type="number"],
.setting-input select,
.setting-input textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.setting-input textarea {
    min-height: 100px;
    resize: vertical;
}

.setting-description {
    font-size: 13px;
    color: #999;
    margin-top: 4px;
}

/* Сортируемый список */
.sortable-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sortable-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: move;
    transition: all 0.2s;
}

.sortable-item:hover {
    background: #f0f0f0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.sortable-item.dragging {
    opacity: 0.5;
    transform: scale(1.02);
}

.drag-handle {
    font-size: 18px;
    color: #999;
    cursor: grab;
}

.drag-handle:active {
    cursor: grabbing;
}

.sortable-item-content {
    flex: 1;
}

.sortable-item-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: #f5f5f5;
    border-color: #999;
}

/* Кнопки действий */
.actions-bar {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e0e0e0;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #2196f3;
    color: #fff;
}

.btn-primary:hover {
    background: #1976d2;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
}

.btn-secondary {
    background: #f5f5f5;
    color: #333;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-success {
    background: #4caf50;
    color: #fff;
}

.btn-success:hover {
    background: #388e3c;
}

.btn-danger {
    background: #f44336;
    color: #fff;
}

.btn-danger:hover {
    background: #d32f2f;
}

/* Статистика */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-card.blue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.green {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card.orange {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card.purple {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

/* Импорт/Экспорт */
.import-export-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.ie-card {
    padding: 24px;
    background: #f9f9f9;
    border: 2px dashed #ddd;
    border-radius: 8px;
    text-align: center;
}

.ie-card h3 {
    margin: 0 0 12px 0;
    color: #333;
}

.ie-card p {
    margin: 0 0 16px 0;
    color: #666;
    font-size: 14px;
}

/* Адаптивность */
@media (max-width: 768px) {
    .admin-tabs-nav {
        flex-direction: column;
    }

    .admin-tabs-content {
        padding: 16px;
    }

    .setting-item {
        flex-direction: column;
        align-items: stretch;
    }

    .setting-label {
        min-width: auto;
    }

    .import-export-section {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="admin-tabs-wrapper">
    <!-- Табы навигации -->
    <div class="admin-tabs-nav">
        <button class="admin-tab-button active" data-tab="general" onclick="switchTab('general')">
            ⚙️ Основные настройки
        </button>
        <button class="admin-tab-button" data-tab="advanced" onclick="switchTab('advanced')">
            🔧 Дополнительно
        </button>
        <button class="admin-tab-button" data-tab="sorting" onclick="switchTab('sorting')">
            📋 Сортировка
        </button>
        <button class="admin-tab-button" data-tab="import-export" onclick="switchTab('import-export')">
            💾 Импорт/Экспорт
        </button>
    </div>

    <!-- Контент табов -->
    <div class="admin-tabs-content">

        <!-- Таб 1: Основные настройки -->
        <div class="admin-tab-pane active" id="tab-general">
            <form id="settings-form" method="POST">
                <?=bitrix_sessid_post()?>
                <input type="hidden" name="action" value="save_settings">

                <div class="settings-group">
                    <div class="settings-group-title">Общие параметры</div>

                    <div class="setting-item">
                        <div class="setting-label">Название модуля:</div>
                        <div class="setting-input">
                            <input type="text" name="MODULE_NAME" value="<?=htmlspecialcharsbx($settings['MODULE_NAME'] ?? '')?>" required>
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">Email администратора:</div>
                        <div class="setting-input">
                            <input type="email" name="ADMIN_EMAIL" value="<?=htmlspecialcharsbx($settings['ADMIN_EMAIL'] ?? '')?>">
                            <div class="setting-description">Уведомления будут отправляться на этот адрес</div>
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">Включить логирование:</div>
                        <div class="setting-input">
                            <input type="checkbox" name="ENABLE_LOGGING" value="Y" <?=($settings['ENABLE_LOGGING'] ?? '') === 'Y' ? 'checked' : ''?>>
                        </div>
                    </div>
                </div>

                <div class="settings-group">
                    <div class="settings-group-title">Производительность</div>

                    <div class="setting-item">
                        <div class="setting-label">Время кеширования (сек):</div>
                        <div class="setting-input">
                            <input type="number" name="CACHE_TIME" value="<?=(int)($settings['CACHE_TIME'] ?? 3600)?>" min="0">
                        </div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">Элементов на странице:</div>
                        <div class="setting-input">
                            <input type="number" name="ITEMS_PER_PAGE" value="<?=(int)($settings['ITEMS_PER_PAGE'] ?? 20)?>" min="1" max="100">
                        </div>
                    </div>
                </div>

                <div class="actions-bar">
                    <button type="submit" class="btn btn-primary">💾 Сохранить настройки</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">🔄 Сбросить</button>
                </div>
            </form>
        </div>

        <!-- Таб 2: Дополнительные настройки -->
        <div class="admin-tab-pane" id="tab-advanced">
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-value">1,234</div>
                    <div class="stat-label">Всего записей</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-value">567</div>
                    <div class="stat-label">Активных</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-value">89</div>
                    <div class="stat-label">За сегодня</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-value">12 MB</div>
                    <div class="stat-label">Размер кеша</div>
                </div>
            </div>

            <div class="settings-group">
                <div class="settings-group-title">Управление кешем</div>

                <div class="setting-item">
                    <div class="setting-label">Текущий размер кеша:</div>
                    <div class="setting-input">
                        <strong>12.5 MB</strong>
                    </div>
                </div>

                <div class="actions-bar">
                    <button type="button" class="btn btn-danger" onclick="clearCache()">
                        🗑️ Очистить кеш
                    </button>
                </div>
            </div>

            <div class="settings-group">
                <div class="settings-group-title">Интеграции</div>

                <div class="setting-item">
                    <div class="setting-label">API ключ:</div>
                    <div class="setting-input">
                        <input type="text" name="API_KEY" value="<?=htmlspecialcharsbx($settings['API_KEY'] ?? '')?>" placeholder="Введите API ключ">
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">Webhook URL:</div>
                    <div class="setting-input">
                        <input type="url" name="WEBHOOK_URL" value="<?=htmlspecialcharsbx($settings['WEBHOOK_URL'] ?? '')?>" placeholder="https://example.com/webhook">
                    </div>
                </div>
            </div>
        </div>

        <!-- Таб 3: Сортировка -->
        <div class="admin-tab-pane" id="tab-sorting">
            <div class="settings-group">
                <div class="settings-group-title">Порядок отображения разделов</div>
                <p style="color: #666; margin-bottom: 16px;">Перетащите элементы для изменения порядка</p>

                <ul class="sortable-list" id="sortable-sections">
                    <li class="sortable-item" data-id="1">
                        <span class="drag-handle">⋮⋮</span>
                        <div class="sortable-item-content">
                            <strong>Главная страница</strong>
                            <div style="font-size: 13px; color: #999;">Приоритет: 1</div>
                        </div>
                        <div class="sortable-item-actions">
                            <button class="btn-icon" title="Редактировать">✏️</button>
                            <button class="btn-icon" title="Удалить">🗑️</button>
                        </div>
                    </li>
                    <li class="sortable-item" data-id="2">
                        <span class="drag-handle">⋮⋮</span>
                        <div class="sortable-item-content">
                            <strong>Каталог</strong>
                            <div style="font-size: 13px; color: #999;">Приоритет: 2</div>
                        </div>
                        <div class="sortable-item-actions">
                            <button class="btn-icon" title="Редактировать">✏️</button>
                            <button class="btn-icon" title="Удалить">🗑️</button>
                        </div>
                    </li>
                    <li class="sortable-item" data-id="3">
                        <span class="drag-handle">⋮⋮</span>
                        <div class="sortable-item-content">
                            <strong>О компании</strong>
                            <div style="font-size: 13px; color: #999;">Приоритет: 3</div>
                        </div>
                        <div class="sortable-item-actions">
                            <button class="btn-icon" title="Редактировать">✏️</button>
                            <button class="btn-icon" title="Удалить">🗑️</button>
                        </div>
                    </li>
                    <li class="sortable-item" data-id="4">
                        <span class="drag-handle">⋮⋮</span>
                        <div class="sortable-item-content">
                            <strong>Контакты</strong>
                            <div style="font-size: 13px; color: #999;">Приоритет: 4</div>
                        </div>
                        <div class="sortable-item-actions">
                            <button class="btn-icon" title="Редактировать">✏️</button>
                            <button class="btn-icon" title="Удалить">🗑️</button>
                        </div>
                    </li>
                </ul>

                <div class="actions-bar">
                    <button type="button" class="btn btn-primary" onclick="saveSortOrder()">
                        💾 Сохранить порядок
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetSortOrder()">
                        🔄 Сбросить
                    </button>
                </div>
            </div>
        </div>

        <!-- Таб 4: Импорт/Экспорт -->
        <div class="admin-tab-pane" id="tab-import-export">
            <div class="import-export-section">
                <div class="ie-card">
                    <h3>📥 Импорт данных</h3>
                    <p>Загрузите файл настроек (JSON)</p>
                    <form method="POST" enctype="multipart/form-data">
                        <?=bitrix_sessid_post()?>
                        <input type="hidden" name="action" value="import">
                        <input type="file" name="import_file" accept=".json" required style="margin-bottom: 12px;">
                        <br>
                        <button type="submit" class="btn btn-primary">Загрузить</button>
                    </form>
                </div>

                <div class="ie-card">
                    <h3>📤 Экспорт данных</h3>
                    <p>Скачать текущие настройки в JSON</p>
                    <form method="POST">
                        <?=bitrix_sessid_post()?>
                        <input type="hidden" name="action" value="export">
                        <button type="submit" class="btn btn-success">Скачать</button>
                    </form>
                </div>
            </div>

            <div class="settings-group" style="margin-top: 32px;">
                <div class="settings-group-title">История изменений</div>
                <table class="adm-list-table">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Пользователь</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>2026-10-09 14:30</td>
                            <td>Администратор</td>
                            <td>Изменение настроек</td>
                        </tr>
                        <tr>
                            <td>2026-10-08 10:15</td>
                            <td>Администратор</td>
                            <td>Экспорт данных</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
// Переключение табов
function switchTab(tabId) {
    // Деактивация всех табов
    document.querySelectorAll('.admin-tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.admin-tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });

    // Активация выбранного
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    document.getElementById(`tab-${tabId}`).classList.add('active');
}

// Drag and Drop сортировка
(function() {
    const sortableList = document.getElementById('sortable-sections');
    if (!sortableList) return;

    let draggingElement = null;

    sortableList.querySelectorAll('.sortable-item').forEach(item => {
        item.draggable = true;

        item.addEventListener('dragstart', (e) => {
            draggingElement = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('dragging');
            draggingElement = null;
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const afterElement = getDragAfterElement(sortableList, e.clientY);
            if (afterElement == null) {
                sortableList.appendChild(draggingElement);
            } else {
                sortableList.insertBefore(draggingElement, afterElement);
            }
        });
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [
            ...container.querySelectorAll('.sortable-item:not(.dragging)')
        ];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
})();

// Сохранение порядка сортировки
function saveSortOrder() {
    const order = [];
    document.querySelectorAll('.sortable-item').forEach((item, index) => {
        order.push({
            id: item.dataset.id,
            sort: index + 1
        });
    });

    console.log('Новый порядок:', order);

    BX.UI.Notification.Center.notify({
        content: 'Порядок сохранён',
        position: 'top-right',
        autoHideDelay: 3000
    });
}

// Сброс порядка сортировки
function resetSortOrder() {
    if (confirm('Сбросить порядок к значениям по умолчанию?')) {
        location.reload();
    }
}

// Сброс формы
function resetForm() {
    if (confirm('Сбросить все изменения?')) {
        document.getElementById('settings-form').reset();
    }
}

// Очистка кеша
function clearCache() {
    if (confirm('Очистить весь кеш модуля?')) {
        BX.ajax.post(
            window.location.href,
            {
                action: 'clear_cache',
                sessid: BX.bitrix_sessid()
            },
            function(response) {
                BX.UI.Notification.Center.notify({
                    content: 'Кеш очищен',
                    position: 'top-right',
                    autoHideDelay: 3000
                });
            }
        );
    }
}

// Обработка формы настроек
document.getElementById('settings-form')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    BX.ajax.post(
        window.location.href,
        formData,
        function(response) {
            BX.UI.Notification.Center.notify({
                content: 'Настройки сохранены',
                position: 'top-right',
                autoHideDelay: 3000
            });
        }
    );
});
</script>

<?php
// Функции обработки действий

function handleSaveSettings($request) {
    $settings = [
        'MODULE_NAME' => $request->get('MODULE_NAME'),
        'ADMIN_EMAIL' => $request->get('ADMIN_EMAIL'),
        'ENABLE_LOGGING' => $request->get('ENABLE_LOGGING'),
        'CACHE_TIME' => $request->get('CACHE_TIME'),
        'ITEMS_PER_PAGE' => $request->get('ITEMS_PER_PAGE'),
    ];

    // Сохранение в опции
    foreach ($settings as $key => $value) {
        COption::SetOptionString('mymodule', $key, $value);
    }

    LocalRedirect($APPLICATION->GetCurPageParam('success=Y', ['success']));
}

function handleExport() {
    $settings = loadSettings();

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="settings_' . date('Y-m-d') . '.json"');
    echo json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    die();
}

function handleImport($request) {
    if (isset($_FILES['import_file'])) {
        $file = $_FILES['import_file'];
        $data = json_decode(file_get_contents($file['tmp_name']), true);

        if ($data) {
            foreach ($data as $key => $value) {
                COption::SetOptionString('mymodule', $key, $value);
            }
        }
    }

    LocalRedirect($APPLICATION->GetCurPageParam('success=Y', ['success']));
}

function handleClearCache() {
    // Очистка кеша
    BXClearCache(true, '/mymodule/');

    echo json_encode(['success' => true]);
    die();
}

function loadSettings() {
    return [
        'MODULE_NAME' => COption::GetOptionString('mymodule', 'MODULE_NAME', 'Мой модуль'),
        'ADMIN_EMAIL' => COption::GetOptionString('mymodule', 'ADMIN_EMAIL', ''),
        'ENABLE_LOGGING' => COption::GetOptionString('mymodule', 'ENABLE_LOGGING', 'N'),
        'CACHE_TIME' => COption::GetOptionString('mymodule', 'CACHE_TIME', '3600'),
        'ITEMS_PER_PAGE' => COption::GetOptionString('mymodule', 'ITEMS_PER_PAGE', '20'),
        'API_KEY' => COption::GetOptionString('mymodule', 'API_KEY', ''),
        'WEBHOOK_URL' => COption::GetOptionString('mymodule', 'WEBHOOK_URL', ''),
    ];
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
