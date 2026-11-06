<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\UI\Extension;

Extension::load(['ui.notification', 'ui.dialogs.messagebox', 'ui.entity-selector', 'ui.buttons']);
?>

<div class="admin-panel" id="<?= $arResult['COMPONENT_ID'] ?>">
    <!-- Заголовок и действия -->
    <div class="panel-header">
        <h2>Управление элементами</h2>
        <div class="panel-actions">
            <button class="btn btn-primary" id="show-notifications-btn">
                Примеры уведомлений
            </button>
            <button class="btn btn-secondary" id="show-dialogs-btn">
                Примеры диалогов
            </button>
            <button class="btn btn-success" id="assign-users-btn">
                Назначить пользователей
            </button>
        </div>
    </div>

    <!-- Фильтры -->
    <div class="panel-filters">
        <label>
            <input type="radio" name="filter" value="all" checked> Все
        </label>
        <label>
            <input type="radio" name="filter" value="active"> Активные
        </label>
        <label>
            <input type="radio" name="filter" value="inactive"> Неактивные
        </label>
        <button class="btn btn-small" id="refresh-btn">Обновить</button>
    </div>

    <!-- Таблица элементов -->
    <div class="panel-content">
        <table class="items-table">
            <thead>
                <tr>
                    <th width="50"><input type="checkbox" id="select-all"></th>
                    <th width="60">Фото</th>
                    <th width="80">ID</th>
                    <th>Название</th>
                    <th width="120">Статус</th>
                    <th width="150">Дата изменения</th>
                    <th width="150">Действия</th>
                </tr>
            </thead>
            <tbody id="items-list">
                <tr>
                    <td colspan="7" class="loading-row">
                        <div class="loader"></div>
                        Загрузка...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Пагинация -->
    <div class="panel-pagination" id="pagination" style="display: none;"></div>
</div>

<style>
.admin-panel {
    max-width: 1200px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}

.panel-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.panel-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #218838;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-small {
    padding: 6px 12px;
    font-size: 13px;
}

