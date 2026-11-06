<?php

namespace BitrixCourse\Checkout;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Sale;

/**
 * Собирает Bitrix\Sale\Order из корзины fuser'а и данных формы чекаута.
 * Инкапсулирует правильный порядок вызовов D7 Order API — самую частую
 * причину багов "заказ создался, а сумма/свойства не те".
 */
class OrderBuilder
{
    private string $siteId;
    private int $userId;

    public function __construct(string $siteId, int $userId)
    {
        $this->siteId = $siteId;
        $this->userId = $userId;
    }

    /**
     * @param array $formData [
     *   'PERSON_TYPE_ID' => int,
     *   'DELIVERY_ID'    => int,
     *   'PAY_SYSTEM_ID'  => int,
     *   'PROPERTIES'     => [propertyId => value, ...],
     * ]
     */
    public function build(Sale\Basket $basket, array $formData): Result
    {
        $result = new Result();

        if ($basket->count() === 0) {
            $result->addError(new Error('Корзина пуста'));
            return $result;
        }

        $personTypeId = (int)($formData['PERSON_TYPE_ID'] ?? 0);
        if ($personTypeId <= 0) {
            $result->addError(new Error('Не выбран тип плательщика'));
            return $result;
        }

        $order = Sale\Order::create($this->siteId, $this->userId);

        // ВАЖНО: тип плательщика — ДО заполнения свойств. Набор доступных
        // свойств (PropertyValueCollection) зависит от PERSON_TYPE_ID,
        // поэтому обратный порядок молча обнулит уже введённые значения.
        $order->setPersonTypeId($personTypeId);
        $order->setField('CURRENCY', \Bitrix\Currency\CurrencyManager::getBaseCurrency());

        $basketResult = $order->setBasket($basket);
        if (!$basketResult->isSuccess()) {
            $result->addErrors($basketResult->getErrors());
            return $result;
        }

        $propsResult = $this->fillProperties($order, $formData['PROPERTIES'] ?? []);
        if (!$propsResult->isSuccess()) {
            $result->addErrors($propsResult->getErrors());
            return $result;
        }

        $shipmentResult = $this->createShipment($order, (int)($formData['DELIVERY_ID'] ?? 0));
        if (!$shipmentResult->isSuccess()) {
            $result->addErrors($shipmentResult->getErrors());
            return $result;
        }

        // Оплату создаём ПОСЛЕ отгрузки — к этому моменту order->getPrice()
        // уже учитывает стоимость доставки. Обратный порядок даёт SUM
        // по недопосчитанному заказу.
        $paymentResult = $this->createPayment($order, (int)($formData['PAY_SYSTEM_ID'] ?? 0));
        if (!$paymentResult->isSuccess()) {
            $result->addErrors($paymentResult->getErrors());
            return $result;
        }

        $saveResult = $order->save();
        if (!$saveResult->isSuccess()) {
            $result->addErrors($saveResult->getErrors());
            return $result;
        }

        $result->setData(['ORDER' => $order]);
        return $result;
    }

    private function fillProperties(Sale\Order $order, array $values): Result
    {
        $result = new Result();
        $propertyCollection = $order->getPropertyCollection();

        foreach ($propertyCollection as $property) {
            /** @var Sale\PropertyValue $property */
            $propId = $property->getPropertyId();
            if (!array_key_exists($propId, $values)) {
                continue;
            }

            $setResult = $property->setValue($values[$propId]);
            if (!$setResult->isSuccess()) {
                $result->addErrors($setResult->getErrors());
            }
        }

        // setValue() не проверяет обязательность — делаем это явно,
        // иначе заказ "успешно" сохранится без обязательного телефона.
        foreach ($propertyCollection as $property) {
            if ($property->isRequired() && (string)$property->getValue() === '') {
                $result->addError(new Error(
                    'Не заполнено обязательное поле: ' . $property->getField('NAME')
                ));
            }
        }

        return $result;
    }

    private function createShipment(Sale\Order $order, int $deliveryId): Result
    {
        $result = new Result();

        $deliveryService = Sale\Delivery\Services\Manager::getById($deliveryId);
        if (!$deliveryService) {
            $result->addError(new Error('Служба доставки не найдена'));
            return $result;
        }

        $shipmentCollection = $order->getShipmentCollection();
        $shipment = $shipmentCollection->createItem($deliveryService);
        $shipment->setFields([
            'DELIVERY_ID'   => $deliveryId,
            'DELIVERY_NAME' => $deliveryService['NAME'] ?? '',
            'CURRENCY'      => $order->getCurrency(),
        ]);

        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($order->getBasket() as $basketItem) {
            /** @var Sale\BasketItem $basketItem */
            $shipmentItem = $shipmentItemCollection->createItem($basketItem);
            $shipmentItem->setQuantity($basketItem->getQuantity());
        }

        return $result;
    }

    private function createPayment(Sale\Order $order, int $paySystemId): Result
    {
        $result = new Result();

        $paySystemService = Sale\PaySystem\Manager::getObjectById($paySystemId);
        if (!$paySystemService) {
            $result->addError(new Error('Способ оплаты не найден'));
            return $result;
        }

        $paymentCollection = $order->getPaymentCollection();
        $payment = $paymentCollection->createItem($paySystemService);
        $payment->setFields([
            'SUM'      => $order->getPrice(),
            'CURRENCY' => $order->getCurrency(),
        ]);

        return $result;
    }
}
