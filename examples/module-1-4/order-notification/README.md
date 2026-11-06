# Задача 3: Уведомления администратора о крупных заказах

## Описание

Автоматическая отправка email-уведомления администратору при создании заказа на сумму более 100 000 рублей. Письмо содержит полную информацию о заказе, покупателе и составе корзины.

## Что делает решение

- Отслеживает создание новых заказов
- Проверяет сумму заказа
- Отправляет уведомление если сумма превышает порог
- Включает информацию о покупателе и составе заказа
- Форматирует красивое HTML-письмо
- Логирует отправку уведомлений

## Структура файлов

```
order-notification/
├── init.php          # Основной файл с обработчиками событий
├── mail_template.php # Почтовый шаблон (опционально)
└── README.md         # Эта инструкция
```

## Установка

### Шаг 1: Скопируйте файлы

Скопируйте папку `order-notification` в `/local/examples/`:

```
/local/examples/order-notification/
```

### Шаг 2: Настройте параметры

Откройте файл `init.php` и измените константы:

```php
// Порог суммы заказа (в рублях)
const THRESHOLD_AMOUNT = 100000;

// Email администратора
const ADMIN_EMAIL = 'admin@example.com';
```

### Шаг 3: Подключите обработчики

Добавьте в файл `/local/php_interface/init.php`:

```php
<?php
// Подключаем уведомления о крупных заказах
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/order-notification/init.php';
```

### Шаг 4: Создайте почтовое событие (опционально)

Для более гибкой настройки писем создайте почтовое событие в админке:

**Настройки → Настройки продукта → Почтовые события**

**Тип события:** `LARGE_ORDER_NOTIFICATION`

**Шаблон:**
```
Тема: [#SITE_NAME#] Крупный заказ ##ORDER_ID# на сумму #ORDER_PRICE#

Сообщение:
Создан крупный заказ!

Номер заказа: #ORDER_ID#
Сумма: #ORDER_PRICE#
Дата: #DATE_INSERT#

Покупатель:
Имя: #CUSTOMER_NAME#
Email: #CUSTOMER_EMAIL#
Телефон: #CUSTOMER_PHONE#

Состав заказа:
#BASKET_HTML#

Ссылка на заказ: #ORDER_URL#
```

## Использование

После установки уведомления отправляются автоматически:

### Пример работы

```php
use Bitrix\Sale;

// Создаём новый заказ
$order = Sale\Order::create(SITE_ID, 1); // userId = 1

// Добавляем товары в корзину на сумму > 100 000
$basket = Sale\Basket::loadItemsForFUser(
    Sale\Fuser::getId(),
    SITE_ID
);

// Устанавливаем корзину
$order->setBasket($basket);

// Сохраняем заказ
$result = $order->save();

// → Автоматически отправится email администратору
```

### Пример письма

```
Тема: [Мой магазин] Крупный заказ #1234 на сумму 150 000 руб.

⚠️ Создан крупный заказ

Номер заказа:    #1234
Сумма заказа:    150 000 руб.
Дата создания:   2024-01-15 14:30:25
Покупатель:      Иванов Иван Иванович
Email:           ivanov@example.com
Телефон:         +7 (999) 123-45-67

Состав заказа:
┌────────────────────────────┬─────────┬────────────┬──────────────┐
│ Товар                      │ Кол-во  │ Цена       │ Сумма        │
├────────────────────────────┼─────────┼────────────┼──────────────┤
│ iPhone 15 Pro Max 256GB    │ 2       │ 120 000 р. │ 120 000 руб. │
│ AirPods Pro 2nd Gen        │ 1       │ 30 000 р.  │ 30 000 руб.  │
└────────────────────────────┴─────────┴────────────┴──────────────┘

[Открыть заказ в админке]
```

## Какие события используются

### OnSaleOrderSaved
**Модуль:** sale  
**Когда срабатывает:** При сохранении заказа (создание или обновление)  
**Параметры:** 
- `ENTITY` — объект заказа (Order)
- `IS_NEW` — флаг нового заказа

