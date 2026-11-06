# Комбинированная оплата заказа (Бонусы + Платежная система)

## Описание

Реализация комбинированной оплаты заказа в Bitrix:
- Частичная оплата бонусами (баллами)
- Доплата через платежную систему
- Автоматическое списание бонусов после оплаты
- Возврат бонусов при отмене заказа
- Контроль баланса пользователя
- Ограничение максимальной суммы оплаты бонусами

## Структура

```
combined-payment/
├── CombinedPayment.php      # Основной класс для работы с комбинированной оплатой
├── event_handlers.php       # Обработчики событий
├── usage_example.php        # Примеры использования
└── README.md                # Эта инструкция
```

## Установка

### 1. Копирование файлов

Скопируйте файлы в проект:

```
/local/php_interface/classes/Sale/Helpers/CombinedPayment.php
/local/php_interface/include/event_handlers.php
```

### 2. Подключение класса

В файле `/local/php_interface/init.php`:

```php
<?php
// Автозагрузка классов
Bitrix\Main\Loader::registerAutoLoadClasses(
    null,
    [
        'Sale\\Helpers\\CombinedPayment' => 
            '/local/php_interface/classes/Sale/Helpers/CombinedPayment.php'
    ]
);

// Подключение обработчиков событий
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/event_handlers.php';
```

### 3. Создание пользовательского поля для бонусов

В админке Bitrix:

**Настройки → Настройки пользователей → Пользовательские поля**

Создайте поле:
- **Код**: `UF_BONUS_BALANCE`
- **Тип**: Число
- **Название**: Баланс бонусов
- **Значение по умолчанию**: 0

### 4. Создание свойства заказа для хранения использованных бонусов

**Магазин → Настройки → Типы плательщиков → Свойства**

Создайте свойство:
- **Код**: `BONUS_USED`
- **Название**: Использовано бонусов
- **Тип**: Число
- **Обязательное**: Нет

### 5. Настройка платежной системы "Внутренний счет"

В константе `INNER_PAY_SYSTEM_ID` класса `CombinedPayment` укажите ID платежной системы "Внутренний счет" (обычно это 1).

## Использование

### Базовый пример: Создание комбинированной оплаты

```php
use Bitrix\Sale\Order;
use Sale\Helpers\CombinedPayment;

// Загружаем заказ
$order = Order::load($orderId);

// Создаем комбинированную оплату
$bonusAmount = 500;      // Оплата бонусами
$paySystemId = 3;        // ID платежной системы для доплаты

$result = CombinedPayment::create($order, $bonusAmount, $paySystemId);

if ($result->isSuccess()) {
    $data = $result->getData();
    
    echo "Создано 2 платежа:";
    echo "1. Бонусы: " . $data['bonus_amount'] . " руб.";
    echo "2. Доплата: " . $data['remaining_amount'] . " руб.";
    
    // Инициируем оплату основного платежа
    $mainPayment = CombinedPayment::getMainPayment($order);
    $paySystem = \Bitrix\Sale\PaySystem\Manager::getObjectById($paySystemId);
    $initResult = $paySystem->initiatePay($mainPayment);
    
    echo $initResult; // HTML формы оплаты
} else {
    echo "Ошибка: " . implode(', ', $result->getErrorMessages());
}
```

### Полная оплата бонусами

```php
// Заказ на 300 руб., у пользователя 500 бонусов
$order = Order::load($orderId);

$result = CombinedPayment::create(
    $order, 
    300,  // Оплачиваем всю сумму заказа
    3     // paySystemId не используется
);

if ($result->isSuccess()) {
    echo "Заказ полностью оплачен бонусами!";
    
    // Бонусы списываются автоматически
    $bonusPayment = CombinedPayment::getBonusPayment($order);
    
    if ($bonusPayment->isPaid()) {
        echo "Бонусы списаны со счета пользователя";
    }
}
```

### Проверка баланса бонусов

```php
$userId = 123;

// Получить баланс
$balance = CombinedPayment::getUserBonusBalance($userId);

echo "Доступно бонусов: {$balance} руб.";
```

### Расчет максимальной суммы бонусов

```php
$order = Order::load($orderId);

// Не более 50% от суммы заказа
$maxBonusAmount = CombinedPayment::getMaxBonusAmount($order, 50);

echo "Максимум можно использовать: {$maxBonusAmount} руб.";
```

### Получение информации об оплате

```php
$order = Order::load($orderId);

$info = CombinedPayment::getPaymentInfo($order);

echo "Общая сумма: {$info['total_amount']}";
echo "Бонусами: {$info['bonus_amount']}";
echo "Доплата: {$info['main_amount']}";
echo "Полностью оплачен: " . ($info['fully_paid'] ? 'Да' : 'Нет');
```

### Ручное списание бонусов

```php
// Обычно бонусы списываются автоматически через обработчик события
// Но можно сделать это вручную:

$order = Order::load($orderId);
$bonusPayment = CombinedPayment::getBonusPayment($order);

if ($bonusPayment && !$bonusPayment->isPaid()) {
    $result = CombinedPayment::chargeBonuses($order, $bonusPayment);
    
    if ($result->isSuccess()) {
        echo "Бонусы списаны";
    }
}
```

