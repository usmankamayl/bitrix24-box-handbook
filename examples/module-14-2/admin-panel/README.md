# Задача 3: Админ-панель с уведомлениями и диалогами

## Описание

Полноценная **админ-панель** с демонстрацией всех UI компонентов BX.JS:
- **BX.UI.Notification** - уведомления (успех, ошибка, предупреждение)
- **BX.UI.Dialogs.MessageBox** - диалоговые окна подтверждения
- **BX.UI.EntitySelector** - выбор пользователей
- **BX.UI.Button** - кнопки с разными стилями
- CRUD операции через AJAX
- Пагинация и фильтрация

## Структура компонента

```
admin-panel/
├── .description.php           # Описание компонента
├── .parameters.php            # Параметры компонента
├── class.php                  # Класс с AJAX методами
└── templates/.default/
    └── template.php           # Шаблон с админ-панелью
```

## Установка

### 1. Копируем компонент

```bash
cp -r admin-panel /local/components/bitrix/module.14.2.admin-panel/
```

### 2. Подключаем на странице

```php
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->SetTitle('Админ-панель');
?>

<h1>Управление элементами</h1>

<?php
$APPLICATION->IncludeComponent(
    'bitrix:module.14.2.admin-panel',
    '.default',
    [
        'IBLOCK_ID' => 1,          // ID вашего инфоблока
        'PAGE_SIZE' => 20,         // Элементов на страницу
    ]
);
?>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
?>
```

## Демонстрируемые возможности

### 1. BX.UI.Notification - Уведомления

```javascript
// Простое уведомление
BX.UI.Notification.Center.notify({
    content: 'Операция выполнена успешно',
    autoHideDelay: 3000
});

// С позиционированием
BX.UI.Notification.Center.notify({
    content: '<strong>Успех!</strong> Данные сохранены',
    position: 'top-right',        // top-right, top-left, bottom-right, bottom-left
    autoHideDelay: 3000
});

// С кнопкой закрытия
BX.UI.Notification.Center.notify({
    content: '<strong>Внимание!</strong> Проверьте данные',
    position: 'top-right',
    autoHideDelay: 5000,
    closeButton: true,
    category: 'warning'           // Для группировки
});
```

**В компоненте:** Кнопка "Примеры уведомлений" показывает 4 типа уведомлений с задержкой.

### 2. BX.UI.Dialogs.MessageBox - Диалоги

```javascript
// Простое информационное окно
BX.UI.Dialogs.MessageBox.alert(
    'Это информационное сообщение',
    function(messageBox) {
        messageBox.close();
    }
);

// Подтверждение действия
BX.UI.Dialogs.MessageBox.confirm(
    'Вы уверены, что хотите удалить элемент?',
    function(messageBox) {
        // Пользователь нажал "Да"
        deleteItem();
        messageBox.close();
    },
    'Удалить'  // Текст кнопки подтверждения
);

// Диалог с кастомными кнопками
BX.UI.Dialogs.MessageBox.show({
    title: 'Выберите действие',
    message: 'Что вы хотите сделать?',
    modal: true,
    buttons: [
        new BX.UI.Button({
            text: 'Активировать',
            color: BX.UI.Button.Color.SUCCESS,
            onclick: function() {
                activateItems();
                this.getContext().close();
            }
        }),
        new BX.UI.Button({
            text: 'Деактивировать',
            color: BX.UI.Button.Color.DANGER,
            onclick: function() {
                deactivateItems();
                this.getContext().close();
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
```

**В компоненте:** Кнопка "Примеры диалогов" показывает 3 типа окон последовательно.

### 3. BX.UI.EntitySelector - Выбор пользователей

