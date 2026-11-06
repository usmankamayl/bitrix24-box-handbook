# Задача 1: Модуль складского учета

## Описание

Полноценный модуль `acme.warehouse` для учета товаров на складе с ORM-моделью, миграциями и административным интерфейсом.

## Функциональность

- ✅ Установка/удаление модуля через административную панель
- ✅ ORM-модель для товаров (ProductTable)
- ✅ Миграции базы данных (добавление/изменение полей)
- ✅ Страница настроек модуля
- ✅ Агент для синхронизации остатков
- ✅ Обработчик событий инфоблоков
- ✅ Валидация данных на уровне ORM

## Структура модуля

```
/local/modules/acme.warehouse/
├── install/
│   ├── index.php              # Класс установки
│   ├── version.php            # Версия модуля
│   └── db/
│       └── migrations/        # Миграции БД
│           └── 001_add_category_field.php
├── lib/
│   ├── Model/
│   │   └── ProductTable.php   # ORM модель
│   ├── Service/
│   │   └── ProductService.php # Бизнес-логика
│   ├── Agents/
│   │   └── SyncAgent.php      # Агент синхронизации
│   ├── EventHandlers.php      # Обработчики событий
│   └── Migrator.php           # Система миграций
├── lang/
│   └── ru/
│       ├── install/
│       │   └── index.php      # Переводы установщика
│       └── options.php        # Переводы настроек
├── options.php                # Страница настроек
└── include.php                # Точка входа
```

## Установка

### 1. Скопировать модуль

```bash
cp -r acme.warehouse /local/modules/
```

### 2. Установить через админку

Marketplace → Установленные решения → acme.warehouse → Установить

Или через код:

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\ModuleManager;

$moduleId = 'acme.warehouse';

if (!ModuleManager::isModuleInstalled($moduleId)) {
    $module = \Bitrix\Main\IO\Path::combine(
        $_SERVER['DOCUMENT_ROOT'],
        '/local/modules/' . $moduleId . '/install/index.php'
    );
    
    if (file_exists($module)) {
        include_once $module;
        
        $class = str_replace('.', '_', $moduleId);
        $moduleInstance = new $class();
        $moduleInstance->DoInstall();
        
        echo "Модуль {$moduleId} установлен";
    }
}
```

## Использование

### 1. Подключение модуля

```php
<?php
use Bitrix\Main\Loader;

if (!Loader::includeModule('acme.warehouse')) {
    throw new \Exception('Модуль acme.warehouse не установлен');
}
```

### 2. Работа с товарами через ORM

```php
<?php
use Acme\Warehouse\Model\ProductTable;

// Добавление товара
$result = ProductTable::add([
    'NAME' => 'Ноутбук ASUS',
    'SKU' => 'ASUS-001',
    'QUANTITY' => 10,
    'PRICE' => 45000.00,
    'CATEGORY' => 'Электроника',
]);

if ($result->isSuccess()) {
    $productId = $result->getId();
    echo "Товар добавлен с ID: {$productId}";
}

// Получение товара
$product = ProductTable::getById(1)->fetch();
print_r($product);

// Список товаров с фильтром
$products = ProductTable::getList([
    'filter' => [
        '>=QUANTITY' => 5,
        'CATEGORY' => 'Электроника',
    ],
    'order' => ['NAME' => 'ASC'],
    'limit' => 10,
])->fetchAll();

// Обновление товара
ProductTable::update(1, [
    'QUANTITY' => 8,
    'PRICE' => 47000.00,
]);

// Удаление товара
ProductTable::delete(1);
```

### 3. Использование сервиса

```php
<?php
use Acme\Warehouse\Service\ProductService;

$service = new ProductService();

// Получить товары с низким остатком
$lowStock = $service->getLowStockProducts(5);

// Получить товар по артикулу
$product = $service->getProductBySku('ASUS-001');

// Списать товар со склада
$service->writeOff('ASUS-001', 2);

// Оприходовать товар на склад
$service->receive('ASUS-001', 15);

// Проверить наличие на складе
$isAvailable = $service->checkAvailability('ASUS-001', 3);
```

### 4. Миграции

```php
<?php
use Acme\Warehouse\Migrator;

$migrator = new Migrator();

// Применить все новые миграции
$migrator->migrate();

// Откатить последнюю миграцию
$migrator->rollback();

// Узнать текущую версию миграций
$version = $migrator->getCurrentVersion();
```

## Настройки модуля

Настройки → Настройки продукта → Настройки модулей → Модуль складского учета

Доступные настройки:
- **URL внешнего API** - для синхронизации с 1C
- **API ключ** - ключ доступа к API
- **Синхронизация** - включить/выключить автоматическую синхронизацию
- **Минимальный остаток** - порог для уведомлений о низком остатке

## Агент синхронизации

Агент запускается автоматически каждые 24 часа:

```php
<?php
use Acme\Warehouse\Agents\SyncAgent;

