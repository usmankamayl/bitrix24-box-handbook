-- Контрольное задание: таблицы заявок и журнала закрытий.

CREATE TABLE IF NOT EXISTS `mycompany_request`
(
    `ID`            INT(11)      NOT NULL AUTO_INCREMENT,
    `TITLE`         VARCHAR(255) NOT NULL,
    `STATUS`        VARCHAR(20)  NOT NULL DEFAULT 'new',
    `AUTHOR_ID`     INT(11)      NOT NULL,
    `CLOSED_AT`     DATETIME     DEFAULT NULL,
    `CLOSE_COMMENT` TEXT         DEFAULT NULL,
    `UPDATED_AT`    DATETIME     NOT NULL,
    PRIMARY KEY (`ID`),
    INDEX `IX_MYCOMPANY_REQUEST_STATUS` (`STATUS`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mycompany_request_log`
(
    `ID`         INT(11)      NOT NULL AUTO_INCREMENT,
    `REQUEST_ID` INT(11)      NOT NULL,
    `ACTION`     VARCHAR(32)  NOT NULL,
    `COMMENT`    TEXT         DEFAULT NULL,
    `CREATED_AT` DATETIME     NOT NULL,
    PRIMARY KEY (`ID`),
    INDEX `IX_MYCOMPANY_REQUEST_LOG_REQUEST` (`REQUEST_ID`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
