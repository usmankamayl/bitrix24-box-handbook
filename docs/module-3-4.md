# Модуль 3.4: Платежные системы и доставка

## Теория

### Платежные системы в Bitrix

Bitrix предоставляет унифицированный API для работы с различными платежными системами через модуль **sale**.

#### Основные классы для работы с платежными системами

**1. `\Bitrix\Sale\PaySystem\Manager`** - управление платежными системами
```php
use Bitrix\Sale\PaySystem\Manager;

// Получить список всех платежных систем
$paySystems = Manager::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['ID', 'NAME', 'ACTION_FILE']
]);

// Получить обработчик платежной системы
$service = Manager::getObjectById($paySystemId);
```

**2. `\Bitrix\Sale\Payment`** - работа с платежами
```php
use Bitrix\Sale\Payment;

// Создание платежа
$payment = Payment::create($paymentCollection, $paySystemId);
$payment->setField('SUM', 1000);
$payment->setField('CURRENCY', 'RUB');
```

**3. `\Bitrix\Sale\PaySystem\Service`** - обработчик платежной системы
```php
// Инициация оплаты
$service = Manager::getObjectById($paySystemId);
$initResult = $service->initiatePay($payment, null, \Bitrix\Sale\PaySystem\BaseServiceHandler::STRING);
```

#### Структура обработчика платежной системы

Обработчик должен наследоваться от `\Bitrix\Sale\PaySystem\ServiceHandler`:

```php
namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Request;
use Bitrix\Sale\PaySystem;

class YourPaySystemHandler extends PaySystem\ServiceHandler
{
    // Показ формы оплаты
    public function initiatePay(Payment $payment, Request $request = null)
    {
        // Генерация формы оплаты или редирект
    }
    
    // Обработка уведомления от платежной системы
    public function processRequest(Payment $payment, Request $request)
    {
        // Проверка подписи и обновление статуса платежа
    }
    
    // Получение URL для уведомлений
    public function getPaymentIdFromRequest(Request $request)
    {
        return $request->get('order_id');
    }
}
```

#### Типы платежных систем

1. **Внешние** - перенаправление на сайт платежной системы (ЮKassa, PayPal, Сбербанк)
2. **Внутренние** - оплата через внутренний счет пользователя
3. **Наложенный платеж** - оплата при получении

#### Обработка callback от платежной системы

```php
// В обработчике запроса от платежной системы
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

// Получаем ID платежа
$paymentId = $request->get('order_id');

// Загружаем платеж
$payment = \Bitrix\Sale\Payment::getByPrimary($paymentId)->fetchObject();

// Проверяем подпись
if ($this->checkSignature($request)) {
    // Устанавливаем статус "Оплачен"
    $payment->setPaid('Y');
    $payment->save();
}
```

### Службы доставки в Bitrix

#### Основные классы для работы с доставкой

**1. `\Bitrix\Sale\Delivery\Services\Manager`** - управление службами доставки
```php
use Bitrix\Sale\Delivery\Services\Manager;

// Получить список служб доставки
$deliveryList = Manager::getRestrictedList([
    'filter' => ['ACTIVE' => 'Y']
]);

// Получить службу по ID
$service = Manager::getById($deliveryId);
```

**2. `\Bitrix\Sale\Shipment`** - отгрузка товаров
```php
use Bitrix\Sale\Shipment;

// Создание отгрузки
$shipment = $shipmentCollection->createItem($delivery);
$shipment->setField('DELIVERY_ID', $deliveryId);
$shipment->setField('BASE_PRICE_DELIVERY', $deliveryPrice);
```

**3. Расчет стоимости доставки**
```php
$result = $delivery->calculate($shipment);
if ($result->isSuccess()) {
    $price = $result->getPrice();
    $period = $result->getPeriodDescription();
}
```

#### Создание собственной службы доставки

Обработчик должен наследоваться от `\Bitrix\Sale\Delivery\CalculationResult`:

