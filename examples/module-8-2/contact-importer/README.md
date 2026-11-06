# Задача 3: Управление контактами (импорт из CSV)

## Описание

Класс для работы с контактами Bitrix24 CRM через REST API с поддержкой массового импорта и экспорта в CSV.

## 📂 Где разместить код в проекте

### Вариант 1: В `/local/php_interface/classes/` (рекомендуется)

```
bitrix-site/
├── local/
│   ├── php_interface/
│   │   ├── init.php
│   │   └── classes/
│   │       └── ContactManager.php    ← Основной класс
│   └── scripts/
│       └── import_contacts.php       ← CLI скрипт импорта
├── admin/
│   └── contacts_example.php          ← Веб-пример
└── upload/
    └── contacts/
        ├── example.csv               ← Пример CSV
        └── import/                   ← Папка для загрузки CSV
```

**Подключение в `/local/php_interface/init.php`:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/ContactManager.php';
```

### Вариант 2: Компонент для импорта

```
bitrix-site/
└── local/
    └── components/
        └── mycompany/
            └── contact.importer/
                ├── class.php                    ← ContactManager
                ├── component.php                ← Логика компонента
                ├── ajax.php                     ← AJAX обработчик
                └── templates/
                    └── .default/
                        ├── template.php         ← Форма загрузки CSV
                        ├── style.css
                        └── script.js
```

### Вариант 3: Standalone интеграция

```
integration/
├── ContactManager.php
├── import.php              ← Веб-интерфейс
├── import_cli.php          ← CLI скрипт
├── config.php              ← Настройки (webhook URL)
└── data/
    ├── example.csv
    └── logs/
```

## Возможности

### ✅ Что умеет класс

- **Создание контакта** с множественными телефонами и email
- **Обновление контакта**
- **Поиск контакта** по ID, телефону, email
- **Фильтрация контактов** с пагинацией
- **Импорт из CSV** с автоматической проверкой дублей
- **Экспорт в CSV**
- **Нормализация телефонов** (8ххх → +7ххх)
- **Защита от дублей** (по телефону и email)
- **Массовая обработка** с контролем лимитов API

### 📋 Поля контакта

- `NAME`, `LAST_NAME`, `SECOND_NAME` - ФИО
- `PHONE` - массив телефонов
- `EMAIL` - массив email
- `COMPANY_ID` - привязка к компании
- `TYPE_ID` - тип контакта (CLIENT, SUPPLIER, PARTNER)
- `SOURCE_ID` - источник (WEB, CALL, EMAIL, ADVERTISING)
- `ASSIGNED_BY_ID` - ответственный
- `COMMENTS` - комментарий

## Установка

### Шаг 1: Получить webhook

1. Bitrix24 → **Разработчикам** → **Вебхуки**
2. Создать **входящий webhook**
3. Права: **CRM (crm)** - полный доступ
4. Скопировать URL

### Шаг 2: Разместить класс

Скопировать `ContactManager.php` в `/local/php_interface/classes/`

### Шаг 3: Подключить в init.php

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/ContactManager.php';
```

## Формат CSV файла

### Структура

```csv
name,last_name,second_name,phone,email,company_id,type,source,phone_type,email_type,assigned_by_id,comments
Иван,Петров,Сергеевич,+7 (999) 123-45-67,ivanov@example.com,1,CLIENT,WEB,WORK,WORK,1,Клиент с сайта
Мария,Сидорова,,8 (999) 234-56-78,sidorova@example.com,2,CLIENT,CALL,MOBILE,WORK,1,Звонила
```

### Обязательные колонки

- `name` и/или `last_name` - хотя бы одно должно быть заполнено
- Остальные колонки опциональны

### Опциональные колонки

| Колонка | Значение | Описание |
|---------|----------|----------|
| `second_name` | Текст | Отчество |
| `phone` | Телефон | Любой формат: +7, 8, 7 |
| `email` | Email | Email адрес |
| `company_id` | Число | ID компании в Bitrix24 |
| `type` | CLIENT/SUPPLIER/PARTNER | Тип контакта |
| `source` | WEB/CALL/EMAIL/ADVERTISING | Источник |
| `phone_type` | WORK/MOBILE/HOME/FAX | Тип телефона |
| `email_type` | WORK/MAILING/OTHER | Тип email |
| `assigned_by_id` | Число | ID ответственного |
| `comments` | Текст | Комментарий |

### Нормализация телефонов

Класс автоматически нормализует телефоны:

```
8 (999) 123-45-67  →  +7 (999) 123-45-67
7 9991234567       →  +7 9991234567
+7-999-123-45-67   →  +79991234567
```

