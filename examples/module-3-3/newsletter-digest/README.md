# Задача 3: Еженедельный дайджест новостей

## Описание задачи

Реализовать систему еженедельной email-рассылки дайджеста новостей подписчикам с возможностью отписки. Агент собирает новости за неделю, формирует красивое HTML-письмо и отправляет всем подписчикам каждое воскресенье.

## Что включено

### 1. `install.php` - Установка типа события и шаблона
- Создает тип почтового события `WEEKLY_DIGEST`
- Создает профессиональный HTML-шаблон дайджеста
- Определяет макросы для новостей и отписки

### 2. `WeeklyDigestAgent.php` - Агент рассылки
- Собирает новости за последние 7 дней из инфоблока
- Получает список подписчиков
- Формирует HTML с новостями, картинками и ссылками
- Отправляет персонализированные письма
- Генерирует уникальные ссылки для отписки

### 3. `unsubscribe_page.php` - Страница отписки
- Обрабатывает запрос на отписку
- Проверяет токен безопасности
- Обновляет статус подписки пользователя

### 4. `test.php` - Тестирование
- Отправляет тестовое письмо с примерами новостей

### 5. `setup_agent.php` - Установка агента
- Регистрирует агент с запуском каждое воскресенье в 10:00

## Установка

### Шаг 1: Создайте пользовательское поле для подписки

**Админка:** Настройки → Настройки продукта → Пользовательские поля → Пользователи

- **Название:** Подписка на новости
- **Символьный код:** `UF_NEWSLETTER_SUBSCRIBE`
- **Тип:** Да/Нет
- **Значение по умолчанию:** Да

### Шаг 2: Установите тип события и шаблон

```bash
php install.php
```

Или откройте в браузере:
```
http://your-site.ru/local/examples/install.php
```

### Шаг 3: Скопируйте агент в проект

```bash
mkdir -p /local/php_interface/agents/
cp WeeklyDigestAgent.php /local/php_interface/agents/
```

### Шаг 4: Настройте ID инфоблока новостей

Откройте `WeeklyDigestAgent.php` и измените константу:

```php
/** @var int ID инфоблока с новостями */
const NEWS_IBLOCK_ID = 1; // ИЗМЕНИТЬ НА СВОЙ
```

### Шаг 5: Настройте автозагрузку в init.php

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

### Шаг 6: Установите агент

Откройте в браузере:
```
http://your-site.ru/local/examples/setup_agent.php
```

### Шаг 7: Создайте страницу отписки

Скопируйте `unsubscribe_page.php` в `/unsubscribe/index.php`

## Как работает

### 1. Агент запускается каждое воскресенье в 10:00

**Путь проверки:** Настройки → Инструменты → Агенты

Агент: `\Local\Agents\WeeklyDigestAgent::sendWeeklyDigest()`

### 2. Сбор новостей

Агент выбирает из инфоблока:
- Активные новости
- Созданные за последние 7 дней
- Максимум 5 новостей (настраивается)
- Сортировка по дате создания (новые сверху)

### 3. Получение подписчиков

Выбираются пользователи, у которых:
- `ACTIVE = Y`
- `UF_NEWSLETTER_SUBSCRIBE = 1`
- Указан email

### 4. Формирование письма

Для каждой новости добавляется:
- Картинка (PREVIEW_PICTURE)
- Категория
- Заголовок
- Дата публикации
- Краткое описание (200 символов)
- Ссылка "Читать полностью"

### 5. Отправка и логирование

Письма добавляются в очередь и отправляются агентом почты. Все действия логируются.

## Настройка

### Изменить количество новостей в дайджесте

В `WeeklyDigestAgent.php`:

```php
/** @var int Максимальное количество новостей в дайджесте */
const MAX_NEWS_COUNT = 10; // Изменить на 10 новостей
```

### Изменить день и время рассылки

При установке агента (или в админке):

```php
// Например, каждую пятницу в 18:00
$nextFriday = new \DateTime('next Friday');
$nextFriday->setTime(18, 0);

\CAgent::AddAgent(
    self::getAgentName(),
    '',
    'N',
    604800, // 7 дней
    '',
    'Y',
    $nextFriday->format('d.m.Y H:i:s'),
    30
);
```

### Изменить дизайн письма

Отредактируйте шаблон в админке:
**Настройки → Настройки продукта → Почтовые шаблоны → WEEKLY_DIGEST**

### Добавить фильтр по категориям новостей

```php
private static function getWeekNews(): array
{
    $newsQuery = ElementTable::getList([
        'filter' => [
            'IBLOCK_ID' => self::NEWS_IBLOCK_ID,
            'ACTIVE' => 'Y',
            '>=DATE_CREATE' => $weekAgo,
            'PROPERTY_CATEGORY' => 'Технологии' // Только определенная категория
        ],
        // ...
    ]);
}
```

### Использовать отдельную таблицу подписчиков

Раскомментируйте и настройте метод `getSubscribersFromCustomTable()`:

```php
private static function getSubscribers(): array
{
    return self::getSubscribersFromCustomTable();
}
```

## Тестирование

### Отправить тестовое письмо

```bash
php test.php
```

Или откройте в браузере:
```
http://your-site.ru/local/examples/test.php
```