```php
namespace Sale\Handlers\Delivery;

use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;

class YourDeliveryHandler extends Base
{
    // Расчет стоимости доставки
    protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
    {
        $result = new CalculationResult();
        
        // Логика расчета стоимости
        $price = $this->calculatePrice($shipment);
        $result->setDeliveryPrice($price);
        
        // Срок доставки
        $result->setPeriodDescription('2-3 дня');
        
        return $result;
    }
    
    // Проверка доступности доставки
    public static function isProfile()
    {
        return false; // true для службы с профилями
    }
}
```

#### Интеграция с внешними API доставки

Пример интеграции с СДЭК:

```php
use Bitrix\Main\Web\HttpClient;

class CdekDeliveryHandler extends Base
{
    protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
    {
        $httpClient = new HttpClient();
        
        // Формируем запрос к API СДЭК
        $requestData = [
            'from_location' => [
                'city' => 'Москва'
            ],
            'to_location' => [
                'postal_code' => $shipment->getOrder()->getPropertyCollection()
                    ->getDeliveryLocation()
                    ->getPostalCode()
            ],
            'packages' => [
                [
                    'weight' => $this->getWeight($shipment),
                    'length' => 10,
                    'width' => 10,
                    'height' => 10
                ]
            ]
        ];
        
        $response = $httpClient->post(
            'https://api.cdek.ru/v2/calculator/tariff',
            \Bitrix\Main\Web\Json::encode($requestData)
        );
        
        $result = new CalculationResult();
        
        if ($response) {
            $data = \Bitrix\Main\Web\Json::decode($response);
            $result->setDeliveryPrice($data['total_sum']);
            $result->setPeriodDescription($data['period_min'] . '-' . $data['period_max'] . ' дней');
        }
        
        return $result;
    }
}
```

#### Ограничения для служб доставки

Bitrix позволяет задавать ограничения (restrictions) для служб доставки:

```php
// По весу
\Bitrix\Sale\Delivery\Restrictions\ByWeight

// По цене заказа
\Bitrix\Sale\Delivery\Restrictions\ByPrice

// По местоположению
\Bitrix\Sale\Delivery\Restrictions\ByLocation
```

### Связь платежей и доставки с заказом

```php
use Bitrix\Sale\Order;

// Загрузка заказа
$order = Order::load($orderId);

// Получение коллекции платежей
$paymentCollection = $order->getPaymentCollection();
foreach ($paymentCollection as $payment) {
    echo $payment->getSum();
    echo $payment->isPaid() ? 'Оплачен' : 'Не оплачен';
}

// Получение коллекции отгрузок
$shipmentCollection = $order->getShipmentCollection();
foreach ($shipmentCollection as $shipment) {
    if (!$shipment->isSystem()) {
        echo $shipment->getDeliveryName();
        echo $shipment->getPrice();
    }
}
```

## Вопросы для собеседования

### Базовые вопросы

**1. Какие основные классы используются для работы с платежными системами в Bitrix?**

**Ответ:** 
- `\Bitrix\Sale\PaySystem\Manager` - управление платежными системами
- `\Bitrix\Sale\Payment` - работа с платежами
- `\Bitrix\Sale\PaySystem\Service` - обработчик конкретной платежной системы
- `\Bitrix\Sale\PaySystem\ServiceHandler` - базовый класс для создания обработчика

**2. Как создать платеж для заказа?**

**Ответ:**
```php
$order = \Bitrix\Sale\Order::load($orderId);
$paymentCollection = $order->getPaymentCollection();

$payment = $paymentCollection->createItem(
    \Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId)
);

$payment->setField('SUM', $order->getPrice());
$payment->setField('CURRENCY', $order->getCurrency());

$order->save();
```

**3. Как обработать callback от платежной системы?**

