# Модуль 14.4: CRM Фабрики и динамические типы (SPA)

## Теория

### Что такое CRM Фабрики (Factory)

**CRM Фабрики** — это механизм Bitrix24, позволяющий создавать динамические типы сущностей CRM (SPA - Smart Process Automation) с собственной структурой, полями и бизнес-логикой.

С помощью фабрик можно:
- Создавать кастомные сущности CRM (например: "Проекты", "Контракты", "Оборудование")
- Управлять полями динамических типов
- Настраивать права доступа
- Создавать воронки и стадии
- Интегрировать с существующей CRM

### Основные классы

#### 1. Factory (\Bitrix\Crm\Service\Factory)

Главный класс для работы с типами сущностей:

```php
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Factory;

// Получить фабрику для конкретного типа
$factory = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);

// Получить все фабрики динамических типов
$factories = Container::getInstance()->getDynamicTypesMap();
```

#### 2. Container (\Bitrix\Crm\Service\Container)

Центральный контейнер для доступа к сервисам CRM:

```php
$container = Container::getInstance();

// Получить фабрику по ID типа
$factory = $container->getFactory($entityTypeId);

// Получить Router для работы с URL
$router = $container->getRouter();

// Получить сервис для работы с пользовательскими полями
$userFieldManager = $container->getUserFieldManager();
```

#### 3. Item (\Bitrix\Crm\Item)

Представляет отдельную запись сущности CRM:

```php
// Создать новый элемент
$item = $factory->createItem();
$item->set('TITLE', 'Название');
$item->set('STAGE_ID', 'NEW');

// Сохранить
$result = $item->save();

// Получить элемент по ID
$item = $factory->getItem($id);

// Обновить
$item->set('STAGE_ID', 'IN_PROCESS');
$item->save();

// Удалить
$factory->getDeleteOperation($item)->launch();
```

### Создание динамического типа

```php
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Service\Container;

// Создать новый тип
$result = TypeTable::add([
    'TITLE' => 'Проекты',
    'NAME' => 'Проект',
    'CODE' => 'PROJECT',
    'ENTITY_TYPE_ID' => \CCrmOwnerType::getDynamicTypeBasedStaticId(\CCrmOwnerType::FirstDynamicTypeId),
    'IS_STAGES_ENABLED' => 'Y',
    'IS_BEGIN_CLOSE_DATES_ENABLED' => 'Y',
    'IS_CLIENT_ENABLED' => 'Y',
    'IS_USE_IN_USERFIELD_ENABLED' => 'Y',
    'IS_CATEGORIES_ENABLED' => 'N',
    'IS_DOCUMENTS_ENABLED' => 'Y',
    'IS_SOURCE_ENABLED' => 'Y',
    'IS_RECYCLEBIN_ENABLED' => 'Y',
    'IS_AUTOMATION_ENABLED' => 'Y',
    'IS_BIZ_PROC_ENABLED' => 'Y',
    'IS_SET_OPEN_PERMISSIONS' => 'Y',
    'IS_LINK_WITH_PRODUCTS_ENABLED' => 'N',
]);

if ($result->isSuccess()) {
    $typeId = $result->getId();
}
```

### Работа с полями динамического типа

```php
use Bitrix\Crm\Service\Container;

$factory = Container::getInstance()->getFactory($entityTypeId);

// Получить все поля
$fieldsInfo = $factory->getFieldsInfo();

// Создать пользовательское поле
$userFieldManager = Container::getInstance()->getUserFieldManager();

$field = [
    'ENTITY_ID' => \CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId),
    'FIELD_NAME' => 'UF_PRIORITY',
    'USER_TYPE_ID' => 'enumeration',
    'EDIT_FORM_LABEL' => ['ru' => 'Приоритет'],
    'LIST' => [
        ['VALUE' => 'Низкий', 'SORT' => 100],
        ['VALUE' => 'Средний', 'SORT' => 200, 'DEF' => 'Y'],
        ['VALUE' => 'Высокий', 'SORT' => 300],
    ],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
];

$fieldId = $userFieldManager->addField($field);
```

### Стадии (Stages) для динамических типов

