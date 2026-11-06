-- Таблица проектного журнала для practice-1-log-cleanup.
-- Выполнить один раз в БД инсталляции:
--   mysql -u root -p b24 < install.sql

CREATE TABLE IF NOT EXISTS mycompany_agents_log (
    ID INT NOT NULL AUTO_INCREMENT,
    CODE VARCHAR(64) NOT NULL,
    LEVEL VARCHAR(16) NOT NULL DEFAULT 'INFO',
    MESSAGE TEXT NOT NULL,
    CREATED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    KEY ix_mycompany_agents_log_created (CREATED_AT),
    KEY ix_mycompany_agents_log_code (CODE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
