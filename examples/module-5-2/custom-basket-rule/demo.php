<?php

/**
 * Применяет правило "каждый 3-й товар — минус 50%" к текущей корзине
 * (по cookie-сессии, как на обычном фронте) и сохраняет её.
 *
 * В реальном проекте NthItemDiscountRule::apply() встраивается прямо
 * в сервисный слой корзины (см. examples/module-5-2/basket-service)
 * — вызов ставится непосредственно перед $basket->save() в
 * BasketService::addProduct()/updateQuantity().
 */

define('STOP_STATISTICS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once __DIR__ . '/lib/NthItemDiscountCalculator.php';
require_once __DIR__ . '/lib/NthItemDiscountRule.php';

use Bitrix\Main\Loader;
use Bitrix\Sale;
use BitrixCourse\BasketDiscount\NthItemDiscountRule;

if (!Loader::includeModule('sale')) {
    die('Модуль sale не установлен');
}

// ID товаров — участников акции "каждый 3-й дешевле на 50%"
$eligibleProductIds = [101, 205];

$fuserId = Sale\Fuser::getId();
$basket = Sale\Basket::loadItemsForFUser($fuserId, SITE_ID);

$rule = new NthItemDiscountRule($eligibleProductIds);

if ($rule->apply($basket)) {
    $result = $basket->save();
    echo $result->isSuccess()
        ? "Скидка применена и сохранена.\n"
        : implode("\n", $result->getErrorMessages()) . "\n";
} else {
    echo "Нечего менять: либо товаров-участников нет в корзине, либо скидка уже применена.\n";
}
