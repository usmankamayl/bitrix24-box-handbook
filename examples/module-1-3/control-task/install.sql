-- Очередь для контрольного задания (control-task).
--   mysql -u root -p b24 < install.sql

CREATE TABLE IF NOT EXISTS mycompany_agents_control_queue (
    ID INT NOT NULL AUTO_INCREMENT,
    EMAIL VARCHAR(255) NOT NULL,
    STATUS VARCHAR(16) NOT NULL DEFAULT 'new',
    ATTEMPTS INT NOT NULL DEFAULT 0,
    LAST_ERROR TEXT NULL,
    UPDATED_AT DATETIME NULL,
    PRIMARY KEY (ID),
    KEY ix_mycompany_agents_control_queue_status (STATUS)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
