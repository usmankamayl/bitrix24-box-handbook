# Задача 2: Модуль для работы с заявками

## Описание

Полнофункциональный модуль Bitrix для управления заявками через динамический тип CRM. Демонстрирует создание модуля с использованием Factory API, обработчиков событий и автоматизации.

## Что демонстрирует

- ✅ Структура модуля Bitrix (index.php, version.php, lib/)
- ✅ Установка и удаление модуля через админку
- ✅ Создание динамического типа при установке модуля
- ✅ Регистрация обработчиков событий CRM
- ✅ Сервисный класс для работы с заявками
- ✅ Валидация данных в событиях OnBefore
- ✅ Автоматизация в событиях OnAfter
- ✅ Отправка уведомлений через модуль IM
- ✅ Логирование действий
- ✅ Работа с настройками модуля через Option

## Структура модуля

```
mycompany.requests/
├── index.php              # Главный файл модуля (класс установки)
├── version.php            # Версия модуля
├── lib/
│   ├── RequestService.php # Сервис для работы с заявками
│   └── EventHandlers.php  # Обработчики событий
└── README.md              # Документация
```

## Файлы

### 1. `index.php`

**Назначение:** Главный файл модуля с классом установки/удаления

**Класс:** `mycompany_requests extends CModule`

**Основные методы:**

```php
// Установка модуля
public function DoInstall()

// Удаление модуля
public function DoUninstall()

// Создание БД (динамический тип, поля, стадии)
public function InstallDB()

// Удаление БД (удаление типа)
public function UnInstallDB()

// Регистрация событий
public function InstallEvents()

// Удаление событий
public function UnInstallEvents()

// Копирование файлов (компоненты)
public function InstallFiles()

// Удаление файлов
public function UnInstallFiles()
```

**Что создаётся при установке:**

**Динамический тип "Заявки":**
- Код: `REQUEST`
- Стадии: 5 шт (NEW, IN_WORK, PENDING, RESOLVED, REJECTED)
- Привязка к клиентам: ДА
- Автоматизация: ДА
- Бизнес-процессы: ДА

**Пользовательские поля:**
- `UF_REQUEST_TYPE` - тип заявки (список)
- `UF_PRIORITY` - приоритет (список)
- `UF_DESCRIPTION` - описание проблемы (текст)
- `UF_EXECUTOR` - исполнитель (пользователь)
- `UF_EXECUTOR_COMMENT` - комментарий исполнителя (текст)
- `UF_FILES` - файлы (множественные)
- `UF_DEADLINE` - срок выполнения (дата/время)

**Стадии:**
- NEW - Новая (синий)
- IN_WORK - В работе (оранжевый)
- PENDING - Ожидание ответа (голубой)
- RESOLVED - Решена (зелёный, SUCCESS)
- REJECTED - Отклонена (красный, FAILURE)

### 2. `version.php`

Версия модуля для системы обновлений:

```php
$arModuleVersion = [
    'VERSION' => '1.0.0',
    'VERSION_DATE' => '2024-01-15',
];
```

### 3. `lib/RequestService.php`

**Назначение:** Сервисный класс для работы с заявками

**Namespace:** `MyCompany\Requests`

**Основные методы:**

```php
// Создать заявку
public function createRequest(array $data): Result

// Обновить заявку
public function updateRequest(int $id, array $data): Result

// Удалить заявку
public function deleteRequest(int $id): Result

// Получить заявку по ID
public function getRequest(int $id): ?Item

// Получить список заявок
public function getRequests(array $filter, array $order, int $limit, int $offset): array

// Получить количество заявок
public function getRequestsCount(array $filter): int

// Изменить статус
public function changeStatus(int $id, string $stageId): Result

// Назначить исполнителя
public function assignExecutor(int $id, int $executorId): Result

// Решить заявку
public function resolveRequest(int $id, string $comment): Result

// Отклонить заявку
public function rejectRequest(int $id, string $reason): Result

// Получить новые заявки
public function getNewRequests(): array

// Получить заявки в работе
public function getRequestsInWork(): array

// Получить заявки исполнителя
public function getRequestsByExecutor(int $executorId): array

// Получить просроченные заявки
public function getOverdueRequests(): array

// Получить статистику
public function getStatistics(): array
```

**Особенности:**
- Автоматически получает Entity Type ID из настроек модуля
- Все операции через Operation API (проверка прав)
- Обработка ошибок через Result
- Поддержка фильтрации и сортировки

### 4. `lib/EventHandlers.php`

**Назначение:** Обработчики событий для автоматизации

**Namespace:** `MyCompany\Requests`

