<?php
/**
 * Конфигурационный файл для обработчика вебхуков
 *
 * ВАЖНО: Скопируйте этот файл в config.php и укажите свои настройки
 * Файл config.php должен быть добавлен в .gitignore
 */

// === БЕЗОПАСНОСТЬ ===

// Секретный токен для защиты вебхука
// Используется в URL: https://ваш-сайт.com/webhook-handler.php?token=ВАШ_ТОКЕН
define('WEBHOOK_SECRET_TOKEN', 'ваш-секретный-токен-12345');

// Секрет для проверки подписи Bitrix24 (опционально)
// Указывается при регистрации вебхука в Bitrix24
define('BITRIX24_WEBHOOK_SECRET', '');

// === БАЗА ДАННЫХ ===

// Параметры подключения к MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'bitrix_webhooks');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// === BITRIX24 ===

// URL вебхука для исходящих запросов к Bitrix24
// Формат: https://ваш-портал.bitrix24.ru/rest/1/ваш_ключ/
define('BITRIX24_WEBHOOK_URL', 'https://ваш-портал.bitrix24.ru/rest/1/ключ/');

// Домен портала Bitrix24
define('BITRIX24_DOMAIN', 'https://ваш-портал.bitrix24.ru');

// === УВЕДОМЛЕНИЯ ===

// ID администраторов для уведомлений
define('ADMIN_USER_IDS', [1]);

// Telegram Bot Token (опционально)
define('TELEGRAM_BOT_TOKEN', '');

// Telegram Chat IDs для уведомлений (опционально)
define('TELEGRAM_CHAT_IDS', []);

// === НАСТРОЙКИ ЛОГИРОВАНИЯ ===

// Уровень логирования (DEBUG, INFO, WARNING, ERROR)
define('LOG_LEVEL', 'INFO');

// Максимальный размер лог-файла (в байтах)
define('MAX_LOG_FILE_SIZE', 10 * 1024 * 1024); // 10 MB

// Срок хранения логов в базе данных (дни)
define('LOG_RETENTION_DAYS', 30);

// === ПРОИЗВОДИТЕЛЬНОСТЬ ===

// Таймаут для внешних запросов (секунды)
define('EXTERNAL_REQUEST_TIMEOUT', 10);

// Максимальное количество повторных попыток при ошибках
define('MAX_RETRY_ATTEMPTS', 3);

// === ДОПОЛНИТЕЛЬНО ===

// Режим отладки (true/false)
define('DEBUG_MODE', false);

// Временная зона
date_default_timezone_set('Europe/Moscow');

// Кодировка
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
