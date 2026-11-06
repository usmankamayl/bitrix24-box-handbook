<?php

/**
 * Подключить в /local/php_interface/init.php:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/partner_pricing/events.php';
 */

require_once __DIR__ . '/lib/PartnerMarkupTable.php';
require_once __DIR__ . '/lib/PartnerPriceCalculator.php';

use Bitrix\Main\Loader;
use BitrixCourse\PartnerPricing\PartnerPriceCalculator;

if (Loader::includeModule('catalog')) {
    AddEventHandler(
        'catalog',
        'OnGetOptimalPrice',
        [PartnerPriceCalculator::class, 'onGetOptimalPriceHandler']
    );
}