```php
use Bitrix\Crm\PhaseSemantics;
use Bitrix\Crm\StatusTable;

// Создать стадии для динамического типа
$entityTypeId = 128; // ID вашего динамического типа
$statusEntityId = 'DYNAMIC_' . $entityTypeId . '_STAGE_1';

$stages = [
    [
        'STATUS_ID' => 'NEW',
        'NAME' => 'Новый',
        'SORT' => 100,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#39A8EF',
    ],
    [
        'STATUS_ID' => 'IN_WORK',
        'NAME' => 'В работе',
        'SORT' => 200,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#FFA900',
    ],
    [
        'STATUS_ID' => 'SUCCESS',
        'NAME' => 'Успешно завершен',
        'SORT' => 300,
        'SEMANTICS' => PhaseSemantics::SUCCESS,
        'COLOR' => '#7BD500',
    ],
    [
        'STATUS_ID' => 'FAIL',
        'NAME' => 'Провалено',
        'SORT' => 400,
        'SEMANTICS' => PhaseSemantics::FAILURE,
        'COLOR' => '#FF5752',
    ],
];

foreach ($stages as $stage) {
    StatusTable::add([
        'ENTITY_ID' => $statusEntityId,
        'STATUS_ID' => $stage['STATUS_ID'],
        'NAME' => $stage['NAME'],
        'SORT' => $stage['SORT'],
        'SEMANTICS' => $stage['SEMANTICS'],
        'COLOR' => $stage['COLOR'],
    ]);
}
```

### Права доступа для динамических типов

```php
use Bitrix\Crm\Security\EntityAuthorization;
use Bitrix\Crm\Service\Container;

$factory = Container::getInstance()->getFactory($entityTypeId);
$authorization = Container::getInstance()->getUserPermissions()->getCrmPermissions();

// Проверить право на чтение
$canRead = $authorization->checkReadPermission($entityTypeId, $entityId);

// Проверить право на редактирование
$canUpdate = $authorization->checkUpdatePermission($entityTypeId, $entityId);

// Проверить право на удаление
$canDelete = $authorization->checkDeletePermission($entityTypeId, $entityId);

// Настроить права для роли
use Bitrix\Crm\Service\UserPermissions;

$roleId = 1; // ID роли
$permissions = [
    'ADD' => 'Y',
    'WRITE' => 'Y',
    'DELETE' => 'Y',
    'EXPORT' => 'Y',
];

// Установить права через настройки CRM
```

### Operation API для безопасной работы с данными

```php
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Operation;

$factory = Container::getInstance()->getFactory($entityTypeId);

// Создание с проверкой прав
$item = $factory->createItem(['TITLE' => 'Тест']);
$addOperation = $factory->getAddOperation($item);
$result = $addOperation->launch();

if ($result->isSuccess()) {
    $itemId = $result->getId();
} else {
    $errors = $result->getErrorMessages();
}

// Обновление с проверкой прав
$item = $factory->getItem($itemId);
$item->set('STAGE_ID', 'IN_WORK');
$updateOperation = $factory->getUpdateOperation($item);
$result = $updateOperation->launch();

// Удаление с проверкой прав
$deleteOperation = $factory->getDeleteOperation($item);
$result = $deleteOperation->launch();
```

### DataManager для динамических типов

```php
use Bitrix\Crm\Model\Dynamic\Item;
use Bitrix\Crm\Model\Dynamic\TypeTable;

// Получить DataManager класс для типа
$type = TypeTable::getById($typeId)->fetch();
$tableClass = $type['TABLE_NAME']; // например: DynamicItem128Table

// Использовать ORM
$items = $tableClass::getList([
    'select' => ['ID', 'TITLE', 'STAGE_ID', 'CREATED_TIME'],
    'filter' => ['=STAGE_ID' => 'NEW'],
    'order' => ['CREATED_TIME' => 'DESC'],
    'limit' => 10,
])->fetchAll();
```

### События для динамических типов

```php
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

// Перед добавлением элемента
$eventManager->addEventHandler(
    'crm',
    'OnBeforeCrmDynamicItemAdd',
    function($event) {
        $fields = $event->getParameter('fields');
        $entityTypeId = $event->getParameter('entityTypeId');
        
        // Ваша логика
        if (empty($fields['TITLE'])) {
            return new \Bitrix\Main\EventResult(
                \Bitrix\Main\EventResult::ERROR,
                ['error' => 'Заголовок обязателен']
            );
        }
        
        return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS);
    }
);

// После добавления элемента
$eventManager->addEventHandler(
    'crm',
    'OnAfterCrmDynamicItemAdd',
    function($event) {
        $id = $event->getParameter('id');
        $fields = $event->getParameter('fields');
        
        // Отправить уведомление, запустить бизнес-процесс и т.д.
    }
);

// Перед обновлением
$eventManager->addEventHandler(
    'crm',
    'OnBeforeCrmDynamicItemUpdate',
    function($event) {
        // Аналогично Add
    }
);

// После обновления
$eventManager->addEventHandler(
    'crm',
    'OnAfterCrmDynamicItemUpdate',
    function($event) {
        // Аналогично Add
    }
);
```

