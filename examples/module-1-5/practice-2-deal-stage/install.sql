-- Практика 2 (1.5): журнал идемпотентности служебной точки входа.
-- Таблица — не «кеш на 10 минут», а источник истины: UNIQUE-индекс и есть
-- захват запроса. Второй INSERT с тем же ключом падает, и мы возвращаем
-- сохранённый ответ вместо повторного действия.
--
-- Префикс mycompany_ — чтобы не пересечься с таблицами ядра и модулей.

CREATE TABLE IF NOT EXISTS `mycompany_deal_stage_request`
(
    `ID`         INT(11)        NOT NULL AUTO_INCREMENT,
    `USER_ID`    INT(11)        NOT NULL COMMENT 'Кто вызвал',
    `REQUEST_ID` VARCHAR(64)    NOT NULL COMMENT 'Ключ идемпотентности от клиента',
    `DEAL_ID`    INT(11)        NOT NULL COMMENT 'Сделка',
    `STAGE_ID`   VARCHAR(50)    NOT NULL COMMENT 'Целевая стадия',
    `HTTP_CODE`  SMALLINT(6)    NOT NULL DEFAULT 0 COMMENT '0 — запрос в работе, >0 — готовый ответ',
    `RESPONSE`   MEDIUMTEXT     NULL COMMENT 'Тело ответа для повтора, байт в байт',
    `CREATED_AT` DATETIME       NOT NULL COMMENT 'Захват ключа; по нему же определяется «зависший» запрос',
    PRIMARY KEY (`ID`),
    UNIQUE KEY `UX_MYCOMPANY_DSR_USER_REQUEST` (`USER_ID`, `REQUEST_ID`),
    KEY `IX_MYCOMPANY_DSR_CREATED` (`CREATED_AT`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT 'Журнал идемпотентности смены стадии (пример к 1.5)';
