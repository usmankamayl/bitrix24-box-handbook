# Синхронизация задач между внешней системой и Bitrix24

## Описание

Двусторонняя синхронизация задач между вашей системой и Bitrix24 через REST API. Позволяет автоматически создавать, обновлять и отслеживать изменения задач в обеих системах.

## Файлы

- `TaskManager.php` — менеджер для работы с задачами через REST API
- `TaskSynchronizer.php` — синхронизатор с логикой двусторонней синхронизации
- `example.php` — примеры использования
- `sync_cron.php` — скрипт для автоматической синхронизации по расписанию

## Возможности

### TaskManager
- Создание, чтение, обновление, удаление задач
- Фильтрация и поиск задач
- Завершение и возобновление задач
- Добавление комментариев
- Автоматическая pagination

### TaskSynchronizer
- **Двусторонняя синхронизация:** из внешней системы в Bitrix24 и обратно
- **Отслеживание связей:** автоматическое сопоставление ID между системами
- **Инкрементальная синхронизация:** только измененные данные
- **Логирование:** полная история всех операций
- **Обработка конфликтов:** определение создания vs обновления

## Установка

### 1. Создайте webhook в Bitrix24

1. **Настройки → Разработчикам → Входящий вебхук**
2. Выберите права: **Задачи** (чтение и запись)
3. Скопируйте URL

### 2. Настройте подключение

В `example.php` и `sync_cron.php`:

```php
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/ваш_webhook_код/';
```

### 3. Запустите пример

```bash
php example.php
```

## Примеры использования

### Базовая инициализация

```php
require_once 'TaskManager.php';
require_once 'TaskSynchronizer.php';

$taskManager = new TaskManager($WEBHOOK_URL);
$synchronizer = new TaskSynchronizer(
    $taskManager,
    __DIR__ . '/sync.log',
    __DIR__ . '/task_mapping.json'
);
```

### Синхронизация из внешней системы в Bitrix24

```php
// Данные из вашей системы
$externalTasks = [
    [
        'id' => 'ext-001',
        'title' => 'Разработать API',
        'description' => 'REST API для интеграции',
        'responsible_id' => 1,
        'deadline' => '2026-10-15',
        'completed' => false
    ],
    [
        'id' => 'ext-002',
        'title' => 'Написать тесты',
        'description' => 'Unit и интеграционные тесты',
        'responsible_id' => 1,
        'deadline' => '2026-10-20',
        'completed' => false
    ]
];

// Синхронизируем
$result = $synchronizer->syncFromExternal($externalTasks);

echo "Создано: " . count($result['created']) . "\n";
echo "Обновлено: " . count($result['updated']) . "\n";
echo "Ошибок: " . count($result['errors']) . "\n";
```

### Синхронизация из Bitrix24 во внешнюю систему

```php
// Получить измененные задачи с определенной даты
$sinceDate = date('Y-m-d H:i:s', strtotime('-1 hour'));
$changedTasks = $synchronizer->syncToBitrix($sinceDate);

foreach ($changedTasks as $task) {
    echo "Bitrix24 ID: {$task['bitrix_id']}\n";
    echo "Действие: {$task['action']}\n"; // 'create' или 'update'
    echo "Данные: " . print_r($task['data'], true) . "\n";
    
    // Сохраните в вашу систему
    if ($task['action'] === 'create') {
        // Создать новую задачу
    } else {
        // Обновить существующую по external_id
    }
}
```

### Работа с TaskManager напрямую

```php
// Создать задачу
$taskId = $taskManager->createTask([
    'TITLE' => 'Новая задача',
    'DESCRIPTION' => 'Описание задачи',
    'RESPONSIBLE_ID' => 1,
    'DEADLINE' => date('c', strtotime('+7 days'))
]);

// Получить задачу
$task = $taskManager->getTask($taskId);

// Обновить задачу
$taskManager->updateTask($taskId, [
    'TITLE' => 'Обновленное название',
    'DESCRIPTION' => 'Новое описание'
]);

// Добавить комментарий
$taskManager->addComment($taskId, 'Важное замечание');

// Завершить задачу
$taskManager->completeTask($taskId);

// Возобновить задачу
$taskManager->renewTask($taskId);

// Удалить задачу
$taskManager->deleteTask($taskId);
```

### Получение задач с фильтрацией

```php
// Мои активные задачи
$myTasks = $taskManager->getTasks(
    ['RESPONSIBLE_ID' => 1, 'REAL_STATUS' => [1, 2, 3]],
    ['ID', 'TITLE', 'DEADLINE', 'STATUS'],
    ['DEADLINE' => 'ASC']
);

// Просроченные задачи
$overdueTasks = $taskManager->getTasks([
    '<DEADLINE' => date('Y-m-d H:i:s'),
    '!STATUS' => 5 // не завершенные
]);

// Задачи, измененные за последний час
$recentTasks = $taskManager->getTasksModifiedSince(
    date('Y-m-d H:i:s', strtotime('-1 hour'))
);
```

## Автоматическая синхронизация через cron

### Настройка cron

```bash
# Синхронизация каждые 5 минут
*/5 * * * * /usr/bin/php /path/to/sync_cron.php

# Синхронизация каждый час
0 * * * * /usr/bin/php /path/to/sync_cron.php

# Синхронизация каждый день в 9:00
0 9 * * * /usr/bin/php /path/to/sync_cron.php
```

