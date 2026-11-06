# Модуль 14.1: Создание своего модуля (структура, install/uninstall, миграции)

## Теория

### Что такое модуль в Bitrix

**Модуль** — это изолированный программный компонент, который расширяет функциональность Bitrix24 Коробка. Модули используются для:

- Добавления новой бизнес-логики
- Интеграции с внешними системами
- Создания собственных CRM-сущностей
- Расширения REST API
- Добавления административных интерфейсов

### ⚠️ Важно: БУС vs Битрикс24

**Это критическое отличие для понимания:**

| Аспект | БУС (Управление сайтом) | Битрикс24 |
|--------|-------------------------|-----------|
| Философия | Пустой фреймворк | Готовый продукт |
| Проекты | Каждый уникален | Единая кодовая база |
| Модификация ядра | Допускается (не рекомендуется) | **Запрещено** |
| Копирование компонентов в `/local/` | Стандартная практика | **Опасно и не рекомендуется** |
| Модули | Опциональный подход | **Основная методика доработок** |
| Доступ к файлам | Всегда открыт | Облако — закрыт, коробка — открыт |

**Ключевой принцип Битрикс24:** Не переписывай ядро — расширяй через модули!

### Структура модуля

```
/local/modules/vendor.modulename/
├── install/
│   ├── index.php              # Класс установки модуля (CModule)
│   ├── version.php            # Версия модуля
│   ├── components/            # Компоненты (копируются при установке)
│   └── db/                    # Миграции БД (опционально)
│       ├── install.sql        # SQL для установки
│       └── uninstall.sql      # SQL для удаления
├── lib/                       # Классы модуля (D7, автозагрузка)
│   ├── Service/               # Бизнес-логика
│   │   └── RecordService.php  # Сервис с методами работы с данными
│   ├── Controller/            # Контроллеры (AJAX)
│   │   └── RecordController.php
│   └── Entity/                # ORM-сущности
│       └── RecordTable.php    # DataManager-модели
├── lang/                      # Языковые файлы
│   └── ru/
│       └── install/
│           └── index.php      # Переводы для установщика
├── .settings.php              # Конфигурация (контроллеры, Service Locator)
├── options.php                # Страница настроек в админке
└── include.php                # Точка входа, регистрация автозагрузки классов
```

**Ключевые принципы:**
- **Логика в модуле** — сервисы, контроллеры, ORM
- **Компонент — обёртка** — вызывает сервисы модуля
- **Не переписывай ядро** — не трогай `/bitrix/`

### Именование модуля

**Правила:**
- Формат: `vendor.modulename` (только латиница, точка, строчные буквы)
- `vendor` — префикс компании (например: `acme`, `mycompany`)
- `modulename` — название модуля (например: `warehouse`, `crm.custom`)
- Примеры: `acme.warehouse`, `mycompany.crm.analytics`

**Важно:**
- Не используйте префикс `bitrix.` — он зарезервирован
- Не используйте подчеркивания, пробелы, заглавные буквы

### Файл версии (install/version.php)

```php
<?php
$arModuleVersion = [
    'VERSION' => '1.0.0',
    'VERSION_DATE' => '2024-01-15 12:00:00',
];
```

### Класс установки модуля (install/index.php)

