<?php
/**
 * Класс для работы с комбинированной оплатой заказа
 * Поддерживает оплату бонусами + доплата через платежную систему
 */

namespace Sale\Helpers;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Sale\Order;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem\Manager;

/**
 * Класс для комбинированной оплаты
 */
class CombinedPayment
{
    /** @var int ID платежной системы "Внутренний счет" */
    const INNER_PAY_SYSTEM_ID = 1;

    /** @var string Код свойства заказа с бонусами */
    const BONUS_PROPERTY_CODE = 'BONUS_USED';

    /**
     * Создание комбинированной оплаты для заказа
     *
     * @param Order $order Заказ
     * @param float $bonusAmount Сумма оплаты бонусами
     * @param int $paySystemId ID платежной системы для доплаты
     * @return Result
     */
    public static function create(Order $order, $bonusAmount, $paySystemId)
    {
        $result = new Result();

        // Проверяем, что заказ не оплачен
        if ($order->isPaid()) {
            $result->addError(new Error('Заказ уже оплачен'));
            return $result;
        }

        // Получаем стоимость заказа
        $orderPrice = $order->getPrice();

        // Проверяем корректность суммы бонусов
        if ($bonusAmount < 0) {
            $result->addError(new Error('Сумма бонусов не может быть отрицательной'));
            return $result;
        }

        if ($bonusAmount > $orderPrice) {
            $result->addError(new Error('Сумма бонусов превышает стоимость заказа'));
            return $result;
        }

        // Проверяем баланс бонусов пользователя
        $userId = $order->getUserId();
        $userBonusBalance = self::getUserBonusBalance($userId);

        if ($bonusAmount > $userBonusBalance) {
            $result->addError(new Error(
                sprintf('Недостаточно бонусов. Доступно: %.2f', $userBonusBalance)
            ));
            return $result;
        }

        // Получаем коллекцию платежей
        $paymentCollection = $order->getPaymentCollection();

        // Очищаем существующие платежи (кроме системного)
        foreach ($paymentCollection as $payment) {
            if (!$payment->isInner()) {
                $payment->delete();
            }
        }

        // Создаем платеж бонусами (если сумма > 0)
        if ($bonusAmount > 0) {
            $bonusPayment = $paymentCollection->createItem(
                Manager::getObjectById(self::INNER_PAY_SYSTEM_ID)
            );

            $bonusPayment->setField('SUM', $bonusAmount);
            $bonusPayment->setField('CURRENCY', $order->getCurrency());
            $bonusPayment->setField('PAID', 'N');

            // Сохраняем информацию о бонусах в свойстве заказа
            $propertyCollection = $order->getPropertyCollection();
            $bonusProperty = $propertyCollection->getItemByOrderPropertyCode(self::BONUS_PROPERTY_CODE);

            if ($bonusProperty) {
                $bonusProperty->setValue($bonusAmount);
            }
        }

        // Рассчитываем сумму доплаты
        $remainingAmount = $orderPrice - $bonusAmount;

        // Создаем платеж через платежную систему (если есть доплата)
        if ($remainingAmount > 0) {
            $mainPayment = $paymentCollection->createItem(
                Manager::getObjectById($paySystemId)
            );

            $mainPayment->setField('SUM', $remainingAmount);
            $mainPayment->setField('CURRENCY', $order->getCurrency());
            $mainPayment->setField('PAID', 'N');

            $result->setData([
                'bonus_payment_id' => $bonusAmount > 0 ? $bonusPayment->getId() : null,
                'main_payment_id' => $mainPayment->getId(),
                'bonus_amount' => $bonusAmount,
                'remaining_amount' => $remainingAmount
            ]);
        } else {
            // Заказ полностью оплачен бонусами
            $result->setData([
                'bonus_payment_id' => $bonusPayment->getId(),
                'main_payment_id' => null,
                'bonus_amount' => $bonusAmount,
                'remaining_amount' => 0
            ]);
        }

        // Сохраняем заказ
        $saveResult = $order->save();

        if (!$saveResult->isSuccess()) {
            $result->addErrors($saveResult->getErrors());
            return $result;
        }

        // Если заказ полностью оплачен бонусами - списываем их сразу
        if ($remainingAmount <= 0 && $bonusAmount > 0) {
            $chargeResult = self::chargeBonuses($order, $bonusPayment);

            if (!$chargeResult->isSuccess()) {
                $result->addErrors($chargeResult->getErrors());
            }
        }

        return $result;
    }

