# Задача 2: Напоминание о брошенной корзине

## Описание задачи

Реализовать автоматическую систему отправки email-напоминаний пользователям, которые добавили товары в корзину, но не завершили заказ в течение 24 часов. В письме предлагается промокод со скидкой для мотивации завершить покупку.

## Что включено

### 1. `install.php` - Установка типа события и шаблона
- Создает тип почтового события `ABANDONED_CART_REMINDER`
- Создает красивый HTML-шаблон с таймером и промокодом
- Определяет все необходимые макросы для корзины

### 2. `AbandonedCartAgent.php` - Агент проверки корзин
- Проверяет корзины, неактивные 24+ часов
- Получает данные пользователя и содержимое корзины
- Генерирует персональный промокод
- Отправляет email-напоминание
- Отслеживает отправленные письма (не дублирует)

### 3. `setup_agent.php` - Установка агента
- Регистрирует агент в системе
- Настраивает запуск каждый час

### 4. `test.php` - Тестирование
- Отправляет тестовое письмо
- Проверяет работу шаблона

## Установка

### Шаг 1: Установите тип события и шаблон

```bash
php install.php
```

Или откройте в браузере:
```
http://your-site.ru/local/examples/install.php
```

### Шаг 2: Скопируйте агент в проект

```bash
# Создайте директорию
mkdir -p /local/php_interface/agents/

# Скопируйте файл
cp AbandonedCartAgent.php /local/php_interface/agents/
```

### Шаг 3: Настройте автозагрузку в init.php

Добавьте в `/local/php_interface/init.php`:

```php
<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'Local\\Agents\\') === 0) {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/agents/'
            . str_replace('\\', '/', substr($class, 13)) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
```

### Шаг 4: Установите агент

Откройте в браузере:
```
http://your-site.ru/local/examples/setup_agent.php
```

Или выполните:
```php
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
use Local\Agents\AbandonedCartAgent;
AbandonedCartAgent::install();
?>
```

## Как работает

### 1. Агент запускается каждый час

**Путь проверки:** Настройки → Инструменты → Агенты

Агент: `\Local\Agents\AbandonedCartAgent::checkAbandonedCarts()`

### 2. Проверка брошенных корзин

Агент ищет корзины, которые:
- Не обновлялись **24 часа**
- Содержат хотя бы 1 товар
- Пользователю еще **не отправлялось** напоминание (или прошло 7+ дней)

### 3. Отправка письма

Для каждой найденной корзины:
1. Получаются данные пользователя и товары
2. Генерируется уникальный промокод со скидкой 10%
3. Формируется HTML со списком товаров
4. Отправляется email
5. Отмечается, что письмо отправлено

### 4. Содержимое письма

- Персональное обращение к пользователю
- Таймер: "Товары зарезервированы еще на 24 часа"
- Список товаров из корзины с ценами
- Общая сумма корзины
- Промокод со скидкой 10%
- Кнопка возврата к корзине

## Настройка

### Изменить время до отправки напоминания

В `AbandonedCartAgent.php`:

```php
/** @var int Через сколько часов отправлять напоминание */
const HOURS_BEFORE_REMINDER = 48; // Изменить на 48 часов
```

### Изменить размер скидки

```php
/** @var int Размер скидки по промокоду (%) */
const PROMO_DISCOUNT = 15; // Изменить на 15%
```

### Изменить частоту запуска агента

В `setup_agent.php` или через админку:

```php
\CAgent::AddAgent(
    self::getAgentName(),
    '',
    'N',
    7200, // Каждые 2 часа (вместо 3600)
    '',
    'Y'
);
```

### Настроить генерацию промокода

В методе `generatePromoCode()`:

```php
private static function generatePromoCode(int $userId): string
{
    // Свой формат промокода
    $code = 'SAVE15-' . strtoupper(substr(md5($userId . time()), 0, 8));
    
    // Создать скидку в модуле sale
    // ...
    
    return $code;
}
```

## Тестирование

### Отправить тестовое письмо

```bash
# Замените email в test.php на свой
php test.php
```

Или откройте в браузере:
```
http://your-site.ru/local/examples/test.php
```

### Проверить работу агента вручную

```php
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Local\Agents\AbandonedCartAgent;

// Запустить агент вручную
AbandonedCartAgent::checkAbandonedCarts();

echo "Агент выполнен. Проверьте логи и очередь писем.";
?>
```

### Проверить логи

**Админка:** Настройки → Инструменты → Журнал событий

Фильтр: `AUDIT_TYPE_ID = abandoned_cart`

Или в коде:
```php
$logs = CEventLog::GetList(
    ['asc' => 'ID'],
    ['AUDIT_TYPE_ID' => 'abandoned_cart']
);

while ($log = $logs->Fetch()) {
    echo $log['DESCRIPTION'] . "<br>";
}
```

### Проверить очередь писем

**Админка:** Настройки → Инструменты → Почтовые события

## Расширение функционала

### 1. Каскадные напоминания

Отправлять несколько писем с разными интервалами:

```php
// Первое напоминание - через 24 часа (скидка 5%)
// Второе напоминание - через 48 часов (скидка 10%)
// Третье напоминание - через 72 часа (скидка 15%)

private static function getReminderStage(int $fuserId): int
{
    $option = \COption::GetOptionString('sale', 'abandoned_cart_stage_' . $fuserId, '0');
    return (int)$option;
}

private static function getDiscountByStage(int $stage): int
{
    $discounts = [1 => 5, 2 => 10, 3 => 15];
    return $discounts[$stage] ?? 5;
}
```

### 2. Персонализация на основе товаров

```php
// Разные шаблоны для разных категорий товаров
if ($basket['CATEGORY'] === 'electronics') {
    $eventName = 'ABANDONED_CART_ELECTRONICS';
} else {
    $eventName = 'ABANDONED_CART_REMINDER';
}
```

### 3. Интеграция с CRM

```php
// Создать лид/сделку в CRM при брошенной корзине
if (Loader::includeModule('crm')) {
    $lead = new \CCrmLead();
    $lead->Add([
        'TITLE' => 'Брошенная корзина: ' . $user['NAME'],
        'NAME' => $user['NAME'],
        'EMAIL' => [['VALUE' => $user['EMAIL'], 'VALUE_TYPE' => 'WORK']],
        'OPPORTUNITY' => $basket['TOTAL'],
        'CURRENCY_ID' => 'RUB'
    ]);
}
```

### 4. A/B тестирование промокодов

```php
// Разные размеры скидок для тестирования конверсии
$discounts = [5, 10, 15];
$randomDiscount = $discounts[array_rand($discounts)];

// Логировать какой вариант был использован
\COption::SetOptionString(
    'sale',
    'ab_test_' . $fuserId,
    json_encode(['discount' => $randomDiscount, 'date' => time()])
);
```

### 5. Уведомления в мессенджеры

```php
// Отправить также в Telegram/WhatsApp
if ($user['PERSONAL_PHONE']) {
    self::sendTelegramNotification($user['PERSONAL_PHONE'], $basket);
}
```

## Возможные проблемы

### Агент не запускается

**Причины:**
- Агент не установлен
- Агент отключен в админке
- Cron не настроен (для production)

**Решение:**
```php
// Проверить наличие агента
$agent = \CAgent::GetList(
    [],
    ['NAME' => '%AbandonedCartAgent%']
)->Fetch();

if (!$agent) {
    echo "Агент не найден. Установите через setup_agent.php";
} elseif ($agent['ACTIVE'] !== 'Y') {
    echo "Агент отключен. Включите в админке.";
}
```

### Письма не отправляются

**Проверить:**
1. Тип события создан
2. Шаблон активен
3. Агент отправки почты работает
4. Email пользователя валиден

```php
// Проверить шаблон
$template = \Bitrix\Main\Mail\Internal\EventMessageTable::getList([
    'filter' => [
        'EVENT_NAME' => 'ABANDONED_CART_REMINDER',
        'ACTIVE' => 'Y'
    ]
])->fetch();

if (!$template) {
    echo "Шаблон не найден или неактивен";
}
```

### Корзины не находятся

**Причины:**
- Пользователь не авторизован (корзина без USER_ID)
- Корзина обновлялась недавно
- Уже отправлялось напоминание

**Отладка:**
```php
// Проверить корзины в БД
$baskets = \Bitrix\Sale\Basket::getList([
    'filter' => ['>QUANTITY' => 0],
    'select' => ['FUSER_ID', 'DATE_UPDATE', 'QUANTITY']
]);

while ($basket = $baskets->fetch()) {
    print_r($basket);
}
```

### Дублирование писем

**Решение:** Убедитесь, что метка отправки сохраняется:

```php
// После успешной отправки
self::markReminderSent($cart['FUSER_ID']);

// Проверить метки
$sent = \COption::GetOptionString('sale', 'abandoned_cart_sent_' . $fuserId);
echo "Последняя отправка: " . $sent;
```

## Метрики эффективности

### Отслеживание конверсии

```php
// Сохранить промокод с меткой источника
$promoData = [
    'code' => $promoCode,
    'user_id' => $userId,
    'sent_at' => time(),
    'used' => false
];

\COption::SetOptionString(
    'sale',
    'promo_' . $promoCode,
    json_encode($promoData)
);

// При использовании промокода отметить
// и рассчитать конверсию
```

### Статистика по отправкам

```sql
-- Количество отправленных напоминаний за период
SELECT COUNT(*) 
FROM b_event_log 
WHERE AUDIT_TYPE_ID = 'abandoned_cart' 
  AND TIMESTAMP_X > DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## Выводы

Эта задача демонстрирует:
- ✅ Работу с корзинами через Sale API
- ✅ Создание и настройку агентов
- ✅ Отправку писем с динамическим контентом
- ✅ Генерацию и использование промокодов
- ✅ Персонализацию email-рассылок
- ✅ Отслеживание отправленных писем
- ✅ Форматирование сложных HTML-писем
- ✅ Логирование и отладку