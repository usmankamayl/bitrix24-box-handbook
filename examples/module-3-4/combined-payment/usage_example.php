<?php
/**
 * Пример использования комбинированной оплаты в компоненте оформления заказа
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Sale\Helpers\CombinedPayment;

Loader::includeModule('sale');

// ID заказа
$orderId = 123;

// Загружаем заказ
$order = Order::load($orderId);

if (!$order) {
    die('Заказ не найден');
}

// Получаем пользователя
$userId = $order->getUserId();

// Получаем баланс бонусов пользователя
$userBonusBalance = CombinedPayment::getUserBonusBalance($userId);

echo "Баланс бонусов: {$userBonusBalance} руб.<br>";
echo "Стоимость заказа: {$order->getPrice()} руб.<br><br>";

// Определяем максимальную сумму бонусов для оплаты (например, не более 50%)
$maxBonusAmount = CombinedPayment::getMaxBonusAmount($order, 50);

echo "Максимально можно использовать бонусов: {$maxBonusAmount} руб.<br><br>";

// ============================================
// Сценарий 1: Частичная оплата бонусами
// ============================================

echo "<h3>Сценарий 1: Оплата 500 руб. бонусами + доплата картой</h3>";

$bonusAmount = 500; // Пользователь хочет использовать 500 руб. бонусами
$paySystemId = 3;   // ID платежной системы (например, банковская карта)

$result = CombinedPayment::create($order, $bonusAmount, $paySystemId);

if ($result->isSuccess()) {
    $data = $result->getData();

    echo "✓ Платежи созданы успешно<br>";
    echo "Платеж бонусами: {$data['bonus_amount']} руб. (ID: {$data['bonus_payment_id']})<br>";
    echo "Платеж картой: {$data['remaining_amount']} руб. (ID: {$data['main_payment_id']})<br><br>";

    // Получаем основной платеж для инициации оплаты
    $mainPayment = CombinedPayment::getMainPayment($order);

    if ($mainPayment) {
        // Инициируем оплату через платежную систему
        $paySystem = \Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId);
        $initResult = $paySystem->initiatePay($mainPayment);

        echo "Перенаправление на оплату...<br>";
        echo $initResult; // HTML с формой оплаты или редиректом
    }
} else {
    echo "✗ Ошибка: " . implode(', ', $result->getErrorMessages()) . "<br>";
}

echo "<hr>";

// ============================================
// Сценарий 2: Полная оплата бонусами
// ============================================

echo "<h3>Сценарий 2: Полная оплата бонусами</h3>";

// Создаем новый заказ для примера
$order2 = Order::create(SITE_ID, $userId);
$order2->setPersonTypeId(1);
$order2->setField('CURRENCY', 'RUB');

// Добавляем товар на 300 руб.
$basket = \Bitrix\Sale\Basket::create(SITE_ID);
$item = $basket->createItem('catalog', 1);
$item->setFields([
    'QUANTITY' => 1,
    'CURRENCY' => 'RUB',
    'LID' => SITE_ID,
    'PRICE' => 300,
    'NAME' => 'Тестовый товар'
]);
$order2->setBasket($basket);

$order2->save();

// Оплачиваем полностью бонусами
$bonusAmount = 300;
$paySystemId = 3; // Не важно, т.к. доплаты не будет

$result = CombinedPayment::create($order2, $bonusAmount, $paySystemId);

if ($result->isSuccess()) {
    $data = $result->getData();

    echo "✓ Заказ полностью оплачен бонусами<br>";
    echo "Платеж бонусами: {$data['bonus_amount']} руб.<br>";
    echo "Доплата: {$data['remaining_amount']} руб.<br>";

    // Бонусы уже списаны автоматически
    $bonusPayment = CombinedPayment::getBonusPayment($order2);

    if ($bonusPayment && $bonusPayment->isPaid()) {
        echo "Бонусы списаны. Заказ оплачен!<br>";
    }
} else {
    echo "✗ Ошибка: " . implode(', ', $result->getErrorMessages()) . "<br>";
}

echo "<hr>";

// ============================================
// Сценарий 3: Получение информации об оплате
// ============================================

echo "<h3>Сценарий 3: Проверка статуса оплаты</h3>";

$paymentInfo = CombinedPayment::getPaymentInfo($order);

echo "<pre>";
print_r($paymentInfo);
echo "</pre>";

/*
Вывод:
Array (
    [total_amount] => 1000
    [bonus_amount] => 500
    [bonus_paid] => true
    [main_amount] => 500
    [main_paid] => true
    [fully_paid] => true
)
*/

