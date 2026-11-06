<?php
/**
 * Логгер для синхронизации с внешними системами
 */

class ExternalSyncLogger
{
    private $db;
    private $logDir;

    public function __construct()
    {
        $this->logDir = __DIR__ . '/logs';

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

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
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            $this->db = null;
        }
    }

    /**
     * Проверка, обработан ли вебхук
     */
    public function isWebhookProcessed($webhookId)
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('
                SELECT id FROM external_sync_log
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
        $this->logInfo("Sync event received: $eventName", [
            'webhook_id' => $webhookId,
            'entity_id' => $eventData['FIELDS']['ID'] ?? $eventData['id'] ?? null
        ]);

        if ($this->db) {
            try {
                $stmt = $this->db->prepare('
                    INSERT INTO external_sync_log
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
     * Логирование успешной синхронизации
     */
    public function logSyncSuccess($system, $entityType, $entityId, $details = [])
    {
        $this->logInfo("Sync success: $system - $entityType #$entityId", $details);

        if ($this->db) {
            try {
                $stmt = $this->db->prepare('
                    INSERT INTO sync_history
                    (system, entity_type, entity_id, status, details, synced_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ');

                $stmt->execute([
                    $system,
                    $entityType,
                    $entityId,
                    'success',
                    json_encode($details, JSON_UNESCAPED_UNICODE)
                ]);
            } catch (PDOException $e) {
                $this->logError('Error saving sync history: ' . $e->getMessage());
            }
        }
    }

    /**
     * Логирование ошибки синхронизации
     */
    public function logSyncError($system, $entityType, $entityId, $error, $details = [])
    {
        $this->logError("Sync error: $system - $entityType #$entityId", array_merge(
            ['error' => $error],
            $details
        ));

        if ($this->db) {
            try {
                $stmt = $this->db->prepare('
                    INSERT INTO sync_history
                    (system, entity_type, entity_id, status, error_message, details, synced_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ');

                $stmt->execute([
                    $system,
                    $entityType,
                    $entityId,
                    'error',
                    $error,
                    json_encode($details, JSON_UNESCAPED_UNICODE)
                ]);
            } catch (PDOException $e) {
                $this->logError('Error saving sync error: ' . $e->getMessage());
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

        $logFile = $this->logDir . '/' . $level . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        $generalLog = $this->logDir . '/sync.log';
        file_put_contents($generalLog, $logMessage, FILE_APPEND);
    }

    /**
     * Получить статистику синхронизации
     */
    public function getSyncStats($days = 7)
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare('
                SELECT
                    system,
                    entity_type,
                    status,
                    COUNT(*) as count,
                    DATE(synced_at) as date
                FROM sync_history
                WHERE synced_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY system, entity_type, status, DATE(synced_at)
                ORDER BY date DESC, count DESC
            ');

            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Error getting sync stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить последние ошибки синхронизации
     */
    public function getRecentErrors($limit = 50)
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare('
                SELECT
                    id,
                    system,
                    entity_type,
                    entity_id,
                    error_message,
                    details,
                    synced_at
                FROM sync_history
                WHERE status = ?
                ORDER BY synced_at DESC
                LIMIT ?
            ');

            $stmt->execute(['error', $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Error getting recent errors: ' . $e->getMessage());
            return [];
        }
    }
}
