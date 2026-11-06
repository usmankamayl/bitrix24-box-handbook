# Задача 1: Система управления проектами (SPA)

## Описание

Полнофункциональная система для создания и управления динамическим типом "Проекты" в Bitrix24 CRM с использованием Factory API.

## Что демонстрирует

- ✅ Создание динамического типа (Smart Process Automation)
- ✅ Настройка пользовательских полей всех типов
- ✅ Создание стадий с семантикой (process/success/failure)
- ✅ Работа через Factory API и Container
- ✅ CRUD операции через Operation API
- ✅ Item API для работы с элементами
- ✅ Привязка контактов и компаний
- ✅ Фильтрация и поиск
- ✅ Статистика и аналитика
- ✅ Построение URL через Router

## Файлы

### 1. `install_dynamic_type.php`

**Назначение:** Скрипт для создания динамического типа "Проекты"

**Что делает:**
- Создаёт новый тип CRM сущности с кодом `PROJECT`
- Настраивает возможности (стадии, клиенты, документы, автоматизация)
- Создаёт 8 пользовательских полей:
  - `UF_PROJECT_BUDGET` - бюджет (число с точкой)
  - `UF_PRIORITY` - приоритет (список: Низкий/Средний/Высокий/Критический)
  - `UF_PROJECT_MANAGER` - менеджер проекта (пользователь)
  - `UF_PROJECT_TEAM` - команда (множественные пользователи)
  - `UF_PROGRESS` - прогресс выполнения (0-100%)
  - `UF_PROJECT_TYPE` - тип проекта (список: Разработка/Внедрение/Консалтинг/и т.д.)
  - `UF_DESCRIPTION` - описание (текст)
  - `UF_PROJECT_FILES` - файлы (множественные файлы)
- Создаёт 9 стадий:
  - Новый проект
  - Анализ требований
  - Планирование
  - Разработка
  - Тестирование
  - Внедрение
  - Завершён успешно (SUCCESS)
  - Отменён (FAILURE)
  - Приостановлен (FAILURE)

**Запуск:**
```bash
# Разместить в /local/tools/
# Открыть в браузере (требуются права администратора)
https://your-domain.com/local/tools/install_dynamic_type.php
```

**Важно:**
- После запуска запомните `Entity Type ID` - он понадобится для работы с API
- Замените константу `ENTITY_TYPE_ID` в `ProjectService.php` на полученный ID

### 2. `ProjectService.php`

**Назначение:** Сервисный класс для работы с проектами через Factory API

**Основные методы:**

```php
// Создание проекта
$result = $projectService->createProject([
    'TITLE' => 'Название',
    'STAGE_ID' => 'NEW',
    'UF_PROJECT_BUDGET' => 500000,
    'UF_PRIORITY' => 'HIGH',
    'CONTACT_IDS' => [1, 2],
]);

// Обновление проекта
$result = $projectService->updateProject($id, [
    'TITLE' => 'Новое название',
    'UF_PROGRESS' => 50,
]);

// Удаление проекта
$result = $projectService->deleteProject($id);

// Получение проекта
$item = $projectService->getProject($id);

// Получение списка проектов
$items = $projectService->getProjects(
    ['=STAGE_ID' => 'NEW'],  // Фильтр
    ['ID' => 'DESC'],        // Сортировка
    50,                      // Лимит
    0                        // Смещение
);

// Подсчёт проектов
$count = $projectService->getProjectsCount(['=STAGE_ID' => 'NEW']);

// Изменение стадии
$result = $projectService->changeStage($id, 'DEVELOPMENT');

// Обновление прогресса
$result = $projectService->updateProgress($id, 75);

// Назначение менеджера
$result = $projectService->assignManager($id, $userId);

// Добавление членов команды
$result = $projectService->addTeamMembers($id, [4, 5, 6]);

// Получение проектов менеджера
$items = $projectService->getProjectsByManager($userId);

// Получение активных проектов
$items = $projectService->getActiveProjects();

// Получение проектов с высоким приоритетом
$items = $projectService->getHighPriorityProjects();

// Статистика
$stats = $projectService->getStatistics();
```

**Особенности:**
- Все операции используют Operation API для проверки прав
- Автоматическая валидация данных
- Обработка ошибок через Result
- Поддержка фильтрации и сортировки
- Работа с привязками (контакты, компании)

### 3. `usage_examples.php`

**Назначение:** 15 практических примеров использования ProjectService

**Примеры:**
1. Создание нового проекта со всеми полями
2. Получение проекта по ID и вывод данных
3. Обновление полей проекта
4. Изменение стадии проекта
5. Обновление прогресса выполнения
6. Добавление членов команды
7. Получение списка проектов с фильтром
8. Подсчёт и статистика по проектам
9. Фильтрация активных проектов
10. Получение проектов менеджера
11. Фильтрация по высокому приоритету
12. Прямая работа с Factory (поля, стадии)
13. Построение URL для элементов
14. Удаление проекта (закомментировано)
15. Пакетное создание проектов

**Запуск:**
```bash
# Разместить в /local/tools/ вместе с ProjectService.php
https://your-domain.com/local/tools/usage_examples.php
```

## Инструкция по использованию

### Шаг 1: Установка динамического типа

1. Разместите `install_dynamic_type.php` в `/local/tools/`
2. Откройте в браузере под учётной записью администратора
3. Запомните полученный `Entity Type ID`

### Шаг 2: Настройка ProjectService

Откройте `ProjectService.php` и замените константу:

```php
private const ENTITY_TYPE_ID = 128; // Замените на ваш ID
```

### Шаг 3: Использование в коде

