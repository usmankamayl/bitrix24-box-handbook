# Задача 1: Welcome-письмо при регистрации

## Описание задачи

Реализовать систему автоматической отправки персонализированного приветственного письма пользователю сразу после регистрации на сайте с начислением бонусных баллов.

## Что включено

### 1. `install.php` - Установка типа события и шаблона
- Создает тип почтового события `USER_WELCOME`
- Создает HTML-шаблон письма с красивым дизайном
- Определяет все необходимые макросы

### 2. `UserRegistrationHandler.php` - Обработчик события
- Подключается к событию `OnAfterUserAdd`
- Получает данные нового пользователя
- Рассчитывает бонусные баллы
- Отправляет welcome-письмо
- Логирует результат отправки

### 3. `init_example.php` - Пример регистрации в init.php
- Показывает, как подключить обработчик
- Настраивает автозагрузку классов

## Установка

### Шаг 1: Запустите установку типа события и шаблона

```bash
php install.php
```

Или откройте файл в браузере:
```
http://your-site.ru/local/examples/install.php
```

### Шаг 2: Скопируйте обработчик в проект

```bash
# Создайте директорию
mkdir -p /local/php_interface/handlers/

# Скопируйте файл
cp UserRegistrationHandler.php /local/php_interface/handlers/
```

### Шаг 3: Добавьте регистрацию в init.php

Добавьте код из `init_example.php` в `/local/php_interface/init.php`

## Функциональность

### Автоматическая отправка письма

При регистрации нового пользователя:
1. Срабатывает событие `OnAfterUserAdd`
2. Обработчик получает данные пользователя
3. Рассчитываются бонусные баллы:
   - **100 баллов** - базовая награда
   - **+50 баллов** - если указано имя
   - **+50 баллов** - если указан телефон
   - **+100 баллов** - если регистрация с 10:00 до 12:00 (утренняя акция)
4. Отправляется красивое HTML-письмо

### Содержимое письма

- Персональное приветствие с именем
- Информация о бонусных баллах
- Данные для входа (логин, email)
- Кнопка перехода в личный кабинет
- Подсказки для дальнейших действий

## Тестирование

### Отправить тестовое письмо

```php
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Local\Handlers\UserRegistrationHandler;

// Отправить тестовое письмо на свой email
UserRegistrationHandler::sendTestEmail('your-email@example.com');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
?>
```

### Проверить очередь писем

**Админка:** Настройки → Инструменты → Почтовые события

### Просмотреть логи

```php
<?php
// В любом месте сайта
$logs = CEventLog::GetList([
    'asc' => 'ID'
], [
    'AUDIT_TYPE_ID' => 'user_registration'
]);

while ($log = $logs->Fetch()) {
    echo $log['DESCRIPTION'] . "<br>";
}
?>
```

## Настройка

### Изменить количество бонусных баллов

Отредактируйте метод `calculateBonusPoints()` в `UserRegistrationHandler.php`:

```php
private static function calculateBonusPoints(array $user): int
{
    $points = 200; // Изменить базовое значение

    // Добавить свои условия
    if (!empty($user['PERSONAL_CITY'])) {
        $points += 30; // За указание города
    }

    return $points;
}
```

### Изменить дизайн письма

Отредактируйте шаблон в админке:
**Настройки → Настройки продукта → Почтовые шаблоны → USER_WELCOME**

Или измените HTML в `install.php` и переустановите.

### Добавить вложение

```php
public static function onAfterUserAdd(&$fields)
{
    // ... существующий код ...

    Event::send([
        'EVENT_NAME' => 'USER_WELCOME',
        'LID' => $fields['LID'] ?? SITE_ID,
        'C_FIELDS' => $emailFields,
        'FILE' => [
            [
                'name' => 'welcome-guide.pdf',
                'path' => $_SERVER['DOCUMENT_ROOT'] . '/upload/docs/welcome-guide.pdf',
                'type' => 'application/pdf'
            ]
        ]
    ]);
}
```

## Расширение функционала

### 1. Двойное подтверждение email

```php
// В обработчике перед отправкой welcome-письма
if (!$user['CONFIRM_CODE']) {
    // Сначала отправить письмо с подтверждением
    Event::send([
        'EVENT_NAME' => 'USER_CONFIRM_EMAIL',
        'LID' => SITE_ID,
        'C_FIELDS' => [
            'USER_EMAIL' => $user['EMAIL'],
            'CONFIRM_CODE' => $user['CONFIRM_CODE']
        ]
    ]);
} else {
    // Затем welcome-письмо
    self::sendWelcomeEmail($user);
}
```

### 2. Отложенная отправка через 1 час

```php
// Создать агент для отложенной отправки
\CAgent::AddAgent(
    "Local\\Handlers\\UserRegistrationHandler::sendDelayedWelcome({$user['ID']});",
    '',
    'N',
    3600, // Через 1 час
    '',
    'Y',
    \ConvertTimeStamp(time() + 3600, 'FULL')
);
```

### 3. A/B тестирование шаблонов

```php
// Случайный выбор шаблона
$templates = [123, 124]; // ID шаблонов
$randomTemplate = $templates[array_rand($templates)];

Event::send([
    'EVENT_NAME' => 'USER_WELCOME',
    'LID' => SITE_ID,
    'C_FIELDS' => $emailFields,
    'MESSAGE_ID' => $randomTemplate // Указать конкретный шаблон
]);
```

## Возможные проблемы

### Письмо не отправляется

**Проверить:**
1. Тип события создан: `EventTypeTable::getList(['filter' => ['EVENT_NAME' => 'USER_WELCOME']])`
2. Шаблон активен и привязан к сайту
3. Агент отправки работает: проверить `b_agent` таблицу
4. Email пользователя валиден

### Макросы не подставляются

**Решение:** Проверить точное совпадение имен в `C_FIELDS` и в шаблоне (`#USER_NAME#`)

### Письма дублируются

**Решение:** Проверить, не зарегистрирован ли обработчик несколько раз в init.php

## Полезные команды

```php
// Проверить, зарегистрирован ли обработчик
$handlers = \Bitrix\Main\EventManager::getInstance()->getHandlers('main', 'OnAfterUserAdd');
print_r($handlers);

// Вручную отправить письмо для пользователя
Event::send([
    'EVENT_NAME' => 'USER_WELCOME',
    'LID' => 's1',
    'C_FIELDS' => [
        'USER_EMAIL' => 'test@example.com',
        'USER_NAME' => 'Test'
        // ... остальные поля
    ]
]);

// Проверить очередь писем
$events = \Bitrix\Main\Mail\Internal\EventTable::getList([
    'filter' => ['EVENT_NAME' => 'USER_WELCOME']
]);
while ($event = $events->fetch()) {
    print_r($event);
}
```

## Выводы

Эта задача демонстрирует:
- ✅ Создание типа почтового события
- ✅ Создание HTML-шаблона письма
- ✅ Подключение к событию OnAfterUserAdd
- ✅ Отправку email через Event::send()
- ✅ Работу с макросами
- ✅ Логирование результатов
- ✅ Персонализацию контента
- ✅ Расчет бонусов по условиям