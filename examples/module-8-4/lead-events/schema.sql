-- SQL схема для хранения логов вебхуков
-- Создайте эту таблицу в вашей базе данных

CREATE TABLE IF NOT EXISTS `webhook_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `webhook_id` VARCHAR(255) NOT NULL COMMENT 'Уникальный ID вебхука от Bitrix24',
    `event_name` VARCHAR(100) NOT NULL COMMENT 'Название события (onCrmLeadAdd, и т.д.)',
    `event_data` JSON NOT NULL COMMENT 'Данные события в формате JSON',
    `created_at` DATETIME NOT NULL COMMENT 'Когда событие было создано в Bitrix24',
    `processed_at` DATETIME NOT NULL COMMENT 'Когда событие было обработано',
    `status` ENUM('pending', 'processed', 'failed', 'retry') DEFAULT 'pending' COMMENT 'Статус обработки',
    `error_message` TEXT NULL COMMENT 'Сообщение об ошибке, если обработка не удалась',
    `retry_count` TINYINT UNSIGNED DEFAULT 0 COMMENT 'Количество повторных попыток',

    INDEX `idx_webhook_id` (`webhook_id`),
    INDEX `idx_event_name` (`event_name`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_status` (`status`),

    -- Виртуальная колонка для быстрого поиска по ID сущности
    `entity_id` INT UNSIGNED AS (JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.FIELDS.ID'))) VIRTUAL,
    INDEX `idx_entity_id` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Лог вебхуков от Bitrix24';

-- Таблица для хранения конфигурации вебхуков
CREATE TABLE IF NOT EXISTS `webhook_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_name` VARCHAR(100) NOT NULL COMMENT 'Название события',
    `handler_url` VARCHAR(500) NOT NULL COMMENT 'URL обработчика',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Активен ли вебхук',
    `registered_at` DATETIME NOT NULL COMMENT 'Когда был зарегистрирован',
    `last_event_at` DATETIME NULL COMMENT 'Последнее полученное событие',
    `total_events` INT UNSIGNED DEFAULT 0 COMMENT 'Общее количество обработанных событий',

    UNIQUE KEY `unique_event_handler` (`event_name`, `handler_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Конфигурация зарегистрированных вебхуков';

-- Таблица для хранения уведомлений
CREATE TABLE IF NOT EXISTS `notification_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `webhook_log_id` INT UNSIGNED NULL COMMENT 'Связь с webhook_log',
    `user_id` INT UNSIGNED NOT NULL COMMENT 'ID пользователя Bitrix24',
    `type` ENUM('email', 'bitrix24', 'telegram', 'sms') NOT NULL COMMENT 'Тип уведомления',
    `title` VARCHAR(255) NOT NULL COMMENT 'Заголовок уведомления',
    `message` TEXT NOT NULL COMMENT 'Текст уведомления',
    `sent_at` DATETIME NOT NULL COMMENT 'Когда было отправлено',
    `status` ENUM('sent', 'failed') DEFAULT 'sent' COMMENT 'Статус отправки',
    `error` TEXT NULL COMMENT 'Ошибка, если отправка не удалась',

    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_sent_at` (`sent_at`),
    INDEX `idx_webhook_log_id` (`webhook_log_id`),

    FOREIGN KEY (`webhook_log_id`) REFERENCES `webhook_log` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Лог отправленных уведомлений';

-- Примеры запросов для аналитики

-- 1. Количество событий по типам за последние 7 дней
-- SELECT
--     event_name,
--     COUNT(*) as count,
--     DATE(created_at) as date
-- FROM webhook_log
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY event_name, DATE(created_at)
-- ORDER BY date DESC, count DESC;

-- 2. События с ошибками
-- SELECT
--     id,
--     webhook_id,
--     event_name,
--     error_message,
--     retry_count,
--     created_at
-- FROM webhook_log
-- WHERE status = 'failed'
-- ORDER BY created_at DESC
-- LIMIT 50;

-- 3. Статистика по лиду (все события)
-- SELECT
--     event_name,
--     created_at,
--     processed_at,
--     status,
--     JSON_EXTRACT(event_data, '$.FIELDS.STATUS_ID') as status_id,
--     JSON_EXTRACT(event_data, '$.FIELDS.TITLE') as title
-- FROM webhook_log
-- WHERE entity_id = 123
-- ORDER BY created_at DESC;

-- 4. Очистка старых логов (старше 30 дней)
-- DELETE FROM webhook_log
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