**Ответ:**
```php
// В обработчике processRequest
public function processRequest(Payment $payment, Request $request)
{
    // Получаем данные из запроса
    $orderId = $request->get('order_id');
    $signature = $request->get('signature');
    
    // Проверяем подпись
    if ($this->checkSignature($request)) {
        // Устанавливаем статус оплачен
        $result = $payment->setPaid('Y');
        
        if ($result->isSuccess()) {
            return new \Bitrix\Sale\PaySystem\ServiceResult();
        }
    }
    
    return new \Bitrix\Sale\PaySystem\ServiceResult();
}
```

**4. Какие типы платежных систем существуют?**

**Ответ:**
- **Внешние** - с перенаправлением на сайт платежной системы (ЮKassa, PayPal)
- **Внутренние** - оплата со внутреннего счета пользователя
- **Наложенный платеж** - оплата при получении
- **Терминальные** - оплата через терминал

**5. Что такое `initiatePay` и когда он вызывается?**

**Ответ:** `initiatePay` - метод обработчика платежной системы, который генерирует форму оплаты или выполняет редирект на страницу оплаты. Вызывается когда пользователь нажимает кнопку "Оплатить" на странице заказа.

**6. Как получить список доступных платежных систем?**

**Ответ:**
```php
use Bitrix\Sale\PaySystem\Manager;

$paySystems = Manager::getList([
    'filter' => ['ACTIVE' => 'Y'],
    'select' => ['ID', 'NAME', 'DESCRIPTION', 'ACTION_FILE'],
    'order' => ['SORT' => 'ASC']
]);

while ($paySystem = $paySystems->fetch()) {
    // Обработка
}
```

### Вопросы по доставке

**7. Какие основные классы используются для работы со службами доставки?**

**Ответ:**
- `\Bitrix\Sale\Delivery\Services\Manager` - управление службами доставки
- `\Bitrix\Sale\Shipment` - отгрузка товаров
- `\Bitrix\Sale\Delivery\Services\Base` - базовый класс для создания службы доставки
- `\Bitrix\Sale\Delivery\CalculationResult` - результат расчета доставки

**8. Как рассчитать стоимость доставки?**

**Ответ:**
```php
$delivery = \Bitrix\Sale\Delivery\Services\Manager::getById($deliveryId);
$shipment = $order->getShipmentCollection()->createItem($delivery);

// Добавляем товары в отгрузку
$basket = $order->getBasket();
foreach ($basket as $basketItem) {
    $shipmentItem = $shipment->getShipmentItemCollection()->createItem($basketItem);
    $shipmentItem->setQuantity($basketItem->getQuantity());
}

// Расчет стоимости
$result = $delivery->calculate($shipment);
if ($result->isSuccess()) {
    $price = $result->getPrice();
    $period = $result->getPeriodDescription();
}
```

**9. Как создать свою службу доставки?**

**Ответ:** Нужно создать класс-обработчик, наследующийся от `\Bitrix\Sale\Delivery\Services\Base`, и реализовать метод `calculateConcrete`:

```php
namespace Sale\Handlers\Delivery;

use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;

class MyDelivery extends Base
{
    protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
    {
        $result = new CalculationResult();
        
        // Расчет стоимости
        $price = 500; // Фиксированная стоимость
        $result->setDeliveryPrice($price);
        $result->setPeriodDescription('1-2 дня');
        
        return $result;
    }
    
    public static function isProfile()
    {
        return false;
    }
}
```

**10. Что такое ограничения (restrictions) для служб доставки?**

**Ответ:** Ограничения позволяют определить условия, при которых служба доставки доступна. Встроенные ограничения:
- `ByWeight` - по весу
- `ByPrice` - по стоимости заказа
- `ByLocation` - по местоположению
- `ByDimensions` - по габаритам

### Продвинутые вопросы

**11. Как интегрировать внешний API службы доставки (например, СДЭК)?**

**Ответ:** В методе `calculateConcrete` выполняем HTTP-запрос к API службы:

```php
protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
{
    $httpClient = new \Bitrix\Main\Web\HttpClient();
    
    // Формируем данные для запроса
    $requestData = [
        'from' => $this->getConfig('WAREHOUSE_LOCATION'),
        'to' => $shipment->getOrder()->getPropertyCollection()
            ->getDeliveryLocation()->getCityName(),
        'weight' => $this->getWeight($shipment)
    ];
    
    // Отправляем запрос
    $response = $httpClient->post(
        'https://api.cdek.ru/v2/calculator/tariff',
        json_encode($requestData)
    );
    
    $result = new CalculationResult();
    
    if ($response) {
        $data = json_decode($response, true);
        $result->setDeliveryPrice($data['price']);
        $result->setPeriodDescription($data['period']);
    } else {
        $result->addError(new \Bitrix\Main\Error('Ошибка расчета доставки'));
    }
    
    return $result;
}
```

**12. Как обрабатывать возврат платежа (refund)?**

**Ответ:**
```php
$payment = \Bitrix\Sale\Payment::getByPrimary($paymentId)->fetchObject();

// Проверяем, поддерживает ли платежная система возвраты
$service = \Bitrix\Sale\PaySystem\Manager::getObjectById(
    $payment->getPaymentSystemId()
);

if ($service->isRefundable()) {
    // Выполняем возврат
    $result = $service->refund($payment);
    
    if ($result->isSuccess()) {
        // Обновляем статус платежа
        $payment->setReturn($payment->getSum());
        $payment->save();
    }
}
```

**13. Как создать автоматический возврат при отмене заказа?**

**Ответ:** Использовать обработчик события `OnSaleOrderCanceled`:

```php
// В init.php или local/php_interface/init.php
AddEventHandler('sale', 'OnSaleOrderCanceled', 'handleOrderCancel');

function handleOrderCancel(\Bitrix\Main\Event $event)
{
    $order = $event->getParameter('ENTITY');
    
    // Получаем все платежи заказа
    $paymentCollection = $order->getPaymentCollection();
    
    foreach ($paymentCollection as $payment) {
        if ($payment->isPaid() && !$payment->isReturn()) {
            // Выполняем возврат
            $service = \Bitrix\Sale\PaySystem\Manager::getObjectById(
                $payment->getPaymentSystemId()
            );
            
            if ($service && $service->isRefundable()) {
                $service->refund($payment);
            }
        }
    }
}
```

**14. Как реализовать частичную оплату заказа?**

**Ответ:**
```php
$order = \Bitrix\Sale\Order::load($orderId);
$paymentCollection = $order->getPaymentCollection();

// Создаем первый платеж на 50% суммы
$payment1 = $paymentCollection->createItem(
    \Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId)
);
$payment1->setField('SUM', $order->getPrice() * 0.5);
$payment1->setField('CURRENCY', $order->getCurrency());

// Создаем второй платеж на оставшуюся сумму
$payment2 = $paymentCollection->createItem(
    \Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId)
);
$payment2->setField('SUM', $order->getPrice() * 0.5);
$payment2->setField('CURRENCY', $order->getCurrency());

$order->save();
```

**15. Как отследить статус доставки через трек-номер?**

**Ответ:**
```php
// Сохраняем трек-номер в отгрузке
$shipment = $shipmentCollection->current();
$shipment->setField('TRACKING_NUMBER', $trackingNumber);
$shipment->save();

// Получаем статус через API службы доставки
function getTrackingStatus($trackingNumber)
{
    $httpClient = new \Bitrix\Main\Web\HttpClient();
    $response = $httpClient->get(
        'https://api.delivery.com/track/' . $trackingNumber
    );
    
    if ($response) {
        $data = json_decode($response, true);
        return $data['status']; // 'in_transit', 'delivered', etc.
    }
    
    return null;
}
```

## Частые ошибки

### 1. Незащищенная обработка callback

❌ **Неправильно:**
```php
// Принимаем данные без проверки подписи
$orderId = $_GET['order_id'];
$payment = \Bitrix\Sale\Payment::getByPrimary($orderId)->fetchObject();
$payment->setPaid('Y');
```

