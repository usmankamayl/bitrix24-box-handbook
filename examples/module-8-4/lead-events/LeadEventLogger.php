<?php
/**
 * Класс для логирования событий вебхуков
 *
 * Функции:
 * - Запись событий в базу данных
 * - Проверка на повторную обработку (webhook_id)
 * - Хранение истории событий для аудита
 * - Логирование в файлы (info, warning, error)
 */

class LeadEventLogger
{
    private $db;
    private $logDir;

    public function __construct()
    {
        $this->logDir = __DIR__ . '/logs';

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        // Подключаем базу данных
        $this->connectDatabase();
    }

    /**
     * Подключение к базе данных
     */
    private function connectDatabase()
    {
        try {
            $this->db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            // Работаем без БД (только файловое логирование)
            $this->db = null;
        }
    }

    /**
     * Проверка, был ли webhook уже обработан
     */
    public function isWebhookProcessed($webhookId)
    {
        if (!$this->db) {
            return false; // Если нет БД, не можем проверить
        }

        try {
            $stmt = $this->db->prepare('
                SELECT id FROM webhook_log
                WHERE webhook_id = ?
                LIMIT 1
            ');
            $stmt->execute([$webhookId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError('Error checking webhook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Логирование события
     */
    public function logEvent($webhookId, $eventName, $eventData)
    {
        // 1. Логируем в файл
        $this->logInfo("Event received: $eventName", [
            'webhook_id' => $webhookId,
            'entity_id' => $eventData['FIELDS']['ID'] ?? $eventData['id'] ?? null
        ]);

        // 2. Логируем в базу данных
        if ($this->db) {
            try {
                $stmt = $this->db->prepare('
                    INSERT INTO webhook_log
                    (webhook_id, event_name, event_data, created_at, processed_at, status)
                    VALUES (?, ?, ?, NOW(), NOW(), ?)
                ');

                $stmt->execute([
                    $webhookId,
                    $eventName,
                    json_encode($eventData, JSON_UNESCAPED_UNICODE),
                    'processed'
                ]);
            } catch (PDOException $e) {
                $this->logError('Error saving to database: ' . $e->getMessage());
            }
        }
    }

    /**
     * Логирование информации
     */
    public function logInfo($message, $context = [])
    {
        $this->writeLog('info', $message, $context);
    }

    /**
     * Логирование предупреждений
     */
    public function logWarning($message, $context = [])
    {
        $this->writeLog('warning', $message, $context);
    }

    /**
     * Логирование ошибок
     */
    public function logError($message, $context = [])
    {
        $this->writeLog('error', $message, $context);
    }

    /**
     * Запись в лог-файл
     */
    private function writeLog($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$timestamp] " . strtoupper($level) . ": $message$contextStr\n";

        // Пишем в отдельный файл в зависимости от уровня
        $logFile = $this->logDir . '/' . $level . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        // Также пишем в общий лог
        $generalLog = $this->logDir . '/webhook.log';
        file_put_contents($generalLog, $logMessage, FILE_APPEND);
    }

    /**
     * Получить историю событий для конкретного лида
     */
    public function getLeadHistory($leadId)
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare('
                SELECT
                    id,
                    webhook_id,
                    event_name,
                    event_data,
                    created_at,
                    status
                FROM webhook_log
                WHERE JSON_EXTRACT(event_data, "$.FIELDS.ID") = ?
                   OR JSON_EXTRACT(event_data, "$.id") = ?
                ORDER BY created_at DESC
                LIMIT 50
            ');

            $stmt->execute([$leadId, $leadId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Error getting lead history: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Очистка старых логов (старше 30 дней)
     */
    public function cleanupOldLogs($days = 30)
    {
        if (!$this->db) {
            return;
        }

        try {
            $stmt = $this->db->prepare('
                DELETE FROM webhook_log
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ');

            $stmt->execute([$days]);
            $deletedCount = $stmt->rowCount();

            $this->logInfo("Cleaned up $deletedCount old webhook logs");
        } catch (PDOException $e) {
            $this->logError('Error cleaning up logs: ' . $e->getMessage());
        }
    }

    /**
     * Получить статистику по событиям
     */
    public function getStatistics($days = 7)
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare('
                SELECT
                    event_name,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM webhook_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY event_name, DATE(created_at)
                ORDER BY date DESC, count DESC
            ');

            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Error getting statistics: ' . $e->getMessage());
            return [];
        }
    }
}