### Возврат бонусов при отмене

```php
// Автоматически через обработчик события OnSaleOrderCanceled
// Или вручную:

$order = Order::load($orderId);

if ($order->isCanceled()) {
    $result = CombinedPayment::refundBonuses($order);
    
    if ($result->isSuccess()) {
        echo "Бонусы возвращены пользователю";
    }
}
```

## Интеграция в форму оформления заказа

### HTML форма

```html
<form id="checkout-form">
    <div class="bonus-section">
        <h3>Оплата бонусами</h3>
        
        <p>Доступно бонусов: <strong id="user-balance">1500</strong> руб.</p>
        
        <label>
            <input 
                type="number" 
                id="bonus-amount" 
                name="bonus_amount" 
                min="0" 
                max="750" 
                step="10" 
                value="0"
            >
            <span>Использовать бонусов</span>
        </label>
        
        <p class="hint">Можно использовать до 50% от суммы заказа</p>
    </div>
    
    <div class="payment-summary">
        <div class="summary-row">
            <span>Итого:</span>
            <strong id="total-price">1500</strong> руб.
        </div>
        
        <div class="summary-row">
            <span>Оплата бонусами:</span>
            <strong id="bonus-price">0</strong> руб.
        </div>
        
        <div class="summary-row total">
            <span>К оплате:</span>
            <strong id="remaining-price">1500</strong> руб.
        </div>
    </div>
    
    <button type="submit" class="btn-primary">Оформить заказ</button>
</form>
```

### JavaScript

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const bonusInput = document.getElementById('bonus-amount');
    const totalPrice = parseFloat(document.getElementById('total-price').textContent);
    const maxBonus = parseFloat(bonusInput.getAttribute('max'));
    
    // Обновление отображения при изменении суммы бонусов
    bonusInput.addEventListener('input', function() {
        let bonusAmount = parseFloat(this.value) || 0;
        
        // Ограничение по максимуму
        if (bonusAmount > maxBonus) {
            bonusAmount = maxBonus;
            this.value = maxBonus;
        }
        
        const remainingAmount = totalPrice - bonusAmount;
        
        document.getElementById('bonus-price').textContent = bonusAmount.toFixed(2);
        document.getElementById('remaining-price').textContent = remainingAmount.toFixed(2);
    });
    
    // Отправка формы
    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const bonusAmount = parseFloat(bonusInput.value) || 0;
        const formData = new FormData(this);
        
        fetch('/ajax/create_combined_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: window.currentOrderId,
                bonus_amount: bonusAmount,
                pay_system_id: 3
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.redirect_url) {
                    // Перенаправляем на оплату
                    window.location.href = data.redirect_url;
                } else {
                    // Заказ полностью оплачен бонусами
                    window.location.href = '/personal/orders/' + window.currentOrderId + '/';
                }
            } else {
                alert('Ошибка: ' + data.errors.join(', '));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при оформлении заказа');
        });
    });
});
```

### Ajax-обработчик

```php
<?php
// /ajax/create_combined_payment.php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Sale\Helpers\CombinedPayment;

Loader::includeModule('sale');

header('Content-Type: application/json');

try {
    // Получаем данные из запроса
    $requestData = json_decode(file_get_contents('php://input'), true);
    
    $orderId = (int)$requestData['order_id'];
    $bonusAmount = (float)$requestData['bonus_amount'];
    $paySystemId = (int)$requestData['pay_system_id'];
    
    // Загружаем заказ
    $order = Order::load($orderId);
    
    if (!$order) {
        throw new Exception('Заказ не найден');
    }
    
    // Создаем комбинированную оплату
    $result = CombinedPayment::create($order, $bonusAmount, $paySystemId);
    
    if (!$result->isSuccess()) {
        throw new Exception(implode(', ', $result->getErrorMessages()));
    }
    
    $data = $result->getData();
    
    $response = [
        'success' => true,
        'data' => $data
    ];
    
    // Если есть доплата - формируем URL оплаты
    if ($data['remaining_amount'] > 0) {
        $mainPayment = CombinedPayment::getMainPayment($order);
        
        if ($mainPayment) {
            $response['redirect_url'] = '/payment/' . $mainPayment->getId() . '/';
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'errors' => [$e->getMessage()]
    ]);
}
```

## Автоматические процессы

### Списание бонусов

Бонусы списываются автоматически после оплаты основного платежа через обработчик события `OnSalePaymentPaid`.

**Процесс:**
1. Пользователь оплачивает основной платеж через платежную систему
2. Срабатывает событие `OnSalePaymentPaid`
3. Автоматически списываются бонусы
4. Заказ отмечается как полностью оплаченный

### Возврат бонусов

Бонусы возвращаются автоматически при отмене заказа через обработчик события `OnSaleOrderCanceled`.

**Процесс:**
1. Заказ отменяется
2. Срабатывает событие `OnSaleOrderCanceled`
3. Автоматически возвращаются бонусы пользователю
4. Записывается история операции

## Логирование

Все операции логируются в файл `/upload/logs/combined_payment.log`:

```
[2024-10-08 12:34:56] Bonuses charged
Array (
    [order_id] => 123
    [user_id] => 456
    [amount] => 500
)

