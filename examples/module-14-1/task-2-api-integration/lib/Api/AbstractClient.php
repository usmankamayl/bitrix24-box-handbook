<?php
/**
 * Абстрактный API-клиент
 *
 * Базовый класс для всех API-клиентов модуля.
 * Обеспечивает:
 * - HTTP-запросы (GET, POST, PUT, DELETE)
 * - Логирование запросов/ответов
 * - Retry-механизм при ошибках
 * - Кеширование ответов
 * - Обработку ошибок
 */

namespace Acme\Integration\Api;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Acme\Integration\Service\LogService;
use Acme\Integration\Service\CacheService;

abstract class AbstractClient
{
    /** @var HttpClient */
    protected $httpClient;

    /** @var LogService */
    protected $logger;

    /** @var CacheService */
    protected $cache;

    /** @var int Таймаут запроса в секундах */
    protected $timeout = 30;

    /** @var int Количество повторов при ошибке */
    protected $retryCount = 3;

    /** @var int Задержка между повторами (мс) */
    protected $retryDelay = 1000;

    public function __construct()
    {
        $this->httpClient = new HttpClient([
            'timeout' => $this->timeout,
            'redirect' => true,
        ]);

        $this->logger = new LogService();
        $this->cache = new CacheService();

        $this->configure();
    }

    /**
     * URL базового адреса API
     *
     * @return string
     */
    abstract protected function getApiUrl(): string;

    /**
     * API ключ для авторизации
     *
     * @return string
     */
    abstract protected function getApiKey(): string;

    /**
     * Конфигурация клиента
     *
     * Переопределите для настройки заголовков, таймаутов и т.д.
     */
    protected function configure(): void
    {
        // Устанавливаем заголовки по умолчанию
        $this->httpClient->setHeader('Content-Type', 'application/json');
        $this->httpClient->setHeader('Accept', 'application/json');
        $this->httpClient->setHeader('User-Agent', 'Bitrix24-Integration/1.0');

        // Авторизация через API ключ
        $apiKey = $this->getApiKey();
        if (!empty($apiKey)) {
            $this->httpClient->setHeader('Authorization', 'Bearer ' . $apiKey);
        }
    }

    /**
     * GET запрос
     *
     * @param string $endpoint Путь эндпоинта (например: /users/123)
     * @param array $params Параметры запроса
     * @param bool $useCache Использовать кеширование
     * @param int $cacheTtl Время жизни кеша (секунды)
     * @return Result
     */
    protected function get(string $endpoint, array $params = [], bool $useCache = false, int $cacheTtl = 3600): Result
    {
        $url = $this->buildUrl($endpoint, $params);

        // Проверяем кеш
        if ($useCache) {
            $cacheKey = 'api_get_' . md5($url);
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                $this->logger->logCacheHit($url);
                return $this->createSuccessResult($cached);
            }
        }

        // Выполняем запрос с retry
        $result = $this->executeWithRetry('GET', $url);

        // Сохраняем в кеш
        if ($useCache && $result->isSuccess()) {
            $this->cache->set($cacheKey, $result->getData(), $cacheTtl);
        }

        return $result;
    }

    /**
     * POST запрос
     *
     * @param string $endpoint Путь эндпоинта
     * @param array $data Данные для отправки
     * @return Result
     */
    protected function post(string $endpoint, array $data = []): Result
    {
        $url = $this->buildUrl($endpoint);
        return $this->executeWithRetry('POST', $url, $data);
    }

    /**
     * PUT запрос
     *
     * @param string $endpoint Путь эндпоинта
     * @param array $data Данные для отправки
     * @return Result
     */
    protected function put(string $endpoint, array $data = []): Result
    {
        $url = $this->buildUrl($endpoint);
        return $this->executeWithRetry('PUT', $url, $data);
    }

    /**
     * DELETE запрос
     *
     * @param string $endpoint Путь эндпоинта
     * @return Result
     */
    protected function delete(string $endpoint): Result
    {
        $url = $this->buildUrl($endpoint);
        return $this->executeWithRetry('DELETE', $url);
    }

    /**
     * Выполнить HTTP запрос с retry-механизмом
     *
     * @param string $method HTTP метод
     * @param string $url URL запроса
     * @param array|null $data Данные
     * @return Result
     */
    protected function executeWithRetry(string $method, string $url, ?array $data = null): Result
    {
        $attempt = 0;

        while ($attempt < $this->retryCount) {
            $result = $this->executeRequest($method, $url, $data);

            if ($result->isSuccess()) {
                return $result;
            }

            // Проверяем, стоит ли повторять
            $statusCode = $this->httpClient->getStatus();
            if (!$this->shouldRetry($statusCode)) {
                return $result;
            }

            $attempt++;

            if ($attempt < $this->retryCount) {
                // Задержка перед повтором
                usleep($this->retryDelay * 1000);

                $this->logger->logRetry($url, $attempt);
            }
        }

        return $result;
    }

    /**
     * Выполнить HTTP запрос
     *
     * @param string $method HTTP метод
     * @param string $url URL запроса
     * @param array|null $data Данные
     * @return Result
     */
    protected function executeRequest(string $method, string $url, ?array $data = null): Result
    {
        $startTime = microtime(true);

        try {
            // Логируем запрос
            $this->logger->logRequest($method, $url, $data);

            // Выполняем запрос
            switch ($method) {
                case 'GET':
                    $response = $this->httpClient->get($url);
                    break;

                case 'POST':
                    $response = $this->httpClient->post($url, json_encode($data));
                    break;

                case 'PUT':
                    $this->httpClient->query('PUT', $url, json_encode($data));
                    $response = $this->httpClient->getResult();
                    break;

                case 'DELETE':
                    $this->httpClient->query('DELETE', $url);
                    $response = $this->httpClient->getResult();
                    break;

                default:
                    return $this->createErrorResult("Неподдерживаемый HTTP метод: {$method}");
            }

            $duration = round((microtime(true) - $startTime) * 1000); // мс
            $statusCode = $this->httpClient->getStatus();

            // Логируем ответ
            $this->logger->logResponse($statusCode, $response, $duration);

            // Проверяем статус
            if ($statusCode >= 200 && $statusCode < 300) {
                // Успешный ответ
                $responseData = json_decode($response, true);

                return $this->createSuccessResult($responseData ?? []);
            } else {
                // Ошибка
                return $this->createErrorResult(
                    "HTTP {$statusCode}: {$response}"
                );
            }

        } catch (\Exception $e) {
            $this->logger->logError($e->getMessage(), [
                'url' => $url,
                'method' => $method,
            ]);

            return $this->createErrorResult($e->getMessage());
        }
    }

    /**
     * Нужно ли повторять запрос при данном статус-коде
     *
     * @param int $statusCode
     * @return bool
     */
    protected function shouldRetry(int $statusCode): bool
    {
        // Повторяем при временных ошибках
        return in_array($statusCode, [408, 429, 500, 502, 503, 504]);
    }

    /**
     * Построить полный URL
     *
     * @param string $endpoint
     * @param array $params
     * @return string
     */
    protected function buildUrl(string $endpoint, array $params = []): string
    {
        $url = rtrim($this->getApiUrl(), '/') . '/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Создать успешный результат
     *
     * @param mixed $data
     * @return Result
     */
    protected function createSuccessResult($data): Result
    {
        $result = new Result();
        $result->setData($data);
        return $result;
    }

    /**
     * Создать результат с ошибкой
     *
     * @param string $message
     * @return Result
     */
    protected function createErrorResult(string $message): Result
    {
        $result = new Result();
        $result->addError(new Error($message));
        return $result;
    }
}
