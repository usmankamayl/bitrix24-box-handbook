-- Практика 3: остатки и журнал операций для транзакции.

CREATE TABLE IF NOT EXISTS `mycompany_stock`
(
    `ID`             INT(11)     NOT NULL AUTO_INCREMENT,
    `PRODUCT_ID`     INT(11)     NOT NULL COMMENT 'Товар',
    `WAREHOUSE_CODE` VARCHAR(20) NOT NULL COMMENT 'Код склада',
    `QUANTITY`       INT(11)     NOT NULL DEFAULT 0 COMMENT 'Остаток',
    PRIMARY KEY (`ID`),
    UNIQUE KEY `UX_MYCOMPANY_STOCK` (`PRODUCT_ID`, `WAREHOUSE_CODE`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT 'Остатки по складам (пример к модулю 1.8)';

CREATE TABLE IF NOT EXISTS `mycompany_stock_log`
(
    `ID`             INT(11)     NOT NULL AUTO_INCREMENT,
    `PRODUCT_ID`     INT(11)     NOT NULL,
    `WAREHOUSE_FROM` VARCHAR(20) NOT NULL,
    `WAREHOUSE_TO`   VARCHAR(20) NOT NULL,
    `QUANTITY`       INT(11)     NOT NULL,
    `OP_KEY`         VARCHAR(64) NOT NULL COMMENT 'Ключ операции: защита от повтора',
    `CREATED_AT`     DATETIME    NOT NULL,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `UX_MYCOMPANY_STOCK_LOG_OP` (`OP_KEY`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT 'Журнал перемещений (пример к модулю 1.8)';
