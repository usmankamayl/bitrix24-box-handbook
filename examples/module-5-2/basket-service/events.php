<?php

/**
 * Подключить в /local/php_interface/init.php:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/local/basket/events.php';
 */

namespace BitrixCourse\Basket;

use Bitrix\Main\Loader;
use Bitrix\Sale;

require_once __DIR__ . '/lib/BasketService.php';

if (Loader::includeModule('sale')) {
    \AddEventHandler('main', 'OnBeforeUserLogin', __NAMESPACE__ . '\\onBeforeUserLoginHandler');
    \AddEventHandler('main', 'OnUserLogin', __NAMESPACE__ . '\\onUserLoginHandler');
}

/**
 * Запоминаем FUSER_ID анонима ДО авторизации. Если сделать это в
 * OnUserLogin — будет поздно: CUser::Authorize() уже отработал,
 * и Sale\Fuser::getId() на этот момент может вернуть уже другой
 * (привязанный к пользователю) fuser, а анонимную корзину мы потеряем.
 */
function onBeforeUserLoginHandler(&$arFields): void
{
    $GLOBALS['~anonymousFuserIdBeforeLogin'] = Sale\Fuser::getId();
}

function onUserLoginHandler(&$arFields): void
{
    $anonymousFuserId = (int)($GLOBALS['~anonymousFuserIdBeforeLogin'] ?? 0);
    unset($GLOBALS['~anonymousFuserIdBeforeLogin']);

    if ($anonymousFuserId <= 0) {
        return;
    }

    $userId = (int)($arFields['USER_ID'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    // mergeAnonymousBasket сама считает текущий (уже авторизованный) fuser —
    // достаточно передать только старый анонимный ID.
    (new BasketService())->mergeAnonymousBasket($anonymousFuserId);
}