echo "<hr>";

// ============================================
// Сценарий 4: Отмена заказа и возврат бонусов
// ============================================

echo "<h3>Сценарий 4: Отмена заказа</h3>";

// Отменяем заказ
$order->setField('CANCELED', 'Y');
$order->setField('REASON_CANCELED', 'Отмена по просьбе клиента');

// Бонусы вернутся автоматически через обработчик события
// Но можно вызвать и вручную:
$refundResult = CombinedPayment::refundBonuses($order);

if ($refundResult->isSuccess()) {
    echo "✓ Бонусы возвращены пользователю<br>";
} else {
    echo "✗ Ошибка возврата бонусов: " . implode(', ', $refundResult->getErrorMessages()) . "<br>";
}

echo "<hr>";

// ============================================
// Сценарий 5: Ajax-запрос для расчета доплаты
// ============================================

echo "<h3>Сценарий 5: Ajax-расчет (для формы оформления заказа)</h3>";

// Симуляция Ajax-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bonus_amount'])) {

    $bonusAmount = (float)$_POST['bonus_amount'];
    $orderId = (int)$_POST['order_id'];

    $order = Order::load($orderId);

    if ($order) {
        $orderPrice = $order->getPrice();
        $maxBonusAmount = CombinedPayment::getMaxBonusAmount($order, 50);

        // Валидация
        if ($bonusAmount > $maxBonusAmount) {
            $bonusAmount = $maxBonusAmount;
        }

        $remainingAmount = $orderPrice - $bonusAmount;

        // Возвращаем JSON для фронтенда
        $response = [
            'success' => true,
            'data' => [
                'order_price' => $orderPrice,
                'bonus_amount' => $bonusAmount,
                'remaining_amount' => $remainingAmount,
                'max_bonus_amount' => $maxBonusAmount
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Пример формы
?>

<form id="payment-form">
    <div>
        <label>Оплатить бонусами:</label>
        <input type="number" name="bonus_amount" id="bonus-amount" min="0" max="<?= $maxBonusAmount ?>" step="1" value="0">
        <span>Доступно: <?= $userBonusBalance ?> руб.</span>
    </div>

    <div id="payment-summary">
        <p>К оплате: <span id="total-price"><?= $order->getPrice() ?></span> руб.</p>
        <p>Оплата бонусами: <span id="bonus-price">0</span> руб.</p>
        <p>Доплата картой: <span id="remaining-price"><?= $order->getPrice() ?></span> руб.</p>
    </div>

    <button type="submit">Оформить заказ</button>
</form>

<script>
// Обработка изменения суммы бонусов
document.getElementById('bonus-amount').addEventListener('input', function() {
    const bonusAmount = parseFloat(this.value) || 0;
    const orderPrice = <?= $order->getPrice() ?>;

    // Обновляем отображение
    document.getElementById('bonus-price').textContent = bonusAmount.toFixed(2);
    document.getElementById('remaining-price').textContent = (orderPrice - bonusAmount).toFixed(2);
});

// Отправка формы
document.getElementById('payment-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const bonusAmount = parseFloat(document.getElementById('bonus-amount').value) || 0;

    // Ajax-запрос для создания комбинированной оплаты
    fetch('/ajax/create_combined_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: <?= $orderId ?>,
            bonus_amount: bonusAmount,
            pay_system_id: 3 // ID платежной системы
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Перенаправляем на оплату или показываем сообщение
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                alert('Заказ полностью оплачен бонусами!');
            }
        } else {
            alert('Ошибка: ' + data.errors.join(', '));
        }
    });
});
</script>

<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
?>
