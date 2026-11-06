<?php
/**
 * Code Review #2 — Исправленный REST-эндпоинт повтора заказа
 *
 * Исправлены все проблемы из bad_api.php.
 */

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Sale\Fuser;

Loader::includeModule('sale');

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    die();
}

// БЫЛО: $orderId = $_REQUEST['order_id']; — без приведения типа
// СТАЛО: явное приведение к int — защита от инъекций и странных значений
$orderId = (int)($_REQUEST['order_id'] ?? 0);
if (!$orderId) {
    respond(['error' => 'order_id required'], 400);
}

// Порядок проверок: auth -> sessid -> owner -> business rules
global $USER;
if (!$USER->IsAuthorized()) {
    respond(['error' => 'unauthorized'], 401);
}

if (!check_bitrix_sessid()) {
    respond(['error' => 'invalid sessid'], 403);
}

$order = Order::load($orderId);
if (!$order) {
    respond(['error' => 'not found'], 404);
}

// БЫЛО: проверки владельца НЕ БЫЛО вообще — IDOR!
// Любой авторизованный пользователь мог повторить ЧУЖОЙ заказ
// (и получить чужую корзину с чужими адресами в новом заказе).
$userId = (int)$USER->GetID();
if ((int)$order->getUserId() !== $userId) {
    respond(['error' => 'forbidden'], 403);
}

// Повтор заказа — мердж в АКТИВНУЮ корзину пользователя, а не создание нового заказа.
// БЫЛО: сразу создавался новый Order — обходя стандартный checkout,
// пропуская проверку актуальности цен/наличия на складе.
$basket = \Bitrix\Sale\Basket::loadItemsForFUser(Fuser::getId(), SITE_ID);
if (!$basket) {
    $basket = \Bitrix\Sale\Basket::create(SITE_ID);
}

$oldBasket = $order->getBasket();
$addedCount = 0;

foreach ($oldBasket as $item) {
    if ($item->isDelay()) {
        continue;
    }

    $productId = $item->getProductId();

    // Мердж — если товар уже есть в текущей корзине, плюсуем количество,
    // а не создаём дубль позиции.
    // БЫЛО: всегда createItem — при повторном вызове repeat будут дубли строк.
    $existing = null;
    foreach ($basket as $basketItem) {
        if ($basketItem->getProductId() === $productId) {
            $existing = $basketItem;
            break;
        }
    }

    if ($existing) {
        $existing->setField('QUANTITY', $existing->getQuantity() + $item->getQuantity());
    } else {
        $newItem = $basket->createItem('catalog', $productId);
        $newItem->setFields([
            'QUANTITY' => $item->getQuantity(),
            // БЫЛО: PRICE копировалась старая + CUSTOM_PRICE='Y'
            // СТАЛО: CUSTOM_PRICE не ставим — цена пересчитается по актуальным
            // правилам (акции могли закончиться, цена могла измениться).
            'CURRENCY' => $item->getCurrency(),
        ]);
    }
    $addedCount++;
}

if ($addedCount === 0) {
    respond(['error' => 'nothing to repeat — all items are delayed or unavailable'], 400);
}

$saveResult = $basket->save();

// БЫЛО: при ошибке — 'error' => 'save failed' без деталей.
// Для продакшена: детали логируем, пользователю — безопасное сообщение.
if (!$saveResult->isSuccess()) {
    \Bitrix\Main\Diag\Debug::writeToFile(
        [
            'order_id' => $orderId,
            'user_id' => $userId,
            'errors' => $saveResult->getErrorMessages(),
        ],
        'basket repeat failed',
        '/logs/order_repeat_errors.log'
    );
    respond(['error' => 'Не удалось добавить товары в корзину'], 500);
}

// БЫЛО: письмо отправлялось синхронно прямо в обработчике —
// блокирует ответ, и при падении SMTP весь repeat падает с 500,
// хотя товары в корзину уже добавлены.
// СТАЛО: письмо не нужно для "повтора в корзину" — это не создание заказа,
// а просто наполнение корзины. Письмо уйдёт при реальном оформлении
// через обычный checkout-flow (OnSaleOrderSaved).

respond([
    'success' => true,
    'added_items' => $addedCount,
    'basket_count' => $basket->count(),
]);
