# История изменений заказов (Order History)

## Описание

Компонент для логирования и отображения истории изменений заказов с использованием Highload-блока. Демонстрирует аудит изменений, сериализацию сложных данных и временную шкалу событий.

## Что демонстрирует компонент

1. **Статический метод логирования** для вызова из обработчиков событий
2. **Сериализация/десериализация** сложных данных (массивы, объекты)
3. **Временная шкала** с визуализацией изменений
4. **Типизация изменений** с разной стилизацией
5. **Статистика по типам** изменений
6. **Форматирование значений** для читаемого отображения

## Структура Highload-блока

### Создание HL-блока

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$result = HighloadBlockTable::add([
    'NAME' => 'OrderHistory',
    'TABLE_NAME' => 'b_hlbd_order_history',
]);

$hlblockId = $result->getId();
```

### Поля HL-блока

```php
use Bitrix\Main\UserFieldTable;

$fields = [
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_ORDER_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID заказа'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_CHANGE_TYPE',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Тип изменения'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_FIELD_NAME',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Название поля'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_OLD_VALUE',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'Старое значение'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_NEW_VALUE',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'Новое значение'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_USER_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID пользователя'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_CHANGE_DATE',
        'USER_TYPE_ID' => 'datetime',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Дата изменения'],
    ],
    [
        'ENTITY_ID' => 'HLBLOCK_' . $hlblockId,
        'FIELD_NAME' => 'UF_COMMENT',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'N',
        'EDIT_FORM_LABEL' => ['ru' => 'Комментарий'],
    ],
];

foreach ($fields as $field) {
    UserFieldTable::add($field);
}
```

### Индексы

```php
$connection = \Bitrix\Main\Application::getConnection();