**События:**

#### OnBeforeItemAdd (перед добавлением)
- Валидация обязательных полей (TITLE, UF_REQUEST_TYPE, UF_DESCRIPTION)
- Установка значений по умолчанию (STAGE_ID = NEW)
- Автоматическое назначение ответственного
- Автоматический расчёт дедлайна по приоритету

#### OnAfterItemAdd (после добавления)
- Отправка уведомления ответственному
- Уведомление администраторов при критическом приоритете
- Логирование создания

#### OnBeforeItemUpdate (перед обновлением)
- Проверка комментария при решении заявки
- Автоматическое изменение стадии при назначении исполнителя

#### OnAfterItemUpdate (после обновления)
- Обработка изменения стадии (уведомления)
- Уведомление исполнителя при назначении
- Логирование изменений

**Вспомогательные методы:**
```php
// Отправить уведомление пользователю
private static function sendNotification(int $userId, string $message)

// Уведомить всех администраторов
private static function notifyAdmins(string $message)

// Записать в лог
private static function logAction(string $action, int $id, array $fields)

// Получить дедлайн по приоритету
private static function getDeadlineByPriority($priorityId): int

// Проверить критический приоритет
private static function isCriticalPriority($priorityId): bool
```

## Установка модуля

### Шаг 1: Размещение файлов

Разместите папку модуля в `/local/modules/`:

```
/local/modules/mycompany.requests/
├── index.php
├── version.php
└── lib/
    ├── RequestService.php
    └── EventHandlers.php
```

### Шаг 2: Установка через админку

1. Перейдите в раздел: **Настройки → Настройки продукта → Модули**
2. Найдите модуль **"Управление заявками"**
3. Нажмите **"Установить"**

При установке:
- Создастся динамический тип "Заявки"
- Создадутся все пользовательские поля
- Создадутся стадии
- Зарегистрируются обработчики событий

### Шаг 3: Проверка установки

После установки в настройках модуля сохранится `entity_type_id`:

```php
$entityTypeId = \Bitrix\Main\Config\Option::get('mycompany.requests', 'entity_type_id');
echo $entityTypeId; // Например: 128
```

Проверьте доступность заявок:
```
/crm/type/{entityTypeId}/list/
```

## Использование в коде

### Базовые операции

```php
use MyCompany\Requests\RequestService;

try {
    $service = new RequestService();
    
    // Создать заявку
    $result = $service->createRequest([
        'TITLE' => 'Не работает email',
        'UF_REQUEST_TYPE' => 'SUPPORT',  // ID значения из enumeration
        'UF_PRIORITY' => 'HIGH',         // ID значения из enumeration
        'UF_DESCRIPTION' => 'Не приходят письма с сайта',
        'CONTACT_IDS' => [10],
        'COMPANY_ID' => 5,
    ]);
    
    if ($result->isSuccess()) {
        $requestId = $result->getData()['ID'];
        echo "Заявка создана: #{$requestId}";
    } else {
        foreach ($result->getErrorMessages() as $error) {
            echo "Ошибка: {$error}";
        }
    }
    
} catch (\Exception $e) {
    echo "Исключение: {$e->getMessage()}";
}
```

### Получение списка

```php
// Получить новые заявки
$newRequests = $service->getNewRequests();

// Получить заявки исполнителя
$myRequests = $service->getRequestsByExecutor($USER->GetID());

// Получить просроченные
$overdueRequests = $service->getOverdueRequests();

// Фильтрация
$requests = $service->getRequests(
    [
        '=UF_PRIORITY' => 'HIGH',
        '>UF_DEADLINE' => date('Y-m-d'),
    ],
    ['ID' => 'DESC'],
    20
);
```

### Работа с заявкой

```php
// Назначить исполнителя
$result = $service->assignExecutor($requestId, $userId);

// Решить заявку
$result = $service->resolveRequest($requestId, 'Проблема устранена. Настроил SMTP.');

// Отклонить заявку
$result = $service->rejectRequest($requestId, 'Дубликат заявки #123');

// Изменить статус
$result = $service->changeStatus($requestId, 'PENDING');
```

### Статистика

```php
$stats = $service->getStatistics();

echo "Всего заявок: {$stats['total']}\n";
echo "Новых: {$stats['new']}\n";
echo "В работе: {$stats['in_work']}\n";
echo "Решено: {$stats['resolved']}\n";
echo "Просрочено: {$stats['overdue']}\n";
```

## Кастомизация

### Добавление своих полей

Отредактируйте метод `createFields()` в `index.php`:

```php
private function createFields()
{
    // ... существующие поля
    
    // Добавить новое поле
    $fields[] = [
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_CUSTOM_FIELD',
        'USER_TYPE_ID' => 'string',
        'EDIT_FORM_LABEL' => ['ru' => 'Моё поле'],
        'MANDATORY' => 'N',
        'SHOW_FILTER' => 'Y',
        'SHOW_IN_LIST' => 'Y',
    ];
    
    // ...
}
```

### Добавление своих стадий

Отредактируйте метод `createStages()` в `index.php`:

```php
$stages[] = [
    'STATUS_ID' => 'MY_STAGE',
    'NAME' => 'Моя стадия',
    'SORT' => 250,
    'SEMANTICS' => PhaseSemantics::PROCESS,
    'COLOR' => '#FF00FF',
];
```

### Добавление своей логики в события

Отредактируйте `lib/EventHandlers.php`:

```php
public static function onAfterItemAdd(Event $event): void
{
    // ... существующая логика
    
    // Добавить свою логику
    $fields = $event->getParameter('fields');
    
    if ($fields['UF_PRIORITY'] === 'CRITICAL') {
        // Создать задачу для руководителя
        // Отправить SMS
        // Запустить бизнес-процесс
    }
}
```

## Удаление модуля

1. Перейдите в **Настройки → Настройки продукта → Модули**
2. Найдите модуль **"Управление заявками"**
3. Нажмите **"Удалить"**

**ВНИМАНИЕ:** При удалении модуля:
- Удалится динамический тип "Заявки"
- Удалятся все заявки и данные
- Удалятся обработчики событий

## Лог действий

Все действия с заявками записываются в лог:

```
/local/logs/requests.log
```

Формат записи:
```
[2024-01-15 10:30:45] User #1 create request #10: {"TITLE":"Заявка","STAGE_ID":"NEW",...}
[2024-01-15 10:31:20] User #2 update request #10: {"STAGE_ID":"IN_WORK","UF_EXECUTOR":2}
```

## Типичные ошибки

### 1. Модуль не устанавливается

**Проблема:** Ошибка "Модуль CRM не установлен"

**Решение:** Убедитесь, что у вас Bitrix24 или установлен модуль CRM

### 2. Entity Type ID не сохраняется

**Проблема:** RequestService выбрасывает исключение

**Решение:**
```php
// Проверить настройку вручную
$entityTypeId = \Bitrix\Main\Config\Option::get('mycompany.requests', 'entity_type_id');
var_dump($entityTypeId); // Должен быть числом > 0
```

### 3. События не работают

**Проблема:** Валидация и уведомления не срабатывают

**Решение:**
```php
// Проверить регистрацию событий
$eventManager = \Bitrix\Main\EventManager::getInstance();
$handlers = $eventManager->findEventHandlers('crm', 'OnBeforeCrmDynamicItemAdd');
print_r($handlers); // Должен быть наш обработчик
```

### 4. Уведомления не отправляются

**Проблема:** Метод `sendNotification()` не работает

**Решение:** Убедитесь, что модуль IM установлен:
```php
if (!\Bitrix\Main\Loader::includeModule('im')) {
    // Модуль IM не установлен
}
```

## Расширение функционала

### Добавить REST API

Создайте контроллер для REST API:

```php
namespace MyCompany\Requests\Controller;

use Bitrix\Main\Engine\Controller;

class Request extends Controller
{
    public function listAction($filter = [], $order = ['ID' => 'DESC'], $limit = 50)
    {
        $service = new \MyCompany\Requests\RequestService();
        $items = $service->getRequests($filter, $order, $limit);
        
        return ['items' => $items];
    }
    
    public function getAction($id)
    {
        $service = new \MyCompany\Requests\RequestService();
        $item = $service->getRequest($id);
        
        return ['item' => $item];
    }
}
```

### Добавить компоненты

Создайте компоненты для отображения:
- `mycompany:requests.list` - список заявок
- `mycompany:requests.detail` - карточка заявки
- `mycompany:requests.add` - форма создания

## Полезные ссылки

- [Создание модулей Bitrix](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2830)
- [Factory API](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/service/factory.php)
- [События CRM](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/events.php)
- [Модуль IM (уведомления)](https://dev.1c-bitrix.ru/api_d7/bitrix/im/index.php)

## Что дальше?

После изучения этой задачи:
1. Установите модуль и проверьте его работу
2. Добавьте свои поля и стадии
3. Расширьте логику обработчиков событий
4. Создайте REST API контроллер (см. Задачу 3)
5. Разработайте компоненты для публичной части