```php
<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;

Loc::loadMessages(__FILE__);

class acme_warehouse extends CModule
{
    public $MODULE_ID = 'acme.warehouse';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('ACME_WAREHOUSE_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ACME_WAREHOUSE_MODULE_DESC');

        $this->PARTNER_NAME = 'ACME Corp';
        $this->PARTNER_URI = 'https://acme.com';
    }

    /**
     * Установка модуля
     */
    public function DoInstall()
    {
        global $APPLICATION;

        // Проверка версии PHP
        if (!$this->checkRequirements()) {
            $APPLICATION->ThrowException(
                Loc::getMessage('ACME_WAREHOUSE_REQUIREMENTS_ERROR')
            );
            return false;
        }

        // Регистрация модуля
        ModuleManager::registerModule($this->MODULE_ID);

        // Создание таблиц БД
        $this->InstallDB();

        // Копирование файлов
        $this->InstallFiles();

        // Регистрация событий
        $this->InstallEvents();

        // Установка агентов
        $this->InstallAgents();

        return true;
    }

    /**
     * Удаление модуля
     */
    public function DoUninstall()
    {
        global $APPLICATION;

        // Удаление агентов
        $this->UnInstallAgents();

        // Удаление событий
        $this->UnInstallEvents();

        // Удаление файлов
        $this->UnInstallFiles();

        // Удаление таблиц БД
        $this->UnInstallDB();

        // Удаление настроек
        Option::delete($this->MODULE_ID);

        // Снятие регистрации модуля
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }

    /**
     * Проверка требований
     */
    protected function checkRequirements(): bool
    {
        // Проверка версии PHP
        if (version_compare(PHP_VERSION, '7.4.0') < 0) {
            return false;
        }

        // Проверка наличия необходимых модулей
        if (!ModuleManager::isModuleInstalled('iblock')) {
            return false;
        }

        return true;
    }

    /**
     * Установка таблиц БД
     */
    public function InstallDB()
    {
        global $DB;

        $connection = Application::getConnection();
        
        // Выполнение SQL из файла
        $sqlPath = __DIR__ . '/db/install.sql';
        if (file_exists($sqlPath)) {
            $connection->executeSqlBatch(file_get_contents($sqlPath));
        }

        // Или создание через ORM
        if (!$connection->isTableExists('acme_warehouse_products')) {
            $connection->query("
                CREATE TABLE IF NOT EXISTS `acme_warehouse_products` (
                    `ID` INT(11) NOT NULL AUTO_INCREMENT,
                    `NAME` VARCHAR(255) NOT NULL,
                    `SKU` VARCHAR(50) NOT NULL,
                    `QUANTITY` INT(11) DEFAULT 0,
                    `PRICE` DECIMAL(10,2) DEFAULT 0.00,
                    `CREATED_AT` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `UPDATED_AT` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`ID`),
                    UNIQUE KEY `UX_SKU` (`SKU`),
                    KEY `IX_NAME` (`NAME`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

        return true;
    }

    /**
     * Удаление таблиц БД
     */
    public function UnInstallDB()
    {
        $connection = Application::getConnection();

        // Удаление таблиц
        $connection->dropTable('acme_warehouse_products');

        return true;
    }

    /**
     * Копирование файлов
     */
    public function InstallFiles()
    {
        // Копирование компонентов
        CopyDirFiles(
            __DIR__ . '/components',
            $_SERVER['DOCUMENT_ROOT'] . '/local/components',
            true,
            true
        );

        // Копирование JS/CSS
        CopyDirFiles(
            __DIR__ . '/js',
            $_SERVER['DOCUMENT_ROOT'] . '/local/js/' . $this->MODULE_ID,
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/css',
            $_SERVER['DOCUMENT_ROOT'] . '/local/css/' . $this->MODULE_ID,
            true,
            true
        );

        return true;
    }

    /**
     * Удаление файлов
     */
    public function UnInstallFiles()
    {
        // Удаление компонентов
        Directory::deleteDirectory(
            $_SERVER['DOCUMENT_ROOT'] . '/local/components/acme'
        );

        // Удаление JS/CSS
        Directory::deleteDirectory(
            $_SERVER['DOCUMENT_ROOT'] . '/local/js/' . $this->MODULE_ID
        );

        Directory::deleteDirectory(
            $_SERVER['DOCUMENT_ROOT'] . '/local/css/' . $this->MODULE_ID
        );

        return true;
    }

    /**
     * Регистрация обработчиков событий
     */
    public function InstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();

        // Регистрация события
        $eventManager->registerEventHandler(
            'iblock',
            'OnAfterIBlockElementAdd',
            $this->MODULE_ID,
            '\Acme\Warehouse\EventHandlers',
            'onIBlockElementAdd'
        );

        return true;
    }

    /**
     * Удаление обработчиков событий
     */
    public function UnInstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();

        $eventManager->unRegisterEventHandler(
            'iblock',
            'OnAfterIBlockElementAdd',
            $this->MODULE_ID,
            '\Acme\Warehouse\EventHandlers',
            'onIBlockElementAdd'
        );

        return true;
    }

    /**
     * Установка агентов
     */
    public function InstallAgents()
    {
        CAgent::AddAgent(
            '\Acme\Warehouse\Agents\SyncAgent::run();',
            $this->MODULE_ID,
            'N', // не проверять на дубли
            86400, // интервал 24 часа
            '', // дата первой проверки
            'Y', // активность
            '', // дата первого запуска
            100 // сортировка
        );

        return true;
    }

    /**
     * Удаление агентов
     */
    public function UnInstallAgents()
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);

        return true;
    }
}
```

### Точка входа модуля (include.php)

```php
<?php

// Подключение пространства имен
\Bitrix\Main\Loader::registerAutoLoadClasses(
    'acme.warehouse',
    [
        // ORM модели
        'Acme\Warehouse\Model\ProductTable' => 'lib/Model/ProductTable.php',
        
        // Сервисы
        'Acme\Warehouse\Service\ProductService' => 'lib/Service/ProductService.php',
        
        // Обработчики событий
        'Acme\Warehouse\EventHandlers' => 'lib/EventHandlers.php',
        
        // Агенты
        'Acme\Warehouse\Agents\SyncAgent' => 'lib/Agents/SyncAgent.php',
    ]
);
```

### ORM модель (lib/Model/ProductTable.php)

```php
<?php

namespace Acme\Warehouse\Model;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

class ProductTable extends Entity\DataManager
{
    /**
     * Название таблицы
     */
    public static function getTableName()
    {
        return 'acme_warehouse_products';
    }

    /**
     * Описание полей
     */
    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID',
            ]),

            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
                'title' => 'Название',
            ]),

            new Entity\StringField('SKU', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50),
                        new Entity\Validator\Unique(),
                    ];
                },
                'title' => 'Артикул',
            ]),

            new Entity\IntegerField('QUANTITY', [
                'default' => 0,
                'title' => 'Количество',
            ]),

            new Entity\FloatField('PRICE', [
                'default' => 0.00,
                'scale' => 2,
                'title' => 'Цена',
            ]),

            new Entity\DatetimeField('CREATED_AT', [
                'default' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания',
            ]),

            new Entity\DatetimeField('UPDATED_AT', [
                'default' => function() {
                    return new DateTime();
                },
                'title' => 'Дата обновления',
            ]),
        ];
    }

    /**
     * Действия перед добавлением
     */
    public static function onBeforeAdd(Entity\Event $event)
    {
        $result = new Entity\EventResult();
        $data = $event->getParameter('fields');

        // Нормализация SKU
        if (!empty($data['SKU'])) {
            $data['SKU'] = strtoupper(trim($data['SKU']));
            $result->modifyFields($data);
        }

        return $result;
    }

    /**
     * Действия перед обновлением
     */
    public static function onBeforeUpdate(Entity\Event $event)
    {
        $result = new Entity\EventResult();
        $data = $event->getParameter('fields');

        // Обновление времени изменения
        $data['UPDATED_AT'] = new DateTime();
        $result->modifyFields($data);

        return $result;
    }
}
```