### Router для построения URL

```php
use Bitrix\Crm\Service\Container;

$router = Container::getInstance()->getRouter();

// URL детальной карточки
$detailUrl = $router->getItemDetailUrl($entityTypeId, $itemId);

// URL списка
$listUrl = $router->getItemListUrl($entityTypeId);

// URL канбана
$kanbanUrl = $router->getKanbanUrl($entityTypeId);
```

### Timeline (лента активности)

```php
use Bitrix\Crm\Timeline\Entity\TimelineTable;
use Bitrix\Crm\Timeline\TimelineType;

// Добавить запись в ленту
$controller = \Bitrix\Crm\Timeline\Controller::getInstance();

$controller->onCreate(
    $itemId,
    [
        'AUTHOR_ID' => $userId,
        'CREATED' => new \Bitrix\Main\Type\DateTime(),
        'SETTINGS' => [
            'COMMENT' => 'Элемент создан',
        ],
    ]
);

// Добавить комментарий
\Bitrix\Crm\Timeline\CommentController::getInstance()->add(
    $itemId,
    $entityTypeId,
    [
        'TEXT' => 'Мой комментарий',
        'AUTHOR_ID' => $userId,
    ]
);
```

### Интеграция с продуктами

```php
use Bitrix\Crm\Binding\ProductRowTable;

// Привязать товары к элементу динамического типа
$products = [
    [
        'PRODUCT_ID' => 123,
        'PRODUCT_NAME' => 'Товар 1',
        'PRICE' => 1000,
        'QUANTITY' => 2,
        'DISCOUNT_RATE' => 10,
    ],
    [
        'PRODUCT_ID' => 456,
        'PRODUCT_NAME' => 'Товар 2',
        'PRICE' => 500,
        'QUANTITY' => 1,
    ],
];

foreach ($products as $index => $product) {
    ProductRowTable::add([
        'OWNER_ID' => $itemId,
        'OWNER_TYPE' => \CCrmOwnerTypeAbbr::ResolveByTypeID($entityTypeId),
        'PRODUCT_ID' => $product['PRODUCT_ID'],
        'PRODUCT_NAME' => $product['PRODUCT_NAME'],
        'PRICE' => $product['PRICE'],
        'QUANTITY' => $product['QUANTITY'],
        'DISCOUNT_RATE' => $product['DISCOUNT_RATE'] ?? 0,
        'SORT' => ($index + 1) * 10,
    ]);
}
```

### Конвертация элементов

```php
use Bitrix\Crm\Conversion\EntityConversionWizard;
use Bitrix\Crm\Conversion\EntityConverter;

// Конвертировать лид в сделку и контакт
$converter = new EntityConverter(
    \CCrmOwnerType::Lead,
    $leadId
);

$config = $converter->getConfig();
$config->enableEntityType(\CCrmOwnerType::Deal);
$config->enableEntityType(\CCrmOwnerType::Contact);

$result = $converter->execute();

if ($result->isSuccess()) {
    $entities = $result->getData();
    $dealId = $entities[\CCrmOwnerType::Deal] ?? null;
    $contactId = $entities[\CCrmOwnerType::Contact] ?? null;
}
```

---

## Вопросы для собеседования

### 1. Что такое CRM фабрики и для чего они используются?

**Ответ:**

CRM Фабрики (Factory) — это механизм Bitrix24 для унифицированной работы с различными типами сущностей CRM (лиды, сделки, контакты, динамические типы).

**Основные возможности:**
- Единый интерфейс для работы с любым типом CRM
- Создание динамических типов (SPA)
- Управление полями и стадиями
- Проверка прав доступа через Operation API
- Работа с Item (элементами сущностей)

**Преимущества:**
- Код не зависит от конкретного типа сущности
- Автоматическая проверка прав
- Единообразная обработка ошибок
- Поддержка новых динамических типов

