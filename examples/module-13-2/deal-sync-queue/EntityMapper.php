<?php

require_once __DIR__ . '/config.php';

final class EntityMapper
{
    public function find(string $entityType, int $bitrixId, string $externalSystem): ?array
    {
        $stmt = getPdo()->prepare(
            'SELECT * FROM entity_mapping
             WHERE entity_type = ? AND bitrix_id = ? AND external_system = ?'
        );
        $stmt->execute([$entityType, $bitrixId, $externalSystem]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(string $entityType, int $bitrixId, string $externalSystem, string $externalId, ?string $hash = null): void
    {
        getPdo()->prepare(
            'INSERT INTO entity_mapping
                (entity_type, bitrix_id, external_system, external_id, last_synced_hash, updated_at)
             VALUES (:entity_type, :bitrix_id, :external_system, :external_id, :hash, NOW())
             ON DUPLICATE KEY UPDATE
                external_id = VALUES(external_id),
                last_synced_hash = VALUES(last_synced_hash),
                updated_at = NOW()'
        )->execute([
            'entity_type' => $entityType,
            'bitrix_id' => $bitrixId,
            'external_system' => $externalSystem,
            'external_id' => $externalId,
            'hash' => $hash,
        ]);
    }

    public function updateHash(int $mappingId, string $hash): void
    {
        getPdo()->prepare(
            'UPDATE entity_mapping SET last_synced_hash = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$hash, $mappingId]);
    }
}