```javascript
BX.Runtime.loadExtension('ui.entity-selector').then(function() {
    var selectedUsers = [];
    
    var dialog = new BX.UI.EntitySelector.Dialog({
        targetNode: document.body,
        context: 'my-selector',
        multiple: true,            // Множественный выбор
        dropdownMode: false,       // Полноэкранный режим
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
            },
            'Item:onDeselect': function(event) {
                var item = event.getData().item;
                var userId = item.id.toString().replace('user-', '');
                selectedUsers = selectedUsers.filter(function(u) {
                    return u.id !== userId;
                });
            },
            'onClose': function() {
                console.log('Выбрано пользователей:', selectedUsers.length);
            }
        }
    });
    
    dialog.show();
});
```

**В компоненте:** Кнопка "Назначить пользователей" открывает селектор.

### 4. CRUD операции

#### Загрузка элементов

```javascript
BX.ajax.runComponentAction(
    'bitrix:module.14.2.admin-panel',
    'getItems',
    {
        mode: 'class',
        data: {
            page: 1,
            filter: 'active'
        }
    }
).then(function(response) {
    if (response.data.success) {
        renderItems(response.data.items);
        renderPagination(response.data.pagination);
    }
});
```

#### Удаление с подтверждением

```javascript
function confirmDelete(id, name) {
    BX.Runtime.loadExtension('ui.dialogs.messagebox').then(function() {
        BX.UI.Dialogs.MessageBox.confirm(
            'Вы уверены, что хотите удалить "' + name + '"?',
            function(messageBox) {
                // Выполняем удаление
                BX.ajax.runComponentAction(
                    'bitrix:module.14.2.admin-panel',
                    'deleteItem',
                    {
                        mode: 'class',
                        data: { id: id }
                    }
                ).then(function(response) {
                    if (response.data.success) {
                        BX.UI.Notification.Center.notify({
                            content: 'Элемент удален',
                            autoHideDelay: 3000
                        });
                        loadItems(); // Перезагружаем список
                    }
                });
                
                messageBox.close();
            },
            'Удалить'
        );
    });
}
```

#### Активация/деактивация

```javascript
function toggleItemActive(id, active) {
    BX.ajax.runComponentAction(
        'bitrix:module.14.2.admin-panel',
        'activateItem',
        {
            mode: 'class',
            data: {
                id: id,
                active: active
            }
        }
    ).then(function(response) {
        if (response.data.success) {
            BX.UI.Notification.Center.notify({
                content: response.data.message,
                autoHideDelay: 3000
            });
            loadItems();
        }
    });
}
```

## Функционал компонента

### Таблица элементов

- ✅ Загрузка элементов из инфоблока через AJAX
- ✅ Отображение картинки/placeholder
- ✅ Статус элемента (активен/неактивен)
- ✅ Дата последнего изменения
- ✅ Действия: активация/деактивация, удаление

### Фильтрация

```javascript
// Переключение фильтра
BX.bindDelegate(
    document.body,
    'change',
    {attribute: 'name', value: 'filter'},
    function(e) {
        currentFilter = e.target.value; // 'all', 'active', 'inactive'
        currentPage = 1;
        loadItems();
    }
);
```

### Пагинация

```javascript
function renderPagination(pagination) {
    // pagination = { page: 1, pageSize: 20, total: 150, pages: 8 }
    
    for (var i = 1; i <= pagination.pages; i++) {
        var btn = BX.create('button', {
            props: { className: 'pagination-btn' + (i === currentPage ? ' active' : '') },
            text: i,
            events: {
                click: function() {
                    currentPage = i;
                    loadItems();
                }
            }
        });
        paginationDiv.appendChild(btn);
    }
}
```

## Расширение функционала

### 1. Множественное удаление

