# Модуль 5.2: Корзина и оформление заказа (уровень senior)

## Теория

### Архитектура корзины (D7, модуль sale)

Корзина — это **не таблица `b_sale_fuser` напрямую**, а объектная модель:

- `\Bitrix\Sale\Basket` — коллекция `\Bitrix\Sale\BasketItem`, привязанная к `FUSER_ID` (анонимный ID покупателя, кука `BX_USER_ID` / запись в `b_sale_fuser`) и `SITE_ID`.
- `b_sale_basket` — строки корзины: `PRODUCT_ID`, `PRICE`, `CUSTOM_PRICE` (Y — цена задана извне и не пересчитывается), `CAN_BUY`, `WEIGHT`, `QUANTITY`, `MODULE`/`PRODUCT_PROVIDER_CLASS` (по ним система знает, как получать остаток/свойства товара).
- `\Bitrix\Sale\Order` — агрегат заказа: держит `Basket`, `PropertyValueCollection`, `ShipmentCollection`, `PaymentCollection`, доставку/оплату и вычисляет итоги.

Ключевой принцип senior-уровня: **все изменения идут через объектную модель и `save()`**, а не через `CSaleBasket::Add`/`b_sale_basket` напрямую — иначе не сработают правила корзины, резервы, отгрузки и события.

### Правильная работа с корзиной

```php
use Bitrix\Sale;

$siteId = SITE_ID;
$fuserId = Sale\Fuser::getId();           // анонимный ID текущего посетителя
$basket = Sale\Basket::loadItemsForFUser($fuserId, $siteId);

// Добавление товара "как надо"
$item = $basket->createItem('catalog', $productId);
$item->setFields([
    'QUANTITY'               => 2,
    'CURRENCY'               => 'RUB',
    'LID'                    => $siteId,
    'PRODUCT_PROVIDER_CLASS' => \Bitrix\Catalog\Product\BasketProvider::class,
]);
$basket->save();   // внутри: проверка остатка, применение правил корзины, пересчёт скидок
```

### Купоны и правила корзины

- `CSaleBasket::isCouponUsed`, `CouponsManager::add`/`apply`, сами правила — `sale.discount`.
- Дисконт считается **в момент `basket->save()`** через `\Bitrix\Sale\Discount\ActionDiscountCalculator` (старый `CSaleDiscount`) или новый `\Bitrix\Sale\Discount\CouponsManager`.
- Свой тип правила корзины — `CSaleActionCtrl` + регистрация через `OnCondSaleActionsControlBuildList` (действия) / `OnCondSaleControlBuildList` (условия).
- Важный момент для собеседования: купон применяется к корзине, а не к конкретной позиции — для «скидки на 2-й товар» пишется кастомный action.

### Мердж корзин при авторизации

Классическая головная боль: аноним положил товары → залогинился → корзина старого `FUSER_ID` не должна потеряться.

- `Sale\Fuser` после `CUser::Authorize` мэпится на реального `USER_ID`.
- Ручной перенос: загрузить корзину старого `FUSER_ID`, переложить `BasketItem` в корзину нового, `delete` старую. Есть встроенный `Basket::transferItems` / событие `OnUserLogin`.
- Частая ошибка — просто поменять `FUSER_ID` у строк корзины напрямую: ломаются резервы и правила.

### Оформление заказа (Order API)

Правильный способ создать заказ программно:

```php
$order = Sale\Order::create($siteId, $userId);
$order->setPersonTypeId($personTypeId);        // физ./юр. лицо — важно для свойств
$order->setBasket($basket);

$propertyCollection = $order->getPropertyCollection();
$propertyCollection->getItemByOrderPropertyId($phonePropId)->setValue('+7...');

$shipmentCollection = $order->getShipmentCollection();
$shipment = $shipmentCollection->createItem(
    Sale\Delivery\Services\Manager::getById($deliveryId)
);
$shipmentItemCollection = $shipment->getShipmentItemCollection();
foreach ($basket as $basketItem) {
    $shipmentItemCollection->createItem($basketItem)->setQuantity($basketItem->getQuantity());
}

$paymentCollection = $order->getPaymentCollection();
$payment = $paymentCollection->createItem(
    Sale\PaySystem\Manager::getById($paySystemId)
);
$payment->setField('SUM', $order->getPrice());
$payment->setField('CURRENCY', $order->getCurrency());

$order->save();  // списание резервов, отгрузка в b_sale_shipment_item, события OnSaleOrderSaved
```

Ловушки:

- `setPersonTypeId` вызывать **до** заполнения свойств — набор свойств зависит от типа плательщика.
- Нельзя мутировать `basket` после привязки к заказу без `order->save()` — потеряешь консистентность.
- Создавать оплату после всех отгрузок — иначе `SUM` будет рассчитан по недопосчитанной корзине.

### События заказа (куда вешать бизнес-логику)

- `OnSaleOrderSaved` — после сохранения заказа (отправка письма, экспорт в 1С, внешние уведомления).
- `OnSaleBeforeStatusChange` / `OnSaleStatusOrder` — смена статуса.
- `OnSaleShipmentSaved` — отгрузки.
- `OnSalePaymentSaved` / `OnSaleOrderPaid` — оплата.
- `OnOrderAdd` (старое, через `CSaleOrder::Add`) — всё ещё встречается в legacy, но **не вызывается** при сохранении через D7 `Order::save()` — классическая причина «не срабатывает событие».
- `OnSaleComponentOrderCreated` / `OnSaleComponentOrderResultPrepared` — модификация результата в `sale.order.ajax`.

