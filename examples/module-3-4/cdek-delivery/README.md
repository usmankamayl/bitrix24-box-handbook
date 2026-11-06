# Служба доставки СДЭК с расчетом через API

## Описание

Полноценная интеграция со службой доставки СДЭК для Bitrix с поддержкой:
- Автоматического расчета стоимости и сроков доставки через API v2
- Двух профилей доставки (курьер и самовывоз)
- Кеширования результатов расчета
- Тестового и боевого режимов
- Наценки на стоимость доставки
- OAuth 2.0 авторизации

## Структура

```
cdek-delivery/
├── .description.php           # Описание и параметры службы доставки
├── handler.php                # Основной обработчик
├── lang/ru/.description.php   # Языковые константы
└── README.md                  # Эта инструкция
```

## Установка

### 1. Копирование файлов

Скопируйте папку `cdek-delivery` в директорию обработчиков служб доставки:

```
/bitrix/php_interface/include/sale_delivery/cdek/
```

### 2. Регистрация обработчика

Создайте файл регистрации (если требуется для вашей версии Bitrix):

```php
<?php
// /bitrix/php_interface/include/sale_delivery/cdek/init.php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

\Bitrix\Main\Loader::registerAutoLoadClasses(
    null,
    [
        '\\Sale\\Handlers\\Delivery\\CdekHandler' => 
            __DIR__ . '/handler.php'
    ]
);
```

### 3. Добавление в список служб доставки

В админке Bitrix:
1. Перейдите в **Магазин → Настройки → Службы доставки**
2. Нажмите **Добавить службу доставки**
3. Выберите **Доставка СДЭК**
4. Заполните параметры

## Настройка

### Получение учетных данных СДЭК

