<?php
/**
 * Конфигурация для синхронизации с внешними системами
 */

// === БАЗОВЫЕ НАСТРОЙКИ ===
require_once __DIR__ . '/../lead-events/config.php';

// === ИНТЕГРАЦИЯ С 1C ===

// Включить синхронизацию с 1C
define('ENABLE_1C_SYNC', true);

// URL API 1C
define('ONE_C_API_URL', 'http://1c-server.company.local:8080/api/v1');

// Учетные данные для доступа к 1C
define('ONE_C_API_USER', 'bitrix_user');
define('ONE_C_API_PASSWORD', 'password123');

// === ВНЕШНЯЯ CRM ===

// Включить синхронизацию с внешней CRM
define('ENABLE_EXTERNAL_CRM_SYNC', false);

// URL внешней CRM
define('EXTERNAL_CRM_API_URL', 'https://external-crm.com/api/v2');

// API ключ
define('EXTERNAL_CRM_API_KEY', 'your-api-key-here');

// === SLACK ИНТЕГРАЦИЯ ===

// Включить уведомления в Slack
define('ENABLE_SLACK_NOTIFICATIONS', false);

// Webhook URL для Slack
define('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL');

// Канал по умолчанию
define('SLACK_DEFAULT_CHANNEL', '#sales');

// Канал для уведомлений о продажах
define('SLACK_CHANNEL_SALES', '#sales-wins');

// === TELEGRAM ИНТЕГРАЦИЯ ===

// Включить уведомления в Telegram
define('ENABLE_TELEGRAM_NOTIFICATIONS', false);

// Bot Token (получите у @BotFather)
define('TELEGRAM_BOT_TOKEN', '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');

// Chat IDs для уведомлений
define('TELEGRAM_CHAT_IDS', [
    123456789,  // Чат 1
    987654321   // Чат 2
]);

// === НАСТРОЙКИ СИНХРОНИЗАЦИИ ===

// Таймаут для запросов к внешним системам (секунды)
define('EXTERNAL_REQUEST_TIMEOUT', 30);

// Максимальное количество повторных попыток
define('MAX_SYNC_RETRIES', 5);

// Интервал между повторными попытками (секунды)
// Используется экспоненциальная задержка: delay * 2^attempt
define('RETRY_DELAY_BASE', 60);

// Включить очередь для отложенной обработки
define('ENABLE_RETRY_QUEUE', true);

// === ФИЛЬТРЫ СИНХРОНИЗАЦИИ ===

// Синхронизировать только сделки с суммой больше указанной
define('MIN_DEAL_AMOUNT_FOR_SYNC', 0);

// Синхронизировать только определенные стадии сделок
define('SYNC_DEAL_STAGES', [
    'C1:PREPARATION',
    'C1:PREPAYMENT_INVOICE',
    'C1:EXECUTING',
    'C1:WON'
]);

// Синхронизировать все стадии (установите true, чтобы игнорировать фильтр выше)
define('SYNC_ALL_DEAL_STAGES', true);

// === МАППИНГ ПОЛЕЙ ===

// Маппинг типов сделок Bitrix → 1C
define('DEAL_TYPE_MAPPING', [
    'SALE' => 'Продажа',
    'SERVICE' => 'Услуга',
    'RENT' => 'Аренда'
]);

// Маппинг источников
define('SOURCE_MAPPING', [
    'CALL' => 'Звонок',
    'EMAIL' => 'Email',
    'WEB' => 'Сайт',
    'OTHER' => 'Другое'
]);

// === ЛОГИРОВАНИЕ ===

// Детальное логирование (включать только для отладки)
define('DETAILED_SYNC_LOGGING', false);

// Срок хранения логов синхронизации (дни)
define('SYNC_LOG_RETENTION_DAYS', 90);

// === ДОПОЛНИТЕЛЬНО ===

// Отправлять уведомления об ошибках синхронизации
define('NOTIFY_ON_SYNC_ERRORS', true);

// Email для уведомлений об ошибках
define('SYNC_ERROR_EMAIL', 'admin@company.com');
