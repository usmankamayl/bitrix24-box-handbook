# Модуль 13.1: Создание полноценного приложения для маркетплейса

## Теория

Этот модуль — не про азы OAuth (это уже в [8.1](module-8-1.md) и [9.4](module-9-4.md)), а про то, как Market-приложение устроено **внутри**, когда его ставят не 1 портал, а 500 или 5000. Именно это спрашивают на собеседованиях уровня мидл+/синьор: «как вы храните токены», «что будет, если два запроса одновременно рефрешат токен», «как не словить бан по рейт-лимиту».

### Чем Market-приложение принципиально отличается от локального

Локальное приложение (см. 9.4) живёт на **одном** портале, токен можно хранить хоть в файле. Market-приложение — это SaaS: один и тот же код обслуживает множество независимых порталов одновременно. Отсюда вытекают все архитектурные требования:

- данные **обязательно** партиционируются по порталу (по `member_id`, не по домену — домен может смениться);
- токены хранятся в БД, а не в сессии/файле — запрос от Bitrix24 может прийти в любой момент, без участия пользователя (например, событие или крон);
- установка/удаление — это события жизненного цикла арендатора (tenant), а не разовый скрипт.

### Схема хранения токенов (multi-tenant)

```sql
CREATE TABLE app_installations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(64) NOT NULL UNIQUE,   -- постоянный ID портала, не меняется
    domain VARCHAR(255) NOT NULL,             -- может смениться, хранить отдельно
    access_token VARCHAR(255) NOT NULL,
    refresh_token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    scope VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended', 'uninstalled') DEFAULT 'active',
    installed_at DATETIME NOT NULL,
    uninstalled_at DATETIME NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE UNIQUE INDEX idx_member_id ON app_installations (member_id);
```

**Почему `member_id`, а не `domain`** — классический вопрос на собеседовании. `member_id` — неизменный идентификатор портала в рамках Bitrix24, он не меняется при смене домена (например, при переходе с тестового на боевой домен или ребрендинге компании). Если ключевать по домену, после смены домена вы потеряете связь с установленным приложением и получите «дубликат» арендатора в БД.

### Жизненный цикл установки: `ONAPPINSTALL`

При установке Market-приложения Bitrix24 присылает обработчику событие `ONAPPINSTALL` (POST-запрос), а не просто редиректит с `code`, как в локальном приложении. Это важно: регистрировать обработчик нужно заранее через `event.bind` либо (для стандартного REST-приложения) он приходит автоматически на `install.php`.

```php
// install.php — точка входа, которую Bitrix24 вызывает при установке
<?php
require_once __DIR__ . '/config.php';

$code   = $_REQUEST['code']   ?? null;   // для первой установки
$domain = $_REQUEST['DOMAIN'] ?? $_REQUEST['domain'] ?? null;
$event  = $_REQUEST['event']  ?? null;   // ONAPPINSTALL при повторной установке/обновлении прав

if (!$code && !$event) {
    http_response_code(400);
    die('Missing authorization code');
}

$tokens = exchangeCodeForTokens($code, $domain);

saveInstallation(
    memberId: $tokens['member_id'],
    domain: $domain,
    accessToken: $tokens['access_token'],
    refreshToken: $tokens['refresh_token'],
    expiresIn: $tokens['expires_in'],
    scope: $tokens['scope']
);

// Обязательно: ответ в формате, который ожидает iframe Bitrix24 при установке
echo "<script>
    if (window.parent && window.parent.BX24) {
        window.parent.BX24.init(function() {
            window.parent.BX24.installFinish();
        });
    }
</script>";
```

`BX24.installFinish()` — не декоративный вызов. Без него интерфейс установки у пользователя «зависнет» на экране загрузки, даже если токены на самом деле сохранились.

### Обработка `ONAPPUNINSTALL`

Критически частая ошибка — не чистить за собой данные при удалении. На собеседовании уровня синьор это прямой вопрос про **GDPR/152-ФЗ**: после удаления приложения у вас не должно оставаться персональных данных портала дольше необходимого.

```php
// event-handler.php — обработчик событий, прописанных через event.bind
if (($_POST['event'] ?? null) === 'ONAPPUNINSTALL') {
    $memberId = $_POST['auth']['member_id'] ?? null;

    if ($memberId) {
        // Soft-delete: помечаем, не удаляем сразу — вдруг переустановят через 5 минут
        markInstallationUninstalled($memberId);

        // Планируем окончательную очистку персональных данных через отложенную задачу,
        // а не удаляем синхронно в этом запросе — Bitrix24 ждёт быстрый ответ (обычно < 5 сек)
        scheduleDataPurge($memberId, delayDays: 14);
    }

    http_response_code(200);
    exit;
}
```

Почему отложенно, а не сразу: 1) пользователи иногда удаляют приложение по ошибке и ставят обратно — мгновенное физическое удаление данных это необратимо усложняет; 2) обработчик события должен отвечать быстро, тяжёлую очистку (архивация, удаление файлов) лучше вынести в очередь/крон.

