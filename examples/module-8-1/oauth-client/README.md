# Задача 2: OAuth-клиент для Bitrix24 с обновлением токена

## 📋 Описание

Полная реализация **OAuth 2.0 flow** для приложения Bitrix24:

1. **Авторизация** пользователя через Bitrix24
2. **Получение кода** авторизации
3. **Обмен кода на токены** (access_token + refresh_token)
4. **Сохранение токенов** на сервере
5. **Обновление access_token** когда он истекает
6. **Использование токена** для вызова REST API

## 🎯 Что демонстрирует

- ✅ Полный OAuth 2.0 flow (Authorization Code Grant)
- ✅ Обмен кода авторизации на токены
- ✅ Обновление access_token через refresh_token
- ✅ Автоматическое обновление при истечении
- ✅ Безопасное хранение токенов
- ✅ Вызов REST API методов с OAuth токеном
- ✅ Обработка ошибок авторизации

## 📦 Требования

- PHP 7.4+
- Приложение зарегистрировано в Bitrix24.Market
- HTTPS для redirect_uri (обязательно для OAuth)

## 🔧 Настройка

### Шаг 1: Зарегистрируй приложение в Bitrix24.Market

1. Перейди на https://dev.bitrix24.ru/marketplace/
2. Создай новое приложение:
   - **Название:** Твое приложение
   - **Тип:** Внешнее (External)
   - **Redirect URI:** `https://your-domain.com/oauth-client/oauth-example.php?action=callback`
3. Скопируй **CLIENT_ID** и **CLIENT_SECRET**

### Шаг 2: Обнови конфигурацию

Открой файл `oauth-example.php` и замени константы:

```php
define('OAUTH_CLIENT_ID', 'your-app-id-here');
define('OAUTH_CLIENT_SECRET', 'your-app-secret-here');
define('OAUTH_REDIRECT_URI', 'https://your-domain.com/oauth-client/oauth-example.php?action=callback');
```

### Шаг 3: Убедись, что папка пишется

Скрипт сохраняет токены в файл `oauth_tokens.json` в той же папке. Убедись, что папка имеет права на запись:

```bash
chmod 755 /path/to/oauth-client/
```

## 🚀 Использование

### Меню авторизации

```bash
php oauth-example.php
```

Или через браузер:
```
https://your-domain.com/oauth-client/oauth-example.php
```

**Вывод:**
```
=== OAuth 2.0 Авторизация для Bitrix24 ===

❌ Токены не найдены

Старт авторизации:
1. Нажми на ссылку ниже
2. Подтверди доступ приложению
3. Будешь перенаправлен на URL-callback

🔗 Ссылка для авторизации:
https://oauth.bitrix.info/oauth/authorize/?client_id=...&response_type=code&redirect_uri=...
```

### Получение URL авторизации

```bash
php oauth-example.php?action=auth-url
```

### Обработка callback (автоматически)

После того, как пользователь подтвердит доступ, Bitrix24 перенаправит на:
```
https://your-domain.com/oauth-client/oauth-example.php?action=callback&code=ABC123...
```

Скрипт автоматически:
1. ✅ Получит код авторизации из URL
2. ✅ Обменяет код на токены
3. ✅ Сохранит токены в файл

**Вывод:**
```
🔄 Обмениваю код на токены...

✅ Авторизация успешна!
Сохраненная информация:
Array
(
    [domain] => my-company.bitrix24.ru
    [user_id] => 123
    [scope] => crm task user disk
)
```

### Тест REST API

```bash
php oauth-example.php?action=test-api
```

Получит список лидов используя OAuth токен:

```
=== Тест REST API через OAuth ===

✅ Использую токен для портала: my-company.bitrix24.ru

📋 Получаю список лидов...
✅ Получено 5 лидов:

  ID: 123 | Заявка на консультацию | Email: ivan@example.com | Статус: NEW
  ID: 124 | Обратный звонок | Email: maria@example.com | Статус: PROCESSING
  ...
```

### Удаление токенов (logout)

```bash
php oauth-example.php?action=logout
```

## 🔍 Как работает OAuth 2.0

### Шаг 1: Authorization Request

Пользователь переходит по ссылке:
```
https://oauth.bitrix.info/oauth/authorize/
  ?client_id=APP_ID
  &response_type=code
  &redirect_uri=https://example.com/callback
  &scope=crm+task+user
```

### Шаг 2: User Authorization

Bitrix24 показывает форму подтверждения прав:
```
Приложение "Моя система" запрашивает доступ к:
- CRM (лиды, сделки, контакты)
- Задачи
- Пользователи

[Разрешить]  [Отменить]
```

### Шаг 3: Authorization Code

Bitrix24 перенаправляет на:
```
https://example.com/callback?code=ABC123XYZ789
```

### Шаг 4: Token Exchange

Приложение обменивает код на токены:

```
POST https://oauth.bitrix.info/oauth/token/
  grant_type=authorization_code
  &client_id=APP_ID
  &client_secret=APP_SECRET
  &code=ABC123XYZ789
  &redirect_uri=https://example.com/callback
```

**Ответ:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1...",
  "refresh_token": "abc123xyz789...",
  "expires_in": 3600,
  "scope": "crm task user disk",
  "domain": "my-company.bitrix24.ru",
  "member_id": "123"
}
```

### Шаг 5: API Call

Теперь можешь вызывать REST API:

```
GET https://my-company.bitrix24.ru/rest/crm.lead.list.json
Headers: Authorization: Bearer {access_token}
```

### Шаг 6: Token Refresh

Когда access_token истекает (через 1 час):

```
POST https://oauth.bitrix.info/oauth/token/
  grant_type=refresh_token
  &client_id=APP_ID
  &client_secret=APP_SECRET
  &refresh_token=abc123xyz789