### 2. Как получить фабрику для работы с типом CRM?

**Ответ:**

```php
use Bitrix\Crm\Service\Container;

// Для стандартных типов (сделки, лиды и т.д.)
$factory = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);

// Для динамических типов по ID
$entityTypeId = 128;
$factory = Container::getInstance()->getFactory($entityTypeId);

// Проверить существование фабрики
if ($factory === null) {
    // Фабрика не найдена
}

// Получить все динамические типы
$factories = Container::getInstance()->getDynamicTypesMap();
foreach ($factories as $typeId => $factory) {
    // Работа с каждой фабрикой
}
```

### 3. Чем Item отличается от массива с данными?

**Ответ:**

**Item (\Bitrix\Crm\Item)** — это объектная обёртка над записью CRM с дополнительными возможностями:

**Преимущества Item:**
1. **Типобезопасность**: автоматическое приведение типов полей
2. **Валидация**: проверка обязательных полей
3. **Связи**: удобная работа с контактами, компаниями, товарами
4. **Изменения**: трекинг изменённых полей для оптимизации
5. **Бизнес-логика**: автоматический запуск событий и проверок

```php
// Массив - "сырые" данные
$data = ['TITLE' => 'Тест', 'STAGE_ID' => 'NEW'];

// Item - объект с логикой
$item = $factory->createItem();
$item->setTitle('Тест');
$item->setStageId('NEW');

// Получить только изменённые поля
$changedFields = $item->getChangedFieldNames();

// Работа с контактами
$item->setContactIds([1, 2, 3]);
$contacts = $item->getContacts(); // Вернёт Item[] контактов
```

### 4. Что такое Operation API и зачем он нужен?

**Ответ:**

**Operation API** — это слой для безопасной работы с данными CRM через операции Add/Update/Delete.

**Основные задачи:**
1. **Проверка прав доступа** - автоматически проверяет права пользователя
2. **Валидация данных** - проверяет обязательные поля и форматы
3. **Запуск событий** - триггерит OnBefore/OnAfter события
4. **Транзакции** - обеспечивает атомарность операций
5. **Обработка ошибок** - единообразное возвращение результата

```php
$factory = Container::getInstance()->getFactory($entityTypeId);

// БЕЗ Operation API (небезопасно, нет проверки прав)
$item = $factory->createItem(['TITLE' => 'Тест']);
$item->save(); // Может сохранить без прав!

// С Operation API (безопасно)
$addOperation = $factory->getAddOperation($item);
$result = $addOperation->launch();

if (!$result->isSuccess()) {
    // Обработать ошибки (нет прав, невалидные данные и т.д.)
    $errors = $result->getErrorMessages();
}
```

### 5. Как создать динамический тип (Smart Process)?

**Ответ:**

```php
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Service\Container;

$result = TypeTable::add([
    'TITLE' => 'Проекты', // Название во множественном числе
    'NAME' => 'Проект',   // Название в единственном числе
    'CODE' => 'PROJECT',  // Символьный код
    
    // Базовый ID для динамического типа
    'ENTITY_TYPE_ID' => \CCrmOwnerType::getDynamicTypeBasedStaticId(
        \CCrmOwnerType::FirstDynamicTypeId
    ),
    
    // Включенные возможности
    'IS_STAGES_ENABLED' => 'Y',              // Стадии
    'IS_BEGIN_CLOSE_DATES_ENABLED' => 'Y',   // Даты начала/завершения
    'IS_CLIENT_ENABLED' => 'Y',              // Привязка клиентов
    'IS_USE_IN_USERFIELD_ENABLED' => 'Y',    // Использование в UF
    'IS_CATEGORIES_ENABLED' => 'N',          // Направления (воронки)
    'IS_DOCUMENTS_ENABLED' => 'Y',           // Документы
    'IS_SOURCE_ENABLED' => 'Y',              // Источник
    'IS_RECYCLEBIN_ENABLED' => 'Y',          // Корзина
    'IS_AUTOMATION_ENABLED' => 'Y',          // Автоматизация
    'IS_BIZ_PROC_ENABLED' => 'Y',            // Бизнес-процессы
    'IS_SET_OPEN_PERMISSIONS' => 'Y',        // Открытые права
    'IS_LINK_WITH_PRODUCTS_ENABLED' => 'N',  // Товарные позиции
]);

if ($result->isSuccess()) {
    $typeId = $result->getId();
    
    // Получить фабрику для нового типа
    Container::getInstance()->getDynamicTypesMap()->invalidate();
    $factory = Container::getInstance()->getFactory($typeId);
}
```

