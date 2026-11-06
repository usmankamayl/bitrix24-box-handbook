-- Таблица установок приложения: одна строка = один портал (арендатор)
CREATE TABLE IF NOT EXISTS app_installations (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Постоянный идентификатор портала. НЕ меняется при смене домена —
    -- ключуем именно по нему, а не по domain.
    member_id VARCHAR(64) NOT NULL,

    -- Текущий домен портала, нужен только для построения REST-URL.
    -- Может меняться со временем, обновляем при каждой установке/событии.
    domain VARCHAR(255) NOT NULL,

    access_token VARCHAR(255) NOT NULL,
    refresh_token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    scope VARCHAR(255) NOT NULL,

    -- application_token из события ONAPPINSTALL — используется для проверки
    -- подлинности последующих событий (ONAPPUNINSTALL и др.) от этого портала.
    application_token VARCHAR(64) NOT NULL,

    status ENUM('active', 'uninstalled') NOT NULL DEFAULT 'active',

    installed_at DATETIME NOT NULL,
    uninstalled_at DATETIME NULL,
    purge_after DATETIME NULL,
    updated_at DATETIME NOT NULL,

    UNIQUE KEY uniq_member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