### Миграции базы данных

**Зачем нужны миграции:**
- Версионность изменений БД
- Возможность откатить изменения
- Синхронизация структуры между окружениями

**Создание миграции (install/db/migrations/001_add_category_field.php):**

```php
<?php

namespace Acme\Warehouse\Migrations;

use Bitrix\Main\Application;

class Migration001AddCategoryField
{
    public function up()
    {
        $connection = Application::getConnection();

        $connection->query("
            ALTER TABLE `acme_warehouse_products`
            ADD COLUMN `CATEGORY` VARCHAR(100) DEFAULT NULL AFTER `SKU`
        ");
    }

    public function down()
    {
        $connection = Application::getConnection();

        $connection->query("
            ALTER TABLE `acme_warehouse_products`
            DROP COLUMN `CATEGORY`
        ");
    }
}
```

**Запуск миграций (lib/Migrator.php):**

```php
<?php

namespace Acme\Warehouse;

use Bitrix\Main\Config\Option;

class Migrator
{
    protected $moduleId = 'acme.warehouse';

    /**
     * Применить все миграции
     */
    public function migrate()
    {
        $currentVersion = $this->getCurrentVersion();
        $migrations = $this->getMigrations();

        foreach ($migrations as $version => $migration) {
            if ($version > $currentVersion) {
                $migration->up();
                $this->setCurrentVersion($version);
            }
        }
    }

    /**
     * Откатить последнюю миграцию
     */
    public function rollback()
    {
        $currentVersion = $this->getCurrentVersion();
        $migrations = $this->getMigrations();

        if (isset($migrations[$currentVersion])) {
            $migrations[$currentVersion]->down();
            $this->setCurrentVersion($currentVersion - 1);
        }
    }

    protected function getCurrentVersion(): int
    {
        return (int)Option::get($this->moduleId, 'migration_version', 0);
    }

    protected function setCurrentVersion(int $version)
    {
        Option::set($this->moduleId, 'migration_version', $version);
    }

    protected function getMigrations(): array
    {
        return [
            1 => new \Acme\Warehouse\Migrations\Migration001AddCategoryField(),
            // 2 => new Migration002AddIndexes(),
        ];
    }
}
```

### Языковые файлы (lang/ru/install/index.php)

```php
<?php

$MESS['ACME_WAREHOUSE_MODULE_NAME'] = 'Модуль складского учета';
$MESS['ACME_WAREHOUSE_MODULE_DESC'] = 'Управление товарами на складе';
$MESS['ACME_WAREHOUSE_REQUIREMENTS_ERROR'] = 'Требуется PHP 7.4+ и модуль Инфоблоков';
$MESS['ACME_WAREHOUSE_INSTALL_SUCCESS'] = 'Модуль успешно установлен';
$MESS['ACME_WAREHOUSE_UNINSTALL_SUCCESS'] = 'Модуль успешно удален';
```

### Service Locator (Локатор служб)

**Зачем нужен:**
Service Locator решает проблему множественных экземпляров сервисов. Когда вы создаёте объект через `new Service()` в разных местах кода, каждый раз создаётся новый экземпляр со своим кешем и состоянием. Service Locator обеспечивает получение **одного и того же экземпляра** во всех частях приложения.

**Когда использовать:**
- Сервис используется в нескольких местах
- Сервис содержит кеш или состояние
- Нужна централизованная конфигурация

**Три способа регистрации:**

| Способ | Где регистрируется | Когда доступен | Рекомендация |
|--------|-------------------|----------------|--------------|
| `.settings.php` главный | `/bitrix/.settings.php` | Сразу | ❌ Не рекомендуется (файл вне Git) |
| `.settings.php` модуля | `/local/modules/vendor.module/.settings.php` | После `Loader::includeModule()` | ✅ Рекомендуется для модулей |
| API в kernel.php | `/local/php_interface/kernel.php` | Сразу | ✅ Для глобальных сервисов |

**Пример регистрации в .settings.php модуля:**

```php
<?php
// /local/modules/acme.warehouse/.settings.php

return [
    'services' => [
        'value' => [
            // Простая регистрация по классу
            'acme.warehouse.product.service' => [
                'className' => \Acme\Warehouse\Service\ProductService::class,
            ],
            
            // С параметрами конструктора
            'acme.warehouse.api.client' => [
                'className' => \Acme\Warehouse\Integration\ApiClient::class,
                'constructorParams' => static fn() => [
                    \Bitrix\Main\Config\Option::get('acme.warehouse', 'api_url'),
                    \Bitrix\Main\Config\Option::get('acme.warehouse', 'api_key'),
                ],
            ],
            
            // Через фабрику
            'acme.warehouse.cache.manager' => [
                'constructor' => static function() {
                    return new \Acme\Warehouse\Cache\Manager(
                        new \Acme\Warehouse\Cache\LocalCacheImplementation()
                    );
                },
            ],
        ],
    ],
];
```

