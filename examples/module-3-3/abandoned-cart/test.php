<?php
/**
 * Тестирование отправки напоминания о брошенной корзине
 *
 * Отправляет тестовое письмо без реальной проверки корзины
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Bitrix\Main\Mail\Event;
use Bitrix\Main\Loader;

Loader::includeModule('sale');

echo "<h1>Тест отправки напоминания о брошенной корзине</h1>";
echo "<pre>";

// Тестовые данные
$testEmail = 'test@example.com'; // ЗАМЕНИТЕ НА СВОЙ EMAIL

$cartItems = '
<div style="border-bottom: 1px solid #eee; padding: 15px 0;">
    <div style="font-weight: bold;">Apple iPhone 14 Pro 128GB</div>
    <div style="color: #666; font-size: 14px;">1 × 89 990 ₽ = 89 990 ₽</div>
</div>
<div style="border-bottom: 1px solid #eee; padding: 15px 0;">
    <div style="font-weight: bold;">AirPods Pro 2</div>
    <div style="color: #666; font-size: 14px;">1 × 24 990 ₽ = 24 990 ₽</div>
</div>
<div style="border-bottom: 1px solid #eee; padding: 15px 0;">
    <div style="font-weight: bold;">Чехол Silicone Case</div>
    <div style="color: #666; font-size: 14px;">1 × 3 990 ₽ = 3 990 ₽</div>
</div>
';

$fields = [
    'USER_NAME' => 'Иван Тестовый',
    'USER_EMAIL' => $testEmail,
    'CART_ITEMS' => $cartItems,
    'CART_TOTAL' => '118 970 ₽',
    'CART_COUNT' => 3,
    'CART_URL' => '/personal/cart/',
    'PROMO_CODE' => 'COMEBACK123',
    'PROMO_DISCOUNT' => 10,
    'SITE_NAME' => 'Тестовый Магазин',
    'SERVER_NAME' => $_SERVER['HTTP_HOST']
];

// Отправляем письмо
$result = Event::send([
    'EVENT_NAME' => 'ABANDONED_CART_REMINDER',
    'LID' => SITE_ID,
    'C_FIELDS' => $fields
]);

if ($result->isSuccess()) {
    echo "✓ Тестовое письмо отправлено на {$testEmail}\n";
    echo "Проверьте почту (включая папку Спам)\n";
} else {
    echo "✗ Ошибка отправки: " . implode(', ', $result->getErrorMessages()) . "\n";
}

echo "</pre>";
echo "<p><a href='/bitrix/admin/event_list.php'>Посмотреть очередь почтовых событий</a></p>";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
