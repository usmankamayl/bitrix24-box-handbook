-- Практика 2: заявки для доменного сервиса с Result.
-- Таблица демонстрационная: префикс mycompany_, чтобы не пересечься с ядром.

CREATE TABLE IF NOT EXISTS `mycompany_request`
(
    `ID`            INT(11)      NOT NULL AUTO_INCREMENT,
    `TITLE`         VARCHAR(255) NOT NULL COMMENT 'Тема заявки',
    `STATUS`        VARCHAR(20)  NOT NULL DEFAULT 'new' COMMENT 'new | in_work | closed',
    `AUTHOR_ID`     INT(11)      NOT NULL COMMENT 'Автор заявки',
    `CLOSED_AT`     DATETIME     DEFAULT NULL COMMENT 'Когда закрыта',
    `CLOSE_COMMENT` TEXT         DEFAULT NULL COMMENT 'Комментарий при закрытии',
    `UPDATED_AT`    DATETIME     NOT NULL COMMENT 'Обновлено',
    PRIMARY KEY (`ID`),
    INDEX `IX_MYCOMPANY_REQUEST_STATUS` (`STATUS`),
    INDEX `IX_MYCOMPANY_REQUEST_AUTHOR` (`AUTHOR_ID`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT 'Заявки (пример к модулю 1.8)';