**Использование в коде:**

```php
<?php
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;

Loader::includeModule('acme.warehouse');

$locator = ServiceLocator::getInstance();

// Проверка наличия сервиса
if ($locator->has('acme.warehouse.product.service')) {
    // Получение сервиса (всегда один и тот же экземпляр)
    $service = $locator->get('acme.warehouse.product.service');
    $products = $service->getList();
}
```

**Правила именования сервисов:**
- Формат: `vendor.module.service.name`
- Только строчные буквы
- Части разделяются точкой
- Примеры: `acme.warehouse.product.service`, `mycompany.crm.lead.manager`

### Контроллеры в модулях и компонентах

Контроллеры используются для обработки AJAX-запросов. В Bitrix24 есть два способа создания контроллеров:

#### 1. Controllerable в компоненте

Интерфейс `Controllerable` позволяет добавить AJAX-действия непосредственно в компонент:

```php
<?php
namespace Acme\Components;

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Loader;
use Acme\Warehouse\Service\ProductService;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class WarehouseProductsComponent extends \CBitrixComponent implements Controllerable
{
    /**
     * Конфигурация AJAX-действий
     */
    public function configureActions(): array
    {
        return [
            'updateStock' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),  // Проверка авторизации
                    new ActionFilter\Csrf(),           // Защита от CSRF
                ],
            ],
            'getStock' => [
                'prefilters' => [],  // Публичное действие без фильтров
            ],
        ];
    }

    /**
     * AJAX-действие: обновление остатков
     */
    public function updateStockAction(int $id, int $quantity): array
    {
        // ⚠️ Важно: Authentication/Csrf не проверяют права на конкретную запись!
        // Добавляем проверку прав вручную
        if (!$this->canUpdateProduct($id)) {
            return [
                'status' => 'error',
                'message' => 'Нет прав на изменение товара',
            ];
        }

        Loader::includeModule('acme.warehouse');
        
        try {
            $service = new ProductService();
            $service->updateStock($id, $quantity);
            
            return [
                'status' => 'success',
                'data' => [
                    'id' => $id,
                    'quantity' => $quantity,
                ],
            ];
        } catch (\Exception $e) {
            // Не отдаём полный текст исключения клиенту
            \Bitrix\Main\Application::getInstance()
                ->getExceptionHandler()
                ->writeToLog($e);
            
            return [
                'status' => 'error',
                'message' => 'Ошибка обновления остатков',
            ];
        }
    }

    /**
     * Проверка прав на изменение товара
     */
    private function canUpdateProduct(int $id): bool
    {
        global $USER;
        
        // Пример проверки прав
        if ($USER->IsAdmin()) {
            return true;
        }
        
        // Дополнительная логика проверки прав
        // (по отделу, по владельцу записи и т.д.)
        
        return false;
    }

    public function executeComponent(): void
    {
        Loader::includeModule('acme.warehouse');
        
        $service = new ProductService();
        $this->arResult['PRODUCTS'] = $service->getList();
        
        $this->includeComponentTemplate();
    }
}
```

**Вызов из JavaScript:**

```javascript
BX.ajax.runComponentAction('acme:warehouse.products', 'updateStock', {
    mode: 'class',
    data: {
        id: 5,
        quantity: 100,
        sessid: BX.bitrix_sessid(),
    },
}).then(function(response) {
    if (response.data.status === 'success') {
        console.log('Остатки обновлены');
    }
});
```

#### 2. Контроллер в модуле

Отдельный класс контроллера для REST API модуля:

```php
<?php
namespace Acme\Warehouse\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Acme\Warehouse\Service\ProductService;

class ProductController extends Controller
{
    /**
     * Конфигурация действий
     */
    public function configureActions(): array
    {
        return [
            'delete' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                ],
            ],
            'list' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                ],
            ],
        ];
    }

    /**
     * Удаление товара
     */
    public function deleteAction(int $id): array
    {
        // ⚠️ Проверка прав на удаление конкретной записи
        if (!$this->canDeleteProduct($id)) {
            $this->addError(new \Bitrix\Main\Error(
                'Недостаточно прав для удаления',
                'ACCESS_DENIED'
            ));
            return [];
        }

        try {
            $service = new ProductService();
            $result = $service->delete($id);
            
            if ($result->isSuccess()) {
                return ['deleted' => true];
            }
            
            $this->addErrors($result->getErrors());
            return [];
            
        } catch (\Exception $e) {
            \Bitrix\Main\Application::getInstance()
                ->getExceptionHandler()
                ->writeToLog($e);
            
            $this->addError(new \Bitrix\Main\Error(
                'Ошибка при удалении',
                'DELETE_ERROR'
            ));
            return [];
        }
    }

    /**
     * Список товаров
     */
    public function listAction(array $filter = []): array
    {
        // Защита: allowlist для фильтров
        $allowedFilters = ['CATEGORY', 'NAME'];
        $safeFilter = array_intersect_key($filter, array_flip($allowedFilters));
        
        $service = new ProductService();
        $products = $service->getList([
            'filter' => $safeFilter,
            'limit' => 50,  // Ограничение для защиты от перегрузки
        ]);
        
        return ['items' => $products];
    }

    private function canDeleteProduct(int $id): bool
    {
        global $USER;
        return $USER->IsAdmin();
    }
}
```

