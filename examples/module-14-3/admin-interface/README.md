# Задача 3: Админ-интерфейс с табами

## Описание

Полноценная административная страница с многовкладочным интерфейсом, drag-and-drop сортировкой, массовыми операциями и экспортом/импортом данных.

## Возможности

### 1. Многовкладочный интерфейс
- **Основные настройки** — общие параметры модуля
- **Дополнительно** — статистика, кеш, интеграции
- **Сортировка** — drag-and-drop управление порядком
- **Импорт/Экспорт** — обмен данными, история

### 2. Drag-and-Drop сортировка
- Визуальное перетаскивание элементов
- Мгновенное обновление порядка
- Индикация перетаскивания
- Сохранение в базу данных

### 3. Управление настройками
- Валидация полей
- AJAX сохранение
- Уведомления о результатах
- Сброс к значениям по умолчанию

### 4. Импорт/Экспорт
- Экспорт настроек в JSON
- Импорт из файла
- История изменений
- Бэкапы конфигурации

### 5. Статистика
- Красивые карточки с метриками
- Градиентные фоны
- Анимированные счетчики
- Управление кешем

## Установка

### 1. Разместите файл в админке

```bash
# Путь в проекте Bitrix
/local/admin/mymodule_settings.php
```

### 2. Добавьте пункт меню

В файле `/local/php_interface/init.php`:

```php
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler(
    'main',
    'OnBuildGlobalMenu',
    function(&$aGlobalMenu, &$aModuleMenu) {
        $aModuleMenu[] = [
            'parent_menu' => 'global_menu_settings',
            'section' => 'mymodule',
            'sort' => 100,
            'text' => 'Мой модуль',
            'title' => 'Управление модулем',
            'url' => '/local/admin/mymodule_settings.php',
            'icon' => 'settings_menu_icon',
            'page_icon' => 'settings_page_icon',
            'items_id' => 'menu_mymodule',
            'items' => [
                [
                    'text' => 'Настройки',
                    'url' => '/local/admin/mymodule_settings.php',
                    'more_url' => [],
                    'title' => 'Настройки модуля'
                ],
            ],
        ];
    }
);
```

### 3. Настройте права доступа

```php
// Проверка прав в начале файла
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm("Доступ запрещен");
}
```

## Структура файлов

```
admin-interface/
├── admin_page.php           # Основная страница
├── README.md               # Документация
└── examples/               # Примеры использования
    ├── custom_list.php     # Кастомный список
    └── import_handler.php  # Обработчик импорта
```

## Использование

### Открытие страницы

```
/local/admin/mymodule_settings.php
```

### Доступ через меню

**Настройки → Мой модуль → Настройки**

## API функции

### Переключение табов

```javascript
switchTab('general');      // Основные настройки
switchTab('advanced');     // Дополнительно
switchTab('sorting');      // Сортировка
switchTab('import-export'); // Импорт/Экспорт
```

### Сохранение настроек

```javascript
// Сохранение через AJAX
document.getElementById('settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    BX.ajax.post(window.location.href, formData, function(response) {
        BX.UI.Notification.Center.notify({
            content: 'Настройки сохранены',
            position: 'top-right'
        });
    });
});
```

### Drag-and-Drop сортировка

```javascript
// Получение текущего порядка
function saveSortOrder() {
    const order = [];
    
    document.querySelectorAll('.sortable-item').forEach((item, index) => {
        order.push({
            id: item.dataset.id,
            sort: index + 1
        });
    });
    
    // Отправка на сервер
    BX.ajax.post('/local/ajax/save_order.php', {
        order: JSON.stringify(order)
    });
}
```

### Очистка кеша

```javascript
function clearCache() {
    if (confirm('Очистить весь кеш модуля?')) {
        BX.ajax.post(window.location.href, {
            action: 'clear_cache',
            sessid: BX.bitrix_sessid()
        }, function(response) {
            BX.UI.Notification.Center.notify({
                content: 'Кеш очищен'
            });
        });
    }
}
```

## Обработчики действий

### Сохранение настроек

```php
function handleSaveSettings($request) {
    $settings = [
        'MODULE_NAME' => $request->get('MODULE_NAME'),
        'ADMIN_EMAIL' => $request->get('ADMIN_EMAIL'),
        'ENABLE_LOGGING' => $request->get('ENABLE_LOGGING'),
        'CACHE_TIME' => $request->get('CACHE_TIME'),
        'ITEMS_PER_PAGE' => $request->get('ITEMS_PER_PAGE'),
    ];
    
    // Валидация
    if (empty($settings['MODULE_NAME'])) {
        throw new \Exception('Название модуля обязательно');
    }
    
    // Сохранение
    foreach ($settings as $key => $value) {
        COption::SetOptionString('mymodule', $key, $value);
    }
    
    return true;
}
```