### Настройка sync_cron.php

Реализуйте две функции в `sync_cron.php`:

**1. Получение задач из вашей системы:**

```php
function getExternalTasks()
{
    // Пример с базой данных
    $db = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
    $stmt = $db->query('
        SELECT id, title, description, responsible_id, deadline, completed
        FROM tasks 
        WHERE needs_sync = 1
    ');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**2. Сохранение изменений в вашу систему:**

```php
function saveToExternalSystem($tasks)
{
    $db = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
    
    foreach ($tasks as $task) {
        if ($task['action'] === 'create') {
            // Создать новую запись
            $stmt = $db->prepare('
                INSERT INTO tasks (bitrix_id, title, description, completed)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                $task['bitrix_id'],
                $task['data']['title'],
                $task['data']['description'],
                $task['data']['completed']
            ]);
        } else {
            // Обновить существующую
            $stmt = $db->prepare('
                UPDATE tasks 
                SET title = ?, description = ?, completed = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $task['data']['title'],
                $task['data']['description'],
                $task['data']['completed'],
                $task['external_id']
            ]);
        }
    }
}
```

## Основные поля задачи Bitrix24

| Поле | Описание | Пример |
|------|----------|--------|
| `TITLE` | Название задачи | "Разработать модуль" |
| `DESCRIPTION` | Описание | "Подробное описание" |
| `RESPONSIBLE_ID` | ID ответственного | 1 |
| `DEADLINE` | Крайний срок | "2026-10-15T18:00:00+03:00" |
| `STATUS` | Статус задачи | 1-новая, 2-принята, 3-в работе, 4-ждет контроля, 5-завершена |
| `PRIORITY` | Приоритет | 0-низкий, 1-средний, 2-высокий |
| `CREATED_BY` | Кто создал | 1 |
| `TAGS` | Теги | ["api", "integration"] |

## Статусы задач

- `1` — Новая
- `2` — Принята к исполнению
- `3` — В работе
- `4` — Ожидает контроля
- `5` — Завершена
- `6` — Отложена
- `7` — Отклонена

## Фильтрация задач

```php
$tasks = $taskManager->getTasks([
    'RESPONSIBLE_ID' => 1,              // Ответственный
    '>=DEADLINE' => '2026-10-01',       // Дедлайн от даты
    '<=DEADLINE' => '2026-10-31',       // Дедлайн до даты
    'REAL_STATUS' => [1, 2, 3],         // Статусы (новая, принята, в работе)
    '%TITLE' => 'API',                  // Содержит в названии
    '>=CHANGED_DATE' => '2026-10-01'    // Изменены с даты
]);
```

## Архитектура синхронизации

### Связи ID (mapping)

Файл `task_mapping.json` хранит связи между ID:

```json
{
    "ext-001": 123,
    "ext-002": 124,
    "ext-003": 125
}
```

Где ключ — ID во внешней системе, значение — ID в Bitrix24.

### Логика синхронизации

1. **Из внешней системы в Bitrix24:**
   - Проверить, есть ли связь в mapping
   - Если есть → обновить задачу в Bitrix24
   - Если нет → создать новую задачу, сохранить связь

2. **Из Bitrix24 во внешнюю систему:**
   - Получить задачи, измененные с последней синхронизации
   - Найти соответствующие external_id через mapping
   - Вернуть данные для сохранения во внешней системе

### Логирование

Все операции записываются в `sync.log`:

```
[2026-10-08 14:30:00] === Cron sync started ===
[2026-10-08 14:30:01] Created task: external=ext-001, bitrix=123
[2026-10-08 14:30:02] Updated task: external=ext-002, bitrix=124
[2026-10-08 14:30:03] Results: created=1, updated=1, errors=0
[2026-10-08 14:30:04] Last sync time: 2026-10-08 14:30:04
[2026-10-08 14:30:04] === Cron sync finished ===
```

## Обработка ошибок

```php
$result = $synchronizer->syncFromExternal($externalTasks);

foreach ($result['errors'] as $error) {
    echo "External ID: {$error['external_id']}\n";
    echo "Ошибка: {$error['error']}\n";
    
    // Логирование или уведомление
}
```

### Типичные ошибки

- **ERROR_TASK_NOT_FOUND**: Задача не найдена
- **ERROR_INSUFFICIENT_SCOPE**: Недостаточно прав webhook
- **ERROR_WRONG_TASK_DATA**: Неверные данные задачи

## Статистика

```php
$stats = $synchronizer->getStatistics();

echo "Синхронизировано задач: {$stats['total_synced_tasks']}\n";
echo "Последняя синхронизация: {$stats['last_sync_time']}\n";
print_r($stats['mapping']);
```

## Полезные ссылки

- [Tasks REST API](https://dev.1c-bitrix.ru/rest_help/tasks/index.php)
- [Методы задач](https://dev.1c-bitrix.ru/rest_help/tasks/task/index.php)
- [Комментарии к задачам](https://dev.1c-bitrix.ru/rest_help/tasks/comment/index.php)

## Что изучается в этом примере

✅ Двусторонняя синхронизация данных  
✅ Отслеживание связей между системами  
✅ Инкрементальная синхронизация (только изменения)  
✅ Автоматизация через cron  
✅ Логирование операций  
✅ Обработка ошибок синхронизации  
✅ CRUD операции с задачами  
✅ Фильтрация и поиск задач