**Регистрация контроллера в .settings.php модуля:**

```php
<?php
return [
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\Acme\\Warehouse\\Controller',
        ],
    ],
];
```

**Вызов из JavaScript:**

```javascript
BX.ajax.runAction('acme:warehouse.Product.delete', {
    data: {
        id: 5,
        sessid: BX.bitrix_sessid(),
    },
}).then(function(response) {
    console.log('Товар удалён');
});
```

**⚠️ Важные замечания по безопасности:**

1. **Authentication и Csrf — это НЕ проверка прав**: Эти фильтры проверяют только аутентификацию и токен, но не проверяют права пользователя на конкретную запись
2. **Всегда проверяйте права вручную**: Перед изменением/удалением проверьте ACL, владельца записи, принадлежность к отделу
3. **Не отдавайте полный текст исключений клиенту**: Логируйте на сервере, клиенту отдавайте нейтральное сообщение
4. **Используйте allowlist для входных данных**: Фильтры, сортировки, выбираемые поля — только из разрешённого списка
5. **Ограничивайте лимиты**: `PAGE_SIZE`, количество записей, размер данных

### Валидация данных с PHP-атрибутами

Начиная с версии D7, Bitrix поддерживает валидацию данных через PHP 8 атрибуты.

**Доступные атрибуты:**

| Атрибут | Назначение | Пример |
|---------|-----------|--------|
| `#[Email]` | Проверка email | `#[Email(strict: false)]` |
| `#[Phone]` | Проверка телефона | `#[Phone]` |
| `#[Length]` | Длина строки | `#[Length(min: 3, max: 100)]` |
| `#[Range]` | Диапазон числа | `#[Range(min: 1, max: 100)]` |
| `#[NotEmpty]` | Непустое значение | `#[NotEmpty]` |
| `#[PositiveNumber]` | Положительное число | `#[PositiveNumber]` |
| `#[RegExp]` | Регулярное выражение | `#[RegExp(pattern: '/^[A-Z]{2}\d{4}$/')]` |
| `#[Url]` | Проверка URL | `#[Url]` |
| `#[Json]` | Валидный JSON | `#[Json]` |

**DTO с валидацией:**

```php
<?php
namespace Acme\Warehouse\Dto;

use Bitrix\Main\Validation\Rule\AtLeastOnePropertyNotEmpty;
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\Phone;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\PositiveNumber;

#[AtLeastOnePropertyNotEmpty(['email', 'phone'])]
class CreateSupplierDto
{
    #[NotEmpty]
    #[Length(min: 2, max: 100)]
    private string $name;

    #[Email]
    private ?string $email;

    #[Phone]
    private ?string $phone;

    #[PositiveNumber]
    private ?int $rating;

    public function __construct(
        string $name,
        ?string $email = null,
        ?string $phone = null,
        ?int $rating = null
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->rating = $rating;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }
}
```

**Валидация в сервисе:**

```php
<?php
namespace Acme\Warehouse\Service;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Validation\ValidationService;
use Acme\Warehouse\Dto\CreateSupplierDto;

class SupplierService
{
    private ValidationService $validation;

    public function __construct()
    {
        $locator = ServiceLocator::getInstance();
        $this->validation = $locator->get('main.validation.service');
    }

    public function create(CreateSupplierDto $dto): \Bitrix\Main\Result
    {
        $result = new \Bitrix\Main\Result();

        // Валидация DTO
        $validationResult = $this->validation->validate($dto);
        
        if (!$validationResult->isSuccess()) {
            foreach ($validationResult->getErrors() as $error) {
                $result->addError($error);
            }
            return $result;
        }

        // Сохранение данных
        // ...

        return $result;
    }
}
```

**Валидация в параметрах контроллера (AutoWire):**

```php
<?php
namespace Acme\Warehouse\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Validation\Rule\PositiveNumber;

class SupplierController extends Controller
{
    /**
     * Параметр автоматически валидируется перед вызовом
     */
    public function getByIdAction(#[PositiveNumber] int $id): array
    {
        // Если валидация не прошла, метод не будет вызван
        // Bitrix автоматически вернёт ошибку клиенту
        
        $service = new \Acme\Warehouse\Service\SupplierService();
        $supplier = $service->getById($id);
        
        return ['data' => $supplier];
    }
}
```

### Файл настроек модуля (options.php)