### Экспорт данных

```php
function handleExport() {
    $settings = loadSettings();
    
    // Дополнительные данные для экспорта
    $exportData = [
        'version' => '1.0',
        'date' => date('Y-m-d H:i:s'),
        'settings' => $settings,
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="settings_' . date('Y-m-d') . '.json"');
    
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    die();
}
```

### Импорт данных

```php
function handleImport($request) {
    if (!isset($_FILES['import_file'])) {
        throw new \Exception('Файл не выбран');
    }
    
    $file = $_FILES['import_file'];
    
    // Проверка типа файла
    if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
        throw new \Exception('Неверный формат файла');
    }
    
    // Чтение и парсинг
    $content = file_get_contents($file['tmp_name']);
    $data = json_decode($content, true);
    
    if (!$data || !isset($data['settings'])) {
        throw new \Exception('Некорректный файл настроек');
    }
    
    // Импорт настроек
    foreach ($data['settings'] as $key => $value) {
        COption::SetOptionString('mymodule', $key, $value);
    }
    
    // Логирование
    logAction('import', $USER->GetID(), 'Импорт настроек');
    
    return true;
}
```

### Очистка кеша

```php
function handleClearCache() {
    // Очистка кеша модуля
    BXClearCache(true, '/mymodule/');
    
    // Очистка управляемого кеша
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    $cache->cleanDir('/mymodule/');
    
    // Логирование
    logAction('clear_cache', $USER->GetID(), 'Очистка кеша');
    
    return ['success' => true, 'message' => 'Кеш очищен'];
}
```

## Кастомизация

### Добавление нового таба

```php
// HTML
<button class="admin-tab-button" data-tab="mytab" onclick="switchTab('mytab')">
    🎨 Мой таб
</button>

<div class="admin-tab-pane" id="tab-mytab">
    <div class="settings-group">
        <div class="settings-group-title">Мой раздел</div>
        <!-- Ваш контент -->
    </div>
</div>
```

### Добавление кастомного поля

```php
<div class="setting-item">
    <div class="setting-label">Мой параметр:</div>
    <div class="setting-input">
        <input type="text" name="MY_PARAM" value="">
        <div class="setting-description">Описание параметра</div>
    </div>
</div>
```

### Добавление статистической карточки

```php
<div class="stat-card blue">
    <div class="stat-value">
        <?= number_format($myValue) ?>
    </div>
    <div class="stat-label">Моя метрика</div>
</div>
```

### Кастомная валидация

```javascript
document.getElementById('settings-form').addEventListener('submit', function(e) {
    const email = this.querySelector('[name="ADMIN_EMAIL"]').value;
    
    if (email && !isValidEmail(email)) {
        e.preventDefault();
        
        BX.UI.Notification.Center.notify({
            content: 'Некорректный email',
            position: 'top-right'
        });
        
        return false;
    }
});

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
```

## Примеры использования

### Пример 1: Кастомный список с массовыми операциями

```php
<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

$sTableID = "tbl_my_items";
$oSort = new CAdminSorting($sTableID, "ID", "DESC");
$lAdmin = new CAdminList($sTableID, $oSort);

// Заголовки
$lAdmin->AddHeaders([
    ["id" => "ID", "content" => "ID", "sort" => "ID", "default" => true],
    ["id" => "NAME", "content" => "Название", "sort" => "NAME", "default" => true],
    ["id" => "ACTIVE", "content" => "Активность", "sort" => "ACTIVE", "default" => true],
]);

// Групповые действия
$lAdmin->AddGroupActionTable([
    "delete" => "Удалить",
    "activate" => "Активировать",
    "deactivate" => "Деактивировать",
]);

// Обработка групповых действий
if ($arID = $lAdmin->GroupAction()) {
    foreach ($arID as $ID) {
        switch ($_REQUEST['action']) {
            case 'delete':
                MyTable::delete($ID);
                break;
            case 'activate':
                MyTable::update($ID, ['ACTIVE' => 'Y']);
                break;
            case 'deactivate':
                MyTable::update($ID, ['ACTIVE' => 'N']);
                break;
        }
    }
}

// Получение данных
$rsData = MyTable::getList([
    'select' => ['*'],
    'order' => [$by => $order]
]);

// Заполнение
while ($arItem = $rsData->Fetch()) {
    $row =& $lAdmin->AddRow($arItem["ID"], $arItem);
    
    $row->AddActions([
        [
            "ICON" => "edit",
            "TEXT" => "Редактировать",
            "ACTION" => $lAdmin->ActionRedirect("edit.php?ID=".$arItem["ID"])
        ],
        [
            "ICON" => "delete",
            "TEXT" => "Удалить",
            "ACTION" => "if(confirm('Удалить?')) ".$lAdmin->ActionDoGroup($arItem["ID"], "delete")
        ]
    ]);
}

$lAdmin->DisplayList();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
```