[2024-10-08 12:35:10] Bonus operation
Array (
    [user_id] => 456
    [amount] => -500
    [order_id] => 123
    [type] => debit
    [description] => Списание за заказ №123
)

[2024-10-08 13:20:15] Bonuses refunded
Array (
    [order_id] => 123
    [user_id] => 456
    [amount] => 500
)
```

## Расширение функционала

### История операций с бонусами

Создайте таблицу для хранения истории:

```sql
CREATE TABLE b_user_bonus_history (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    USER_ID INT NOT NULL,
    AMOUNT DECIMAL(18,2) NOT NULL,
    ORDER_ID INT NULL,
    TYPE VARCHAR(50) NOT NULL,
    DESCRIPTION VARCHAR(255),
    DATE_CREATE DATETIME NOT NULL,
    INDEX ix_user (USER_ID),
    INDEX ix_order (ORDER_ID)
);
```

Доработайте метод `logBonusOperation`:

```php
protected static function logBonusOperation($userId, $amount, $orderId, $type, $description)
{
    global $DB;
    
    $DB->Query("
        INSERT INTO b_user_bonus_history 
        (USER_ID, AMOUNT, ORDER_ID, TYPE, DESCRIPTION, DATE_CREATE) 
        VALUES (
            {$userId}, 
            {$amount}, 
            {$orderId}, 
            '{$type}', 
            '{$DB->ForSql($description)}', 
            NOW()
        )
    ");
}
```

### Начисление бонусов за покупку

```php
// В обработчике OnSaleOrderPaid
function handleOrderPaid(\Bitrix\Main\Event $event)
{
    $order = $event->getParameter('ENTITY');
    
    // Начисляем 5% от суммы заказа
    $bonusAmount = $order->getPrice() * 0.05;
    $userId = $order->getUserId();
    
    Sale\Helpers\CombinedPayment::creditUserBonuses(
        $userId, 
        $bonusAmount, 
        $order->getId()
    );
}
```

### Ограничение срока действия бонусов

Добавьте поле `DATE_EXPIRE` в таблицу истории и реализуйте проверку:

```php
public static function getUserBonusBalance($userId)
{
    global $DB;
    
    $result = $DB->Query("
        SELECT SUM(AMOUNT) as BALANCE 
        FROM b_user_bonus_history 
        WHERE USER_ID = {$userId} 
        AND (DATE_EXPIRE IS NULL OR DATE_EXPIRE > NOW())
    ");
    
    $data = $result->Fetch();
    return (float)($data['BALANCE'] ?? 0);
}
```

## Частые ошибки

### 1. "Недостаточно бонусов"

**Причина:** У пользователя недостаточно бонусов на счету

**Решение:**
- Проверьте баланс: `CombinedPayment::getUserBonusBalance($userId)`
- Используйте меньшую сумму бонусов

### 2. "Сумма бонусов превышает стоимость заказа"

**Причина:** Указана сумма бонусов больше стоимости заказа

**Решение:**
- Используйте `getMaxBonusAmount()` для расчета максимума
- Валидируйте ввод на клиенте

### 3. Бонусы не списываются после оплаты

**Причина:** Не подключен обработчик события

**Решение:**
- Проверьте, что `event_handlers.php` подключен в `init.php`
- Проверьте логи в `combined_payment.log`

### 4. Бонусы не возвращаются при отмене

**Причина:** Заказ не был оплачен или обработчик не сработал

**Решение:**
- Проверьте, что бонусы были списаны (`$bonusPayment->isPaid()`)
- Вызовите `refundBonuses()` вручную

## Тестирование

### Тестовый сценарий 1: Частичная оплата

```php
// Создать пользователя с балансом 1000 бонусов
// Создать заказ на 1500 руб.
// Оплатить 500 руб. бонусами + 1000 руб. картой
// Проверить:
// - Создано 2 платежа
// - После оплаты картой списались бонусы
// - Баланс = 500 бонусов
```

### Тестовый сценарий 2: Полная оплата бонусами

```php
// Создать пользователя с балансом 1000 бонусов
// Создать заказ на 500 руб.
// Оплатить 500 руб. бонусами
// Проверить:
// - Бонусы списались сразу
// - Заказ оплачен
// - Баланс = 500 бонусов
```

### Тестовый сценарий 3: Отмена заказа

```php
// Оплатить заказ с использованием бонусов
// Отменить заказ
// Проверить:
// - Бонусы вернулись на счет
// - История операций корректна
```

## Дополнительные ресурсы

- [Документация Bitrix: Платежные системы](https://dev.1c-bitrix.ru/api_d7/bitrix/sale/paysystem/index.php)
- [Работа с заказами](https://dev.1c-bitrix.ru/api_d7/bitrix/sale/order.php)
- [События модуля sale](https://dev.1c-bitrix.ru/api_help/sale/events/index.php)