```php
<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

$moduleId = 'acme.warehouse';

Loader::includeModule($moduleId);

$request = \Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();

// Сохранение настроек
if ($request->isPost() && $request['save']) {
    Option::set($moduleId, 'api_url', $request['api_url']);
    Option::set($moduleId, 'api_key', $request['api_key']);
    Option::set($moduleId, 'sync_enabled', $request['sync_enabled'] ? 'Y' : 'N');
}

// Текущие значения
$apiUrl = Option::get($moduleId, 'api_url', '');
$apiKey = Option::get($moduleId, 'api_key', '');
$syncEnabled = Option::get($moduleId, 'sync_enabled', 'N');

$aTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('ACME_WAREHOUSE_TAB_SETTINGS'),
        'TITLE' => Loc::getMessage('ACME_WAREHOUSE_TAB_SETTINGS_TITLE'),
    ],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialchars($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    
    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>
    
    <tr>
        <td width="40%">
            <label for="api_url"><?= Loc::getMessage('ACME_WAREHOUSE_API_URL') ?>:</label>
        </td>
        <td width="60%">
            <input type="text" 
                   id="api_url" 
                   name="api_url" 
                   value="<?= htmlspecialchars($apiUrl) ?>" 
                   size="50">
        </td>
    </tr>
    
    <tr>
        <td>
            <label for="api_key"><?= Loc::getMessage('ACME_WAREHOUSE_API_KEY') ?>:</label>
        </td>
        <td>
            <input type="text" 
                   id="api_key" 
                   name="api_key" 
                   value="<?= htmlspecialchars($apiKey) ?>" 
                   size="50">
        </td>
    </tr>
    
    <tr>
        <td>
            <label for="sync_enabled"><?= Loc::getMessage('ACME_WAREHOUSE_SYNC_ENABLED') ?>:</label>
        </td>
        <td>
            <input type="checkbox" 
                   id="sync_enabled" 
                   name="sync_enabled" 
                   value="Y" 
                   <?= $syncEnabled === 'Y' ? 'checked' : '' ?>>
        </td>
    </tr>
    
    <?php $tabControl->Buttons(); ?>
    
    <input type="submit" 
           name="save" 
           value="<?= Loc::getMessage('ACME_WAREHOUSE_SAVE') ?>" 
           class="adm-btn-save">
    
    <?php $tabControl->End(); ?>
</form>
```

## Частые ошибки

### 1. **Неправильное именование модуля**
```php
// ❌ Неправильно
$MODULE_ID = 'MyModule'; // заглавные буквы
$MODULE_ID = 'my_module'; // подчеркивание
$MODULE_ID = 'module'; // нет префикса

// ✅ Правильно
$MODULE_ID = 'acme.warehouse';
```

### 2. **Забыли зарегистрировать модуль**
```php
// ❌ Неправильно - создали таблицы, но не зарегистрировали
public function DoInstall()
{
    $this->InstallDB();
    // модуль не зарегистрирован!
}

// ✅ Правильно
public function DoInstall()
{
    ModuleManager::registerModule($this->MODULE_ID);
    $this->InstallDB();
}
```

### 3. **Не удалили настройки при деинсталляции**
```php
// ❌ Неправильно - настройки остались в БД
public function DoUninstall()
{
    $this->UnInstallDB();
    ModuleManager::unRegisterModule($this->MODULE_ID);
}

// ✅ Правильно
public function DoUninstall()
{
    $this->UnInstallDB();
    Option::delete($this->MODULE_ID); // удалили настройки
    ModuleManager::unRegisterModule($this->MODULE_ID);
}
```

### 4. **Не проверили зависимости**
```php
// ❌ Неправильно - устанавливаем без проверок
public function DoInstall()
{
    ModuleManager::registerModule($this->MODULE_ID);
}

// ✅ Правильно
public function DoInstall()
{
    if (!$this->checkRequirements()) {
        return false;
    }
    ModuleManager::registerModule($this->MODULE_ID);
}
```

### 5. **SQL инъекции в миграциях**
```php
// ❌ Неправильно
$categoryName = $_GET['category'];
$connection->query("ALTER TABLE products ADD COLUMN `{$categoryName}` VARCHAR(100)");

// ✅ Правильно - используйте подготовленные значения
$connection->query("ALTER TABLE products ADD COLUMN `CATEGORY` VARCHAR(100)");
```

## Вопросы для собеседования

### 1. Какая структура у модуля в Bitrix?

**Ответ:**
Модуль состоит из:
- `install/` — установщик, версия, компоненты, миграции
- `lib/` — классы модуля (D7 ORM, сервисы)
- `lang/` — языковые файлы
- `options.php` — страница настроек
- `include.php` — точка входа, регистрация автозагрузки

### 2. Какие методы обязательны в классе установки модуля?

**Ответ:**
```php
class acme_module extends CModule
{
    public function __construct() {} // инициализация
    public function DoInstall() {}   // установка
    public function DoUninstall() {} // удаление
}
```

Дополнительно используются:
- `InstallDB()` / `UnInstallDB()` — работа с БД
- `InstallFiles()` / `UnInstallFiles()` — копирование файлов
- `InstallEvents()` / `UnInstallEvents()` — регистрация событий
- `InstallAgents()` / `UnInstallAgents()` — установка агентов

### 3. Как правильно именовать модуль?

**Ответ:**
Формат: `vendor.modulename`
- Только латиница, строчные буквы
- Префикс компании + точка + название
- Примеры: `acme.warehouse`, `mycompany.crm`
- ❌ Нельзя: `bitrix.`, `MyModule`, `my_module`

### 4. Как создать таблицу при установке модуля?

**Ответ:**
```php
public function InstallDB()
{
    $connection = Application::getConnection();
    
    if (!$connection->isTableExists('my_table')) {
        $connection->query("
            CREATE TABLE `my_table` (
                `ID` INT AUTO_INCREMENT PRIMARY KEY,
                `NAME` VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
```