// Запустить вручную
SyncAgent::run();
```

Логика агента:
1. Получает данные из внешнего API
2. Сравнивает с локальными остатками
3. Обновляет количество товаров
4. Отправляет уведомления об изменениях

## Обработчики событий

Модуль подписан на событие `OnAfterIBlockElementAdd`:

```php
<?php
// При добавлении товара в инфоблок
// автоматически создается запись на складе

$el = new CIBlockElement;
$productId = $el->Add([
    'IBLOCK_ID' => 10,
    'NAME' => 'Новый товар',
    'PROPERTY_VALUES' => [
        'SKU' => 'NEW-001',
    ],
]);

// Модуль автоматически создаст товар в своей таблице
```

## Примеры использования

### Пример 1: Импорт товаров из CSV

```php
<?php
use Acme\Warehouse\Service\ProductService;

$service = new ProductService();
$file = '/upload/products.csv';

if (($handle = fopen($file, 'r')) !== false) {
    // Пропускаем заголовок
    fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== false) {
        list($name, $sku, $quantity, $price, $category) = $row;
        
        ProductTable::add([
            'NAME' => $name,
            'SKU' => $sku,
            'QUANTITY' => (int)$quantity,
            'PRICE' => (float)$price,
            'CATEGORY' => $category,
        ]);
    }
    
    fclose($handle);
    echo "Импорт завершен";
}
```

### Пример 2: API для получения остатков

```php
<?php
// /local/api/warehouse/get-stock.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Acme\Warehouse\Model\ProductTable;

Loader::includeModule('acme.warehouse');

header('Content-Type: application/json');

$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['error' => 'SKU не указан']);
    exit;
}

$product = ProductTable::getList([
    'filter' => ['SKU' => $sku],
    'select' => ['NAME', 'SKU', 'QUANTITY', 'PRICE'],
])->fetch();

if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Товар не найден']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $product,
]);
```

### Пример 3: Компонент для отображения товаров

```php
<?php
// /local/components/acme/warehouse.list/component.php

use Bitrix\Main\Loader;
use Acme\Warehouse\Model\ProductTable;

if (!Loader::includeModule('acme.warehouse')) {
    ShowError('Модуль acme.warehouse не установлен');
    return;
}

$arResult['PRODUCTS'] = ProductTable::getList([
    'filter' => [
        '>QUANTITY' => 0,
    ],
    'order' => ['NAME' => 'ASC'],
])->fetchAll();

$this->includeComponentTemplate();
```

## Расширение модуля

### Добавление новой миграции

1. Создайте файл `install/db/migrations/002_add_supplier_field.php`:

```php
<?php
namespace Acme\Warehouse\Migrations;

use Bitrix\Main\Application;

