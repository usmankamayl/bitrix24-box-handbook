<?php

require_once __DIR__ . '/config.php';

final class SyncQueue
{
    public function enqueue(string $entityType, int $entityId, string $externalSystem): void
    {
        // Без защиты от дублей в очереди: несколько записей на одну и ту же сущность —
        // не проблема, т.к. сама синхронизация идемпотентна (см. SyncService)
        getPdo()->prepare(
            'INSERT INTO sync_queue (entity_type, entity_id, external_system, status, created_at, updated_at)
             VALUES (?, ?, ?, "pending", NOW(), NOW())'
        )->execute([$entityType, $entityId, $externalSystem]);
    }

    public function getPending(int $limit = 20): array
    {
        $stmt = getPdo()->prepare(
            'SELECT * FROM sync_queue WHERE status = "pending" ORDER BY id ASC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markDone(int $taskId): void
    {
        getPdo()->prepare(
            'UPDATE sync_queue SET status = "done", updated_at = NOW() WHERE id = ?'
        )->execute([$taskId]);
    }

    public function markFailed(int $taskId, int $currentAttempts, string $error, int $maxAttempts = 5): void
    {
        $attempts = $currentAttempts + 1;
        // После исчерпания попыток задача остаётся в статусе failed и требует
        // ручного разбора — бесконечный ретрай забил бы очередь мёртвыми задачами
        $status = $attempts >= $maxAttempts ? 'failed' : 'pending';

        getPdo()->prepare(
            'UPDATE sync_queue
             SET status = ?, attempts = ?, last_error = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$status, $attempts, substr($error, 0, 512), $taskId]);
    }
}
