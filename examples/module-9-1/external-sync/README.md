# Пример: Внешнее приложение — синхронизация лидов

## Описание

Внешнее приложение на PHP, которое:
1. Подключается к Bitrix24 через OAuth 2.0
2. Получает список всех лидов из CRM
3. Экспортирует их в CSV-файл

## Как работает

1. При первом запуске нужно пройти OAuth-авторизацию (см. `oauth-install.php`)
2. Приложение сохраняет `access_token` и `refresh_token` в `tokens.json`
3. Скрипт `sync.php` использует токен для вызова REST API
4. Загружает все лиды порциями по 50 и сохраняет в CSV
5. Запускается через cron или вручную

## Настройка

### 1. Зарегистрируй приложение

1. Перейди на https://dev.bitrix24.ru/marketplace/
2. Создай приложение:
   - **Тип:** Внешнее
   - **Redirect URI:** `https://your-domain.com/oauth-install.php`
   - **Scope:** `crm`
3. Скопируй `CLIENT_ID` и `CLIENT_SECRET`

### 2. Настрой `sync.php`

Открой `sync.php` и замени:
```php
define('CLIENT_ID', 'local.xxxxx.your_client_id');
define('CLIENT_SECRET', 'your_client_secret');
define('REDIRECT_URI', 'https://your-domain.com/sync.php');
```

### 3. Пройди OAuth-авторизацию

Создай файл `oauth-install.php` (пример ниже) или используй `install.php` из модуля 9.4.

### 4. Запусти синхронизацию

```bash
php sync.php
```

Или добавь в cron:
```bash
*/15 * * * * cd /path/to/app && php sync.php
```

## Что демонстрирует

- ✅ OAuth 2.0 авторизация для внешних приложений
- ✅ Сохранение и обновление `access_token` через `refresh_token`
- ✅ Вызов REST API с сервера (без BX24.js)
- ✅ Пагинация результатов (`start`, `next`)
- ✅ Экспорт данных в CSV

## Для собеседования

Объясни:
- Чем внешнее приложение отличается от встраиваемого
- Как работает OAuth 2.0 flow
- Что такое `access_token` и `refresh_token`
- Как автоматизировать запуск (cron)