### 6. Как добавить пользовательские поля в динамический тип?

**Ответ:**

```php
use Bitrix\Crm\Service\Container;

$entityTypeId = 128; // ID динамического типа
$entityId = \CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);

// Список (enumeration)
$field = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PRIORITY',
    'USER_TYPE_ID' => 'enumeration',
    'EDIT_FORM_LABEL' => ['ru' => 'Приоритет', 'en' => 'Priority'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Приоритет'],
    'LIST' => [
        ['VALUE' => 'Низкий', 'SORT' => 100],
        ['VALUE' => 'Средний', 'SORT' => 200, 'DEF' => 'Y'],
        ['VALUE' => 'Высокий', 'SORT' => 300],
    ],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
];

$userFieldManager = Container::getInstance()->getUserFieldManager();
$fieldId = $userFieldManager->addField($field);

// Другие типы полей
$types = [
    'string' => 'Строка',
    'integer' => 'Число',
    'double' => 'Число с точкой',
    'datetime' => 'Дата/Время',
    'date' => 'Дата',
    'boolean' => 'Да/Нет',
    'file' => 'Файл',
    'enumeration' => 'Список',
    'crm' => 'Привязка к CRM',
    'employee' => 'Привязка к пользователю',
];
```

### 7. Как настроить стадии для динамического типа?

**Ответ:**

```php
use Bitrix\Crm\StatusTable;
use Bitrix\Crm\PhaseSemantics;

$entityTypeId = 128;
$categoryId = 0; // Для типов без направлений всегда 0

// Идентификатор сущности для статусов
$statusEntityId = 'DYNAMIC_' . $entityTypeId . '_STAGE_' . $categoryId;

$stages = [
    [
        'STATUS_ID' => 'NEW',
        'NAME' => 'Новый',
        'SORT' => 100,
        'SEMANTICS' => PhaseSemantics::PROCESS, // Промежуточная стадия
        'COLOR' => '#39A8EF',
    ],
    [
        'STATUS_ID' => 'IN_WORK',
        'NAME' => 'В работе',
        'SORT' => 200,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#FFA900',
    ],
    [
        'STATUS_ID' => 'SUCCESS',
        'NAME' => 'Успешно',
        'SORT' => 300,
        'SEMANTICS' => PhaseSemantics::SUCCESS, // Успешная стадия
        'COLOR' => '#7BD500',
    ],
    [
        'STATUS_ID' => 'FAIL',
        'NAME' => 'Провал',
        'SORT' => 400,
        'SEMANTICS' => PhaseSemantics::FAILURE, // Провальная стадия
        'COLOR' => '#FF5752',
    ],
];

foreach ($stages as $stage) {
    StatusTable::add([
        'ENTITY_ID' => $statusEntityId,
        'STATUS_ID' => $stage['STATUS_ID'],
        'NAME' => $stage['NAME'],
        'SORT' => $stage['SORT'],
        'SEMANTICS' => $stage['SEMANTICS'],
        'COLOR' => $stage['COLOR'],
    ]);
}

// Типы семантики:
// PhaseSemantics::PROCESS - промежуточная стадия (работа идёт)
// PhaseSemantics::SUCCESS - успешное завершение
// PhaseSemantics::FAILURE - провальное завершение
```

### 8. Как работать с привязками (контакты, компании)?

**Ответ:**

```php
$factory = Container::getInstance()->getFactory($entityTypeId);
$item = $factory->getItem($itemId);

// Установить контакты
$item->setContactIds([1, 2, 3]);

// Получить ID контактов
$contactIds = $item->getContactIds();

// Установить компанию
$item->setCompanyId(10);

// Получить ID компании
$companyId = $item->getCompanyId();

// Работа с привязками через Binding API
use Bitrix\Crm\Binding\ContactTable;

// Получить все контакты элемента
$bindings = ContactTable::getList([
    'filter' => [
        '=ENTITY_TYPE_ID' => $entityTypeId,
        '=ENTITY_ID' => $itemId,
    ],
])->fetchAll();

// Добавить контакт
ContactTable::add([
    'ENTITY_TYPE_ID' => $entityTypeId,
    'ENTITY_ID' => $itemId,
    'CONTACT_ID' => $contactId,
    'SORT' => 100,
    'IS_PRIMARY' => 'Y',
]);

// Установить основной контакт
ContactTable::bindContactIsPrimary(
    $itemId,
    $contactId,
    $entityTypeId
);
```