```

**Ответ:**
```json
{
  "access_token": "NEW_TOKEN_HERE",
  "refresh_token": "NEW_REFRESH_TOKEN",
  "expires_in": 3600
}
```

## 💡 Примеры использования

### Пример 1: Проверка авторизации

```php
$oauth = new Bitrix24OAuth(CLIENT_ID, CLIENT_SECRET, REDIRECT_URI);
$storage = new TokenStorage(TOKENS_FILE);

if ($storage->load()) {
    echo "✅ Пользователь авторизован";
} else {
    echo "❌ Требуется авторизация";
    echo "Ссылка: " . $oauth->getAuthorizationUrl(['crm']);
}
```

### Пример 2: Автоматическое обновление токена

```php
$storage = new TokenStorage(TOKENS_FILE);

// Этот метод автоматически обновляет токен если нужно
$accessToken = $storage->getValidAccessToken($oauth);

// Используем токен для API запроса
$result = $oauth->callApi($accessToken, $domain, 'crm.lead.list', [
    'limit' => 10,
]);
```

### Пример 3: Обработка ошибки INVALID_TOKEN

```php
try {
    $result = $oauth->callApi($accessToken, $domain, 'crm.lead.list');
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'INVALID_TOKEN') !== false) {
        // Обновляем токен и повторяем запрос
        $newToken = $storage->getValidAccessToken($oauth);
        $result = $oauth->callApi($newToken, $domain, 'crm.lead.list');
    }
}
```

## ⚠️ Важные моменты

### 1. Redirect URI должен быть HTTPS

```
❌ Неправильно:
http://your-domain.com/oauth-client/oauth-example.php

✅ Правильно:
https://your-domain.com/oauth-client/oauth-example.php
```

### 2. ACCESS_TOKEN живет 1 час

Не забывай обновлять через refresh_token:

```php
// Старый способ (неправильно):
$token = $tokens['access_token'];  // Может быть просроченным!

// Правильный способ:
$token = $storage->getValidAccessToken($oauth);  // Обновляет если нужно
```

### 3. REFRESH_TOKEN живет бесконечно

Но может быть отозван если:
- Пользователь удалит приложение
- Администратор отозовет доступ
- Слишком долго не использовался (зависит от политики портала)

### 4. Не теряй REFRESH_TOKEN

Сохраняй его надежно:

```php
// ❌ Неправильно: сохранять в сессию
$_SESSION['refresh_token'] = $tokens['refresh_token'];

// ✅ Правильно: сохранять в БД или защищенный файл
$db->save('oauth_tokens', $tokens);
```

### 5. Ограничение scope

Запрашивай ТОЛЬКО необходимые права:

```php
// ❌ Запрашиваешь все:
$oauth->getAuthorizationUrl(['crm', 'task', 'user', 'calendar', 'disk', 'telephony', 'im'])

// ✅ Запрашиваешь нужные:
$oauth->getAuthorizationUrl(['crm', 'task'])  // Если работаешь только с лидами и задачами
```

## 🚀 Расширение

### Как сохранить токены в БД?

```php
class DatabaseTokenStorage
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(string $userId, array $tokens): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE oauth_tokens SET
                access_token = :access,
                refresh_token = :refresh,
                expires_at = :expires,
                domain = :domain
            WHERE user_id = :user_id
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':access' => $tokens['access_token'],
            ':refresh' => $tokens['refresh_token'],
            ':expires' => time() + ($tokens['expires_in'] ?? 3600),
            ':domain' => $tokens['domain'],
        ]);
    }

    public function load(string $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
```

### Как использовать в Laravel?

```php
// app/Services/Bitrix24Service.php
class Bitrix24Service
{
    public function getAuthorizationUrl()
    {
        $oauth = new Bitrix24OAuth(
            config('bitrix24.client_id'),
            config('bitrix24.client_secret'),
            config('bitrix24.redirect_uri')
        );
        
        return $oauth->getAuthorizationUrl(['crm', 'task']);
    }

    public function handleCallback(string $code)
    {
        $oauth = new Bitrix24OAuth(...);
        $tokens = $oauth->getTokensByCode($code);
        
        // Сохраняем в БД
        auth()->user()->oauth_tokens()->update($tokens);
        
        return true;
    }
}
```

## 📚 Полезные ссылки

- [OAuth 2.0 в Bitrix24](https://dev.1c-bitrix.ru/rest_help/oauth/app_authentication.php)
- [Регистрация приложения](https://dev.bitrix24.ru/marketplace/)
- [Список методов REST API](https://dev.1c-bitrix.ru/rest_help/index.php)
- [OAuth 2.0 спецификация](https://tools.ietf.org/html/rfc6749)

## 🎓 Что ты узнал

После выполнения этой задачи ты понимаешь:

1. ✅ Как работает OAuth 2.0 Authorization Code Grant flow
2. ✅ Как получить код авторизации
3. ✅ Как обменять код на access_token и refresh_token
4. ✅ Как обновить access_token когда он истекает
5. ✅ Как безопасно хранить токены
6. ✅ Как использовать OAuth токен для вызова REST API
7. ✅ Как обрабатывать ошибки авторизации