.panel-filters {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.panel-filters label {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.items-table thead {
    background: #f8f9fa;
}

.items-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.items-table td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.items-table tbody tr:hover {
    background-color: #f8f9fa;
}

.item-picture {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    object-fit: cover;
}

.item-picture-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    padding: 4px 8px;
    font-size: 12px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
}

.loading-row {
    text-align: center;
    padding: 40px !important;
    color: #6c757d;
}

.loader {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.panel-pagination {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 5px;
}

.pagination-btn {
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    background: white;
    cursor: pointer;
    border-radius: 3px;
}

.pagination-btn:hover {
    background: #f8f9fa;
}

.pagination-btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}
</style>

<script>
BX.ready(function() {
    var componentId = '<?= $arResult['COMPONENT_ID'] ?>';
    var iblockId = <?= $arResult['IBLOCK_ID'] ?>;
    var currentPage = 1;
    var currentFilter = 'all';

    // Загрузка элементов при инициализации
    loadItems();

    // Обработчик фильтров
    BX.bindDelegate(
        document.body,
        'change',
        {attribute: 'name', value: 'filter'},
        function(e) {
            currentFilter = e.target.value;
            currentPage = 1;
            loadItems();
        }
    );

    // Кнопка обновления
    BX.bind(BX('refresh-btn'), 'click', function() {
        loadItems();
    });

    // === ДЕМОНСТРАЦИЯ УВЕДОМЛЕНИЙ ===
    BX.bind(BX('show-notifications-btn'), 'click', function() {
        showNotificationExamples();
    });

    function showNotificationExamples() {
        // 1. Простое уведомление
        BX.UI.Notification.Center.notify({
            content: 'Это простое информационное уведомление',
            autoHideDelay: 3000
        });

        // 2. Успешная операция
        setTimeout(function() {
            BX.UI.Notification.Center.notify({
                content: '<strong>Успех!</strong> Данные сохранены',
                position: 'top-right',
                autoHideDelay: 3000
            });
        }, 500);

        // 3. Предупреждение
        setTimeout(function() {
            BX.UI.Notification.Center.notify({
                content: '<strong>Внимание!</strong> Проверьте введенные данные',
                position: 'top-right',
                autoHideDelay: 4000,
                closeButton: true
            });
        }, 1000);

        // 4. Ошибка
        setTimeout(function() {
            BX.UI.Notification.Center.notify({
                content: '<strong>Ошибка!</strong> Не удалось выполнить операцию',
                position: 'top-right',
                autoHideDelay: 5000,
                closeButton: true,
                category: 'error'
            });
        }, 1500);
    }

    // === ДЕМОНСТРАЦИЯ ДИАЛОГОВ ===
    BX.bind(BX('show-dialogs-btn'), 'click', function() {
        showDialogExamples();
    });

    function showDialogExamples() {
        BX.Runtime.loadExtension('ui.dialogs.messagebox').then(function() {
            // 1. Простое информационное окно
            BX.UI.Dialogs.MessageBox.alert(
                'Это простое информационное окно',
                function(messageBox) {
                    messageBox.close();

                    // 2. Подтверждение действия
                    setTimeout(function() {
                        BX.UI.Dialogs.MessageBox.confirm(
                            'Вы уверены, что хотите продолжить?',
                            function(messageBox) {
                                messageBox.close();

                                BX.UI.Notification.Center.notify({
                                    content: 'Вы нажали "Да"'
                                });

                                // 3. Диалог с кастомными кнопками
                                setTimeout(function() {
                                    showCustomDialog();
                                }, 1000);
                            },
                            'Да, продолжить'
                        );
                    }, 500);
                }
            );
        });
    }

    function showCustomDialog() {
        BX.UI.Dialogs.MessageBox.show({
            title: 'Выберите действие',
            message: 'Что вы хотите сделать с выбранными элементами?',
            modal: true,
            buttons: [
                new BX.UI.Button({
                    text: 'Активировать',
                    color: BX.UI.Button.Color.SUCCESS,
                    onclick: function() {
                        this.getContext().close();
                        BX.UI.Notification.Center.notify({
                            content: 'Элементы активированы'
                        });
                    }
                }),
                new BX.UI.Button({
                    text: 'Деактивировать',
                    color: BX.UI.Button.Color.DANGER,
                    onclick: function() {
                        this.getContext().close();
                        BX.UI.Notification.Center.notify({
                            content: 'Элементы деактивированы'
                        });
                    }
                }),
                new BX.UI.CancelButton({
                    text: 'Отмена',
                    onclick: function() {
                        this.getContext().close();
                    }
                })
            ]
        });
    }

    // === СЕЛЕКТОР ПОЛЬЗОВАТЕЛЕЙ ===
    BX.bind(BX('assign-users-btn'), 'click', function() {
        showUserSelector();
    });

    function showUserSelector() {
        BX.Runtime.loadExtension('ui.entity-selector').then(function() {
            var selectedUsers = [];

            var dialog = new BX.UI.EntitySelector.Dialog({
                targetNode: document.body,
                context: 'admin-panel-users',
                multiple: true,
                dropdownMode: false,
                showAvatars: true,
                enableSearch: true,
                width: 500,
                height: 400,
                entities: [
                    {
                        id: 'user',
                        options: {
                            inviteEmployeeLink: false
                        }
                    }
                ],
                events: {
                    'Item:onSelect': function(event) {
                        var item = event.getData().item;
                        var userId = item.id.toString().replace('user-', '');
                        selectedUsers.push({
                            id: userId,
                            name: item.title
                        });
                        console.log('Выбран:', item.title);
                    },
                    'Item:onDeselect': function(event) {
                        var item = event.getData().item;
                        var userId = item.id.toString().replace('user-', '');
                        selectedUsers = selectedUsers.filter(function(u) {
                            return u.id !== userId;
                        });
                        console.log('Удален:', item.title);
                    },
                    'onClose': function() {
                        if (selectedUsers.length > 0) {
                            var names = selectedUsers.map(function(u) { return u.name; }).join(', ');
                            BX.UI.Notification.Center.notify({
                                content: 'Выбрано пользователей: ' + selectedUsers.length + '<br>' + names,
                                autoHideDelay: 5000
                            });
                        }
                    }
                }
            });

            dialog.show();
        });
    }

    // === ЗАГРУЗКА ЭЛЕМЕНТОВ ===
    function loadItems() {
        var tbody = BX('items-list');
        tbody.innerHTML = '<tr><td colspan="7" class="loading-row"><div class="loader"></div>Загрузка...</td></tr>';

        BX.ajax.runComponentAction(
            'bitrix:module.14.2.admin-panel',
            'getItems',
            {
                mode: 'class',
                data: {
                    page: currentPage,
                    filter: currentFilter
                }
            }
        ).then(
            function(response) {
                if (response.data.success) {
                    renderItems(response.data.items);
                    renderPagination(response.data.pagination);
                }
            },
            function(response) {
                tbody.innerHTML = '<tr><td colspan="7" class="loading-row">Ошибка загрузки данных</td></tr>';
                console.error('Ошибка:', response);
            }
        );
    }

    // === ОТРИСОВКА ЭЛЕМЕНТОВ ===
    function renderItems(items) {
        var tbody = BX('items-list');
        tbody.innerHTML = '';

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="loading-row">Элементы не найдены</td></tr>';
            return;
        }

        items.forEach(function(item) {
            var row = BX.create('tr', {
                attrs: { 'data-id': item.id }
            });

            // Чекбокс
            var checkCell = BX.create('td');
            var checkbox = BX.create('input', {
                props: { type: 'checkbox', className: 'item-checkbox' },
                attrs: { 'data-id': item.id }
            });
            checkCell.appendChild(checkbox);
            row.appendChild(checkCell);

            // Картинка
            var pictureCell = BX.create('td');
            if (item.picture) {
                var img = BX.create('img', {
                    props: { className: 'item-picture', src: item.picture, alt: item.name }
                });
                pictureCell.appendChild(img);
            } else {
                var placeholder = BX.create('div', {
                    props: { className: 'item-picture-placeholder' },
                    text: item.name.charAt(0).toUpperCase()
                });
                pictureCell.appendChild(placeholder);
            }
            row.appendChild(pictureCell);

            // ID
            row.appendChild(BX.create('td', { text: item.id }));

            // Название
            row.appendChild(BX.create('td', { text: item.name }));

            // Статус
            var statusCell = BX.create('td');
            var statusBadge = BX.create('span', {
                props: { className: 'status-badge ' + (item.active ? 'status-active' : 'status-inactive') },
                text: item.active ? 'Активен' : 'Неактивен'
            });
            statusCell.appendChild(statusBadge);
            row.appendChild(statusCell);

            // Дата
            row.appendChild(BX.create('td', { text: item.date }));

            // Действия
            var actionsCell = BX.create('td');
            var actionsDiv = BX.create('div', { props: { className: 'action-buttons' } });

            // Кнопка активации/деактивации
            var toggleBtn = BX.create('button', {
                props: { className: 'action-btn btn-secondary' },
                text: item.active ? 'Деактивировать' : 'Активировать',
                events: {
                    click: function() {
                        toggleItemActive(item.id, !item.active);
                    }
                }
            });
            actionsDiv.appendChild(toggleBtn);

            // Кнопка удаления
            var deleteBtn = BX.create('button', {
                props: { className: 'action-btn btn-danger' },
                text: 'Удалить',
                events: {
                    click: function() {
                        confirmDelete(item.id, item.name);
                    }
                }
            });
            actionsDiv.appendChild(deleteBtn);

            actionsCell.appendChild(actionsDiv);
            row.appendChild(actionsCell);

            tbody.appendChild(row);
        });
    }

    // === ПАГИНАЦИЯ ===
    function renderPagination(pagination) {
        var paginationDiv = BX('pagination');
        paginationDiv.innerHTML = '';

        if (pagination.pages <= 1) {
            paginationDiv.style.display = 'none';
            return;
        }

        paginationDiv.style.display = 'flex';

        for (var i = 1; i <= pagination.pages; i++) {
            (function(page) {
                var btn = BX.create('button', {
                    props: { className: 'pagination-btn' + (page === currentPage ? ' active' : '') },
                    text: page,
                    events: {
                        click: function() {
                            currentPage = page;
                            loadItems();
                        }
                    }
                });
                paginationDiv.appendChild(btn);
            })(i);
        }
    }

    // === АКТИВАЦИЯ/ДЕАКТИВАЦИЯ ===
    function toggleItemActive(id, active) {
        BX.ajax.runComponentAction(
            'bitrix:module.14.2.admin-panel',
            'activateItem',
            {
                mode: 'class',
                data: { id: id, active: active }
            }
        ).then(
            function(response) {
                if (response.data.success) {
                    BX.UI.Notification.Center.notify({
                        content: response.data.message,
                        autoHideDelay: 3000
                    });
                    loadItems();
                }
            },
            function(response) {
                BX.UI.Notification.Center.notify({
                    content: 'Ошибка при обновлении элемента',
                    autoHideDelay: 3000
                });
            }
        );
    }

    // === УДАЛЕНИЕ С ПОДТВЕРЖДЕНИЕМ ===
    function confirmDelete(id, name) {
        BX.Runtime.loadExtension('ui.dialogs.messagebox').then(function() {
            BX.UI.Dialogs.MessageBox.confirm(
                'Вы уверены, что хотите удалить элемент "' + name + '"?',
                function(messageBox) {
                    deleteItem(id);
                    messageBox.close();
                },
                'Удалить'
            );
        });
    }

    function deleteItem(id) {
        BX.ajax.runComponentAction(
            'bitrix:module.14.2.admin-panel',
            'deleteItem',
            {
                mode: 'class',
                data: { id: id }
            }
        ).then(
            function(response) {
                if (response.data.success) {
                    BX.UI.Notification.Center.notify({
                        content: response.data.message,
                        autoHideDelay: 3000
                    });
                    loadItems();
                }
            },
            function(response) {
                BX.UI.Notification.Center.notify({
                    content: 'Ошибка при удалении элемента',
                    autoHideDelay: 3000
                });
            }
        );
    }
});
</script>
