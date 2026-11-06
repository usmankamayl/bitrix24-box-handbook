# Модуль 5.3: Обработчики заказа (уровень senior)

## Теория

### Что такое «обработчики» в модуле sale

Слово «обработчик» в Bitrix-магазине используется в трёх разных контекстах, и на собеседовании важно не путать:

1. **Обработчики платёжных систем (PS handlers)** — классы, наследующие `Sale\PaySystem\ServiceHandler` или `Sale\PaySystem\BaseServiceHandler`. Отвечают за инициацию оплаты, проверку ответа платёжного шлюза (callback/webhook), частичный возврат, чек 54-ФЗ.
2. **Обработчики служб доставки (Delivery handlers)** — классы, наследующие `Sale\Delivery\Services\Base`. Считают стоимость/срок доставки (`calculatePrice`), поддерживают профили (`configuredServices`), могут быть одиночными или «профильными» (курьер/ПВЗ/почта).
3. **Обработчики событий заказа** — `OnSaleOrderSaved`, `OnSaleStatusOrderChange`, `OnSaleOrderPaid`, `OnSaleBeforeShipment*`, `OnSaleOrderBeforeSaved` — точки расширения, куда вешается бизнес-логика (уведомления, интеграции, резервы, 1С-экспорт).

Для каждого типа — своя точка регистрации:

- PS: `PaySystem\Manager::includeHandler()` + запись в `b_sale_pay_system_action`.
- Delivery: `\Bitrix\Main\Loader::includeModule('sale'); \Bitrix\Sale\Delivery\Services\Manager::getFieldsList()` и регистрация через `Base::getAdminFieldsList()` либо `sale.delivery.services.add` (в UI — через страницу «Службы доставки»).
- События: `EventManager::getInstance()->addEventHandler('sale', ...)`.

### Жизненный цикл оплаты

```
Order::save()
  -> PaymentCollection::createItem($paySystemId)
    -> Payment::setField(...)
  -> PaySystem\Service::initiatePay($payment, $request)   // вызов хендлера
     -> ServiceHandler::initiatePay()
       -> [HTTP на шлюз] -> url для редиректа, paymentId, paymentUrl
  -> [gateway callback] -> /bitrix/tools/sale_ps_result.php?...
     -> ServiceHandler::processRequest() -> проверка подписи
       -> Payment::setPaid("Y") -> Order::setField('STATUS_ID','P')
```

Важно: **callback проходит отдельным HTTP-запросом** — у хендлера нет доступа к `$_SESSION` пользователя. Идентификация идёт по `PAYMENT_ID` / `ORDER_ID` из тела запроса и проверяется подписью.

### Базовый контракт PaySystem хендлера

```php
class AcmeGatewayHandler extends \Bitrix\Sale\PaySystem\BaseServiceHandler
{
    // вернуть список параметров, которые будут в шаблоне «платёжная форма»
    public static function getHandlerParams(): array
    public function initiatePay(Payment $payment, Request $request = null): ServiceResult
    public function processRequest(Payment $payment, Request $request): ServiceResult
    public function getPaymentIdFromRequest(Request $request): ?int
    public function refund(Payment $payment, $refundableSum): ServiceResult
    public function isPayed(Payment $payment): bool
    public function getPrice(Payment $payment): float
    public function isSignatureValid(...)
    // + sendCheck(), getCheckList() для 54-ФЗ
}
```

Ключевые моменты senior-уровня:

- **`getPaymentIdFromRequest`** должен извлекать `PAYMENT_ID` из тела запроса шлюза — по нему `sale_ps_result.php` находит платёжку. Это не `ORDER_ID`, а именно `PAYMENT_ID` — у заказа может быть несколько платежей.
- **`processRequest` обязан вернуть идемпотентный результат.** Шлюзы любят ретраить webhook'и — двойное `setPaid("Y")` без проверки `PAYED` приведёт к двойным списаниям резервов и двойным письмам. Проверяйте `$payment->getField('PAYED') === 'Y'` в начале.
- **Подпись валидируется до того, как что-либо делается.** Если подпись не сошлась — `ServiceResult` с ошибкой и никаких `setPaid`.
- **`initiatePay` должен быть read-only по заказу.** Заказ ещё сохраняется — изменение его полей в хендлере даст race condition.
- **Частичный возврат**: `refund()` с `$refundableSum`, не `ORDER_CANCELLED`. Bitrix различает «отмена заказа» и «возврат части суммы».
- **54-ФЗ** — чек делается через `PaySystem\Check\Manager` и `ServiceHandler::sendCheck()`, а не вручную `file_get_contents` в callback.

### Жизненный цикл доставки

```
Order::save()
  -> ShipmentCollection::createItem($deliveryServiceId)
     -> Delivery\Services\Base::calculatePrice($shipment, $currency)
        -> [HTTP/библиотека] -> цена, срок, ограничения
```

