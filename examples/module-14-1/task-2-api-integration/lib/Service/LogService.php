<?php
/**
 * Сервис логирования API-запросов
 *
 * Записывает все HTTP-запросы и ответы в лог-файлы
 * для отладки и мониторинга интеграций
 */

namespace Acme\Integration\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Type\DateTime;

class LogService
{
    /** @var string Директория для хранения логов */
    protected $logDir;

    /** @var bool Включено ли логирование */
    protected $enabled;

    /** @var string Уровень детализации: minimal|full */
    protected $level;

    public function __construct()
    {
        $this->logDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/logs/integration/';
        $this->enabled = Option::get('acme.integration', 'log_enabled', 'Y') === 'Y';
        $this->level = Option::get('acme.integration', 'log_level', 'full');

        // Создаем директорию если не существует
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    /**
     * Логировать HTTP-запрос
     *
     * @param string $method HTTP метод
     * @param string $url URL запроса
     * @param array|null $data Данные запроса
     */
    public function logRequest(string $method, string $url, ?array $data = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'request',
            'method' => $method,
            'url' => $url,
        ];

        // Добавляем данные запроса только в full режиме
        if ($this->level === 'full' && $data !== null) {
            $logEntry['data'] = $this->sanitizeData($data);
        }

        $this->writeLog($logEntry);
    }

    /**
     * Логировать HTTP-ответ
     *
     * @param int $statusCode Статус код
     * @param string $response Тело ответа
     * @param int $duration Время выполнения (мс)
     */
    public function logResponse(int $statusCode, string $response, int $duration = 0): void
    {
        if (!$this->enabled) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'response',
            'status' => $statusCode,
            'duration' => $duration . 'ms',
        ];

        // Добавляем тело ответа только в full режиме
        if ($this->level === 'full') {
            $logEntry['response'] = $this->truncate($response, 5000);
        }

        $this->writeLog($logEntry);
    }

    /**
     * Логировать ошибку
     *
     * @param string $message Сообщение об ошибке
     * @param array $context Контекст ошибки
     */
    public function logError(string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'error',
            'message' => $message,
            'context' => $context,
        ];

        $this->writeLog($logEntry);

        // Дублируем критические ошибки в общий лог Bitrix
        AddMessage2Log($message, 'acme.integration');
    }

    /**
     * Логировать повторную попытку запроса
     *
     * @param string $url URL запроса
     * @param int $attempt Номер попытки
     */
    public function logRetry(string $url, int $attempt): void
    {
        if (!$this->enabled) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'retry',
            'url' => $url,
            'attempt' => $attempt,
        ];

        $this->writeLog($logEntry);
    }

    /**
     * Логировать попадание в кеш
     *
     * @param string $url URL запроса
     */
    public function logCacheHit(string $url): void
    {
        if (!$this->enabled || $this->level !== 'full') {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'cache_hit',
            'url' => $url,
        ];

        $this->writeLog($logEntry);
    }

    /**
     * Записать лог в файл
     *
     * @param array $entry Запись лога
     */
    protected function writeLog(array $entry): void
    {
        $logFile = $this->logDir . 'api_' . date('Y-m-d') . '.log';

        $line = sprintf(
            "[%s] [%s] %s\n",
            $entry['timestamp'],
            strtoupper($entry['type']),
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Получить последние записи логов
     *
     * @param int $limit Количество записей
     * @return array
     */
    public function getRecentRequests(int $limit = 100): array
    {
        $logFile = $this->logDir . 'api_' . date('Y-m-d') . '.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile);
        $lines = array_reverse($lines);
        $lines = array_slice($lines, 0, $limit);

        $requests = [];

        foreach ($lines as $line) {
            if (preg_match('/\[([^\]]+)\] \[([^\]]+)\] (.+)/', $line, $matches)) {
                $data = json_decode($matches[3], true);

                if ($data && $data['type'] === 'request') {
                    $requests[] = [
                        'date' => $matches[1],
                        'method' => $data['method'] ?? '',
                        'url' => $data['url'] ?? '',
                    ];
                }
            }
        }

        return $requests;
    }

    /**
     * Очистить старые логи
     *
     * Удаляет файлы логов старше заданного количества дней
     *
     * @param int $days Количество дней хранения
     */
    public function cleanOldLogs(int $days = 30): void
    {
        if (!is_dir($this->logDir)) {
            return;
        }

        $threshold = time() - ($days * 86400);

        $files = glob($this->logDir . 'api_*.log');

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
            }
        }
    }

    /**
     * Санитизация данных (скрытие паролей, токенов)
     *
     * @param array $data
     * @return array
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = ['password', 'api_key', 'token', 'secret', 'auth'];

        array_walk_recursive($data, function(&$value, $key) use ($sensitiveKeys) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $value = '***HIDDEN***';
                    break;
                }
            }
        });

        return $data;
    }

    /**
     * Обрезать строку до заданной длины
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    protected function truncate(string $string, int $length): string
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length) . '... [truncated]';
    }
}