## Настройка

### Изменение порога суммы

В файле `init.php`:

```php
const THRESHOLD_AMOUNT = 50000; // 50 000 рублей вместо 100 000
```

### Изменение email получателя

```php
const ADMIN_EMAIL = 'manager@example.com';
```

Или настроить в админке: **Настройки → Настройки продукта → Email для уведомлений**

### Уведомления для разных порогов

Модифицируйте метод `onOrderSaved()`:

```php
public static function onOrderSaved(\Bitrix\Main\Event $event)
{
    $order = $event->getParameter('ENTITY');
    
    if (!$order->isNew()) {
        return;
    }
    
    $orderPrice = $order->getPrice();
    
    // Разные уровни уведомлений
    if ($orderPrice >= 500000) {
        self::sendNotification($order, 'CRITICAL'); // Директору
    } elseif ($orderPrice >= 100000) {
        self::sendNotification($order, 'HIGH'); // Менеджеру
    } elseif ($orderPrice >= 50000) {
        self::sendNotification($order, 'MEDIUM'); // Отделу продаж
    }
}
```

### Добавление Telegram-уведомлений

```php
private static function sendTelegramNotification($arFields)
{
    $botToken = 'YOUR_BOT_TOKEN';
    $chatId = 'YOUR_CHAT_ID';
    
    $message = sprintf(
        "🔔 Новый крупный заказ!\n\n" .
        "Заказ: #%d\n" .
        "Сумма: %s\n" .
        "Покупатель: %s\n" .
        "Телефон: %s",
        $arFields['ORDER_ID'],
        $arFields['ORDER_PRICE'],
        $arFields['CUSTOMER_NAME'],
        $arFields['CUSTOMER_PHONE']
    );
    
    file_get_contents(sprintf(
        'https://api.telegram.org/bot%s/sendMessage?chat_id=%s&text=%s',
        $botToken,
        $chatId,
        urlencode($message)
    ));
}
```

И вызовите в `sendNotification()`:

```php
self::sendSimpleEmail($arFields);
self::sendTelegramNotification($arFields); // Добавить эту строку
```

## Проверка работы

### 1. Тестовый заказ через админку

1. Откройте **Магазин → Заказы → Добавить заказ**
2. Создайте заказ на сумму > 100 000 руб.
3. Сохраните заказ
4. Проверьте почту администратора

### 2. Тестовый заказ через API

```php
\Bitrix\Main\Loader::includeModule('sale');
\Bitrix\Main\Loader::includeModule('catalog');

// Создаём корзину
$basket = \Bitrix\Sale\Basket::create(SITE_ID);

// Добавляем товар
$item = $basket->createItem('catalog', 123); // productId = 123
$item->setFields([
    'QUANTITY' => 2,
    'CURRENCY' => 'RUB',
    'LID' => SITE_ID,
    'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\Basket\CatalogProvider',
    'NAME' => 'Тестовый товар',
    'PRICE' => 60000, // 60 000 × 2 = 120 000 руб.
]);

// Создаём заказ
$order = \Bitrix\Sale\Order::create(SITE_ID, 1);
$order->setPersonTypeId(1);
$order->setBasket($basket);

// Сохраняем (здесь отправится уведомление)
$result = $order->save();

if ($result->isSuccess()) {
    echo "Заказ #{$order->getId()} создан, уведомление отправлено";
} else {
    print_r($result->getErrorMessages());
}
```

### 3. Проверка логов

Проверьте файл логов:

```bash
tail -f /bitrix/modules/main/log/default.log
```

Должна быть запись:
```
Large order notification sent: Order #1234, Amount: 150 000 руб., Customer: Иванов Иван
```

## Типичные проблемы

### Уведомления не отправляются

**Причина 1:** Файл не подключён в init.php  
**Решение:** Проверьте `require_once` в `/local/php_interface/init.php`

**Причина 2:** Сумма заказа меньше порога  
**Решение:** Проверьте константу `THRESHOLD_AMOUNT`

