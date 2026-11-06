# Задача 2: Агент отправки email-рассылки

## 📝 Описание задачи

Создать агент для еженедельной email-рассылки подписчикам с новостями за неделю.

## 🎯 Что демонстрирует этот пример

- Работа с пользователями через ORM (UserTable)
- Отправка почтовых событий
- Пакетная обработка данных с лимитами
- Работа с инфоблоками (получение новостей)
- Контроль количества отправленных писем
- Форматирование контента для email
- Обработка ошибок отправки

## 📂 Файлы

- `agent.php` - класс агента с логикой рассылки
- `email_template.php` - создание почтового шаблона
- `install.php` - регистрация агента в системе
- `README.md` - описание и инструкции (этот файл)

## 🚀 Установка

### Шаг 1: Создать пользовательское поле для подписки

Перейдите в **Настройки → Настройки продукта → Пользовательские поля → Пользователи**

Создайте поле:
- **Код:** `UF_NEWSLETTER_SUBSCRIBED`
- **Тип:** Да/Нет (boolean)
- **Название:** "Подписка на рассылку"
- **Множественное:** Нет

Или через код:

```php
$oUserTypeEntity = new CUserTypeEntity();
$oUserTypeEntity->Add([
    'ENTITY_ID' => 'USER',
    'FIELD_NAME' => 'UF_NEWSLETTER_SUBSCRIBED',
    'USER_TYPE_ID' => 'boolean',
    'SORT' => 500,
    'MULTIPLE' => 'N',
    'MANDATORY' => 'N',
    'EDIT_FORM_LABEL' => ['ru' => 'Подписка на рассылку', 'en' => 'Newsletter subscription'],
]);
```

### Шаг 2: Создать почтовое событие

Откройте в браузере: `http://ваш-сайт.ru/путь/к/email-newsletter/email_template.php`

Это создаст:
- Тип события `WEEKLY_NEWSLETTER`
- HTML-шаблон письма

### Шаг 3: Зарегистрировать агент

Откройте в браузере: `http://ваш-сайт.ru/путь/к/email-newsletter/install.php`

Агент будет добавлен с запуском каждый понедельник в 10:00.

### Альтернатива: регистрация вручную

```php
use Bitrix\Main\Agent;
use Bitrix\Main\Type\DateTime;

require_once __DIR__ . '/agent.php';

$nextMonday = new DateTime();
// Вычислить следующий понедельник
$dayOfWeek = (int)$nextMonday->format('N');
$daysToAdd = $dayOfWeek >= 1 ? (8 - $dayOfWeek) % 7 : 1;
if ($daysToAdd === 0) $daysToAdd = 7;
$nextMonday->add($daysToAdd . ' days');
$nextMonday->setTime(10, 0, 0);

Agent::add([
    'NAME' => '\\MyCompany\\Agents\\EmailNewsletterAgent::execute();',
    'ACTIVE' => 'Y',
    'AGENT_INTERVAL' => 604800,  // 7 дней
    'IS_PERIOD' => 'Y',
    'NEXT_EXEC' => $nextMonday,
]);
```

## ⚙️ Настройка параметров

В файле `agent.php` можно изменить:

```php
// Максимум писем за один запуск (защита от перегрузки)
const MAX_EMAILS_PER_RUN = 50;

// ID почтового шаблона
const EMAIL_TEMPLATE = "WEEKLY_NEWSLETTER";
```

В методе `getNewsletterContent()`:

```php
'IBLOCK_ID' => 1,  // ID вашего инфоблока новостей
'nTopCount' => 5,  // Количество новостей в рассылке
```

## 📊 Логирование

### Файлы логов:
- `/bitrix/logs/newsletter_agent.log` - успешные операции
- `/bitrix/logs/newsletter_agent_errors.log` - ошибки

### Журнал событий:
**Настройки → Журнал событий**

Тип: `NEWSLETTER_AGENT`

## ✅ Проверка работы

### Проверить статус агента:

```php
use Bitrix\Main\Agent;

$agent = Agent::getList([
    'filter' => ['NAME' => '%EmailNewsletterAgent%']
])->fetch();

echo "Следующий запуск: " . $agent['NEXT_EXEC'];
```

### Запустить агент вручную для тестирования:

```php
\MyCompany\Agents\EmailNewsletterAgent::execute();
```

### Проверить отправленные письма:

**Настройки → Инструменты → Почтовые события → Событие "WEEKLY_NEWSLETTER"**