### 5. Как зарегистрировать обработчик события в модуле?

**Ответ:**
```php
public function InstallEvents()
{
    $eventManager = \Bitrix\Main\EventManager::getInstance();
    
    $eventManager->registerEventHandler(
        'iblock',                           // модуль
        'OnAfterIBlockElementAdd',          // событие
        $this->MODULE_ID,                   // модуль-обработчик
        '\Acme\Warehouse\EventHandlers',    // класс
        'onIBlockElementAdd'                // метод
    );
}
```

### 6. Что такое миграции и зачем они нужны?

**Ответ:**
Миграции — это версионные изменения структуры БД:
- Позволяют применять изменения постепенно
- Можно откатить изменения (rollback)
- Синхронизируют структуру между dev/stage/prod
- Хранят историю изменений

```php
class Migration001 {
    public function up() {
        // применить изменения
    }
    
    public function down() {
        // откатить изменения
    }
}
```

### 7. Как правильно удалить модуль?

**Ответ:**
```php
public function DoUninstall()
{
    // 1. Удалить агентов
    $this->UnInstallAgents();
    
    // 2. Удалить события
    $this->UnInstallEvents();
    
    // 3. Удалить файлы
    $this->UnInstallFiles();
    
    // 4. Удалить таблицы
    $this->UnInstallDB();
    
    // 5. Удалить настройки
    Option::delete($this->MODULE_ID);
    
    // 6. Снять регистрацию
    ModuleManager::unRegisterModule($this->MODULE_ID);
}
```

### 8. Как подключить модуль в коде?

**Ответ:**
```php
// Проверить установлен ли
if (!\Bitrix\Main\Loader::includeModule('acme.warehouse')) {
    throw new \Exception('Модуль acme.warehouse не установлен');
}

// Теперь доступны классы модуля
use Acme\Warehouse\Model\ProductTable;
$products = ProductTable::getList()->fetchAll();
```

### 9. Как создать ORM модель в модуле?

**Ответ:**
```php
namespace Acme\Warehouse\Model;

use Bitrix\Main\Entity;

class ProductTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'acme_warehouse_products';
    }
    
    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\StringField('NAME', [
                'required' => true,
            ]),
        ];
    }
}
```

### 10. Как добавить страницу настроек модуля?

**Ответ:**
Создать файл `options.php` в корне модуля:

```php
<?php
use Bitrix\Main\Config\Option;

$moduleId = 'acme.warehouse';

if ($request->isPost() && $request['save']) {
    Option::set($moduleId, 'api_key', $request['api_key']);
}

$apiKey = Option::get($moduleId, 'api_key', '');
?>

<form method="post">
    <input type="text" name="api_key" value="<?= htmlspecialchars($apiKey) ?>">
    <input type="submit" name="save" value="Сохранить">
</form>
```

### 11. Почему в Битрикс24 нельзя копировать стандартные компоненты в `/local/`?

**Ответ:**
В Битрикс24 (в отличие от БУС) **все клиенты на единой кодовой базе**. Когда вы копируете стандартный компонент в `/local/`, вы создаёте форк, который:
- Не получает обновления интерфейса от вендора
- Может сломаться при обновлении API ядра
- Увеличивает техдолг проекта
- Затрудняет миграцию на новые версии

**Правильный подход:** создавать свой компонент с нуля, который вызывает сервисы из модуля.

### 12. Что такое Service Locator и зачем он нужен?

**Ответ:**
Service Locator (PSR-11) — паттерн для централизованного управления сервисами.

**Проблема:**
```php
// Каждый раз создаётся новый экземпляр с новым кешем
$service1 = new ProductService();
$service2 = new ProductService(); // другой объект!
```

**Решение:**
```php
$locator = ServiceLocator::getInstance();
$service1 = $locator->get('acme.warehouse.product.service');
$service2 = $locator->get('acme.warehouse.product.service'); // тот же объект
```

**Преимущества:**
- Один экземпляр на всё приложение (singleton)
- Общий кеш между вызовами
- Централизованная конфигурация
- Упрощает замену реализации

### 13. В чём разница между Controllerable в компоненте и Controller в модуле?

**Ответ:**

| Аспект | Controllerable | Controller |
|--------|----------------|------------|
| Расположение | В классе компонента | В `/lib/Controller/` модуля |
| Интерфейс | `\Bitrix\Main\Engine\Contract\Controllerable` | Extends `\Bitrix\Main\Engine\Controller` |
| Вызов из JS | `BX.ajax.runComponentAction('vendor:component', 'action')` | `BX.ajax.runAction('vendor:module.Controller.action')` |
| Регистрация | Автоматически | Через `.settings.php` модуля |
| Использование | AJAX в рамках компонента | REST API модуля |

**Когда использовать:**
- **Controllerable** — AJAX-действия привязаны к конкретному компоненту
- **Controller** — общий API модуля, используемый из разных мест

### 14. Зачем нужны фильтры Authentication и Csrf, если они не проверяют права?

**Ответ:**
Фильтры `Authentication` и `Csrf` обеспечивают **базовую безопасность**, но это не проверка прав:

- `Authentication` — проверяет, что пользователь авторизован (не анонимный)
- `Csrf` — проверяет токен защиты от CSRF-атак

