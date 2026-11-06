<?php
/**
 * Задача 2: OAuth-клиент для Bitrix24 с обновлением токена
 *
 * Этот код реализует полный OAuth 2.0 flow:
 * 1. Перенаправление на авторизацию
 * 2. Обмен кода на access_token и refresh_token
 * 3. Сохранение токенов в базе данных (или сессию)
 * 4. Обновление access_token когда он истекает
 * 5. Использование токена для вызова REST API
 */

// ============================================
// КОНФИГУРАЦИЯ
// ============================================

/**
 * ВАЖНО: Замени на реальные данные твоего приложения
 *
 * Как получить CLIENT_ID и CLIENT_SECRET:
 * 1. Зарегистрируй приложение в Bitrix24.Market
 * 2. Получи CLIENT_ID и CLIENT_SECRET
 * 3. Укажи REDIRECT_URI в настройках приложения
 */
define('OAUTH_CLIENT_ID', 'your-app-id-here');
define('OAUTH_CLIENT_SECRET', 'your-app-secret-here');
define('OAUTH_REDIRECT_URI', 'https://example.com/oauth-callback.php');

// URL для OAuth авторизации
define('OAUTH_AUTHORIZE_URL', 'https://oauth.bitrix.info/oauth/authorize/');
define('OAUTH_TOKEN_URL', 'https://oauth.bitrix.info/oauth/token/');

// Файл для сохранения токенов (в реальном проекте используй БД)
define('TOKENS_FILE', __DIR__ . '/oauth_tokens.json');

// ============================================
// КЛАСС ДЛЯ РАБОТЫ С OAUTH
// ============================================

class Bitrix24OAuth
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(string $clientId, string $clientSecret, string $redirectUri)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
    }

    /**
     * Получить URL для перенаправления пользователя на авторизацию
     *
     * @param array $scope Права доступа (например, ['crm', 'task'])
     * @return string URL для редиректа
     */
    public function getAuthorizationUrl(array $scope = ['crm']): string
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scope),
        ];

        return OAUTH_AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * Обменять код авторизации на токены
     *
     * @param string $code Код авторизации (получен после перенаправления)
     * @return array Массив с access_token, refresh_token и другой информацией
     * @throws Exception Если обмен не удался
     */
    public function getTokensByCode(string $code): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ];

        return $this->makeRequest(OAUTH_TOKEN_URL, 'POST', $params);
    }

    /**
     * Обновить access_token используя refresh_token
     *
     * @param string $refreshToken Refresh token
     * @return array Новые токены (access_token, refresh_token)
     * @throws Exception Если обновление не удалось
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $params = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ];

        return $this->makeRequest(OAUTH_TOKEN_URL, 'POST', $params);
    }

    /**
     * Вызвать REST API метод используя OAuth токен
     *
     * @param string $accessToken Access token
     * @param string $domain Домен портала (например, my-company.bitrix24.ru)
     * @param string $method Метод API (например, crm.lead.list)
     * @param array $params Параметры метода
     * @return array Ответ API
     * @throws Exception Если запрос не удался
     */
    public function callApi(string $accessToken, string $domain, string $method, array $params = []): array
    {
        $url = 'https://' . $domain . '/rest/' . $method . '.json';

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                'content' => json_encode($params),
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('Failed to call Bitrix24 API');
        }

        return json_decode($response, true);
    }

    /**
     * Вспомогательный метод для HTTP-запросов
     *
     * @param string $url URL для запроса
     * @param string $method GET или POST
     * @param array $data Данные для отправки
     * @return array Распакованный JSON-ответ
     * @throws Exception Если запрос не удался
     */
    private function makeRequest(string $url, string $method = 'GET', array $data = []): array
    {
        $options = [
            'http' => [
                'method' => $method,
                'header' => ['Content-Type: application/x-www-form-urlencoded'],
            ],
        ];

        if ($method === 'POST') {
            $options['http']['content'] = http_build_query($data);
        } else {
            $url .= '?' . http_build_query($data);
        }

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception('OAuth request failed');
        }

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            throw new Exception('OAuth Error: ' . $result['error'] . ' - ' . ($result['error_description'] ?? ''));
        }

        return $result;
    }
}

// ============================================
// КЛАСС ДЛЯ ХРАНЕНИЯ ТОКЕНОВ
// ============================================

class TokenStorage
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Сохранить токены
     */
    public function save(array $tokens): void
    {
        // Добавляем timestamp истечения access_token
        $tokens['expires_at'] = time() + ($tokens['expires_in'] ?? 3600);

        file_put_contents($this->filePath, json_encode($tokens, JSON_PRETTY_PRINT));
    }

    /**
     * Загрузить токены
     */
    public function load(): ?array
    {
        if (!file_exists($this->filePath)) {
            return null;
        }

        $content = file_get_contents($this->filePath);
        return json_decode($content, true);
    }

    /**
     * Проверить, истек ли access_token
     */
    public function isAccessTokenExpired(): bool
    {
        $tokens = $this->load();
        if (!$tokens) {
            return true;
        }

        // Обновляем за 5 минут до истечения (для безопасности)
        return time() >= ($tokens['expires_at'] ?? 0) - 300;
    }

    /**
     * Получить актуальный access_token (обновляет если нужно)
     */
    public function getValidAccessToken(Bitrix24OAuth $oauth): ?string
    {
        $tokens = $this->load();
        if (!$tokens) {
            return null;
        }

        // Если access_token еще живой, возвращаем его
        if (!$this->isAccessTokenExpired()) {
            return $tokens['access_token'];
        }

        // Обновляем access_token через refresh_token
        try {
            $newTokens = $oauth->refreshAccessToken($tokens['refresh_token']);
            $this->save($newTokens);
            return $newTokens['access_token'];
        } catch (Exception $e) {
            echo "❌ Ошибка при обновлении токена: " . $e->getMessage() . "\n";
            return null;
        }
    }

    /**
     * Получить информацию о портале и пользователе
     */
    public function getInfo(): ?array
    {
        $tokens = $this->load();
        return $tokens ? [
            'domain' => $tokens['domain'] ?? 'unknown',
            'user_id' => $tokens['member_id'] ?? 'unknown',
            'scope' => $tokens['scope'] ?? 'unknown',
        ] : null;
    }
}

