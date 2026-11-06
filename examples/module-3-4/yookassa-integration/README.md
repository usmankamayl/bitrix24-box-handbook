# Интеграция платежной системы ЮKassa

## Описание

Полноценный обработчик платежной системы ЮKassa (бывшая Яндекс.Касса) для Bitrix с поддержкой:
- Создания платежей через API v3
- Обработки webhook уведомлений
- Возврата платежей (refund)
- Различных методов оплаты (карты, ЮMoney, QIWI, Сбербанк Онлайн)
- Тестового и боевого режимов

## Структура

```
yookassa-integration/
├── .description.php           # Описание и параметры платежной системы
├── handler.php                # Основной обработчик
├── lang/ru/.description.php   # Языковые константы
└── README.md                  # Эта инструкция
```

## Установка

### 1. Копирование файлов

Скопируйте папку `yookassa-integration` в директорию обработчиков платежных систем:

```
/bitrix/php_interface/include/sale_payment/yookassa/
```

### 2. Регистрация обработчика

Создайте файл `/bitrix/php_interface/include/sale_payment/yookassa/.description.php`:

```php
<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

include(__DIR__ . '/.description.php');

return $data;
```

### 3. Добавление в список платежных систем

В админке Bitrix:
1. Перейдите в **Магазин → Настройки → Платежные системы**
2. Нажмите **Добавить платежную систему**
3. Выберите **ЮKassa**
4. Заполните параметры

## Настройка

### Получение учетных данных ЮKassa