**Причина 3:** Заказ обновляется, а не создаётся  
**Решение:** Проверка `$order->isNew()` пропускает обновления

**Причина 4:** Модуль sale не загружен  
**Решение:** Убедитесь, что `Loader::includeModule('sale')` выполняется

### Письма не приходят

**Причина 1:** Неправильный email  
**Решение:** Проверьте константу `ADMIN_EMAIL` или настройки `email_from`

**Причина 2:** Почтовый сервер не настроен  
**Решение:** Настройте отправку почты в Bitrix или используйте SMTP

**Причина 3:** Письма попадают в спам  
**Решение:** Настройте SPF/DKIM/DMARC для домена

### В письме неправильные данные

**Причина:** Свойства заказа имеют другие коды  
**Решение:** Проверьте коды свойств в `foreach ($propertyCollection)`:

```php
// Ваши коды могут отличаться
if ($code === 'EMAIL') { // Может быть 'ORDER_EMAIL'
if ($code === 'PHONE') { // Может быть 'PERSONAL_PHONE'
if ($code === 'FIO')   { // Может быть 'FULL_NAME'
```

## Расширение функциональности

### SMS-уведомления

```php
private static function sendSMS($phone, $message)
{
    // Через SMS.RU
    file_get_contents(sprintf(
        'https://sms.ru/sms/send?api_id=YOUR_API_ID&to=%s&msg=%s',
        $phone,
        urlencode($message)
    ));
}
```

### Slack-уведомления

```php
private static function sendSlackNotification($arFields)
{
    $webhookUrl = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    $data = [
        'text' => sprintf(
            ':bell: Новый крупный заказ #%d на сумму *%s*',
            $arFields['ORDER_ID'],
            $arFields['ORDER_PRICE']
        ),
        'attachments' => [
            [
                'fields' => [
                    ['title' => 'Покупатель', 'value' => $arFields['CUSTOMER_NAME'], 'short' => true],
                    ['title' => 'Телефон', 'value' => $arFields['CUSTOMER_PHONE'], 'short' => true],
                ]
            ]
        ]
    ];
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}
```

### Push-уведомления в мобильное приложение

```php
private static function sendPushNotification($arFields)
{
    // Через OneSignal, Firebase Cloud Messaging и т.д.
}
```

## Обучающие моменты

### 1. Событие OnSaleOrderSaved

Современное событие D7 для работы с заказами. Принимает объект `Order`.

### 2. Проверка нового заказа

```php
if (!$order->isNew()) {
    return; // Пропускаем обновления
}
```

Важно, чтобы уведомление отправлялось только при создании, а не при каждом обновлении статуса.

### 3. Работа с объектом Order

```php
$order->getPrice();           // Сумма заказа
$order->getCurrency();        // Валюта
$order->getUserId();          // ID пользователя
$order->getPropertyCollection(); // Свойства заказа
$order->getBasket();          // Корзина
```

### 4. Получение свойств заказа

Свойства (email, телефон, адрес) хранятся в коллекции:

```php
foreach ($propertyCollection as $property) {
    $code = $property->getField('CODE');
    $value = $property->getValue();
}
```

### 5. Отправка почты в Bitrix

Два способа:
- Через почтовые события: `Event::send()`
- Напрямую через `mail()`

## Вопросы для самопроверки

1. Почему проверяется `$order->isNew()`?
2. Как получить email покупателя из заказа?
3. Можно ли отправлять уведомления при изменении статуса?
4. Зачем нужна функция `\CurrencyFormat()`?
5. Как добавить несколько получателей уведомлений?

## Полезные ссылки

- [События модуля sale](https://dev.1c-bitrix.ru/api_d7/bitrix/sale/events.php)
- [Работа с заказами D7](https://dev.1c-bitrix.ru/api_d7/bitrix/sale/order/index.php)
- [Почтовые события](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2935)
- [CEvent::Send()](https://dev.1c-bitrix.ru/api_help/main/reference/cevent/send.php)