## Использование

### Базовый пример

```php
<?php
use Local\Bitrix24\ContactManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new ContactManager($webhookUrl);

// Создание контакта
$contactId = $manager->createContact([
    'NAME' => 'Иван',
    'LAST_NAME' => 'Петров',
    'PHONE' => [
        ['VALUE' => '+7 (999) 123-45-67', 'VALUE_TYPE' => 'WORK']
    ],
    'EMAIL' => [
        ['VALUE' => 'petrov@example.com', 'VALUE_TYPE' => 'WORK']
    ]
]);
```

### Пример 1: Импорт контактов из CSV

**Расположение:** `/local/scripts/import_contacts.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Local\Bitrix24\ContactManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new ContactManager($webhookUrl);

// Путь к CSV файлу
$csvPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/contacts/import.csv';

// Импорт с обновлением существующих
$result = $manager->importFromCsv($csvPath, true);

echo "Создано: {$result['created']}\n";
echo "Обновлено: {$result['updated']}\n";
echo "Пропущено: {$result['skipped']}\n";

if (!empty($result['errors'])) {
    echo "Ошибки:\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}
```

**Запуск через CLI:**
```bash
php /local/scripts/import_contacts.php /path/to/contacts.csv --update
```

### Пример 2: Компонент для импорта с веб-интерфейсом

**Расположение:** `/local/components/mycompany/contact.importer/component.php`

```php
<?php
use Local\Bitrix24\ContactManager;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file'])) {
    $webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
    $manager = new ContactManager($webhookUrl);

    // Временный файл
    $tmpFile = $_FILES['csv_file']['tmp_name'];
    
    // Импорт
    $updateExisting = !empty($_POST['update_existing']);
    $result = $manager->importFromCsv($tmpFile, $updateExisting);

    $arResult['IMPORT_RESULT'] = $result;
    $arResult['SUCCESS'] = true;
}

$this->includeComponentTemplate();
```

**Шаблон:** `/local/components/mycompany/contact.importer/templates/.default/template.php`

```php
<?php if ($arResult['SUCCESS']): ?>
    <div class="alert alert-success">
        <h3>✅ Импорт завершен</h3>
        <ul>
            <li>Создано: <?= $arResult['IMPORT_RESULT']['created'] ?></li>
            <li>Обновлено: <?= $arResult['IMPORT_RESULT']['updated'] ?></li>
            <li>Пропущено: <?= $arResult['IMPORT_RESULT']['skipped'] ?></li>
        </ul>
        
        <?php if (!empty($arResult['IMPORT_RESULT']['errors'])): ?>
            <h4>Ошибки:</h4>
            <ul>
                <?php foreach ($arResult['IMPORT_RESULT']['errors'] as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <h2>Импорт контактов из CSV</h2>
    
    <div class="form-group">
        <label>CSV файл:</label>
        <input type="file" name="csv_file" accept=".csv" required>
    </div>
    
    <div class="form-group">
        <label>
            <input type="checkbox" name="update_existing" value="1">
            Обновлять существующие контакты
        </label>
    </div>
    
    <button type="submit">Импортировать</button>
</form>

<div class="help">
    <h3>Формат CSV:</h3>
    <pre>name,last_name,phone,email,company_id
Иван,Петров,+7 (999) 123-45-67,ivanov@example.com,1</pre>
    
    <a href="/upload/contacts/example.csv" download>📥 Скачать пример CSV</a>
</div>
```

### Пример 3: Проверка дублей при создании

**Расположение:** `/local/php_interface/classes/ContactDuplicateChecker.php`

```php
<?php
namespace Local\Bitrix24;

class ContactDuplicateChecker
{
    private $manager;
    
    public function __construct(ContactManager $manager)
    {
        $this->manager = $manager;
    }
    
    /**
     * Создание контакта с проверкой дублей
     */
    public function createOrUpdate(array $fields)
    {
        $existing = null;
        
        // Проверка по телефону
        if (!empty($fields['PHONE'])) {
            $phone = $fields['PHONE'][0]['VALUE'];
            $existing = $this->manager->findByPhone($phone);
        }
        
        // Проверка по email
        if (!$existing && !empty($fields['EMAIL'])) {
            $email = $fields['EMAIL'][0]['VALUE'];
            $existing = $this->manager->findByEmail($email);
        }
        
        if ($existing) {
            // Обновляем существующий
            $this->manager->updateContact($existing['ID'], $fields);
            
            return [
                'action' => 'updated',
                'id' => $existing['ID']
            ];
        } else {
            // Создаем новый
            $contactId = $this->manager->createContact($fields);
            
            return [
                'action' => 'created',
                'id' => $contactId
            ];
        }
    }
}
```

