# Задача 1: CRM-менеджер лидов

## Описание

Класс для работы с лидами через Bitrix24 REST API. Позволяет создавать, обновлять, искать и удалять лиды.

## 📂 Где разместить код в проекте

### Вариант 1: В `/local/php_interface/classes/` (рекомендуется)

```
bitrix-site/
├── local/
│   └── php_interface/
│       ├── init.php
│       └── classes/
│           └── LeadManager.php    ← Основной класс
└── admin/
    └── leads_example.php          ← Пример использования (опционально)
```

**Подключение в `/local/php_interface/init.php`:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/LeadManager.php';
```

### Вариант 2: В модуле

```
bitrix-site/
├── local/
│   └── modules/
│       └── mycompany.crm/
│           ├── install/
│           ├── lib/
│           │   └── LeadManager.php    ← Основной класс
│           └── include.php
```

### Вариант 3: Standalone скрипт

Если это отдельный скрипт для интеграции (не в рамках Bitrix CMS):

```
integration/
├── vendor/              ← composer dependencies (опционально)
├── config.php          ← настройки webhook
├── LeadManager.php     ← основной класс
└── run.php             ← скрипт запуска
```

## Возможности

### ✅ Что умеет класс

- **Создание лида** с множественными телефонами и email
- **Обновление лида** (статус, ответственный, комментарии)
- **Получение лида** по ID
- **Поиск лидов** с фильтрацией и пагинацией
- **Добавление телефона** к существующему лиду
- **Удаление лида**
- **Получение списка полей** лида

### 📋 Поддерживаемые поля

- `TITLE` - название лида
- `NAME`, `LAST_NAME`, `SECOND_NAME` - ФИО
- `PHONE` - массив телефонов
- `EMAIL` - массив email
- `STATUS_ID` - статус (NEW, IN_PROCESS, CONVERTED, JUNK)
- `SOURCE_ID` - источник (WEB, CALL, EMAIL)
- `ASSIGNED_BY_ID` - ответственный
- `COMMENTS` - комментарий

## Установка

### Шаг 1: Получить webhook

1. Зайти в Bitrix24 → **Разработчикам** → **Вебхуки**
2. Создать **входящий webhook**
3. Выбрать права доступа: **CRM (crm)** - полный доступ
4. Скопировать URL вида:
   ```
   https://вашподомен.bitrix24.ru/rest/1/xxxxxxxxxx/
   ```

### Шаг 2: Разместить класс

Скопировать `LeadManager.php` в `/local/php_interface/classes/`

### Шаг 3: Подключить в init.php

Добавить в `/local/php_interface/init.php`:

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/LeadManager.php';
```

## Использование

### Базовый пример

```php
<?php
use Local\Bitrix24\LeadManager;

// Инициализация
$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook_токен';
$manager = new LeadManager($webhookUrl);

// Создание лида
$leadId = $manager->createLead([
    'TITLE' => 'Клиент с сайта',
    'NAME' => 'Иван',
    'LAST_NAME' => 'Петров',
    'PHONE' => [
        ['VALUE' => '+7 (999) 123-45-67', 'VALUE_TYPE' => 'WORK']
    ],
    'EMAIL' => [
        ['VALUE' => 'petrov@example.com', 'VALUE_TYPE' => 'WORK']
    ],
    'SOURCE_ID' => 'WEB',
    'STATUS_ID' => 'NEW'
]);

echo "Создан лид с ID: $leadId";
```

### Пример 1: Обработка формы обратной связи

**Расположение:** `/local/components/mycompany/feedback.form/ajax.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Local\Bitrix24\LeadManager;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
    $manager = new LeadManager($webhookUrl);

    $leadId = $manager->createLead([
        'TITLE' => 'Заявка с формы обратной связи',
        'NAME' => $_POST['name'],
        'PHONE' => [
            ['VALUE' => $_POST['phone'], 'VALUE_TYPE' => 'MOBILE']
        ],
        'EMAIL' => [
            ['VALUE' => $_POST['email'], 'VALUE_TYPE' => 'WORK']
        ],
        'COMMENTS' => $_POST['message'],
        'SOURCE_ID' => 'WEB',
        'STATUS_ID' => 'NEW',
        'ASSIGNED_BY_ID' => 1  // Назначить администратору
    ]);

    if ($leadId) {
        echo json_encode(['success' => true, 'leadId' => $leadId]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка создания лида']);
    }
}
```

### Пример 2: Поиск дублей по телефону

**Расположение:** `/local/php_interface/classes/LeadDuplicateChecker.php`