1. Зарегистрируйтесь в [личном кабинете ЮKassa](https://yookassa.ru/)
2. Создайте магазин
3. Получите:
   - **shopId** - идентификатор магазина
   - **Секретный ключ** - в разделе "Настройки → Ключи API"

### Параметры платежной системы

| Параметр | Описание | Пример |
|----------|----------|--------|
| ID магазина (shopId) | Идентификатор из ЛК ЮKassa | `123456` |
| Секретный ключ | Ключ для API | `live_A1b2C3d4E5...` |
| Режим работы | Тест/Боевой | `LIVE` |
| Автоматическое списание | Списывать сразу после авторизации | `Y` |
| Метод оплаты | Способ оплаты по умолчанию | `bank_card` |

### Настройка webhook

В личном кабинете ЮKassa:
1. Перейдите в **Настройки → Уведомления**
2. Укажите URL для уведомлений:
   ```
   https://ваш-сайт.ru/bitrix/tools/sale_ps_result.php
   ```
3. Выберите события:
   - `payment.succeeded` - успешная оплата
   - `payment.canceled` - отмена платежа

## Использование

### Создание заказа с оплатой через ЮKassa

```php
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem\Manager;

// Создаем заказ
$order = Order::create(SITE_ID, $userId);
$order->setPersonTypeId(1);
$order->setField('CURRENCY', 'RUB');

// Добавляем товары в корзину
$basket = $order->getBasket();
// ... добавление товаров

// Создаем платеж
$paymentCollection = $order->getPaymentCollection();
$payment = $paymentCollection->createItem(
    Manager::getObjectById($paySystemId) // ID платежной системы ЮKassa
);

$payment->setField('SUM', $order->getPrice());
$payment->setField('CURRENCY', $order->getCurrency());

// Сохраняем заказ
$result = $order->save();

if ($result->isSuccess()) {
    // Получаем ID платежа
    $paymentId = $payment->getId();
    
    // Инициируем оплату (редирект на ЮKassa)
    $service = Manager::getObjectById($paySystemId);
    $initResult = $service->initiatePay($payment, null, \Bitrix\Sale\PaySystem\ServiceHandler::STRING);
    
    echo $initResult; // HTML с редиректом
}
```

### Проверка статуса платежа

```php
use Bitrix\Sale\Payment;

$payment = Payment::getByPrimary($paymentId)->fetchObject();

if ($payment) {
    if ($payment->isPaid()) {
        echo "Платеж оплачен";
    } else {
        echo "Ожидает оплаты";
        echo "Статус: " . $payment->getField('PS_STATUS_DESCRIPTION');
    }
}
```

### Возврат платежа

```php
use Bitrix\Sale\PaySystem\Manager;

$payment = Payment::getByPrimary($paymentId)->fetchObject();
$service = Manager::getObjectById($payment->getPaymentSystemId());

if ($service->isRefundable()) {
    // Полный возврат
    $result = $service->refund($payment);
    
    // Частичный возврат (500 рублей)
    $result = $service->refund($payment, 500);
    
    if ($result->isSuccess()) {
        echo "Возврат выполнен успешно";
    } else {
        echo "Ошибка: " . implode(', ', $result->getErrorMessages());
    }
}
```

## Обработка callback

Обработчик автоматически обрабатывает уведомления от ЮKassa через webhook.

### Что происходит при получении уведомления:

1. Проверяется тип события (`payment.succeeded`, `payment.canceled`)
2. Извлекается ID платежа из метаданных
3. Проверяется сумма платежа
4. Обновляется статус платежа в Bitrix
5. Записывается лог в `payment_yookassa.log`

### Логи

Все операции логируются в файл `/upload/logs/payment_yookassa.log`:

```php
// Посмотреть последние логи
tail -f /path/to/bitrix/upload/logs/payment_yookassa.log
```

Структура лога:
```
[2024-10-08 12:34:56] Payment created
Array (
    [payment_id] => 123
    [yookassa_id] => 2a6a3b7c-000f-5000-8000-18db351245c7
    [amount] => 1500.00
)

[2024-10-08 12:35:10] Webhook received
Array (
    [payment_id] => 123
    [body] => {"type":"notification","event":"payment.succeeded",...}
)
```

## Тестирование

### Тестовый режим

В настройках платежной системы установите **Режим работы = Тестовый**.

### Тестовые карты ЮKassa

| Номер карты | Результат |
|-------------|-----------|
| `5555 5555 5555 4444` | Успешная оплата |
| `5555 5555 5555 5599` | Отклонена (недостаточно средств) |

**Реквизиты для всех карт:**
- Срок действия: любая дата в будущем (например, `12/25`)
- CVV: любые 3 цифры (например, `123`)

### Пример теста

```php
// Создаем тестовый платеж
$payment = Payment::create($paymentCollection, $yookassaPaySystemId);
$payment->setField('SUM', 100);
$payment->setField('CURRENCY', 'RUB');

$service = Manager::getObjectById($yookassaPaySystemId);
$initResult = $service->initiatePay($payment);

// initResult содержит HTML с редиректом на тестовую страницу оплаты
```

## Безопасность

### Важные моменты

1. **Секретный ключ** - храните в безопасности, не коммитьте в git
2. **Webhook** - обработчик автоматически проверяет подлинность запроса
3. **Сумма платежа** - всегда проверяется соответствие суммы в уведомлении

### Рекомендации

```php
// Храните секретный ключ в .env или settings.php
// НЕ храните в коде!

// В .env:
// YOOKASSA_SECRET_KEY=live_A1b2C3d4E5...

// В обработчике:
$secretKey = getenv('YOOKASSA_SECRET_KEY');
```

## Частые ошибки

### 1. Не приходит webhook

**Причины:**
- URL webhook неправильно настроен в ЛК ЮKassa
- Сервер не доступен из интернета (localhost, firewall)
- Ошибка в коде обработчика webhook

**Решение:**
- Проверьте URL в настройках ЮKassa
- Убедитесь, что URL доступен извне: `curl https://ваш-сайт.ru/bitrix/tools/sale_ps_result.php`
- Проверьте логи в `/upload/logs/payment_yookassa.log`

### 2. Ошибка "Unauthorized"

**Причина:** Неверный shopId или секретный ключ

**Решение:** Проверьте учетные данные в настройках платежной системы

### 3. Платеж создается, но не меняется статус

**Причина:** Webhook не настроен или не срабатывает

**Решение:** 
- Настройте webhook в ЛК ЮKassa
- Проверьте, что на сервере нет блокировок входящих запросов

## Доработка

### Добавление поддержки рассрочки

```php
// В handler.php, метод createPayment()
$paymentData['confirmation'] = [
    'type' => 'embedded'
];

$paymentData['payment_method_data'] = [
    'type' => 'installments'
];
```

### Добавление сохранения карты

```php
// Для сохранения карты для повторных платежей
$paymentData['save_payment_method'] = true;
```

### Изменение URL возврата

```php
// В handler.php, метод getReturnUrl()
protected function getReturnUrl(Payment $payment)
{
    return 'https://ваш-сайт.ru/order/success/?order_id=' . $payment->getOrderId();
}
```

## Дополнительные ресурсы

- [Документация ЮKassa API](https://yookassa.ru/developers/api)
- [Личный кабинет ЮKassa](https://yookassa.ru/)
- [Тестовые данные](https://yookassa.ru/developers/payment-acceptance/testing-and-going-live/testing)

## Поддержка

При возникновении проблем:
1. Проверьте логи в `/upload/logs/payment_yookassa.log`
2. Проверьте настройки webhook в ЛК ЮKassa
3. Убедитесь, что учетные данные верны
4. Проверьте, что модуль `sale` подключен