```php
require_once __DIR__ . '/ProjectService.php';

use MyCompany\ProjectManager\ProjectService;

try {
    $projectService = new ProjectService();
    
    // Создать проект
    $result = $projectService->createProject([
        'TITLE' => 'Мой первый проект',
        'STAGE_ID' => 'NEW',
        'UF_PROJECT_BUDGET' => 100000,
        'UF_PRIORITY' => 'MEDIUM',
        'UF_PROJECT_MANAGER' => 1,
    ]);
    
    if ($result->isSuccess()) {
        $projectId = $result->getData()['ID'];
        echo "Проект создан! ID: {$projectId}";
    } else {
        foreach ($result->getErrorMessages() as $error) {
            echo "Ошибка: {$error}";
        }
    }
    
} catch (\Exception $e) {
    echo "Исключение: {$e->getMessage()}";
}
```

## Ключевые концепции

### Container и Factory

```php
use Bitrix\Crm\Service\Container;

// Получить контейнер (singleton)
$container = Container::getInstance();

// Получить фабрику для типа
$factory = $container->getFactory($entityTypeId);

// Получить Router для URL
$router = $container->getRouter();

// Получить UserFieldManager
$ufManager = $container->getUserFieldManager();
```

### Item API

```php
// Создать новый Item
$item = $factory->createItem();

// Установить поля
$item->setTitle('Название');
$item->setStageId('NEW');
$item->setAssignedById(1);

// Установить пользовательские поля
$item->set('UF_PROJECT_BUDGET', 100000);
$item->set('UF_PRIORITY', 'HIGH');

// Получить значения
$title = $item->getTitle();
$stageId = $item->getStageId();
$budget = $item->get('UF_PROJECT_BUDGET');

// Работа с привязками
$item->setContactIds([1, 2, 3]);
$item->setCompanyId(5);

$contactIds = $item->getContactIds();
$companyId = $item->getCompanyId();
```

### Operation API

```php
// Добавление с проверкой прав
$addOperation = $factory->getAddOperation($item);
$result = $addOperation->launch();

if ($result->isSuccess()) {
    $id = $result->getId();
} else {
    $errors = $result->getErrorMessages();
}

// Обновление с проверкой прав
$updateOperation = $factory->getUpdateOperation($item);
$result = $updateOperation->launch();

// Удаление с проверкой прав
$deleteOperation = $factory->getDeleteOperation($item);
$result = $deleteOperation->launch();
```

### Фильтрация и выборка

```php
// Получить элементы
$items = $factory->getItems([
    'filter' => [
        '=STAGE_ID' => 'NEW',
        '>UF_PROJECT_BUDGET' => 100000,
        '@UF_PRIORITY' => ['HIGH', 'CRITICAL'],
        '!@STAGE_SEMANTIC_ID' => ['S', 'F'], // Не SUCCESS и не FAILURE
    ],
    'order' => ['ID' => 'DESC'],
    'limit' => 50,
    'offset' => 0,
]);

// Подсчитать количество
$count = $factory->getItemsCount([
    '=STAGE_ID' => 'NEW',
]);
```

## Типичные ошибки и решения

### 1. Фабрика не найдена

**Проблема:**
```php
$factory = Container::getInstance()->getFactory(128);
// $factory === null
```

**Решение:**
- Проверьте, что тип создан (`TypeTable::getById($typeId)`)
- Инвалидируйте кеш: `Container::getInstance()->getDynamicTypesMap()->invalidate()`
- Убедитесь, что используете правильный `ENTITY_TYPE_ID`, а не `ID` из таблицы

### 2. Пользовательские поля не создаются

**Проблема:**
```php
$field = ['ENTITY_ID' => 'CRM_128', ...]; // Неправильно
```

**Решение:**
```php
// Используйте ResolveUserFieldEntityID
$entityId = \CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);
$field = ['ENTITY_ID' => $entityId, ...]; // Правильно
```

### 3. Стадии не отображаются

**Проблема:**
Стадии созданы, но не видны в интерфейсе.

**Решение:**
- Проверьте `ENTITY_ID`: должен быть `'DYNAMIC_' . $entityTypeId . '_STAGE_0'`
- Убедитесь, что `IS_STAGES_ENABLED => 'Y'` при создании типа
- Очистите кеш Bitrix

### 4. Права доступа

**Проблема:**
```php
$item->save(); // AccessDeniedException
```

**Решение:**
```php
// Всегда используйте Operation API
$operation = $factory->getAddOperation($item);
$result = $operation->launch();

if (!$result->isSuccess()) {
    // Обработать ошибку прав доступа
}
```

### 5. Работа с enumeration полями

**Проблема:**
Как получить ID значения из списка?

**Решение:**
```php
$fields = $factory->getFieldsInfo();
$priorityField = $fields['UF_PRIORITY'];

foreach ($priorityField['ITEMS'] as $item) {
    if ($item['XML_ID'] === 'HIGH' || $item['VALUE'] === 'Высокий') {
        $highPriorityId = $item['ID'];
        break;
    }
}

// Использовать ID
$item->set('UF_PRIORITY', $highPriorityId);
```

## Полезные ссылки

- [Документация Factory API](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/service/factory.php)
- [Item API](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/item.php)
- [Operation API](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/service/operation/)
- [Динамические типы](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=268)

## Что дальше?

После изучения этой задачи:
1. Попробуйте создать свой динамический тип (например, "Контракты" или "Оборудование")
2. Добавьте свои пользовательские поля
3. Создайте REST API для работы с типом (см. Задачу 3)
4. Интегрируйте с бизнес-процессами и роботами
5. Добавьте автоматизацию (события OnBefore/OnAfter)