### Пример 4: Экспорт контактов

**Расположение:** `/admin/export_contacts.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Local\Bitrix24\ContactManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new ContactManager($webhookUrl);

// Экспорт всех клиентов
$outputPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/contacts_export.csv';

$exported = $manager->exportToCsv($outputPath, [
    'TYPE_ID' => 'CLIENT'
]);

if ($exported) {
    // Скачивание файла
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=contacts_export.csv');
    readfile($outputPath);
    exit;
} else {
    echo "Ошибка экспорта";
}
```

### Пример 5: Синхронизация с внешней системой

**Расположение:** `/local/scripts/sync_contacts.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Local\Bitrix24\ContactManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new ContactManager($webhookUrl);

// Получаем контакты из внешней системы (например, 1C)
$externalContacts = getContactsFrom1C();

$stats = ['created' => 0, 'updated' => 0, 'errors' => 0];

foreach ($externalContacts as $extContact) {
    // Ищем по внешнему ID
    $existing = $manager->searchContacts([
        'UF_CRM_EXTERNAL_ID' => $extContact['external_id']
    ]);
    
    $fields = [
        'NAME' => $extContact['name'],
        'LAST_NAME' => $extContact['last_name'],
        'PHONE' => [
            ['VALUE' => $extContact['phone'], 'VALUE_TYPE' => 'WORK']
        ],
        'UF_CRM_EXTERNAL_ID' => $extContact['external_id']
    ];
    
    if (!empty($existing)) {
        // Обновляем
        if ($manager->updateContact($existing[0]['ID'], $fields)) {
            $stats['updated']++;
        } else {
            $stats['errors']++;
        }
    } else {
        // Создаем
        if ($manager->createContact($fields)) {
            $stats['created']++;
        } else {
            $stats['errors']++;
        }
    }
    
    // Защита от лимитов
    usleep(500000); // 0.5 сек между запросами
}

echo "Синхронизация завершена:\n";
echo "Создано: {$stats['created']}\n";
echo "Обновлено: {$stats['updated']}\n";
echo "Ошибок: {$stats['errors']}\n";
```

## CLI импорт

### Использование

```bash
# Базовый импорт (без обновления дублей)
php /local/scripts/import_contacts.php /path/to/contacts.csv

# С обновлением существующих контактов
php /local/scripts/import_contacts.php /path/to/contacts.csv --update
```

### Вывод

```
📄 Файл: /path/to/contacts.csv
🔄 Обновление существующих: Да
⏳ Начинаем импорт...

✅ Импорт завершен за 12.5 сек.

Статистика:
  📝 Создано контактов: 145
  ✏️  Обновлено контактов: 23
  ⏭️  Пропущено записей: 5

❌ Ошибки (2):
  - Строка 56: неверное количество колонок
  - Строка 89: ошибка создания контакта
```

## Защита от дублей

### Автоматическая проверка при импорте

Класс автоматически проверяет дубли по:
1. **Телефону** (первый приоритет)
2. **Email** (если телефон не найден)

### Поведение

- `$updateExisting = false` - пропускать дубли
- `$updateExisting = true` - обновлять найденные контакты

## Обработка ошибок

### Типичные ошибки

**1. Файл не найден**
```php
$result = $manager->importFromCsv('/wrong/path.csv');
// ['success' => false, 'error' => 'Файл не найден']
```

**2. Неверный формат CSV**
```
Строка 15: неверное количество колонок
```

**3. Превышение лимитов API**
```
Решение: Класс автоматически добавляет паузы (sleep) каждые 10 запросов
```

## Лимиты и производительность

- **Webhook:** 2 запроса/сек
- **Автопауза:** каждые 10 записей - 1 секунда
- **Скорость:** ~100-150 контактов в минуту

### Оптимизация

Для больших объемов (1000+ контактов) используйте **batch-запросы** (Модуль 8.3)

## Что изучается в этой задаче

✅ Методы `crm.contact.*`
✅ Создание и поиск контактов
✅ Работа с множественными полями
✅ Импорт/экспорт CSV
✅ Проверка дублей
✅ Нормализация данных (телефоны)
✅ Массовая обработка с контролем лимитов
✅ CLI скрипты для автоматизации
✅ Обработка ошибок при импорте

## Следующие шаги

**Модуль 8.2 завершен!** ✅

Переходите к:
- **Модуль 8.3:** Пакетные запросы (batch) для ускорения массовых операций
- **Модуль 8.4:** События и обработчики webhook