```javascript
// Чекбокс "Выбрать все"
BX.bind(BX('select-all'), 'change', function(e) {
    var checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = e.target.checked;
    });
});

// Удаление выбранных
function deleteSelected() {
    var checkboxes = document.querySelectorAll('.item-checkbox:checked');
    var ids = [];
    
    checkboxes.forEach(function(cb) {
        ids.push(cb.getAttribute('data-id'));
    });
    
    if (ids.length === 0) {
        BX.UI.Notification.Center.notify({
            content: 'Выберите элементы для удаления'
        });
        return;
    }
    
    BX.UI.Dialogs.MessageBox.confirm(
        'Удалить выбранные элементы (' + ids.length + ')?',
        function(messageBox) {
            // Удаляем через цикл или batch-запрос
            Promise.all(ids.map(function(id) {
                return BX.ajax.runComponentAction(
                    'bitrix:module.14.2.admin-panel',
                    'deleteItem',
                    { mode: 'class', data: { id: id } }
                );
            })).then(function() {
                BX.UI.Notification.Center.notify({
                    content: 'Удалено элементов: ' + ids.length
                });
                loadItems();
            });
            
            messageBox.close();
        },
        'Удалить'
    );
}
```

### 2. Inline редактирование

```javascript
// Двойной клик по названию для редактирования
BX.bind(nameCell, 'dblclick', function() {
    var currentName = this.textContent;
    
    var input = BX.create('input', {
        props: { type: 'text', value: currentName },
        events: {
            blur: function() {
                saveNewName(itemId, this.value);
            },
            keydown: function(e) {
                if (e.key === 'Enter') {
                    this.blur();
                } else if (e.key === 'Escape') {
                    this.value = currentName;
                    this.blur();
                }
            }
        }
    });
    
    this.innerHTML = '';
    this.appendChild(input);
    input.focus();
});

function saveNewName(id, newName) {
    BX.ajax.runComponentAction(
        'bitrix:module.14.2.admin-panel',
        'updateName',
        {
            mode: 'class',
            data: { id: id, name: newName }
        }
    ).then(function(response) {
        if (response.data.success) {
            BX.UI.Notification.Center.notify({
                content: 'Название обновлено'
            });
        }
    });
}
```

### 3. Экспорт в CSV

```javascript
function exportToCSV() {
    var rows = [];
    
    // Заголовки
    rows.push(['ID', 'Название', 'Статус', 'Дата']);
    
    // Данные
    document.querySelectorAll('#items-list tr').forEach(function(tr) {
        var id = tr.getAttribute('data-id');
        if (!id) return;
        
        var cells = tr.querySelectorAll('td');
        rows.push([
            cells[2].textContent, // ID
            cells[3].textContent, // Название
            cells[4].textContent, // Статус
            cells[5].textContent  // Дата
        ]);
    });
    
    // Формируем CSV
    var csv = rows.map(function(row) {
        return row.join(';');
    }).join('\n');
    
    // Скачивание файла
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'export_' + Date.now() + '.csv';
    link.click();
    
    BX.UI.Notification.Center.notify({
        content: 'CSV файл экспортирован'
    });
}
```

### 4. Drag & Drop сортировка

```javascript
// Подключаем Sortable.js или используем HTML5 Drag API
var tbody = document.getElementById('items-list');
var sortable = new Sortable(tbody, {
    animation: 150,
    handle: '.drag-handle',
    onEnd: function(evt) {
        var itemId = evt.item.getAttribute('data-id');
        var newIndex = evt.newIndex;
        
        BX.ajax.runComponentAction(
            'bitrix:module.14.2.admin-panel',
            'updateSort',
            {
                mode: 'class',
                data: {
                    id: itemId,
                    sort: newIndex * 100
                }
            }
        ).then(function(response) {
            BX.UI.Notification.Center.notify({
                content: 'Порядок сохранен'
            });
        });
    }
});
```

### 5. Фильтр с datepicker

```javascript
BX.Runtime.loadExtension('main.date').then(function() {
    var calendar = BX.calendar({
        node: document.getElementById('date-filter'),
        field: 'date_from',
        form: '',
        bTime: false,
        callback: function(value) {
            currentFilter.dateFrom = value;
            loadItems();
        }
    });
});
```

