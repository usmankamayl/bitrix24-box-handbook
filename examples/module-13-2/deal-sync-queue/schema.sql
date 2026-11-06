-- Соответствие сущностей Bitrix24 <-> внешняя система (AmoCRM, 1С, др.)
CREATE TABLE IF NOT EXISTS entity_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(32) NOT NULL,
    bitrix_id INT NOT NULL,
    external_system VARCHAR(32) NOT NULL,
    external_id VARCHAR(64) NOT NULL,

    -- md5 от последнего отправленного состояния — чтобы не слать UPDATE,
    -- если синхронизируемые поля фактически не менялись
    last_synced_hash VARCHAR(32) NULL,

    updated_at DATETIME NOT NULL,

    UNIQUE KEY uniq_bitrix (entity_type, bitrix_id, external_system),
    UNIQUE KEY uniq_external (entity_type, external_system, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Очередь задач на синхронизацию: событие CRM только кладёт сюда запись,
-- саму отправку во внешнюю систему выполняет отдельный крон-агент (agent.php)
CREATE TABLE IF NOT EXISTS sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(32) NOT NULL,
    entity_id INT NOT NULL,
    external_system VARCHAR(32) NOT NULL,

    status ENUM('pending', 'done', 'failed') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    last_error VARCHAR(512) NULL,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