## 🔍 Как это работает

1. **Агент запускается** каждый понедельник в 10:00
2. **Получает список подписчиков** с активным флагом `UF_NEWSLETTER_SUBSCRIBED`
3. **Выбирает новости** за последние 7 дней (топ-5)
4. **Отправляет письма пакетами** по 50 за раз
5. **Логирует результаты** (количество отправленных, ошибки)
6. **Сохраняет дату** последней рассылки

## 💡 Ключевые моменты кода

### Работа с пользователями через D7

```php
use Bitrix\Main\UserTable;

$result = UserTable::getList([
    'filter' => [
        'ACTIVE' => 'Y',
        '=UF_NEWSLETTER_SUBSCRIBED' => 1,
    ],
    'select' => ['ID', 'EMAIL', 'NAME', 'LAST_NAME']
]);
```

### Контроль лимита отправки

```php
const MAX_EMAILS_PER_RUN = 50;

foreach ($subscribers as $subscriber) {
    if ($sentCount >= self::MAX_EMAILS_PER_RUN) {
        break; // Остальные отправим при следующем запуске
    }
    // Отправка письма
    $sentCount++;
}
```

### Отправка через почтовые события

```php
use Bitrix\Main\Mail\Event;

Event::send([
    'EVENT_NAME' => 'WEEKLY_NEWSLETTER',
    'LID' => SITE_ID,
    'C_FIELDS' => [
        'EMAIL' => $subscriber['EMAIL'],
        'USER_NAME' => $subscriber['NAME'],
        'NEWS_LIST' => $formattedNews,
    ],
]);
```

### Задержка между письмами

```php
usleep(100000); // 0.1 секунды между письмами
```

## 🎓 Что можно доработать

1. **Персонализация:** учет интересов пользователя при выборе новостей
2. **A/B тестирование:** разные варианты писем для групп пользователей
3. **Отписка:** добавить ссылку для отписки от рассылки
4. **Статистика:** отслеживание открытий и кликов
5. **Таргетинг:** отправка в зависимости от часового пояса пользователя
6. **Дайджест:** настраиваемая частота (ежедневно, еженедельно, ежемесячно)
7. **Приоритеты:** отправка VIP-клиентам в первую очередь

## 🔧 Настройка для работы через cron

Для гарантированной доставки по расписанию:

**1. Создайте `/bitrix/php_interface/cron_agents.php`:**

```php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/path/to/site";
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('BX_CRONTAB', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
CAgent::CheckAgents();
```

**2. Добавьте в crontab:**

```bash
# Каждый понедельник в 10:00
0 10 * * 1 /usr/bin/php /path/to/site/bitrix/php_interface/cron_agents.php
```

**3. Обновите агент:**

```php
Agent::update($agentId, ['IS_CRON' => 'Y']);
```

## 📚 Связанные темы

- Работа с пользователями: `Bitrix\Main\UserTable`
- Почтовые события: `CEventType`, `CEventMessage`, `Event::send()`
- Работа с инфоблоками: `CIBlockElement::GetList()`
- Пользовательские поля: `CUserTypeEntity`

## 🐛 Решение проблем

### Письма не отправляются

- Проверьте настройки почты в Bitrix: **Настройки → Системные настройки → Настройки почты**
- Убедитесь, что почтовое событие создано
- Проверьте логи: `/bitrix/logs/newsletter_agent_errors.log`

### Подписчики не находятся

- Убедитесь, что поле `UF_NEWSLETTER_SUBSCRIBED` создано
- Проверьте, что у пользователей установлен флаг подписки
- Проверьте код поля в настройках

### Новости не загружаются

- Проверьте ID инфоблока в методе `getNewsletterContent()`
- Убедитесь, что есть активные новости за последнюю неделю
- Проверьте права доступа к инфоблоку

### Агент выполняется слишком долго

- Уменьшите `MAX_EMAILS_PER_RUN`
- Убедитесь, что агент настроен на работу через cron
- Проверьте производительность почтового сервера

## 💼 Применение на практике

Этот пример показывает реальный кейс, который часто встречается в проектах:
- Еженедельные дайджесты для блога
- Уведомления о новых товарах в интернет-магазине
- Персональные рекомендации
- Новостные рассылки

На собеседовании могут спросить:
- Как защититься от перегрузки почтового сервера? (лимиты + задержки)
- Как обрабатывать ошибки отправки? (try-catch + логирование)
- Как персонализировать рассылку? (фильтрация по интересам пользователя)
