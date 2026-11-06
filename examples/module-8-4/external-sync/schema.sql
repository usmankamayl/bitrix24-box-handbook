-- SQL схема для синхронизации с внешними системами

-- Таблица логов синхронизации
CREATE TABLE IF NOT EXISTS `external_sync_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `webhook_id` VARCHAR(255) NOT NULL COMMENT 'ID вебхука от Bitrix24',
    `event_name` VARCHAR(100) NOT NULL COMMENT 'Название события',
    `event_data` JSON NOT NULL COMMENT 'Данные события',
    `created_at` DATETIME NOT NULL COMMENT 'Время создания',
    `processed_at` DATETIME NOT NULL COMMENT 'Время обработки',
    `status` ENUM('pending', 'processed', 'failed') DEFAULT 'pending',

    INDEX `idx_webhook_id` (`webhook_id`),
    INDEX `idx_event_name` (`event_name`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Лог событий синхронизации';

-- Таблица истории синхронизации
CREATE TABLE IF NOT EXISTS `sync_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `system` VARCHAR(50) NOT NULL COMMENT 'Внешняя система (1C, external_crm, и т.д.)',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'Тип сущности (deal, contact, и т.д.)',
    `entity_id` INT UNSIGNED NOT NULL COMMENT 'ID сущности в Bitrix24',
    `status` ENUM('success', 'error', 'retry') NOT NULL COMMENT 'Статус синхронизации',
    `error_message` TEXT NULL COMMENT 'Сообщение об ошибке',
    `details` JSON NULL COMMENT 'Дополнительные детали',
    `synced_at` DATETIME NOT NULL COMMENT 'Время синхронизации',

    INDEX `idx_system` (`system`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_synced_at` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='История синхронизации с внешними системами';

-- Таблица маппинга ID между системами
CREATE TABLE IF NOT EXISTS `external_id_mapping` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'Тип сущности (deal, contact, company)',
    `bitrix_id` INT UNSIGNED NOT NULL COMMENT 'ID в Bitrix24',
    `external_system` VARCHAR(50) NOT NULL COMMENT 'Внешняя система (1C, external_crm)',
    `external_id` VARCHAR(255) NOT NULL COMMENT 'ID во внешней системе',
    `created_at` DATETIME NOT NULL COMMENT 'Время создания маппинга',
    `updated_at` DATETIME NOT NULL COMMENT 'Время обновления',

    UNIQUE KEY `unique_mapping` (`entity_type`, `bitrix_id`, `external_system`),
    INDEX `idx_external_id` (`external_system`, `external_id`),
    INDEX `idx_bitrix_id` (`entity_type`, `bitrix_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Маппинг ID между Bitrix24 и внешними системами';

-- Таблица очереди повторной отправки
CREATE TABLE IF NOT EXISTS `sync_retry_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `system` VARCHAR(50) NOT NULL COMMENT 'Внешняя система',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'Тип сущности',
    `entity_id` INT UNSIGNED NOT NULL COMMENT 'ID сущности',
    `data` JSON NOT NULL COMMENT 'Данные для отправки',
    `retry_count` TINYINT UNSIGNED DEFAULT 0 COMMENT 'Количество попыток',
    `max_retries` TINYINT UNSIGNED DEFAULT 5 COMMENT 'Максимум попыток',
    `next_retry_at` DATETIME NOT NULL COMMENT 'Время следующей попытки',
    `created_at` DATETIME NOT NULL COMMENT 'Время создания',
    `last_error` TEXT NULL COMMENT 'Последняя ошибка',

    INDEX `idx_next_retry` (`next_retry_at`),
    INDEX `idx_system` (`system`),
    INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Очередь повторной отправки при ошибках';

-- Примеры запросов

-- 1. Статистика синхронизации за последние 7 дней
-- SELECT
--     system,
--     entity_type,
--     status,
--     COUNT(*) as count,
--     DATE(synced_at) as date
-- FROM sync_history
-- WHERE synced_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY system, entity_type, status, DATE(synced_at)
-- ORDER BY date DESC;

-- 2. Последние ошибки синхронизации
-- SELECT
--     system,
--     entity_type,
--     entity_id,
--     error_message,
--     synced_at
-- FROM sync_history
-- WHERE status = 'error'
-- ORDER BY synced_at DESC
-- LIMIT 20;

-- 3. Найти external_id для сущности Bitrix
-- SELECT external_id FROM external_id_mapping
-- WHERE entity_type = 'deal'
--   AND bitrix_id = 123
--   AND external_system = '1C';

-- 4. Элементы в очереди повторной отправки
-- SELECT * FROM sync_retry_queue
-- WHERE next_retry_at <= NOW()
--   AND retry_count < max_retries
-- ORDER BY next_retry_at ASC;

-- 5. Очистка старых логов (старше 90 дней)
-- DELETE FROM external_sync_log
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
--
-- DELETE FROM sync_history
-- WHERE synced_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