- **`Base` vs `ConfigProvider`** — обычные службы наследуют `Base`, службы с профилями (СДЭК: курьер/ПВЗ/почта) — `Base` + реализуют `IConfigurable`/`IConfigurableProfile`. Профиль — это фактически отдельная «служба» в `b_sale_delivery_srv` с `PARENT_ID`.
- **`calculatePrice`** возвращает `CalculationResult` (`getPrice()`, `getPeriodDescription()`, `getPacking()`), а не float — иначе не отобразятся сроки в оформлении заказа.
- **Restrictions** (`Sale\Delivery\Restrictions\*` — `BySite`, `ByLocation`, `ByPaySystem`, `ByWeight`, `ByDimensions`, `ByPersonType`) применяются **до** `calculatePrice` — хендлер вообще не вызывается, если restriction отклонил службу.
- **Не дёргай внешний API в `calculatePrice` без кеша.** Страница оформления заказа дёргает расчёт на каждое изменение (город, способ оплаты, промокод) — без кеша по `location + weight + dimensions` вы получите таймауты.

### Жизненный цикл заказа — события

```
OnSaleBeforeOrderCreate           — до первого save() нового заказа
OnSaleOrderBeforeSaved            — до сохранения любого изменения Order
OnSaleOrderSaved                  — после сохранения Order
OnSaleStatusOrderChange           — после смены STATUS_ID (в т.ч. PAID->P, шипмент->DE)
OnSaleOrderPaid                   — после Payment::setPaid("Y") для всех платежей
OnSaleShipmentItemSaved           — после сохранения позиции отгрузки
OnSaleOrderCanceled               — после отмены заказа
```

- **`OnSaleOrderBeforeSaved` vs `OnSaleOrderSaved`** — «Before» внутри транзакции: бросок `Result::addError()` откатит заказ. «Saved» — после коммита: здесь безопасно слать почту, push в 1С, метрики.
- **`OnSaleStatusOrderChange` ловит и PAID, и отгрузку** — тип события определяется по `$order->getField('STATUS_ID')` и `$order->isPaid()`/`isShipped()`.
- **Внутри события не вызывай `Order::save()`** — рекурсия. Для изменения полей используй `OnSaleOrderBeforeSaved` (там можно модифицировать объект) или отложенный агент.
- **`Entity\Event` vs `Bitrix\Main\Event`** — D7-события приходят как `Bitrix\Main\Event` с `getParameter('ENTITY')` — объект `Order`. Старые `OnSaleOrder*` без `Before`/`After` дают `ID заказа` — придётся `Order::load($id)` заново.

### Интеграции: HTTP-вызовы из обработчиков

- **Никогда не блокируй сохранение заказа долгим HTTP.** Таймаут платёжного шлюза 10s — иначе `Order::save()` стоит и пользователь видит «бесконечную загрузку». Выносите в очередь: `CAgent::AddAgent` + фоновая отправка, либо RabbitMQ/Redis.
- **Идемпотентность webhook'ов**: таблица `processed_payments` / `processed_shipments` с `UNIQUE (payment_id, event_type)` — второй callback от шлюза проверит запись и вернёт `200` без действий.
- **Circuit breaker**: N подряд ошибок → флаг `b_option` → `calculatePrice` возвращает `CalculationResult` с `IN_DAYS = 0` и `deliveringReason = 'service unavailable'` вместо падения страницы.

### Частые ошибки на собеседовании

1. Спросят «как реализовать PayPal/ЮKassa» — отвечай «наследую `BaseServiceHandler`, реализую `initiatePay/processRequest/refund`, регистрирую через `Manager::includeHandler` + строка в `b_sale_pay_system_action`».
2. Спросят «как понять, что заказ оплачен» — `OnSaleOrderPaid`, а **не** проверка `STATUS_ID == 'P'` на `OnSaleOrderSaved` (заказ может стать `P` до оплаты, или оплата может прийти при статусе `N`).
3. Спросят «как кастомная доставка считает цену» — `calculatePrice(Shipment $shipment)`, вернуть `CalculationResult`, кешировать по `location+weight`.
4. Спросят «callback приходит дважды» — идемпотентность: проверка `PAYED` / уникальный ключ `payment_id+event`.
5. «А если нужно переопределить штатный PS handler?» — `Manager::includeHandler` + в настройках ПС указать свой класс; нельзя править `b_sale_pay_system_action` напрямую в коде, иначе обновление сломает.

### Вопросы для самопроверки

- Чем отличается `Payment::setPaid` от `Order::setField('STATUS_ID','P')`?
- Почему нельзя вызывать `Order::save()` внутри `OnSaleOrderSaved`?
- Какая разница между `initiatePay` и `processRequest`?
- Как зарегистрировать собственную службу доставки с тремя профилями (курьер/ПВЗ/почта)?
- Что вернуть из `calculatePrice`, если внешний API доставки упал?
- Чем `OnSaleBeforeOrderCreate` отличается от `OnSaleOrderBeforeSaved`?

## Практические задачи

- **Кейс 1**: Кастомный платёжный обработчик — авторизация, capture, refund, webhook с проверкой подписи.
- **Кейс 2**: Кастомная служба доставки с внешним API (расчёт по API + кеш + circuit breaker).
- **Кейс 3**: Обработчик жизненного цикла заказа — `OnSaleStatusOrderChange` + `OnSaleOrderPaid` + отправка во внешнюю ERP, идемпотентность.

**Решения задач находятся в `examples/module-5-3/`**
