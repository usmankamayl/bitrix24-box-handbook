<?php
/**
 * Система логирования уровня senior для Bitrix.
 *
 * Требования из реальной жизни:
 * - Уровни: DEBUG, INFO, WARNING, ERROR, CRITICAL
 * - Ротация логов (по дате и размеру)
 * - Контекст: user_id, request_uri, IP, memory
 * - Интеграция с syslog/Sentry для продакшена
 * - Не бросает исключения при ошибках записи (fail-safe)
 */

namespace Vendor\Logger;

use Bitrix\Main\Application;
use Bitrix\Main\Diag\Debug;

class Logger
{
    // Уровни логирования (PSR-3 compatible)
    public const DEBUG    = 'debug';
    public const INFO     = 'info';
    public const WARNING  = 'warning';
    public const ERROR    = 'error';
    public const CRITICAL = 'critical';

    /** @var string Базовая директория логов */
    private string $logDir;

    /** @var string Модуль/канал */
    private string $channel;

    /** @var int Макс. размер файла до ротации (байт) */
    private int $maxFileSize = 10 * 1024 * 1024; // 10MB

    /** @var int Сколько старых файлов хранить */
    private int $maxFiles = 5;

    /** @var array Контекст по умолчанию */
    private array $context = [];

    /** @var callable|null Функция для отправки критичных ошибок наружу */
    private $criticalHandler = null;

    public function __construct(string $channel = 'app', ?string $logDir = null)
    {
        $this->channel = $channel;
        $this->logDir  = $logDir
            ?? ($_SERVER['DOCUMENT_ROOT'] . '/logs/' . $channel);
        $this->ensureLogDir();
    }

    /**
     * Устанавливаем контекст по умолчанию для всех записей.
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Регистрируем обработчик критичных ошибок (Slack, Sentry, email).
     */
    public function onCritical(callable $handler): self
    {
        $this->criticalHandler = $handler;
        return $this;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);

        // Отправляем наружу (Sentry, Slack, email)
        if ($this->criticalHandler) {
            try {
                ($this->criticalHandler)($message, $context);
            } catch (\Throwable $e) {
                // Не даём логгеру упасть
            }
        }
    }

    /**
     * Логирование исключений с трейсом.
     */
    public function exception(\Throwable $e, string $message = '', array $context = []): void
    {
        $this->error($message ?: $e->getMessage(), array_merge($context, [
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]));
    }

    /**
     * Основной метод логирования.
     */
    private function log(string $level, string $message, array $context): void
    {
        $record = array_merge(
            $this->buildDefaultContext(),
            $this->context,
            $context,
            [
                'level'     => $level,
                'message'   => $message,
                'timestamp' => date('Y-m-d H:i:s'),
                'datetime'  => microtime(true),
            ]
        );

        $this->write($level, $record);

        // Critical и Error дублируем в syslog
        if (in_array($level, [self::ERROR, self::CRITICAL])) {
            $this->writeToSyslog($record);
        }
    }

    private function buildDefaultContext(): array
    {
        global $USER;

        return [
            'channel'   => $this->channel,
            'pid'       => getmypid(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'user_id'   => $USER && $USER->IsAuthorized() ? $USER->GetID() : null,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'uri'       => $_SERVER['REQUEST_URI'] ?? 'cli',
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'cli',
            'agent'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100),
        ];
    }

    private function write(string $level, array $record): void
    {
        $file = $this->getLogFile($level);

        // Ротация по размеру
        if (file_exists($file) && filesize($file) > $this->maxFileSize) {
            $this->rotate($file);
        }

        // Формат: [2024-01-15 14:32:01] level: message {context}
        $contextJson = json_encode(
            array_diff_key($record, ['level' => '', 'message' => '', 'timestamp' => '']),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $line = sprintf(
            "[%s] %s: %s %s\n",
            $record['timestamp'],
            strtoupper($record['level']),
            $record['message'],
            $contextJson
        );

        // file_put_contents с LOCK_EX — безопасно при параллельных процессах
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function writeToSyslog(array $record): void
    {
        // Для продакшена — отправка в syslog/Sentry
        // Здесь заглушка, в реальном проекте — Sentry\captureMessage
        if (function_exists('syslog')) {
            $priority = $record['level'] === 'critical' ? LOG_CRIT : LOG_ERR;
            @syslog($priority, "[{$this->channel}] {$record['message']}");
        }
    }

    private function getLogFile(string $level): string
    {
        // Отдельные файлы для error/critical — быстрее найти
        $suffix = in_array($level, [self::ERROR, self::CRITICAL])
            ? '.error'
            : '';
        return $this->logDir . '/' . date('Y-m-d') . $suffix . '.log';
    }

    private function rotate(string $file): void
    {
        // Ротация: app.log → app.log.1, app.log.1 → app.log.2, и т.д.
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $old = $file . '.' . $i;
            $new = $file . '.' . ($i + 1);
            if (file_exists($old)) {
                rename($old, $new);
            }
        }
        rename($file, $file . '.1');

        // Удаляем файлы старше maxFiles
        for ($i = $this->maxFiles + 1; $i <= $this->maxFiles + 3; $i++) {
            if (file_exists($file . '.' . $i)) {
                unlink($file . '.' . $i);
            }
        }
    }

    private function ensureLogDir(): void
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
    }
}


// ============================================================
// Фабрика и интеграция с Bitrix
// ============================================================

class LoggerFactory
{
    private static array $loggers = [];

    public static function get(string $channel = 'app'): Logger
    {
        if (!isset(self::$loggers[$channel])) {
            self::$loggers[$channel] = new Logger($channel);
        }
        return self::$loggers[$channel];
    }
}


// ============================================================
// Пример использования
// ============================================================

/*
use Vendor\Logger\LoggerFactory;

// В init.php — глобальный логгер для модуля
$logger = LoggerFactory::get('vendor.shop');

// С контекстом
$logger->withContext(['import_id' => 42, 'source' => '1c']);

// Логируем
$logger->info('Import started', ['products_count' => 500]);
$logger->warning('Low memory', ['free_mb' => 128]);
$logger->error('Import failed', ['error' => 'DB timeout']);

try {
    // ...
} catch (\Throwable $e) {
    $logger->exception($e, 'Product import error', ['product_id' => 123]);
}

// Critical — отправит в Sentry/Slack
$logger->onCritical(function ($msg, $ctx) {
    \Sentry\captureMessage($msg, \Sentry\Severity::fatal());
});
$logger->critical('Database is down', ['host' => 'db1']);

// В компоненте
public function executeComponent()
{
    $logger = LoggerFactory::get('catalog');
    $logger->info('Component executed', [
        'params' => $this->arParams,
        'user_id' => $USER->GetID(),
    ]);
}
*/