```php
<?php
namespace Local\Bitrix24;

class LeadDuplicateChecker
{
    private $manager;

    public function __construct(LeadManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Проверка на дубли по телефону
     */
    public function findDuplicatesByPhone(string $phone): array
    {
        // Поиск лидов с таким телефоном
        return $this->manager->searchLeads(
            ['PHONE' => $phone],
            ['ID', 'TITLE', 'NAME', 'STATUS_ID']
        );
    }

    /**
     * Создание лида с проверкой дублей
     */
    public function createLeadIfNotExists(array $fields)
    {
        $phones = $fields['PHONE'] ?? [];

        foreach ($phones as $phoneData) {
            $duplicates = $this->findDuplicatesByPhone($phoneData['VALUE']);

            if (!empty($duplicates)) {
                return [
                    'success' => false,
                    'error' => 'Лид с таким телефоном уже существует',
                    'duplicate' => $duplicates[0]
                ];
            }
        }

        $leadId = $this->manager->createLead($fields);

        return [
            'success' => true,
            'leadId' => $leadId
        ];
    }
}
```

### Пример 3: Обновление статусов лидов по расписанию

**Расположение:** `/local/php_interface/agents/LeadStatusUpdater.php`

```php
<?php
namespace Local\Agents;

use Local\Bitrix24\LeadManager;

class LeadStatusUpdater
{
    /**
     * Агент для автоматического перевода старых лидов в JUNK
     * Добавить в агенты: LeadStatusUpdater::updateOldLeads();
     */
    public static function updateOldLeads()
    {
        $webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
        $manager = new LeadManager($webhookUrl);

        // Найти лиды старше 30 дней в статусе NEW
        $oldDate = date('Y-m-d', strtotime('-30 days'));

        $oldLeads = $manager->searchLeads(
            [
                'STATUS_ID' => 'NEW',
                '<DATE_CREATE' => $oldDate
            ],
            ['ID', 'TITLE']
        );

        foreach ($oldLeads as $lead) {
            $manager->updateLead($lead['ID'], [
                'STATUS_ID' => 'JUNK',
                'COMMENTS' => 'Автоматически переведен в JUNK из-за длительного простоя'
            ]);
        }

        // Вернуть имя функции для следующего запуска (через сутки)
        return "\\Local\\Agents\\LeadStatusUpdater::updateOldLeads();";
    }
}
```

### Пример 4: Экспорт лидов в CSV

**Расположение:** `/admin/export_leads.php`

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Local\Bitrix24\LeadManager;

$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook';
$manager = new LeadManager($webhookUrl);

// Получить все лиды
$leads = $manager->searchLeads(
    [],
    ['ID', 'TITLE', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'STATUS_ID', 'DATE_CREATE']
);

// Формирование CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads_export.csv');

$output = fopen('php://output', 'w');

// Заголовки
fputcsv($output, ['ID', 'Название', 'Имя', 'Фамилия', 'Телефон', 'Email', 'Статус', 'Дата создания']);

// Данные
foreach ($leads as $lead) {
    $phone = !empty($lead['PHONE']) ? $lead['PHONE'][0]['VALUE'] : '';
    $email = !empty($lead['EMAIL']) ? $lead['EMAIL'][0]['VALUE'] : '';

    fputcsv($output, [
        $lead['ID'],
        $lead['TITLE'],
        $lead['NAME'],
        $lead['LAST_NAME'],
        $phone,
        $email,
        $lead['STATUS_ID'],
        $lead['DATE_CREATE']
    ]);
}

fclose($output);
exit;
```

## Обработка ошибок

### Проверка результата создания

```php
$leadId = $manager->createLead([...]);

if ($leadId === false) {
    // Ошибка создания - проверить логи
    error_log('Не удалось создать лид');
} else {
    // Успех
    echo "Лид создан: $leadId";
}
```

### Логирование ошибок

Класс автоматически пишет в `error_log()` при ошибках. Логи можно найти:

- **Linux:** `/var/log/apache2/error.log` или `/var/log/nginx/error.log`
- **Windows:** `C:\xampp\apache\logs\error.log`
- **Bitrix:** `/bitrix/modules/error.log`

### Типичные ошибки

**1. INVALID_WEBHOOK**
```
Ошибка: Invalid webhook URL
Решение: Проверить правильность URL webhook
```

**2. REQUIRED_FIELDS**
```
Ошибка: Required fields: TITLE
Решение: Указать обязательное поле TITLE
```

**3. QUERY_LIMIT_EXCEEDED**
```
Ошибка: Превышен лимит запросов
Решение: Добавить задержку между запросами (sleep(1))
```

## Лимиты REST API

| Тип webhook | Лимит | Период |
|-------------|-------|--------|
| Входящий webhook | 2 запроса | в секунду |
| OAuth приложение | 50+ запросов | в секунду |

**Рекомендации:**
- Для массовых операций используйте OAuth
- Добавляйте задержки между запросами для webhook
- Используйте batch-запросы для множественных операций

## Тестирование

Открыть в браузере `/admin/leads_example.php` - будут выполнены все примеры использования класса.

## Что изучается в этой задаче

✅ Методы `crm.lead.*`
✅ Создание и обновление лидов
✅ Работа с множественными полями (телефон, email)
✅ Фильтрация и поиск
✅ Пагинация (получение всех записей)
✅ Обработка ошибок REST API
✅ Структура ответов Bitrix24

## Следующие шаги

После освоения работы с лидами переходите к:
- **Задача 2:** Работа со сделками (воронка продаж)
- **Задача 3:** Управление контактами (импорт из CSV)
