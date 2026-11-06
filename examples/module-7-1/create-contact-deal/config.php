<?php
/**
 * config.php - Конфигурация для подключения к Bitrix24
 *
 * Содержит учетные данные для работы с REST API Bitrix24
 */

// Внимание! В production-коде используйте переменные окружения
// или защищенное хранилище конфигурации, НЕ hardcode в коде!

return [
    // Домен вашего портала Bitrix24
    // Формат: название-портала.bitrix24.ru
    'domain' => 'getenv("BITRIX24_DOMAIN") ?: "your-portal.bitrix24.ru"',

    // Webhook-код для авторизации
    // Получить: Администрирование → REST API → Webhook
    // Копируем часть URL после /rest/
    // Пример: 123/456/abc1234567890def
    'webhook' => 'getenv("BITRIX24_WEBHOOK") ?: "your_webhook_code_here"',

    // Настройки для логирования
    'log' => [
        'enabled' => true,
        'file' => __DIR__ . '/crm_api.log',
        'level' => 'debug',  // debug, info, warning, error
    ],

    // Настройки для обработки ошибок
    'error' => [
        'throw_exceptions' => true,  // Выбрасывать исключения при ошибках
        'log_errors' => true,         // Логировать ошибки
    ],

    // Таймауты для запросов
    'timeout' => 30,  // секунды

    // Максимальное количество повторов при ошибке
    'max_retries' => 3,
];