✅ **Правильно:**
```php
// Всегда проверяем подпись
public function processRequest(Payment $payment, Request $request)
{
    $signature = $request->get('signature');
    $expectedSignature = $this->calculateSignature($request->getValues());
    
    if ($signature !== $expectedSignature) {
        return new \Bitrix\Sale\PaySystem\ServiceResult();
    }
    
    $payment->setPaid('Y');
    return new \Bitrix\Sale\PaySystem\ServiceResult();
}
```

### 2. Неправильный расчет стоимости доставки

❌ **Неправильно:**
```php
// Не учитываем вес и габариты
protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
{
    $result = new CalculationResult();
    $result->setDeliveryPrice(500); // Всегда одна цена
    return $result;
}
```

✅ **Правильно:**
```php
protected function calculateConcrete(\Bitrix\Sale\Shipment $shipment)
{
    $result = new CalculationResult();
    
    // Учитываем вес
    $weight = 0;
    foreach ($shipment->getShipmentItemCollection() as $item) {
        $product = $item->getBasketItem()->getProductId();
        $weight += \CIBlockElement::GetByID($product)
            ->Fetch()['PROPERTY_WEIGHT_VALUE'] * $item->getQuantity();
    }
    
    // Рассчитываем стоимость на основе веса
    $price = 500 + ($weight > 5 ? ($weight - 5) * 50 : 0);
    $result->setDeliveryPrice($price);
    
    return $result;
}
```

### 3. Отсутствие логирования

❌ **Неправильно:**
```php
// Нет логов - сложно отладить проблемы
public function processRequest(Payment $payment, Request $request)
{
    $payment->setPaid('Y');
}
```

✅ **Правильно:**
```php
public function processRequest(Payment $payment, Request $request)
{
    // Логируем входящий запрос
    \Bitrix\Main\Diag\Debug::writeToFile(
        $request->getValues(),
        'Payment callback',
        'payment_system.log'
    );
    
    if ($this->checkSignature($request)) {
        $result = $payment->setPaid('Y');
        
        if (!$result->isSuccess()) {
            \Bitrix\Main\Diag\Debug::writeToFile(
                $result->getErrorMessages(),
                'Payment error',
                'payment_system.log'
            );
        }
    }
}
```

### 4. Игнорирование ошибок API

❌ **Неправильно:**
```php
// Не проверяем ответ API
$response = $httpClient->get($apiUrl);
$data = json_decode($response, true);
return $data['price']; // Может быть null!
```

✅ **Правильно:**
```php
$response = $httpClient->get($apiUrl);

if (!$response) {
    $result->addError(new \Bitrix\Main\Error('API недоступен'));
    return $result;
}

$data = json_decode($response, true);

if (isset($data['error'])) {
    $result->addError(new \Bitrix\Main\Error($data['error']['message']));
    return $result;
}

if (!isset($data['price'])) {
    $result->addError(new \Bitrix\Main\Error('Некорректный ответ API'));
    return $result;
}

$result->setDeliveryPrice($data['price']);
```

## Практические задачи

1. **Интеграция платежной системы ЮKassa**
   - Создание обработчика платежной системы
   - Генерация формы оплаты
   - Обработка уведомлений от ЮKassa
   - Проверка подписи запросов

2. **Служба доставки с расчетом через API**
   - Интеграция с API СДЭК
   - Расчет стоимости и сроков доставки
   - Обработка ошибок API
   - Кеширование результатов

3. **Комбинированная оплата заказа**
   - Частичная оплата бонусами
   - Доплата через платежную систему
   - Создание нескольких платежей для одного заказа
   - Контроль полной оплаты

**Решения задач находятся в `examples/module-3-4/`**

## Ссылки на документацию

- [Платежные системы](https://dev.1c-bitrix.ru/api_d7/bitrix/sale/paysystem/index.php)
- [Службы доставки](https://dev.1c-bitrix.ru/api_d7/bitrix/sale/delivery/index.php)
- [Создание обработчика платежной системы](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=42&LESSON_ID=7829)
- [Создание службы доставки](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=42&LESSON_ID=7830)