    /**
     * Списание бонусов после оплаты основного платежа
     *
     * @param Order $order Заказ
     * @param Payment $bonusPayment Платеж бонусами
     * @return Result
     */
    public static function chargeBonuses(Order $order, Payment $bonusPayment)
    {
        $result = new Result();

        // Проверяем, что бонусы еще не списаны
        if ($bonusPayment->isPaid()) {
            $result->addError(new Error('Бонусы уже списаны'));
            return $result;
        }

        $userId = $order->getUserId();
        $bonusAmount = $bonusPayment->getSum();

        // Списываем бонусы у пользователя
        $chargeResult = self::debitUserBonuses($userId, $bonusAmount, $order->getId());

        if (!$chargeResult->isSuccess()) {
            $result->addErrors($chargeResult->getErrors());
            return $result;
        }

        // Отмечаем платеж бонусами как оплаченный
        $paidResult = $bonusPayment->setPaid('Y');

        if (!$paidResult->isSuccess()) {
            // Откатываем списание бонусов
            self::creditUserBonuses($userId, $bonusAmount, $order->getId());
            $result->addErrors($paidResult->getErrors());
            return $result;
        }

        $bonusPayment->save();

        // Логируем списание
        self::log('Bonuses charged', [
            'order_id' => $order->getId(),
            'user_id' => $userId,
            'amount' => $bonusAmount
        ]);

        return $result;
    }

    /**
     * Возврат бонусов при отмене заказа
     *
     * @param Order $order Заказ
     * @return Result
     */
    public static function refundBonuses(Order $order)
    {
        $result = new Result();

        // Получаем платеж бонусами
        $bonusPayment = self::getBonusPayment($order);

        if (!$bonusPayment) {
            // Бонусы не использовались
            return $result;
        }

        // Проверяем, что бонусы были списаны
        if (!$bonusPayment->isPaid()) {
            // Бонусы еще не списаны, возвращать нечего
            return $result;
        }

        $userId = $order->getUserId();
        $bonusAmount = $bonusPayment->getSum();

        // Возвращаем бонусы пользователю
        $creditResult = self::creditUserBonuses($userId, $bonusAmount, $order->getId());

        if (!$creditResult->isSuccess()) {
            $result->addErrors($creditResult->getErrors());
            return $result;
        }

        // Отмечаем платеж как возвращенный
        $bonusPayment->setField('PAID', 'N');
        $bonusPayment->setField('IS_RETURN', 'Y');
        $bonusPayment->save();

        // Логируем возврат
        self::log('Bonuses refunded', [
            'order_id' => $order->getId(),
            'user_id' => $userId,
            'amount' => $bonusAmount
        ]);

        return $result;
    }