**Что они НЕ проверяют:**
- Имеет ли пользователь право на это действие
- Принадлежит ли запись пользователю
- Входит ли пользователь в нужный отдел
- Может ли пользователь видеть эту сущность

**Правильный подход:**
```php
public function deleteAction(int $id): array
{
    // Authentication/Csrf уже сработали
    
    // Но нужна дополнительная проверка прав
    if (!$this->canDeleteProduct($id)) {
        $this->addError(new \Bitrix\Main\Error('Нет прав'));
        return [];
    }
    
    // Теперь можно удалять
    $service->delete($id);
}
```

### 15. Как использовать валидацию с PHP-атрибутами?

**Ответ:**
С версии D7 Bitrix поддерживает валидацию через PHP 8 атрибуты:

```php
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\NotEmpty;

class CreateUserDto
{
    #[NotEmpty]
    #[Length(min: 2, max: 100)]
    private string $name;
    
    #[Email]
    private string $email;
}
```

**Валидация в сервисе:**
```php
$locator = ServiceLocator::getInstance();
$validation = $locator->get('main.validation.service');

$result = $validation->validate($dto);
if (!$result->isSuccess()) {
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage();
    }
}
```

**AutoWire в контроллере:**
```php
// Валидация происходит автоматически
public function createAction(#[PositiveNumber] int $userId): array
{
    // Если валидация не прошла, метод не будет вызван
}
```

### 16. Почему в Битрикс24 модули — основная методика доработок?

**Ответ:**
В Битрикс24 (в отличие от БУС) модули — **обязательный подход** по следующим причинам:

1. **Единая кодовая база** — все клиенты на одном коде, нельзя модифицировать ядро
2. **Обновления вендора** — `/bitrix/` перезаписывается при обновлениях
3. **Запрет на копирование** — нельзя копировать стандартные компоненты в `/local/`
4. **Изоляция логики** — модуль изолирует кастомную логику от стандартного кода
5. **Версионирование** — модуль можно версионировать через миграции
6. **Переиспользование** — модуль можно переносить между проектами

**Иерархия расширения:**
```
No-code/low-code → CSS/JS встройки → События → Агенты → 
Свои поля → Свои действия БП → Свои сущности → Свой модуль
```

### 17. Какие три способа регистрации Service Locator существуют и какой выбрать?

**Ответ:**

| Способ | Файл | Доступен | Рекомендация |
|--------|------|----------|--------------|
| Главный `.settings.php` | `/bitrix/.settings.php` | Сразу | ❌ Не рекомендуется (файл вне Git) |
| `.settings.php` модуля | `/local/modules/vendor.module/.settings.php` | После `Loader::includeModule()` | ✅ **Основной способ** |
| API в kernel.php | `/local/php_interface/kernel.php` | Сразу | ✅ Для глобальных сервисов |

**Выбор:**
- **Сервисы модуля** → `.settings.php` модуля
- **Глобальные сервисы** (используются до подключения модулей) → `kernel.php`
- **Никогда** не используйте `/bitrix/.settings.php` — он не версионируется

### 18. Как правильно работать с миграциями в Битрикс24?

**Ответ:**
В Bitrix24 нет встроенных миграций. Используется внешний инструмент **sprint.migration**.

**Зачем нужны миграции:**
- Версионирование изменений БД
- Возможность отката (rollback)
- Синхронизация структуры между dev/stage/prod
- История изменений

**Структура миграции:**
```php
class Migration001AddCategoryField
{
    public function up()
    {
        // Применить изменения
        $connection->query("ALTER TABLE ... ADD COLUMN ...");
    }
    
    public function down()
    {
        // Откатить изменения
        $connection->query("ALTER TABLE ... DROP COLUMN ...");
    }
}
```

**Важно:**
- Миграции применяются только на dev → prod переносе
- Никогда не редактируйте применённую миграцию
- Создавайте новую миграцию для исправлений

### 19. Как должен выглядеть компонент в архитектуре Битрикс24?

**Ответ:**
Компонент — **обёртка над сервисом модуля**, не содержит бизнес-логики.

**Правильный подход:**
```php
class ProductListComponent extends \CBitrixComponent
{
    public function executeComponent(): void
    {
        // Подключаем модуль
        Loader::includeModule('acme.warehouse');
        
        // Вызываем сервис (вся логика там)
        $service = new ProductService();
        $this->arResult['PRODUCTS'] = $service->getList([
            'limit' => $this->arParams['PAGE_SIZE'],
        ]);
        
        // Выводим шаблон
        $this->includeComponentTemplate();
    }
}
```

**Разделение ответственности:**
- **Модуль** (`/lib/Service/`) — бизнес-логика, работа с данными
- **Компонент** (`class.php`) — обёртка, подготовка данных для вывода
- **Шаблон** (`template.php`) — представление

## Практические задачи

### Задача 1: Модуль складского учета
Создать модуль для учета товаров на складе с ORM-моделью, миграциями и Service Locator.

### Задача 2: Модуль интеграции с внешним API
Создать модуль с настройками API, Service Locator для HTTP-клиента и контроллером с валидацией.

### Задача 3: Модуль аналитики CRM
Создать модуль с обработчиками событий CRM, контроллером с AJAX-действиями и административным интерфейсом.

**Решения задач находятся в `examples/module-14-1/`**