### Производительность и безопасность корзины

- Не делать `basket->save()` на каждый ajax-экшен «+1 в корзине» без троттлинга — правила корзины и резервы пересчитываются целиком.
- CSRF: все мутации корзины — только `POST` + `check_bitrix_sessid()`.
- Цены из `basketItem->getPrice()` — нельзя доверять клиентскому `price` из JS, всегда пересчитывать на бэкенде.
- Купоны — валидация срока действия и привязки к пользователю.

## Вопросы для собеседования

1. **Чем `FUSER_ID` отличается от `USER_ID` и зачем нужен анонимный ID?**
   `FUSER_ID` — запись в `b_sale_fuser`, создаётся для любого посетителя по куке до авторизации. Позволяет вести корзину анонима, а при входе связать её с `USER_ID` через `b_sale_fuser.USER_ID` и перенести корзину.

2. **Как правильно добавить товар в корзину — CSaleBasket::Add или Basket::createItem?**
   `Basket::createItem` + `basket->save()` — только так срабатывают правила корзины, проверка остатков, события `OnSaleBasketItem*` и резервирование. `CSaleBasket::Add` — legacy, обходит часть логики.

3. **Что произойдёт с корзиной анонима после логина?**
   Если явно не перенести — корзина «нового» пользователя будет пустой, товары останутся на старом `FUSER_ID`. Решается переносом `BasketItem` в обработчике `OnUserLogin` либо `Basket::transferItems`.

4. **Как сделать скидку «купи 2 — третий бесплатно»?**
   Стандартными правилами корзины — сложно (нет «N-я единица бесплатно»). Пишется кастомный `CSaleActionCtrl`, который в `apply` находит целевой товар и модифицирует его цену/количество.

5. **В каком порядке создавать заказ, чтобы всё посчиталось правильно?**
   `Order::create` → `setPersonTypeId` → `setBasket` → свойства → отгрузки → оплаты → `save()`. Тип плательщика задаёт набор свойств и налоги, поэтому ставится до свойств.

6. **Почему событие `OnOrderAdd` не срабатывает при создании заказа в D7-компоненте?**
   `OnOrderAdd` — событие старого `CSaleOrder::Add`. D7-заказы сохраняются через `Order::save()`, для них существуют `OnSaleOrderSaved`/`OnSaleOrderBeforeSaved` и т.д. — события не эквивалентны.

7. **Что такое `CUSTOM_PRICE` в корзине?**
   Флаг «цена задана вручную/извне» — система не пересчитывает её правилами корзины и валютой. Используется для импортов и B2B-договорных цен. Сброс — `setField('CUSTOM_PRICE', 'N')` или пересоздание позиции.

8. **Как пересчитать корзину после изменения цен в каталоге?**
   `\Bitrix\Sale\DiscountCouponsManager::finalApply()` / `basket->refresh(['PRICE','DISCOUNT_PRICE'])` + `save()` — пересчитает цены и применит актуальные правила.

9. **Где физически хранятся резервы корзины и заказа?**
   `b_sale_basket.RESERVED_QUANTITY` + `b_catalog_product.QUANTITY_RESERVED`; резервация происходит на `basket->save()`/`order->save()` в зависимости от настройки `product_reserve_condition`.

10. **Как ограничить оформление заказа для неавторизованных?**
    Настройка модуля sale `viewed/allow_guest_order` либо явная проверка `USER->IsAuthorized()` в `init`/`component.php` чекаута + `OnSaleComponentOrderCreated` для отмены.

## Частые ошибки

- Мутация `b_sale_basket` напрямую → корзина «есть», но `basket->save()` её не видит/перезатирает.
- `setBasket` после `Order::create` без `basket->save()` на новой корзине → `PRODUCT_PROVIDER_CLASS`/`MODULE` пустые.
- `setPersonTypeId` после `setValue` свойств → свойства сбрасываются на набор нового типа плательщика.
- Отправка письма в `OnSaleOrderSaved` синхронно → checkout висит 5 секунд. Правильно — агент/очередь.
- Доверие к `price`/`quantity` из JS-запроса → подмена цены на 1 копейку.

## Практические задачи

- **Кейс 1**: `examples/module-5-2/basket-service` — сервис корзины: add/update/remove с валидацией, купоны, мердж корзины при логине, CSRF-защищённые ajax-экшены.
- **Кейс 2**: `examples/module-5-2/onepage-checkout` — кастомный checkout-компонент: создание заказа через `Order::create`, свойства по типу плательщика, отгрузки/оплаты, отправка письма асинхронно.
- **Кейс 3**: `examples/module-5-2/custom-basket-rule` — правило «каждая N-я единица товара дешевле»: чистый калькулятор + `CUSTOM_PRICE` на `BasketItem` до `save()`, аудит через `OnSaleOrderSaved`. README объясняет, почему выбран явный калькулятор, а не legacy `CSaleActionCtrl`.

**Решения находятся в `examples/module-5-2/`**