### Запустить агент вручную

```php
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Local\Agents\WeeklyDigestAgent;

WeeklyDigestAgent::sendWeeklyDigest();

echo "Дайджест отправлен. Проверьте логи.";
?>
```

### Проверить логи

**Админка:** Настройки → Инструменты → Журнал событий

Фильтр: `AUDIT_TYPE_ID = weekly_digest`

### Тест отписки

1. Получите ссылку отписки из письма
2. Перейдите по ссылке
3. Проверьте, что `UF_NEWSLETTER_SUBSCRIBE` пользователя изменилось на 0

## Расширение функционала

### 1. Персонализация по интересам

```php
// Отправлять только новости по интересам пользователя
$userInterests = explode(',', $user['UF_INTERESTS']); // Например: "Технологии,Маркетинг"

$newsQuery = ElementTable::getList([
    'filter' => [
        'IBLOCK_ID' => self::NEWS_IBLOCK_ID,
        'PROPERTY_CATEGORY' => $userInterests
    ]
]);
```

### 2. Статистика открытий писем

```php
// Добавить пиксель отслеживания в шаблон
<img src="http://#SERVER_NAME#/track.php?user=#USER_ID#&digest=#DIGEST_ID#" width="1" height="1" alt="">

// track.php логирует открытие
```

### 3. Отслеживание кликов

```php
// Оборачивать ссылки через редирект
$trackingUrl = "/click.php?url=" . urlencode($item['URL']) . "&user=" . $userId;

// click.php логирует клик и перенаправляет
```

### 4. Адаптивная частота

```php
// Если пользователь не открывал 3 дайджеста подряд - отправлять раз в месяц
if (self::getUnreadCount($userId) >= 3) {
    // Пропустить этого пользователя
    continue;
}
```

### 5. Интеграция с внешними сервисами

```php
// Отправка через SendGrid/Mailgun для лучшей доставляемости
use Bitrix\Main\Web\HttpClient;

$client = new HttpClient();
$client->post('https://api.sendgrid.com/v3/mail/send', json_encode([
    'personalizations' => [...],
    'from' => [...],
    'content' => [...]
]));
```

### 6. Формат plain text для старых почтовых клиентов

```php
// Создать второй шаблон с BODY_TYPE = 'text'
EventMessageTable::add([
    'EVENT_NAME' => 'WEEKLY_DIGEST',
    'BODY_TYPE' => 'text',
    'MESSAGE' => 'Текстовая версия дайджеста...'
]);
```

## Возможные проблемы

### Агент не запускается в нужное время

**Причина:** Неверная настройка первого запуска

**Решение:**
```php
// Проверить агент в БД
$agent = \CAgent::GetById($agentId)->Fetch();
echo "Следующий запуск: " . $agent['NEXT_EXEC'];

// Изменить время
\CAgent::Update($agentId, [
    'NEXT_EXEC' => \ConvertTimeStamp(strtotime('next Sunday 10:00'), 'FULL')
]);
```

### Новости не находятся

**Причина:** Неверный ID инфоблока или нет новостей за неделю

**Отладка:**
```php
// Проверить ID инфоблока
$iblock = \CIBlock::GetList([], ['CODE' => 'news'])->Fetch();
echo "ID инфоблока: " . $iblock['ID'];

// Проверить новости
$count = \CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 1, '>=DATE_CREATE' => date('d.m.Y', strtotime('-7 days'))],
    []
);
echo "Найдено новостей: " . $count;
```

### Письма попадают в спам

**Решения:**
1. Настроить SPF/DKIM записи для домена
2. Использовать корректный `EMAIL_FROM`
3. Добавить текстовую версию письма
4. Не использовать спам-слова в теме

### Ссылка отписки не работает

**Проверить:**
1. Токен сохраняется: `\COption::GetOptionString('main', 'unsubscribe_token_' . $userId)`
2. Пользовательское поле существует: `UF_NEWSLETTER_SUBSCRIBE`
3. Страница `/unsubscribe/index.php` доступна

## Метрики эффективности

### Open Rate (процент открытий)

```sql
SELECT 
    COUNT(DISTINCT user_id) / (SELECT COUNT(*) FROM b_newsletter_sent) * 100 as open_rate
FROM b_newsletter_opens
WHERE date > DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Click Rate (процент кликов)

```sql
SELECT 
    COUNT(*) / (SELECT COUNT(*) FROM b_newsletter_sent) * 100 as click_rate
FROM b_newsletter_clicks
WHERE date > DATE_SUB(NOW(), INTERVAL 7 DAY);
```

### Unsubscribe Rate (процент отписок)

```php
$totalSent = 1000;
$unsubscribed = 15;
$unsubscribeRate = ($unsubscribed / $totalSent) * 100;
// Норма: < 2%
```

## Выводы

Эта задача демонстрирует:
- ✅ Создание системы email-рассылок
- ✅ Работу с агентами по расписанию
- ✅ Сбор контента из инфоблоков
- ✅ Формирование сложных HTML-писем
- ✅ Персонализацию контента
- ✅ Механизм отписки с токенами
- ✅ Работу с пользовательскими полями
- ✅ Пакетную отправку писем
- ✅ Логирование и отладку рассылок