1. Зарегистрируйтесь в [личном кабинете СДЭК](https://www.cdek.ru/)
2. Перейдите в раздел **Настройки → Интеграция**
3. Получите:
   - **Account (Client ID)** - идентификатор клиента
   - **Secure password (Client Secret)** - секретный ключ

### Параметры службы доставки

| Параметр | Описание | Пример |
|----------|----------|--------|
| Account | Client ID из ЛК СДЭК | `z9GRRu7FxmO53CQ9cFfI6qiy32wpfTkd` |
| Secure password | Client Secret из ЛК СДЭК | `w24JTCy4MnAcuRTx0oHjHLDtyt3mwOPx` |
| Режим работы | Тест/Боевой | `LIVE` |
| Город отправки | Название города | `Москва` |
| Код города | Код СДЭК | `44` (Москва) |
| Время кеширования | Минуты | `60` |
| Наценка | Процент | `10` |

### Коды городов СДЭК

Основные города:
- **Москва**: 44
- **Санкт-Петербург**: 137
- **Новосибирск**: 270
- **Екатеринбург**: 250

[Полный справочник городов СДЭК](https://api-docs.cdek.ru/29923849.html)

## Использование

### Создание заказа с доставкой СДЭК

```php
use Bitrix\Sale\Order;
use Bitrix\Sale\Delivery\Services\Manager;

// Создаем заказ
$order = Order::create(SITE_ID, $userId);
$order->setPersonTypeId(1);
$order->setField('CURRENCY', 'RUB');

// Добавляем товары
$basket = $order->getBasket();
// ... добавление товаров

// Создаем отгрузку с доставкой СДЭК
$shipmentCollection = $order->getShipmentCollection();
$shipment = $shipmentCollection->createItem(
    Manager::getObjectById($cdekDeliveryId) // ID службы доставки СДЭК
);

// Добавляем товары в отгрузку
$shipmentItemCollection = $shipment->getShipmentItemCollection();
foreach ($basket as $basketItem) {
    $shipmentItem = $shipmentItemCollection->createItem($basketItem);
    $shipmentItem->setQuantity($basketItem->getQuantity());
}

// Рассчитываем стоимость доставки
$delivery = Manager::getObjectById($cdekDeliveryId);
$calcResult = $delivery->calculate($shipment);

if ($calcResult->isSuccess()) {
    $price = $calcResult->getPrice();
    $period = $calcResult->getPeriodDescription();
    
    $shipment->setField('BASE_PRICE_DELIVERY', $price);
    
    echo "Стоимость доставки: {$price} руб.";
    echo "Срок доставки: {$period}";
}

// Сохраняем заказ
$order->save();
```

### Расчет стоимости доставки без создания заказа

```php
use Bitrix\Sale\Delivery\Services\Manager;
use Bitrix\Sale\Shipment;

// Получаем службу доставки
$delivery = Manager::getObjectById($cdekDeliveryId);

// Создаем временную отгрузку для расчета
$shipment = Shipment::create($shipmentCollection, $delivery);

// Устанавливаем параметры
// ... добавление товаров

// Рассчитываем
$result = $delivery->calculate($shipment);

if ($result->isSuccess()) {
    echo "Цена: " . $result->getPrice() . " руб.";
    echo "Срок: " . $result->getPeriodDescription();
} else {
    foreach ($result->getErrors() as $error) {
        echo $error->getMessage();
    }
}
```

### Выбор профиля доставки

При создании службы доставки можно выбрать один из профилей:

**Курьером до двери (tariff_code: 136):**
```php
// При настройке службы выбрать профиль "Курьером до двери"
```

**Самовывоз из пункта выдачи (tariff_code: 138):**
```php
// При настройке службы выбрать профиль "Самовывоз из пункта выдачи"
```

## Кеширование

Результаты расчета кешируются для оптимизации производительности.

### Ключ кеша формируется из:
- ID службы доставки
- Профиль доставки
- Индекс получателя
- Город получателя
- Вес отгрузки

### Управление кешем

```php
// Очистка кеша СДЭК
\Bitrix\Main\Data\Cache::clearCache(true, '/sale/delivery/cdek/');

// Или через BX.clearCache (админка)
// Настройки → Производительность → Управление кешем
// Удалить путь: /sale/delivery/cdek/
```

### Отключение кеша

В настройках службы доставки установите **Время кеширования = 0**.

## Логирование

Все операции логируются в файл `/upload/logs/cdek_delivery.log`:

```php
// Посмотреть логи
tail -f /path/to/bitrix/upload/logs/cdek_delivery.log
```

### Структура лога

```
[2024-10-08 12:34:56] API Request
Array (
    [url] => https://api.cdek.ru/v2/calculator/tariff
    [data] => Array (
        [tariff_code] => 136
        [from_location] => Array ([code] => 44)
        [to_location] => Array ([postal_code] => 101000)
        [packages] => Array (...)
    )
)

[2024-10-08 12:34:57] API Response
Array (
    [response] => {"total_sum":450.00,"period_min":2,"period_max":3}
    [status] => 200
)

[2024-10-08 12:34:57] Using cached data
Array (
    [cache_key] => abc123...
)
```

## Дополнительный функционал

### Получение списка пунктов выдачи

```php
use Sale\Handlers\Delivery\CdekHandler;

$delivery = \Bitrix\Sale\Delivery\Services\Manager::getObjectById($cdekDeliveryId);
$handler = $delivery->getHandlerObject();

// Получаем пункты выдачи в Москве (код 44)
$pickupPoints = $handler->getPickupPoints(44);

foreach ($pickupPoints as $point) {
    echo $point['name']; // Название ПВЗ
    echo $point['location']['address']; // Адрес
    echo $point['work_time']; // Время работы
}
```

### Трекинг отправления

```php
$trackingNumber = '1234567890';
$trackingData = $handler->trackShipment($trackingNumber);

if ($trackingData) {
    foreach ($trackingData['statuses'] as $status) {
        echo $status['date_time']; // Дата и время
        echo $status['name']; // Статус
        echo $status['city']; // Город
    }
}
```

## Тестирование

### Тестовый режим

В настройках службы доставки установите **Режим работы = Тестовый**.

Тестовый API использует endpoint: `https://api.edu.cdek.ru/v2`

### Тестовые данные

Для тестирования используйте:
- **Account**: `EMscd6r9JnFiQ3bLoyjJY6eM78JrJceI`
- **Secure password**: `PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG`
- **Код города отправки**: `44` (Москва)

### Пример теста

```php
// Создаем тестовую отгрузку
$shipment = Shipment::create($shipmentCollection, $cdekDelivery);

// Устанавливаем тестовые данные
$order = $shipment->getOrder();
$propertyCollection = $order->getPropertyCollection();

// Устанавливаем адрес доставки (Москва, индекс 101000)
$locationProperty = $propertyCollection->getDeliveryLocation();
// ... установка локации

// Добавляем тестовый товар весом 1000г
// ... добавление товара

// Рассчитываем стоимость
$result = $cdekDelivery->calculate($shipment);

if ($result->isSuccess()) {
    echo "Тест пройден!";
    echo "Цена: " . $result->getPrice();
    echo "Срок: " . $result->getPeriodDescription();
} else {
    echo "Ошибка: " . implode(', ', $result->getErrorMessages());
}
```

## Частые ошибки

### 1. "Не удалось получить токен доступа"

**Причины:**
- Неверные Account или Secure password
- API СДЭК недоступен
- Неправильно выбран режим (тест/боевой)

**Решение:**
- Проверьте учетные данные в ЛК СДЭК
- Убедитесь, что используете правильный режим
- Проверьте доступность API: `curl https://api.cdek.ru/v2/oauth/token`

### 2. "В ответе API отсутствует стоимость доставки"

**Причины:**
- Не указан или неправильный индекс получателя
- Некорректный вес товаров (вес = 0)
- Тариф недоступен для данного направления

**Решение:**
- Проверьте, что в заказе указан корректный индекс
- Убедитесь, что у товаров задан вес
- Проверьте логи для детальной информации об ошибке

### 3. Кеш не очищается

**Причина:** Указан неправильный путь кеша

**Решение:**
```php
// Очистка через код
\Bitrix\Main\Data\Cache::clearCache(true, '/sale/delivery/cdek/');

// Или отключите кеш: установите время кеширования = 0
```

### 4. Неправильная сумма доставки

**Причина:** Не учтена наценка или неправильный вес

**Решение:**
- Проверьте параметр "Наценка" в настройках
- Убедитесь, что вес товаров указан в граммах
- Проверьте логи для просмотра запроса к API

## Доработка

### Добавление новых тарифов

```php
// В .description.php добавьте новый профиль
'PROFILES' => [
    'express' => [
        'NAME' => 'Экспресс доставка',
        'DESCRIPTION' => 'Срочная доставка за 1 день',
        'TARIFF_CODE' => 62, // Код тарифа СДЭК
    ],
]
```

### Расчет габаритов посылки

```php
// В handler.php, метод buildCalculationData()
// Вместо фиксированных габаритов:

$dimensions = $this->calculateDimensions($shipment);

$data['packages'] = [
    [
        'weight' => $weight,
        'length' => $dimensions['length'],
        'width' => $dimensions['width'],
        'height' => $dimensions['height']
    ]
];
```

### Ограничение по весу

```php
// Добавить ограничение в настройках службы доставки
// Магазин → Службы доставки → СДЭК → Ограничения
// Выбрать "По весу" и задать диапазон (например, 0-30000 грамм)
```

### Выбор пункта выдачи на странице оформления заказа

```php
// В шаблоне оформления заказа
$handler = $delivery->getHandlerObject();
$pickupPoints = $handler->getPickupPoints($cityCode);

// Отобразить карту с пунктами выдачи
// Сохранить выбранный ПВЗ в свойство заказа
```

## Дополнительные ресурсы

- [Документация API СДЭК v2](https://api-docs.cdek.ru/)
- [Личный кабинет СДЭК](https://www.cdek.ru/)
- [Тарифы СДЭК](https://www.cdek.ru/ru/calculate)
- [Справочник городов](https://api-docs.cdek.ru/29923849.html)

## Поддержка

При возникновении проблем:
1. Проверьте логи в `/upload/logs/cdek_delivery.log`
2. Убедитесь, что учетные данные верны
3. Проверьте, что модуль `sale` подключен
4. Проверьте доступность API СДЭК
5. Очистите кеш доставки
