<?php
/**
 * Обработчик события оплаты заказа
 * Автоматически списывает бонусы после оплаты основного платежа
 */

use Bitrix\Main\EventManager;
use Bitrix\Sale\Order;
use Sale\Helpers\CombinedPayment;

// Регистрируем обработчики событий
$eventManager = EventManager::getInstance();

// Обработчик успешной оплаты
$eventManager->addEventHandler(
    'sale',
    'OnSalePaymentPaid',
    'handlePaymentPaid'
);

// Обработчик отмены заказа
$eventManager->addEventHandler(
    'sale',
    'OnSaleOrderCanceled',
    'handleOrderCanceled'
);

/**
 * Обработка успешной оплаты
 * Списываем бонусы после оплаты основного платежа
 *
 * @param \Bitrix\Main\Event $event
 */
function handlePaymentPaid(\Bitrix\Main\Event $event)
{
    /** @var \Bitrix\Sale\Payment $payment */
    $payment = $event->getParameter('ENTITY');

    if (!$payment) {
        return;
    }

    // Проверяем, что это не платеж бонусами
    if ($payment->getPaymentSystemId() == CombinedPayment::INNER_PAY_SYSTEM_ID) {
        return;
    }

    // Получаем заказ
    $order = $payment->getOrder();

    if (!$order) {
        return;
    }

    // Получаем платеж бонусами
    $bonusPayment = CombinedPayment::getBonusPayment($order);

    if (!$bonusPayment) {
        // В заказе нет платежа бонусами
        return;
    }

    // Если бонусы еще не списаны - списываем
    if (!$bonusPayment->isPaid()) {
        $result = CombinedPayment::chargeBonuses($order, $bonusPayment);

        if (!$result->isSuccess()) {
            // Логируем ошибку
            \Bitrix\Main\Diag\Debug::writeToFile(
                [
                    'order_id' => $order->getId(),
                    'payment_id' => $payment->getId(),
                    'errors' => $result->getErrorMessages()
                ],
                'Error charging bonuses',
                'combined_payment.log'
            );
        }
    }

    // Проверяем, что заказ полностью оплачен
    if (CombinedPayment::isFullyPaid($order)) {
        // Отмечаем заказ как оплаченный
        if (!$order->isPaid()) {
            $order->setField('PAYED', 'Y');
            $order->save();
        }
    }
}

/**
 * Обработка отмены заказа
 * Возвращаем бонусы при отмене заказа
 *
 * @param \Bitrix\Main\Event $event
 */
function handleOrderCanceled(\Bitrix\Main\Event $event)
{
    /** @var Order $order */
    $order = $event->getParameter('ENTITY');

    if (!$order) {
        return;
    }

    // Возвращаем бонусы
    $result = CombinedPayment::refundBonuses($order);

    if (!$result->isSuccess()) {
        // Логируем ошибку
        \Bitrix\Main\Diag\Debug::writeToFile(
            [
                'order_id' => $order->getId(),
                'errors' => $result->getErrorMessages()
            ],
            'Error refunding bonuses',
            'combined_payment.log'
        );
    }
}
