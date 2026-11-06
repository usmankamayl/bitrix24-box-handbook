# Задача 1: Получение списка лидов через Webhook

## 📋 Описание

Простой PHP-скрипт, который подключается к Bitrix24 через **входящий webhook** и получает список лидов с помощью REST API.

## 🎯 Что демонстрирует

- ✅ Подключение к Bitrix24 через входящий webhook
- ✅ Вызов методов REST API (`crm.lead.list`, `crm.lead.get`, `crm.lead.add`)
- ✅ Работа с пагинацией (получение всех лидов постранично)
- ✅ Фильтрация и выборка полей
- ✅ Обработка ошибок API
- ✅ Подсчет статистики по лидам

## 📦 Требования

- PHP 7.4+
- Включенная функция `file_get_contents` (или cURL)
- Входящий webhook Bitrix24 с правами на чтение CRM

## 🔧 Настройка

### Шаг 1: Создай входящий webhook в Bitrix24

1. Зайди в свой портал Bitrix24
2. Перейди: **Приложения** → **Вебхуки** → **Добавить входящий вебхук**
3. Выбери права доступа:
   - ☑️ **CRM** (для чтения лидов)
   - Можно добавить права на запись, если хочешь тестировать создание лидов
4. Скопируй полученный URL вида:
   ```
   https://your-portal.bitrix24.ru/rest/1/abc123xyz789/
   ```

### Шаг 2: Укажи URL webhook в коде

Открой файл `index.php` и замени константу:

```php
define('WEBHOOK_URL', 'https://your-portal.bitrix24.ru/rest/1/abc123xyz789/');
```

### Шаг 3: Запусти скрипт

```bash
php index.php
```

## 💡 Примеры использования

### Пример 1: Получить все лиды

```php
$allLeads = getAllLeads();
echo "Всего лидов: " . count($allLeads);
```

**Результат:**
```
Всего лидов: 42
```

### Пример 2: Получить лиды по фильтру

```php
// Только лиды со статусом "Новый"
$newLeads = getAllLeads(['STATUS_ID' => 'NEW']);

// Лиды с email
$leadsWithEmail = getAllLeads(['!EMAIL' => '']);

// Лиды, созданные за последние 7 дней
$recentLeads = getAllLeads(['>DATE_CREATE' => date('Y-m-d', strtotime('-7 days'))]);
```

### Пример 3: Получить конкретные поля

```php
// Только ID и название
$leadsMinimal = getAllLeads([], ['ID', 'TITLE']);

// ID, название, телефон, email
$leadsWithContacts = getAllLeads([], ['ID', 'TITLE', 'PHONE', 'EMAIL']);
```

### Пример 4: Получить детали одного лида

```php
$leadDetail = getLeadDetail(123);
echo json_encode($leadDetail, JSON_PRETTY_PRINT);
```

**Результат:**
```json
{
  "ID": "123",
  "TITLE": "Заявка с сайта",
  "NAME": "Иван",
  "PHONE": "+7 (900) 123-45-67",
  "EMAIL": "ivan@example.com",
  "STATUS_ID": "NEW",
  "SOURCE_ID": "WEB",
  "DATE_CREATE": "2024-01-15T10:30:00+03:00"
}
```

### Пример 5: Создать новый лид

```php
$newLeadId = createLead([
    'TITLE' => 'Заявка на консультацию',
    'NAME' => 'Мария',
    'PHONE' => '+7 (900) 555-55-55',
    'EMAIL' => 'maria@example.com',
    'SOURCE_ID' => 'CALL',
]);
echo "Лид создан! ID: " . $newLeadId;
```

## 🔍 Что делает скрипт

### Функция `callBitrix24()`

Базовая функция для вызова любого метода REST API:

```php
$response = callBitrix24('crm.lead.list', [
    'filter' => ['STATUS_ID' => 'NEW'],
    'select' => ['ID', 'TITLE', 'EMAIL'],
]);
```

### Функция `getAllLeads()`

Получает **все** лиды с автоматической пагинацией:

- Bitrix24 возвращает максимум 50 записей за раз
- Функция автоматически делает несколько запросов до получения всех лидов
- Поддерживает фильтрацию и выборку полей

### Функция `getLeadDetail()`

Получает полную информацию об одном лиде (включая все поля, даже пользовательские).

### Функция `createLead()`

Создает новый лид (требует прав на запись в webhook).

## ⚠️ Важные моменты

### 1. Права доступа

Если webhook имеет права только на **чтение**, то метод `createLead()` вернет ошибку:

```
⚠️ Не удалось создать лид: ACCESS_DENIED
```

**Решение:** Пересоздай webhook с правами на запись.

### 2. Пагинация

Bitrix24 возвращает максимум 50 записей за раз. Функция `getAllLeads()` автоматически обрабатывает это:

```php
// Получить все лиды (даже если их 1000)
$allLeads = getAllLeads();
```

### 3. Лимиты запросов

Для входящих webhook'ов в облачной версии Bitrix24 действует лимит:
- **2 запроса в секунду**

Если превысить лимит, получишь ошибку `QUERY_LIMIT_EXCEEDED`.

**Решение:** Добавь задержки между запросами или используй batch-запросы (в следующих модулях).

### 4. Формат ответа

Все методы возвращают данные в формате:

```json
{
  "result": [ /* данные */ ],
  "total": 42,
  "more": true,
  "next": 50
}
```

- `result` — массив данных
- `total` — общее количество (если есть)
- `more` — есть ли еще данные (для пагинации)
- `next` — смещение для следующего запроса

## 🚀 Расширение

### Как добавить свой метод?

Используй функцию `callBitrix24()`:

```php
// Получить список сделок
$deals = callBitrix24('crm.deal.list', [
    'filter' => ['STAGE_ID' => 'NEW'],
    'select' => ['ID', 'TITLE', 'OPPORTUNITY'],
]);

// Получить список контактов
$contacts = callBitrix24('crm.contact.list', [
    'filter' => ['>DATE_CREATE' => '2024-01-01'],
]);

// Получить информацию о текущем пользователе
$currentUser = callBitrix24('user.current');
```

### Как добавить обработку ошибок?

```php
try {
    $leads = getAllLeads();
} catch (Exception $e) {
    // Логируем ошибку
    error_log('Bitrix24 API Error: ' . $e->getMessage());
    
    // Отправляем уведомление администратору
    mail('admin@example.com', 'API Error', $e->getMessage());
}
```

## 📚 Полезные методы REST API

| Метод | Описание |
|-------|----------|
| `crm.lead.list` | Список лидов |
| `crm.lead.get` | Детали лида |
| `crm.lead.add` | Создать лид |
| `crm.lead.update` | Обновить лид |
| `crm.lead.delete` | Удалить лид |
| `crm.lead.fields` | Список полей лида |

**Полный список методов:** https://dev.1c-bitrix.ru/rest_help/crm/leads/index.php

## 🎓 Что ты узнал

После выполнения этой задачи ты понимаешь:

1. ✅ Как создать входящий webhook в Bitrix24
2. ✅ Как вызывать методы REST API через webhook
3. ✅ Как работает пагинация в Bitrix24 API
4. ✅ Как фильтровать и выбирать конкретные поля
5. ✅ Как обрабатывать ошибки API
6. ✅ Основные методы для работы с лидами

## 🔗 Ссылки

- [Официальная документация Bitrix24 REST API](https://dev.1c-bitrix.ru/rest_help/)
- [Работа с вебхуками](https://dev.1c-bitrix.ru/rest_help/general/webhooks.php)
- [Методы для работы с лидами](https://dev.1c-bitrix.ru/rest_help/crm/leads/index.php)
