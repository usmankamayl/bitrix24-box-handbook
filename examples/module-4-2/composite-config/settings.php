<?php
/**
 * Конфигурация Composite в .settings.php.
 *
 * Файл: /bitrix/.settings.php
 * (для коробки Bitrix24 — /local/php_interface/.settings.php
 *  или через Настройки → Композитный сайт)
 */

return [
    // ==========================================
    // Composite
    // ==========================================
    'composite' => [
        'value' => [
            // Включить композитный режим
            'enabled' => true,

            // Хранилище HTML-кеша:
            // 'files'    — /bitrix/composite_cache/ (медленно на NFS)
            // 'memcached' — быстро, но нужен memcached
            // 'redis'     — самый быстрый, нужен redis
            'cache_manager' => 'redis',

            // Redis для composite
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'db'   => 2,              // отдельная база для composite!
            ],

            // TTL HTML-кеша (секунды)
            // 120 = 2 минуты — компромисс между актуальностью и нагрузкой
            'ttl' => 120,

            // Автоматическое обновление кеша при изменении данных
            // Если TRUE — при изменении элемента инфоблока связанные
            // страницы пересобираются автоматически
            'auto_update' => true,
            // Задержка перед пересборкой (сек) — чтобы не генерировать
            // кеш при серии быстрых изменений
            'auto_update_ttl' => 60,

            // Режим работы с динамическими областями
            'include_dynamic_areas' => true,

            // Домены, для которых работает composite
            'domains' => ['example.com', 'www.example.com'],

            // ==========================================
            // Исключения — НЕ кешировать
            // ==========================================

            // Маски URL, которые всегда динамические
            'exclude_mask' => [
                '/personal/*',          // Личный кабинет
                '/cart/*',              // Корзина
                '/order/*',             // Оформление заказа
                '/auth/*',              // Авторизация
                '/search/*',            // Поиск (слишком много вариаций)
                '/bitrix/*',            // Админка
                '*?*nc=1*',             // Параметр "не кешировать"
                '*?*clear_cache*',      // Принудительный сброс
            ],

            // GET-параметры, при которых страница не кешируется
            // UTM-метки, gclid и т.д. — иначе каждая рекламная кампания
            // создаст свою копию кеша
            'exclude_params' => [
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'gclid',                // Google Ads
                'yclid',                // Яндекс.Директ
                'fbclid',               // Facebook
                'openstat',
                'from',
            ],

            // Cookie, при наличии которых не кешируем
            // Если у пользователя есть эта cookie — страница персонализирована
            'exclude_cookies' => [
                'BITRIX_SM_LOGIN',      // Авторизованный пользователь
                'BITRIX_SM_UIDH',       // Хеш пользователя
                'BX_USER_ID',
            ],

            // ==========================================
            // Отладка
            // ==========================================

            // Логирование composite-операций
            // Файл: /bitrix/logs/composite.log
            'debug' => false,           // включать только при проблемах!

            // HTTP-заголовок X-Bitrix-Composite для отладки
            // cache | dynamic | hit | miss | update
            'show_header' => true,

            // Статистика в админке
            'statistic' => true,
        ],
    ],
];

/*
 * ==========================================
 * Как отладить Composite
 * ==========================================

1. Проверь заголовок ответа:
   curl -I https://site.com/catalog/
   → X-Bitrix-Composite: cache  (отдал из кеша)
   → X-Bitrix-Composite: dynamic (страница динамическая — ищи причину)
   → X-Bitrix-Composite: miss   (кеш был, но протух)

2. Включи debug → смотри /bitrix/logs/composite.log:
   [2024-01-15 14:32:01] Page: /catalog/123/ | Mode: auto | Vote: 1
   → "Vote: 0" значит какой-то компонент проголосовал "против"

3. Проверь исключения:
   - URL в exclude_mask?
   - Есть exclude_params?
   - У пользователя есть exclude_cookies?

4. Файловый кеш — смотри /bitrix/composite_cache/:
   ls /bitrix/composite_cache/en/ | wc -l
   → Если 0 — composite не срабатывает

5. Проблема "динамическая страница на всех":
   - Где-то вызван $frame->finishDynamicArea() без startDynamicArea
   - Компонент с setFrameMode(false)
   - Вывод кода ДО начала буферизации (notice, warning, BOM в файле)

 * ==========================================
 * Мониторинг Composite
 * ==========================================

// В админке: Настройки → Композитный сайт → Статистика
// Смотреть:
// - Hit rate (должен быть > 90%)
// - Average generation time
// - Cache size

// CLI-проверка
$status = \Bitrix\Main\Composite\Engine::getStatus();
print_r($status);

 * ==========================================
 * Сброс composite-кеша
 * ==========================================

// Программно
\Bitrix\Main\Composite\Engine::clearCache();

// Файловый
rm -rf /bitrix/composite_cache/en/*
rm -rf /bitrix/composite_cache/ru/*

// При деплое — часто забывают, composite может отдавать
// устаревшие страницы. Лучше включить в deploy-скрипт.
*/