// Индекс на заказ для быстрого поиска истории
$connection->query("
    CREATE INDEX ix_order_id 
    ON b_hlbd_order_history(UF_ORDER_ID)
");

// Составной индекс для фильтрации по типу
$connection->query("
    CREATE INDEX ix_order_type 
    ON b_hlbd_order_history(UF_ORDER_ID, UF_CHANGE_TYPE)
");

// Индекс на дату
$connection->query("
    CREATE INDEX ix_change_date 
    ON b_hlbd_order_history(UF_CHANGE_DATE)
");
```

## Установка и использование

### 1. Создайте Highload-блок

Создайте через админку или программно согласно структуре выше.

### 2. Скопируйте компонент

```bash
cp -r order-history /local/components/custom/order.history
```

### 3. Интеграция с обработчиками событий

**Логирование изменения статуса заказа:**

```php
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandler(
    'sale',
    'OnSaleStatusOrderChange',
    'onOrderStatusChange'
);

function onOrderStatusChange($orderId, $statusId, $oldStatusId)
{
    \OrderHistoryComponent::logChange(
        $orderId,
        'status',
        'STATUS_ID',
        $oldStatusId,
        $statusId,
        'Изменение статуса заказа'
    );
}
```

**Логирование оплаты заказа:**

```php
$eventManager->addEventHandler(
    'sale',
    'OnSalePaymentEntitySaved',
    'onPaymentSaved'
);

function onPaymentSaved(\Bitrix\Main\Event $event)
{
    $payment = $event->getParameter('ENTITY');
    $orderId = $payment->getOrderId();
    
    if ($payment->isPaid()) {
        \OrderHistoryComponent::logChange(
            $orderId,
            'payment',
            'PAID',
            'N',
            'Y',
            'Оплата заказа на сумму ' . $payment->getSum()
        );
    }
}
```

**Логирование изменения цены:**

```php
$eventManager->addEventHandler(
    'sale',
    'OnSaleOrderSaved',
    'onOrderSaved'
);

function onOrderSaved(\Bitrix\Main\Event $event)
{
    $order = $event->getParameter('ENTITY');
    $isNew = $event->getParameter('IS_NEW');
    
    if (!$isNew && $order->isFieldChanged('PRICE')) {
        $oldValues = $order->getOriginalValues();
        
        \OrderHistoryComponent::logChange(
            $order->getId(),
            'price',
            'PRICE',
            $oldValues['PRICE'],
            $order->getPrice(),
            'Изменение суммы заказа'
        );
    }
}
```

### 4. Отображение истории

```php
<?php
// На странице детального просмотра заказа
$APPLICATION->IncludeComponent(
    "custom:order.history",
    ".default",
    [
        "HLBLOCK_ID" => 3,
        "ORDER_ID" => $orderId,
        "PAGE_SIZE" => 50,
        "FILTER_CHANGE_TYPE" => "",
        "FILTER_DAYS" => 0,
        "CACHE_TIME" => 600,
    ]
);
?>
```

## Ключевые моменты кода

### Статический метод для логирования

```php
public static function logChange($orderId, $changeType, $fieldName, $oldValue, $newValue, $comment = '')
{
    global $USER;

    if (!Loader::includeModule('highloadblock')) {
        return false;
    }

    // Получаем DataClass
    $hlblockId = 3;
    $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();
    $entity = HighloadBlockTable::compileEntity($hlblock);
    $dataClass = $entity->getDataClass();

    // Добавляем запись
    $result = $dataClass::add([
        'UF_ORDER_ID' => (int)$orderId,
        'UF_CHANGE_TYPE' => $changeType,
        'UF_FIELD_NAME' => $fieldName,
        'UF_OLD_VALUE' => self::serializeValue($oldValue),
        'UF_NEW_VALUE' => self::serializeValue($newValue),
        'UF_USER_ID' => $USER->GetID() ?: 0,
        'UF_CHANGE_DATE' => new DateTime(),
        'UF_COMMENT' => $comment,
    ]);

    return $result->isSuccess();
}
```

### Сериализация сложных значений

```php
protected static function serializeValue($value)
{
    if (is_array($value) || is_object($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    return (string)$value;
}

protected function unserializeValue($value)
{
    if (empty($value)) {
        return '';
    }

    // Пытаемся декодировать JSON
    $decoded = json_decode($value, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $value;
}
```

### Статистика по типам изменений

```php
protected function getStatistics()
{
    $dataClass = $this->getDataClass();

    // Группировка по типам
    $changesByType = $dataClass::getList([
        'select' => [
            'UF_CHANGE_TYPE',
            'CHANGES_COUNT'
        ],
        'filter' => ['=UF_ORDER_ID' => $orderId],
        'runtime' => [
            new \Bitrix\Main\Entity\ExpressionField(
                'CHANGES_COUNT',
                'COUNT(*)'
            ),
        ],
        'group' => ['UF_CHANGE_TYPE'],
        'order' => ['CHANGES_COUNT' => 'DESC'],
    ])->fetchAll();

    return $changesByType;
}
```

## Примеры использования

### Логирование изменения свойств заказа

```php
$order = \Bitrix\Sale\Order::load($orderId);
$propertyCollection = $order->getPropertyCollection();

foreach ($propertyCollection as $property) {
    if ($property->isModified()) {
        \OrderHistoryComponent::logChange(
            $orderId,
            'properties',
            $property->getField('NAME'),
            $property->getOldValue(),
            $property->getValue(),
            'Изменение свойства заказа'
        );
    }
}
```

### Логирование изменения состава заказа

```php
$basket = $order->getBasket();

if ($basket->isModified()) {
    $changes = [];
    
    foreach ($basket->getBasketItems() as $item) {
        if ($item->isModified()) {
            $changes[] = [
                'product_id' => $item->getProductId(),
                'name' => $item->getField('NAME'),
                'quantity_old' => $item->getQuantity() - $item->getField('QUANTITY'),
                'quantity_new' => $item->getQuantity(),
            ];
        }
    }
    
    \OrderHistoryComponent::logChange(
        $orderId,
        'items',
        'BASKET',
        null,
        $changes,
        'Изменение состава заказа'
    );
}
```

### Логирование отгрузки

```php
$eventManager->addEventHandler(
    'sale',
    'OnShipmentAllowDelivery',
    'onShipmentDelivery'
);

function onShipmentDelivery($shipmentId, $value)
{
    $shipment = \Bitrix\Sale\Shipment::load($shipmentId);
    $orderId = $shipment->getOrder()->getId();
    
    \OrderHistoryComponent::logChange(
        $orderId,
        'shipment',
        'ALLOW_DELIVERY',
        'N',
        $value,
        'Разрешена отгрузка заказа'
    );
}
```

## Что можно улучшить

1. **Откат изменений** — возможность вернуть предыдущее значение
2. **Сравнение версий** — diff между двумя состояниями
3. **Уведомления** — отправка писем при критичных изменениях
4. **Экспорт истории** — выгрузка в PDF/Excel для аудита
5. **Фильтры по пользователю** — кто внес изменения
6. **Комментарии к изменениям** — расширенное описание

## Интеграция с CRM

```php
// Синхронизация истории с Bitrix24 CRM
function syncOrderHistoryToCRM($orderId)
{
    $dataClass = /* получаем DataClass */;
    
    $history = $dataClass::getList([
        'filter' => ['=UF_ORDER_ID' => $orderId],
        'order' => ['UF_CHANGE_DATE' => 'ASC'],
    ])->fetchAll();
    
    foreach ($history as $item) {
        // Добавляем timeline-запись в CRM
        \CCrmActivity::Add([
            'OWNER_TYPE_ID' => \CCrmOwnerType::Order,
            'OWNER_ID' => $orderId,
            'SUBJECT' => 'Изменение: ' . $item['UF_FIELD_NAME'],
            'DESCRIPTION' => sprintf(
                'Было: %s, Стало: %s',
                $item['UF_OLD_VALUE'],
                $item['UF_NEW_VALUE']
            ),
            'CREATED' => $item['UF_CHANGE_DATE'],
        ]);
    }
}
```

## Производительность

### Пакетная запись изменений

```php
public static function logBatch($changes)
{
    if (!Loader::includeModule('highloadblock')) {
        return false;
    }

    $connection = \Bitrix\Main\Application::getConnection();
    $connection->startTransaction();

    try {
        foreach ($changes as $change) {
            self::logChange(
                $change['order_id'],
                $change['type'],
                $change['field'],
                $change['old_value'],
                $change['new_value'],
                $change['comment'] ?? ''
            );
        }
        
        $connection->commitTransaction();
        return true;
    } catch (\Exception $e) {
        $connection->rollbackTransaction();
        return false;
    }
}
```

## Полезные ссылки

- [События модуля Sale](https://dev.1c-bitrix.ru/api_d7/bitrix/sale/index.php)
- [Работа с заказами в D7](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=42&LESSON_ID=7806)
- [Теория модуля 2.2](../../../topics/module-2-2.md)

## Лицензия

Пример создан в учебных целях для курса подготовки к собеседованию Bitrix-разработчика.