    /**
     * Получение платежа бонусами из заказа
     *
     * @param Order $order Заказ
     * @return Payment|null
     */
    public static function getBonusPayment(Order $order)
    {
        $paymentCollection = $order->getPaymentCollection();

        foreach ($paymentCollection as $payment) {
            if ($payment->getPaymentSystemId() == self::INNER_PAY_SYSTEM_ID) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * Получение основного платежа из заказа
     *
     * @param Order $order Заказ
     * @return Payment|null
     */
    public static function getMainPayment(Order $order)
    {
        $paymentCollection = $order->getPaymentCollection();

        foreach ($paymentCollection as $payment) {
            if ($payment->getPaymentSystemId() != self::INNER_PAY_SYSTEM_ID && !$payment->isInner()) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * Проверка полной оплаты заказа
     *
     * @param Order $order Заказ
     * @return bool
     */
    public static function isFullyPaid(Order $order)
    {
        $paymentCollection = $order->getPaymentCollection();
        $totalPaid = 0;

        foreach ($paymentCollection as $payment) {
            if ($payment->isPaid() && !$payment->isInner()) {
                $totalPaid += $payment->getSum();
            }
        }

        return abs($totalPaid - $order->getPrice()) < 0.01;
    }

    /**
     * Получение баланса бонусов пользователя
     *
     * @param int $userId ID пользователя
     * @return float
     */
    protected static function getUserBonusBalance($userId)
    {
        // Здесь должна быть реальная логика получения бонусов
        // Например, из пользовательского поля или отдельной таблицы

        $user = \CUser::GetByID($userId)->Fetch();

        if ($user && isset($user['UF_BONUS_BALANCE'])) {
            return (float)$user['UF_BONUS_BALANCE'];
        }

        // Или из отдельной таблицы бонусов
        // $bonusBalance = \Bitrix\Main\UserTable::getByPrimary($userId, ['select' => ['BONUS_BALANCE']])->fetch();
        // return (float)($bonusBalance['BONUS_BALANCE'] ?? 0);

        return 0;
    }

    /**
     * Списание бонусов у пользователя
     *
     * @param int $userId ID пользователя
     * @param float $amount Сумма списания
     * @param int $orderId ID заказа
     * @return Result
     */
    protected static function debitUserBonuses($userId, $amount, $orderId)
    {
        $result = new Result();

        // Получаем текущий баланс
        $currentBalance = self::getUserBonusBalance($userId);

        if ($amount > $currentBalance) {
            $result->addError(new Error('Недостаточно бонусов для списания'));
            return $result;
        }

        // Новый баланс
        $newBalance = $currentBalance - $amount;

        // Обновляем баланс пользователя
        $user = new \CUser();
        $updateResult = $user->Update($userId, [
            'UF_BONUS_BALANCE' => $newBalance
        ]);

        if (!$updateResult) {
            $result->addError(new Error('Ошибка обновления баланса бонусов: ' . $user->LAST_ERROR));
            return $result;
        }

        // Записываем историю операции
        self::logBonusOperation($userId, -$amount, $orderId, 'debit', 'Списание за заказ №' . $orderId);

        return $result;
    }

    /**
     * Начисление бонусов пользователю
     *
     * @param int $userId ID пользователя
     * @param float $amount Сумма начисления
     * @param int $orderId ID заказа
     * @return Result
     */
    protected static function creditUserBonuses($userId, $amount, $orderId)
    {
        $result = new Result();

        // Получаем текущий баланс
        $currentBalance = self::getUserBonusBalance($userId);

        // Новый баланс
        $newBalance = $currentBalance + $amount;

        // Обновляем баланс пользователя
        $user = new \CUser();
        $updateResult = $user->Update($userId, [
            'UF_BONUS_BALANCE' => $newBalance
        ]);

        if (!$updateResult) {
            $result->addError(new Error('Ошибка обновления баланса бонусов: ' . $user->LAST_ERROR));
            return $result;
        }

        // Записываем историю операции
        self::logBonusOperation($userId, $amount, $orderId, 'credit', 'Возврат за отмену заказа №' . $orderId);

        return $result;
    }

    /**
     * Логирование операции с бонусами
     *
     * @param int $userId ID пользователя
     * @param float $amount Сумма операции (+ начисление, - списание)
     * @param int $orderId ID заказа
     * @param string $type Тип операции (debit/credit)
     * @param string $description Описание
     */
    protected static function logBonusOperation($userId, $amount, $orderId, $type, $description)
    {
        // Здесь должна быть запись в таблицу истории бонусов
        // Например:
        /*
        \Bitrix\Main\Entity\Query::exec([
            'insert' => [
                'USER_ID' => $userId,
                'AMOUNT' => $amount,
                'ORDER_ID' => $orderId,
                'TYPE' => $type,
                'DESCRIPTION' => $description,
                'DATE_CREATE' => new \Bitrix\Main\Type\DateTime()
            ],
            'table' => 'b_user_bonus_history'
        ]);
        */

        self::log('Bonus operation', [
            'user_id' => $userId,
            'amount' => $amount,
            'order_id' => $orderId,
            'type' => $type,
            'description' => $description
        ]);
    }

    /**
     * Логирование событий
     *
     * @param string $title Заголовок записи
     * @param array $data Данные для логирования
     */
    protected static function log($title, $data = [])
    {
        \Bitrix\Main\Diag\Debug::writeToFile(
            $data,
            $title,
            'combined_payment.log'
        );
    }

    /**
     * Расчет максимальной суммы бонусов для заказа
     * (например, можно оплатить бонусами не более 50% заказа)
     *
     * @param Order $order Заказ
     * @param int $maxPercent Максимальный процент оплаты бонусами
     * @return float
     */
    public static function getMaxBonusAmount(Order $order, $maxPercent = 100)
    {
        $orderPrice = $order->getPrice();
        $maxByPercent = $orderPrice * $maxPercent / 100;

        $userId = $order->getUserId();
        $userBalance = self::getUserBonusBalance($userId);

        return min($maxByPercent, $userBalance);
    }

    /**
     * Получение информации о комбинированной оплате заказа
     *
     * @param Order $order Заказ
     * @return array
     */
    public static function getPaymentInfo(Order $order)
    {
        $bonusPayment = self::getBonusPayment($order);
        $mainPayment = self::getMainPayment($order);

        return [
            'total_amount' => $order->getPrice(),
            'bonus_amount' => $bonusPayment ? $bonusPayment->getSum() : 0,
            'bonus_paid' => $bonusPayment ? $bonusPayment->isPaid() : false,
            'main_amount' => $mainPayment ? $mainPayment->getSum() : 0,
            'main_paid' => $mainPayment ? $mainPayment->isPaid() : false,
            'fully_paid' => self::isFullyPaid($order)
        ];
    }
}