### Race condition при рефреше токена

Частый сценарий: несколько параллельных запросов от одного портала получают `access_token` просроченным одновременно и **все** одновременно идут рефрешить. Bitrix24 инвалидирует `refresh_token` после первого успешного использования — второй параллельный запрос с тем же `refresh_token` получит ошибку, и его токены не обновятся.

Решение — блокировка на уровне строки БД (или распределённый лок, если инстансов приложения несколько):

```php
function getValidAccessToken(string $memberId): string
{
    $pdo = getPdo();
    $pdo->beginTransaction();

    // SELECT ... FOR UPDATE — блокирует строку до конца транзакции,
    // параллельный запрос для того же memberId будет ждать здесь
    $stmt = $pdo->prepare(
        'SELECT access_token, refresh_token, expires_at
         FROM app_installations WHERE member_id = ? FOR UPDATE'
    );
    $stmt->execute([$memberId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        throw new RuntimeException("Installation not found: $memberId");
    }

    if (strtotime($row['expires_at']) > time() + 60) {
        // Токен ещё жив, достаточный запас (60 сек на сетевую задержку)
        $pdo->commit();
        return $row['access_token'];
    }

    // Токен протух — рефрешим, пока держим блокировку строки
    $fresh = refreshTokens($row['refresh_token']);

    $pdo->prepare(
        'UPDATE app_installations
         SET access_token = ?, refresh_token = ?, expires_at = ?, updated_at = NOW()
         WHERE member_id = ?'
    )->execute([
        $fresh['access_token'],
        $fresh['refresh_token'],
        date('Y-m-d H:i:s', time() + $fresh['expires_in']),
        $memberId,
    ]);

    $pdo->commit();
    return $fresh['access_token'];
}
```

Пока одна транзакция держит `FOR UPDATE`, вторая параллельная транзакция для того же `member_id` просто ждёт на `SELECT ... FOR UPDATE` и после коммита первой увидит уже свежий токен — повторного рефреша не будет.

### Batch-клиент с устойчивостью к рейт-лимитам

Rate limit REST API Bitrix24 — около 2 запросов/сек на одного пользователя в среднем (с возможностью короткого всплеска), при превышении приходит `HTTP 503` с `error: QUERY_LIMIT_EXCEEDED`. Production-клиент обязан это обрабатывать, а не падать.

```php
final class RestClient
{
    public function __construct(
        private readonly string $domain,
        private readonly TokenProvider $tokenProvider, // инкапсулирует getValidAccessToken()
        private readonly int $maxRetries = 5,
    ) {}

    public function call(string $method, array $params = []): array
    {
        $attempt = 0;

        while (true) {
            $token = $this->tokenProvider->getToken();
            $response = $this->request($method, $params, $token);

            if (($response['error'] ?? null) === 'QUERY_LIMIT_EXCEEDED') {
                $attempt++;
                if ($attempt > $this->maxRetries) {
                    throw new RuntimeException("Rate limit retries exhausted for $method");
                }
                // Экспоненциальный backoff с джиттером — не долбим одновременно с другими воркерами
                $delayMs = (int)(100 * (2 ** $attempt) + random_int(0, 100));
                usleep($delayMs * 1000);
                continue;
            }

            if (($response['error'] ?? null) === 'expired_token') {
                $this->tokenProvider->forceRefresh();
                continue; // не считаем это попыткой rate-limit, но лимит на общее число ретраев всё равно есть
            }

            return $response;
        }
    }

    public function batch(array $commands): array
    {
        // commands: ['key' => ['method' => 'crm.deal.get', 'params' => [...]]]
        // batch сам по себе считается ОДНИМ запросом к API с точки зрения rate limit —
        // поэтому для массовых операций batch предпочтительнее N отдельных call()
        $cmd = [];
        foreach ($commands as $key => $c) {
            $cmd[$key] = $c['method'] . '?' . http_build_query($c['params'] ?? []);
        }

        return $this->call('batch', ['halt' => 0, 'cmd' => $cmd]);
    }

    private function request(string $method, array $params, string $token): array
    {
        $ch = curl_init("https://{$this->domain}/rest/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        return json_decode($raw, true) ?? ['error' => 'invalid_response'];
    }
}
```

Ключевой момент для собеседования: **batch считается одним запросом** к лимитеру, даже если внутри 50 команд. Поэтому при массовых операциях (импорт, синхронизация) правильный паттерн — собирать запросы в пачки по 50 (максимум для batch) и слать через `batch`, а не через `N` отдельных `call()`.

### Безопасность: проверка подлинности входящих запросов

Любой обработчик (`install.php`, обработчик событий, placement-страница) обязан проверять, что запрос реально пришёл от Bitrix24 для **конкретного** портала, а не подделан третьей стороной:

```php
function assertValidDomain(string $domain): void
{
    // Домен обязан быть на bitrix24-инфраструктуре, иначе кто угодно
    // может подставить произвольный domain= в install.php и получить
    // токен, который потом будет использоваться от имени "левого" портала
    if (!preg_match('/^[a-z0-9\-]+\.bitrix24\.(ru|com|by|kz|ua|de)$/i', $domain)) {
        throw new RuntimeException('Untrusted domain');
    }
}

function assertMemberIdMatches(string $memberId, array $authPayload): void
{
    // auth, присланный вместе с событием, должен содержать тот же member_id,
    // что и запись в вашей БД — иначе кто-то пытается дёргать чужой обработчик
    if (($authPayload['member_id'] ?? null) !== $memberId) {
        throw new RuntimeException('member_id mismatch — possible spoofing attempt');
    }
}
```

Для **локальных** приложений (incoming webhook) дополнительно проверяют `application_token` из поля `auth`, сравнивая его с сохранённым при установке значением — это защита от произвольных POST-запросов на ваш обработчик событий.

### Частые ошибки

- **Ключуют записи по домену вместо `member_id`** — ломается при смене домена портала, появляются «осиротевшие» установки.
- **Хранят только `access_token` без `refresh_token`** — после истечения часа жизни токена (стандартный TTL) приложение «умирает» без возможности восстановления без участия пользователя.
- **Рефрешат токен без блокировки** — гонка при параллельных запросах инвалидирует второй рефреш-токен раньше времени, портал теряет доступ до ручного переустановления.
- **N отдельных запросов вместо batch** при массовой синхронизации — упираются в rate limit на первой же сотне записей.
- **Удаляют данные портала синхронно в обработчике `ONAPPUNINSTALL`** — обработчик виснет на тяжёлой очистке, Bitrix24 может посчитать обработчик недоступным.
- **Не проверяют `domain`/`member_id` входящих запросов** — чужой код может подделать установку и получить доступ к чужому порталу через ваш install-скрипт.

## Вопросы для собеседования

**1. Почему для идентификации портала используют `member_id`, а не `domain`?**
`domain` может измениться (смена региона, ребрендинг, переход с тестового портала на боевой), `member_id` — постоянный идентификатор в рамках Bitrix24 и не меняется никогда. Ключевание по домену приводит к потере связи с установкой при смене домена.

**2. Что произойдёт, если два параллельных запроса одновременно попытаются обновить просроченный access_token?**
Refresh_token одноразовый — после первого успешного обмена он инвалидируется. Второй параллельный запрос с тем же refresh_token получит ошибку и не сможет обновить токены, если не предусмотрена блокировка (например, `SELECT ... FOR UPDATE` на уровне БД) вокруг операции рефреша.

**3. Считается ли batch-запрос из 50 команд одним запросом или пятьюдесятью с точки зрения rate limit?**
Одним. Это основная причина, почему batch — обязательный инструмент при массовых операциях: вместо 50 отдельных вызовов, каждый из которых расходует лимит, вы тратите один «слот».

**4. Что нужно сделать в коде, чтобы экран установки приложения у пользователя не завис на загрузке?**
Вызвать `BX24.installFinish()` внутри `BX24.init()` после того, как установка (обмен кода на токены, сохранение в БД) завершена успешно — без этого интерфейс Bitrix24 не узнает, что установка закончена.

**5. Почему физическое удаление персональных данных при `ONAPPUNINSTALL` лучше делать отложенно, а не сразу в обработчике?**
Во-первых, обработчик события должен быстро ответить Bitrix24 (несколько секунд), тяжёлая очистка может привести к таймауту. Во-вторых, пользователи иногда случайно удаляют приложение и быстро ставят обратно — мгновенное необратимое удаление данных усложняет такой сценарий. Практика — мягкое удаление сразу + окончательная очистка через отложенную задачу/крон.

**6. Как защититься от поддельного запроса на `install.php` с произвольным `domain`?**
Валидировать домен регулярным выражением на соответствие инфраструктуре `*.bitrix24.<tld>`, а для последующих запросов (события, placement) — сверять `member_id` из присланного `auth` с тем, что сохранён в БД для этой установки.

**7. В чём разница между Incoming Webhook и OAuth-приложением с точки зрения проверки подлинности?**
Webhook сам по себе — это секрет в URL, его утечка равна полному доступу без дополнительной проверки. OAuth-приложение дополнительно получает `application_token` при установке и должен сверять его в каждом входящем событии — это защита даже в случае, если URL обработчика стал кому-то известен.

**8. Почему нельзя хранить `access_token` в сессии PHP для Market-приложения?**
Запросы от Bitrix24 (события, крон-синхронизация, placement без прямого клика пользователя) приходят вне контекста пользовательской сессии — серверу неоткуда взять токен, если он не сохранён персистентно в БД, привязанной к `member_id` портала.

## Практические задачи

- Задача 1: Multi-tenant приложение — установка/удаление, безопасное хранение и автообновление токенов, batch REST-клиент с защитой от rate limit — `multi-tenant-app`

**Решение задачи находится в `examples/module-13-1/`**

---

**Предыдущий модуль:** [Модуль 12.4: Миграция данных и импорт](module-12-4.md)