### Пример 2: Обработчик импорта с валидацией

```php
<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    try {
        if (!isset($_FILES['import_file'])) {
            throw new \Exception('Файл не выбран');
        }
        
        $file = $_FILES['import_file'];
        
        // Проверка размера (макс 10 МБ)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new \Exception('Файл слишком большой');
        }
        
        // Проверка расширения
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['json', 'csv', 'xml'];
        
        if (!in_array($ext, $allowedExts)) {
            throw new \Exception('Неподдерживаемый формат файла');
        }
        
        // Обработка по типу файла
        switch ($ext) {
            case 'json':
                $data = json_decode(file_get_contents($file['tmp_name']), true);
                break;
            case 'csv':
                $data = parseCsv($file['tmp_name']);
                break;
            case 'xml':
                $data = parseXml($file['tmp_name']);
                break;
        }
        
        // Валидация данных
        $validatedData = validateImportData($data);
        
        // Импорт
        $imported = 0;
        $errors = [];
        
        foreach ($validatedData as $item) {
            try {
                MyTable::add($item);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        
        // Результат
        if ($imported > 0) {
            CAdminMessage::ShowNote("Импортировано: $imported записей");
        }
        
        if (!empty($errors)) {
            CAdminMessage::ShowError("Ошибки: " . implode(', ', $errors));
        }
        
    } catch (\Exception $e) {
        CAdminMessage::ShowError($e->getMessage());
    }
}

function validateImportData($data) {
    $validated = [];
    
    foreach ($data as $item) {
        // Проверка обязательных полей
        if (empty($item['NAME'])) {
            continue;
        }
        
        // Очистка данных
        $validated[] = [
            'NAME' => htmlspecialchars($item['NAME']),
            'EMAIL' => filter_var($item['EMAIL'], FILTER_VALIDATE_EMAIL),
            'ACTIVE' => $item['ACTIVE'] === 'Y' ? 'Y' : 'N',
        ];
    }
    
    return $validated;
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
```

### Пример 3: Расширенная статистика

```php
<div class="stats-grid">
    <?php
    // Получение статистики
    $stats = [
        [
            'value' => MyTable::getCount(['ACTIVE' => 'Y']),
            'label' => 'Активных записей',
            'color' => 'blue',
            'icon' => '✅'
        ],
        [
            'value' => MyTable::getCount(['DATE_CREATE' => new \Bitrix\Main\Type\DateTime()]),
            'label' => 'Создано сегодня',
            'color' => 'green',
            'icon' => '📅'
        ],
        [
            'value' => formatBytes(getCacheSize()),
            'label' => 'Размер кеша',
            'color' => 'orange',
            'icon' => '💾'
        ],
        [
            'value' => MyTable::getCount(),
            'label' => 'Всего записей',
            'color' => 'purple',
            'icon' => '📊'
        ],
    ];
    
    foreach ($stats as $stat):
    ?>
        <div class="stat-card <?= $stat['color'] ?>">
            <div style="font-size: 24px; margin-bottom: 8px;">
                <?= $stat['icon'] ?>
            </div>
            <div class="stat-value"><?= $stat['value'] ?></div>
            <div class="stat-label"><?= $stat['label'] ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php
function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

function getCacheSize() {
    $cacheDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache/mymodule/';
    $size = 0;
    
    if (is_dir($cacheDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDir)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    }
    
    return $size;
}
?>
```

## Безопасность

- Проверка `check_bitrix_sessid()` для всех POST запросов
- Валидация прав доступа `$USER->IsAdmin()`
- Экранирование вывода через `htmlspecialcharsbx()`
- Проверка типов и размеров файлов при импорте
- Логирование всех критичных операций

## Производительность

- AJAX сохранение без перезагрузки страницы
- Ленивая загрузка табов
- Кеширование статистики
- Оптимизированные SQL запросы

## Совместимость

- Bitrix Framework 22.0+
- PHP 7.4+
- Адаптивный дизайн для мобильных устройств
- Поддержка всех современных браузеров

## Логирование действий

```php
function logAction($action, $userId, $description) {
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/admin_actions.log';
    
    $logEntry = sprintf(
        "[%s] User: %d | Action: %s | Description: %s\n",
        date('Y-m-d H:i:s'),
        $userId,
        $action,
        $description
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
```

---

## Полезные ссылки

- [Административный интерфейс Bitrix](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2791)
- [CAdminList документация](https://dev.1c-bitrix.ru/api_help/main/reference/cadminlist/index.php)
- [UI.Notification](https://dev.1c-bitrix.ru/api_d7/bitrix/main/ui/notification/index.php)
- [Работа с опциями](https://dev.1c-bitrix.ru/api_help/main/reference/coption/index.php)