// ============================================
// ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
// ============================================

// Инициализируем OAuth
$oauth = new Bitrix24OAuth(
    OAUTH_CLIENT_ID,
    OAUTH_CLIENT_SECRET,
    OAUTH_REDIRECT_URI
);

$tokenStorage = new TokenStorage(TOKENS_FILE);

// Определяем текущую фазу
$action = $_GET['action'] ?? 'menu';

switch ($action) {
    // ========== ФАЗА 1: Показать меню с ссылкой авторизации ==========
    case 'menu':
        echo "=== OAuth 2.0 Авторизация для Bitrix24 ===\n\n";

        // Проверяем, есть ли уже сохраненные токены
        if ($tokenStorage->load()) {
            echo "✅ Токены уже сохранены!\n";
            echo "Информация:\n";
            print_r($tokenStorage->getInfo());
            echo "\n";

            echo "Доступные действия:\n";
            echo "1. /oauth-example.php?action=test-api — Получить список лидов\n";
            echo "2. /oauth-example.php?action=logout — Удалить токены\n";
        } else {
            echo "❌ Токены не найдены\n";
            echo "\nСтарт авторизации:\n";
            echo "1. Нажми на ссылку ниже\n";
            echo "2. Подтверди доступ приложению\n";
            echo "3. Будешь перенаправлен на URL-callback\n\n";

            $authUrl = $oauth->getAuthorizationUrl(['crm', 'task']);
            echo "🔗 Ссылка для авторизации:\n";
            echo $authUrl . "\n\n";
            echo "Или запусти скрипт с параметром:\n";
            echo "?action=auth-url\n";
        }
        break;

    // ========== ФАЗА 2: Обработка callback после авторизации ==========
    case 'callback':
        if (empty($_GET['code'])) {
            die("❌ Ошибка: код авторизации не получен\n");
        }

        echo "🔄 Обмениваю код на токены...\n\n";

        try {
            $tokens = $oauth->getTokensByCode($_GET['code']);
            $tokenStorage->save($tokens);

            echo "✅ Авторизация успешна!\n";
            echo "Сохраненная информация:\n";
            print_r($tokenStorage->getInfo());
        } catch (Exception $e) {
            echo "❌ Ошибка авторизации: " . $e->getMessage() . "\n";
        }
        break;

    // ========== ФАЗА 3: Тест API с обновлением токена ==========
    case 'test-api':
        echo "=== Тест REST API через OAuth ===\n\n";

        $tokens = $tokenStorage->load();
        if (!$tokens) {
            die("❌ Ошибка: токены не найдены. Запусти авторизацию первой?\n");
        }

        // Получаем актуальный access_token (обновляет если нужно)
        $accessToken = $tokenStorage->getValidAccessToken($oauth);
        if (!$accessToken) {
            die("❌ Ошибка: не удалось получить действительный токен\n");
        }

        $domain = $tokens['domain'];
        echo "✅ Использую токен для портала: " . $domain . "\n\n";

        // Получаем список лидов
        echo "📋 Получаю список лидов...\n";
        try {
            $response = $oauth->callApi($accessToken, $domain, 'crm.lead.list', [
                'select' => ['ID', 'TITLE', 'EMAIL', 'STATUS_ID'],
                'limit' => 5,
            ]);

            if (isset($response['result'])) {
                echo "✅ Получено " . count($response['result']) . " лидов:\n\n";
                foreach ($response['result'] as $lead) {
                    echo sprintf(
                        "  ID: %s | %s | Email: %s | Статус: %s\n",
                        $lead['ID'],
                        $lead['TITLE'],
                        $lead['EMAIL'] ?? 'N/A',
                        $lead['STATUS_ID']
                    );
                }
            } else {
                echo json_encode($response, JSON_PRETTY_PRINT);
            }
        } catch (Exception $e) {
            echo "❌ Ошибка при вызове API: " . $e->getMessage() . "\n";
        }
        break;

    // ========== УДАЛЕНИЕ ТОКЕНОВ ==========
    case 'logout':
        if (file_exists(TOKENS_FILE)) {
            unlink(TOKENS_FILE);
            echo "✅ Токены удалены. Авторизация сброшена.\n";
        } else {
            echo "ℹ️ Токены уже удалены\n";
        }
        break;

    // ========== ПОКАЗАТЬ URL АВТОРИЗАЦИИ ==========
    case 'auth-url':
        $authUrl = $oauth->getAuthorizationUrl(['crm', 'task', 'user', 'disk']);
        echo "🔗 URL для авторизации:\n";
        echo $authUrl . "\n";
        break;

    default:
        echo "❌ Неизвестное действие: " . htmlspecialchars($action) . "\n";
        echo "\nДоступные действия:\n";
        echo "  menu — Главное меню\n";
        echo "  auth-url — Показать URL для авторизации\n";
        echo "  callback — Обработка callback (автоматически)\n";
        echo "  test-api — Тест REST API\n";
        echo "  logout — Удалить токены\n";
}
?>
