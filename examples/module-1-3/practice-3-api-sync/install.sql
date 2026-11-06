-- Таблицы синхронизации с внешним API (practice-3-api-sync).
--   mysql -u root -p b24 < install.sql

CREATE TABLE IF NOT EXISTS mycompany_agents_sync_state (
    CODE VARCHAR(64) NOT NULL,
    CURSOR VARCHAR(255) NULL,
    PAGES_DONE INT NOT NULL DEFAULT 0,
    ITEMS_DONE INT NOT NULL DEFAULT 0,
    UPDATED_AT DATETIME NULL,
    PRIMARY KEY (CODE)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mycompany_agents_sync_item (
    ID INT NOT NULL AUTO_INCREMENT,
    EXTERNAL_ID VARCHAR(64) NOT NULL,
    TITLE VARCHAR(255) NOT NULL,
    CHANGED_AT DATETIME NULL,
    SYNCED_AT DATETIME NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY ux_mycompany_agents_sync_item_ext (EXTERNAL_ID),
    KEY ix_mycompany_agents_sync_item_changed (CHANGED_AT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
