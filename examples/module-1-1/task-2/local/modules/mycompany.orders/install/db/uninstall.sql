-- Удаление таблицы при деинсталляции модуля.
-- В реальном проекте здесь либо DROP, либо сохранение таблицы с данными
-- (тогда uninstall.sql пустой, а UnInstallDB() не вызывается).

DROP TABLE IF EXISTS mycompany_orders;
