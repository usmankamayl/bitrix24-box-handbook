#!/usr/bin/env php
<?php
/**
 * CLI-скрипт прогрева кеша.
 *
 * Использование:
 *   php cache-warmer.php                          — прогреть всё
 *   php cache-warmer.php --type=products          — только товары
 *   php cache-warmer.php --type=products --limit=500 --offset=1000
 *
 * Cron:
 *   */15 * * * * php /path/to/cache-warmer.php --type=products >> /var/log/warmer.log
 *
 * Через агент Bitrix:
 *   \Vendor\Shop\Cache\runWarmAgent();  // раз в час
 */

// Запускаем в контексте Bitrix без сессии
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3); // подстрой под свой путь
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Vendor\Shop\Cache\CacheWarmer;
use Vendor\Shop\Cache\WarmerIdProviders;
use Vendor\Shop\Repository\ProductRepository;

// Парсинг аргументов CLI
$options = getopt('', ['type::', 'limit::', 'offset::']);
$type    = $options['type']   ?? 'all';
$limit   = (int)($options['limit'] ?? 0);
$offset  = (int)($options['offset'] ?? 0);

$warmer = new CacheWarmer();
$repo   = new ProductRepository();

// Регистрируем типы прогрева
$warmer
    ->register(
        'products',
        [WarmerIdProviders::class, 'topProducts'],
        function ($id) use ($repo) {
            $repo->getById((int)$id);
        }
    )
    ->register(
        'sections',
        [WarmerIdProviders::class, 'allSections'],
        function ($id) use ($repo) {
            $repo->getBySection((int)$id);
        }
    );


if ($type === 'all') {
    foreach (['products', 'sections'] as $t) {
        echo "=== Warming {$t} ===\n";
        $warmer->warm($t, $limit, $offset);
    }
} else {
    echo "=== Warming {$type} ===\n";
    $warmer->warm($type, $limit, $offset);
}


// ============================================================
// Альтернатива: агент Bitrix (для cron через админку)
// ============================================================

/**
 * Агент для автоматического прогрева.
 * Добавить в /local/php_interface/init.php:
 *
 *   \CAgent::AddAgent(
 *       "\\Vendor\\Shop\\Cache\\runProductsWarmAgent();",
 *       'vendor.shop',
 *       'N',
 *       3600  // каждый час
 *   );
 */
function runProductsWarmAgent(): string
{
    $warmer = new CacheWarmer();
    $repo   = new ProductRepository();

    $warmer->register(
        'products',
        [WarmerIdProviders::class, 'topProducts'],
        fn($id) => $repo->getById((int)$id)
    );

    // В агенте ограничиваем время выполнения — не вешаем cron
    $warmer->warm('products', limit: 500);

    // Возвращаем строку — Bitrix вызовет функцию снова
    return '\\Vendor\\Shop\\Cache\\runProductsWarmAgent();';
}