class Migration002AddSupplierField
{
    public function up()
    {
        $connection = Application::getConnection();
        
        $connection->query("
            ALTER TABLE `acme_warehouse_products`
            ADD COLUMN `SUPPLIER` VARCHAR(255) DEFAULT NULL AFTER `CATEGORY`
        ");
    }
    
    public function down()
    {
        $connection = Application::getConnection();
        
        $connection->query("
            ALTER TABLE `acme_warehouse_products`
            DROP COLUMN `SUPPLIER`
        ");
    }
}
```

2. Зарегистрируйте миграцию в `lib/Migrator.php`:

```php
protected function getMigrations(): array
{
    return [
        1 => new \Acme\Warehouse\Migrations\Migration001AddCategoryField(),
        2 => new \Acme\Warehouse\Migrations\Migration002AddSupplierField(),
    ];
}
```

3. Примените миграцию:

```php
$migrator = new \Acme\Warehouse\Migrator();
$migrator->migrate();
```

## Тестирование

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Acme\Warehouse\Model\ProductTable;

Loader::includeModule('acme.warehouse');

// Тест 1: Добавление товара
$result = ProductTable::add([
    'NAME' => 'Тестовый товар',
    'SKU' => 'TEST-' . time(),
    'QUANTITY' => 100,
    'PRICE' => 1000.00,
]);

assert($result->isSuccess(), 'Ошибка добавления товара');
$testId = $result->getId();
echo "✓ Товар добавлен: ID = {$testId}\n";

// Тест 2: Получение товара
$product = ProductTable::getById($testId)->fetch();
assert(!empty($product), 'Товар не найден');
echo "✓ Товар получен: {$product['NAME']}\n";

// Тест 3: Обновление товара
$updateResult = ProductTable::update($testId, ['QUANTITY' => 50]);
assert($updateResult->isSuccess(), 'Ошибка обновления');
echo "✓ Товар обновлен\n";

// Тест 4: Удаление товара
$deleteResult = ProductTable::delete($testId);
assert($deleteResult->isSuccess(), 'Ошибка удаления');
echo "✓ Товар удален\n";

echo "\nВсе тесты пройдены успешно!\n";
```

## Удаление модуля

Marketplace → Установленные решения → acme.warehouse → Удалить

⚠️ **Внимание:** При удалении модуля будут удалены:
- Все таблицы БД
- Все настройки
- Агенты
- Обработчики событий

Данные товаров будут потеряны безвозвратно!

## Дополнительные возможности (на основе skill bitrix24-module3)

### Service Locator

Модуль демонстрирует регистрацию сервисов через Service Locator в `.settings.php`:

```php
<?php
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;

Loader::includeModule('acme.warehouse');

$locator = ServiceLocator::getInstance();

// Получение ProductService (один экземпляр на всё приложение)
if ($locator->has('acme.warehouse.product.service')) {
    $service = $locator->get('acme.warehouse.product.service');
    $products = $service->getList();
}
```

**Преимущества:**
- Один экземпляр сервиса во всём приложении (singleton)
- Общий кеш между вызовами
- Централизованная конфигурация

### REST API контроллер

Модуль включает REST API контроллер `ProductController` с фильтрами безопасности:

```javascript
// Удаление товара
BX.ajax.runAction('acme:warehouse.Product.delete', {
    data: {
        id: 5,
        sessid: BX.bitrix_sessid(),
    },
}).then(function(response) {
    console.log('Товар удалён');
});

// Обновление остатков
BX.ajax.runAction('acme:warehouse.Product.update', {
    data: {
        sku: 'ASUS-001',
        quantity: 10,
        sessid: BX.bitrix_sessid(),
    },
}).then(function(response) {
    console.log('Остатки обновлены', response.data);
});

// Получение списка с фильтром
BX.ajax.runAction('acme:warehouse.Product.list', {
    data: {
        filter: {
            CATEGORY: 'Электроника',
        },
    },
}).then(function(response) {
    console.log('Товары:', response.data.items);
});
```

**Важно:** Фильтры `Authentication` и `Csrf` проверяют только авторизацию, но не права на конкретную запись. В контроллере реализована дополнительная проверка прав.

### Валидация с PHP-атрибутами

Модуль демонстрирует валидацию через DTO с PHP 8 атрибутами:

```php
<?php
use Acme\Warehouse\Dto\CreateProductDto;
use Acme\Warehouse\Service\ValidationService;

// Создание DTO
$dto = new CreateProductDto(
    name: 'Ноутбук ASUS',
    sku: 'ASUS-001',
    quantity: 10,
    price: 45000.00,
    category: 'Электроника'
);

// Валидация и сохранение
$validationService = new ValidationService();
$result = $validationService->createProduct($dto);

if ($result->isSuccess()) {
    echo "Товар создан с ID: " . $result->getData()['id'];
} else {
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage() . "\n";
    }
}
```

### Массовый импорт с валидацией

```php
<?php
use Acme\Warehouse\Service\ValidationService;

$items = [
    ['name' => 'Товар 1', 'sku' => 'SKU-001', 'quantity' => 10, 'price' => 1000],
    ['name' => 'Товар 2', 'sku' => 'SKU-002', 'quantity' => 5, 'price' => 2000],
    ['name' => '', 'sku' => 'SKU-003', 'quantity' => -5, 'price' => 500], // Невалидный
];

$validationService = new ValidationService();
$result = $validationService->validateImport($items);

if ($result->isSuccess()) {
    $data = $result->getData();
    echo "Валидных: {$data['valid']}, Невалидных: {$data['invalid']}\n";
    
    // Сохранение валидных товаров
    foreach ($data['valid_items'] as $dto) {
        ProductTable::add($dto->toArray());
    }
}
```

## Архитектурные принципы Битрикс24

Модуль следует правилам разработки для Битрикс24:

1. **Не изменяем ядро** — вся логика в `/local/modules/`
2. **Компонент — обёртка** — бизнес-логика в сервисах модуля
3. **Service Locator** — для централизованного управления сервисами
4. **Контроллеры** — для REST API с фильтрами безопасности
5. **Валидация** — через PHP-атрибуты и DTO
6. **Проверка прав** — явная проверка ACL, не полагаемся только на Authentication/Csrf

## Заключение

Этот модуль демонстрирует:
- ✅ Правильную структуру модуля Bitrix
- ✅ Работу с ORM D7
- ✅ Систему миграций
- ✅ Установку/удаление модуля
- ✅ Страницу настроек
- ✅ Агенты и обработчики событий
- ✅ Валидацию и бизнес-логику
- ✅ Service Locator (PSR-11)
- ✅ REST API контроллеры с безопасностью
- ✅ Валидацию через PHP-атрибуты
- ✅ DTO для структурированных данных

Используйте его как шаблон для своих модулей!