## UI/UX оптимизации

### 1. Skeleton loading

```javascript
function showSkeleton() {
    var tbody = BX('items-list');
    tbody.innerHTML = '';
    
    for (var i = 0; i < 5; i++) {
        var row = BX.create('tr', {
            props: { className: 'skeleton-row' }
        });
        
        for (var j = 0; j < 7; j++) {
            var cell = BX.create('td');
            var skeleton = BX.create('div', {
                props: { className: 'skeleton-box' }
            });
            cell.appendChild(skeleton);
            row.appendChild(cell);
        }
        
        tbody.appendChild(row);
    }
}

// CSS
.skeleton-box {
    height: 20px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
    border-radius: 4px;
}

@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
```

### 2. Плавные переходы

```css
.items-table tbody tr {
    transition: all 0.3s ease;
}

.items-table tbody tr.removing {
    opacity: 0;
    transform: translateX(-20px);
}
```

```javascript
function deleteItem(id) {
    var row = document.querySelector('tr[data-id="' + id + '"]');
    BX.addClass(row, 'removing');
    
    setTimeout(function() {
        // AJAX удаление после анимации
        BX.ajax.runComponentAction(...).then(function() {
            BX.remove(row);
        });
    }, 300);
}
```

### 3. Оптимистичные обновления

```javascript
function toggleItemActive(id, active) {
    // Сразу обновляем UI
    var row = document.querySelector('tr[data-id="' + id + '"]');
    var badge = row.querySelector('.status-badge');
    badge.textContent = active ? 'Активен' : 'Неактивен';
    badge.className = 'status-badge ' + (active ? 'status-active' : 'status-inactive');
    
    // Отправляем запрос
    BX.ajax.runComponentAction(
        'bitrix:module.14.2.admin-panel',
        'activateItem',
        { mode: 'class', data: { id: id, active: active } }
    ).then(
        function(response) {
            // Успех - UI уже обновлен
            BX.UI.Notification.Center.notify({
                content: response.data.message
            });
        },
        function(response) {
            // Ошибка - откатываем изменения
            badge.textContent = active ? 'Неактивен' : 'Активен';
            badge.className = 'status-badge ' + (active ? 'status-inactive' : 'status-active');
            
            BX.UI.Notification.Center.notify({
                content: 'Ошибка при обновлении'
            });
        }
    );
}
```

## Тестирование

### 1. Проверка уведомлений

```javascript
// В консоли браузера
BX.UI.Notification.Center.notify({
    content: 'Тестовое уведомление',
    autoHideDelay: 3000
});
```

### 2. Проверка диалогов

```javascript
BX.Runtime.loadExtension('ui.dialogs.messagebox').then(function() {
    BX.UI.Dialogs.MessageBox.confirm(
        'Тестовое подтверждение',
        function(messageBox) {
            console.log('Пользователь нажал ОК');
            messageBox.close();
        }
    );
});
```

### 3. Проверка селектора пользователей

```javascript
BX.Runtime.loadExtension('ui.entity-selector').then(function() {
    var dialog = new BX.UI.EntitySelector.Dialog({
        targetNode: document.body,
        entities: [{ id: 'user' }]
    });
    dialog.show();
});
```

## Выводы

Эта задача демонстрирует:

✅ **BX.UI.Notification** - красивые уведомления  
✅ **BX.UI.Dialogs.MessageBox** - диалоги подтверждения  
✅ **BX.UI.EntitySelector** - выбор пользователей  
✅ **BX.UI.Button** - кнопки с цветами  
✅ **CRUD операции** - через BX.ajax  
✅ **Пагинация и фильтрация** - клиентская логика  
✅ **UX паттерны** - loader, анимации, оптимистичные обновления

---

**Модуль 14.2 завершен!** 🎉

Все 3 задачи демонстрируют полный набор возможностей BX.JS для создания современных интерактивных интерфейсов в Bitrix.