### 9. Как построить URL для элементов динамического типа?

**Ответ:**

```php
use Bitrix\Crm\Service\Container;

$router = Container::getInstance()->getRouter();
$entityTypeId = 128;
$itemId = 1;

// URL карточки элемента (детальная)
$detailUrl = $router->getItemDetailUrl($entityTypeId, $itemId);
// /crm/type/128/details/1/

// URL списка элементов
$listUrl = $router->getItemListUrl($entityTypeId);
// /crm/type/128/list/

// URL канбана
$kanbanUrl = $router->getKanbanUrl($entityTypeId);
// /crm/type/128/kanban/

// URL календаря
$calendarUrl = $router->getCalendarUrl($entityTypeId);
// /crm/type/128/calendar/

// URL создания нового элемента
$createUrl = $router->getItemDetailUrl($entityTypeId, 0);
// /crm/type/128/details/0/

// URL с категорией (если включены направления)
$categoryId = 1;
$urlWithCategory = $router->getItemListUrl($entityTypeId, $categoryId);
```

### 10. Какие события доступны для динамических типов?

**Ответ:**

**События через EventManager:**

```php
use Bitrix\Main\EventManager;

$em = EventManager::getInstance();

// Перед добавлением
$em->addEventHandler('crm', 'OnBeforeCrmDynamicItemAdd', 
    function($event) {
        $fields = $event->getParameter('fields');
        $entityTypeId = $event->getParameter('entityTypeId');
        
        // Модификация полей или отмена операции
        if (!isset($fields['TITLE'])) {
            return new \Bitrix\Main\EventResult(
                \Bitrix\Main\EventResult::ERROR,
                ['error' => 'Title required']
            );
        }
    }
);

// После добавления
$em->addEventHandler('crm', 'OnAfterCrmDynamicItemAdd',
    function($event) {
        $id = $event->getParameter('id');
        $fields = $event->getParameter('fields');
        // Дополнительная логика после создания
    }
);

// Перед обновлением
$em->addEventHandler('crm', 'OnBeforeCrmDynamicItemUpdate',
    function($event) {
        // Аналогично Add
    }
);

// После обновления
$em->addEventHandler('crm', 'OnAfterCrmDynamicItemUpdate',
    function($event) {
        // Аналогично Add
    }
);

// Перед удалением
$em->addEventHandler('crm', 'OnBeforeCrmDynamicItemDelete',
    function($event) {
        // Можно запретить удаление
    }
);

// После удаления
$em->addEventHandler('crm', 'OnAfterCrmDynamicItemDelete',
    function($event) {
        // Очистка связанных данных
    }
);
```

**События через подписку в модуле:**

```php
// В файле install/index.php модуля
public function InstallEvents()
{
    \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
        'crm',
        'OnAfterCrmDynamicItemAdd',
        $this->MODULE_ID,
        '\\Vendor\\Module\\EventHandlers',
        'onAfterItemAdd'
    );
}
```

---

## Практические задачи

В этом модуле мы создадим 3 практические задачи:

1. **Система управления проектами (SPA)** - создание динамического типа "Проекты" с полями, стадиями и интеграцией
2. **Модуль для работы с заявками** - полноценный модуль с CRUD операциями через Factory API
3. **REST API для динамического типа** - создание собственного REST API для работы с SPA

**Решения задач находятся в `examples/module-14-4/`**

---

## Полезные ссылки

- [Документация по Factory API](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=268)
- [Динамические сущности CRM](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/service/factory.php)
- [Operation API](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/service/operation/)
- [Item API](https://dev.1c-bitrix.ru/api_d7/bitrix/crm/item.php)

---

## Частые ошибки

1. **Прямое сохранение Item без Operation API** - пропускает проверку прав
2. **Забыли инвалидировать кеш фабрик** после создания нового типа
3. **Неправильный entityId для пользовательских полей** - используйте ResolveUserFieldEntityID()
4. **Работа со стадиями без учёта семантики** - не забывайте PhaseSemantics
5. **Игнорирование Result->isSuccess()** - всегда проверяйте результат операций
