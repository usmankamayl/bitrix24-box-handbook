<?php
/**
 * Установка почтового события для брошенной корзины
 *
 * Создает тип события и шаблон для напоминания о незавершенном заказе
 */

use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\Mail\Internal\EventMessageTable;
use Bitrix\Main\Loader;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

Loader::includeModule('main');

// 1. Создаем тип почтового события
$eventTypeResult = EventTypeTable::add([
    'LID' => 'ru',
    'EVENT_NAME' => 'ABANDONED_CART_REMINDER',
    'NAME' => 'Напоминание о брошенной корзине',
    'DESCRIPTION' =>
        "#USER_NAME# - Имя пользователя\n" .
        "#USER_EMAIL# - Email пользователя\n" .
        "#CART_ITEMS# - Список товаров в корзине (HTML)\n" .
        "#CART_TOTAL# - Общая сумма корзины\n" .
        "#CART_COUNT# - Количество товаров\n" .
        "#CART_URL# - Ссылка на корзину\n" .
        "#PROMO_CODE# - Промокод со скидкой\n" .
        "#PROMO_DISCOUNT# - Размер скидки по промокоду\n" .
        "#SITE_NAME# - Название сайта\n" .
        "#SERVER_NAME# - Адрес сайта"
]);

if ($eventTypeResult->isSuccess()) {
    echo "✓ Тип события ABANDONED_CART_REMINDER создан\n";
} else {
    echo "✗ Ошибка: " . implode(', ', $eventTypeResult->getErrorMessages()) . "\n";
}

// 2. Создаем шаблон письма
$templateResult = EventMessageTable::add([
    'EVENT_NAME' => 'ABANDONED_CART_REMINDER',
    'LID' => 's1',
    'ACTIVE' => 'Y',
    'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
    'EMAIL_TO' => '#USER_EMAIL#',
    'SUBJECT' => 'Не забудьте про товары в корзине! + подарок 🎁',
    'BODY_TYPE' => 'html',
    'MESSAGE' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: white; }
        .header { background: linear-gradient(135deg, #FF6B6B 0%, #FFE66D 100%); padding: 30px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .promo-box { background: #fff3cd; border: 2px dashed #ffc107; padding: 20px; margin: 20px 0; text-align: center; border-radius: 10px; }
        .promo-code { font-size: 24px; font-weight: bold; color: #d9534f; letter-spacing: 2px; margin: 10px 0; }
        .cart-items { margin: 20px 0; }
        .cart-item { display: flex; padding: 15px; border-bottom: 1px solid #eee; align-items: center; }
        .button { display: inline-block; background: #FF6B6B; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .total { font-size: 20px; font-weight: bold; color: #333; text-align: right; margin: 20px 0; }
        .footer { background: #333; color: #999; padding: 20px; text-align: center; font-size: 12px; }
        .timer { background: #d9534f; color: white; padding: 15px; text-align: center; font-weight: bold; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>😔 Вы что-то забыли, #USER_NAME#!</h1>
        </div>

        <div class="content">
            <p>Здравствуйте, <strong>#USER_NAME#</strong>!</p>

            <p>Мы заметили, что вы добавили товары в корзину, но не завершили оформление заказа.</p>

            <div class="timer">
                ⏰ Товары зарезервированы для вас еще на 24 часа!
            </div>

            <h3>В вашей корзине (#CART_COUNT# товаров):</h3>
            <div class="cart-items">
                #CART_ITEMS#
            </div>

            <div class="total">
                Итого: <span style="color: #FF6B6B;">#CART_TOTAL#</span>
            </div>

            <div class="promo-box">
                <p style="margin: 0 0 10px 0;">🎁 <strong>Специально для вас!</strong></p>
                <p>Используйте промокод и получите скидку <strong>#PROMO_DISCOUNT#%</strong></p>
                <div class="promo-code">#PROMO_CODE#</div>
                <p style="font-size: 12px; color: #666; margin: 10px 0 0 0;">Промокод действителен 48 часов</p>
            </div>

            <center>
                <a href="http://#SERVER_NAME##CART_URL#" class="button">Вернуться к корзине и оформить заказ</a>
            </center>

            <p style="margin-top: 30px; font-size: 14px; color: #666;">
                <strong>Почему стоит завершить заказ прямо сейчас?</strong><br>
                ✓ Быстрая доставка от 1 дня<br>
                ✓ Гарантия лучшей цены<br>
                ✓ Бесплатный возврат в течение 14 дней
            </p>

            <p style="font-size: 12px; color: #999;">
                Если у вас возникли вопросы или сложности с оформлением заказа,
                наша служба поддержки готова помочь!
            </p>
        </div>

        <div class="footer">
            <p>&copy; #SITE_NAME# | <a href="http://#SERVER_NAME#" style="color: #FF6B6B;">Перейти на сайт</a></p>
            <p>Вы получили это письмо, потому что добавили товары в корзину на нашем сайте.</p>
        </div>
    </div>
</body>
</html>
    '
]);

if ($templateResult->isSuccess()) {
    echo "✓ Шаблон письма создан (ID: " . $templateResult->getId() . ")\n";
} else {
    echo "✗ Ошибка: " . implode(', ', $templateResult->getErrorMessages()) . "\n";
}

echo "\n=== Установка завершена ===\n";
echo "Далее настройте агент через AbandonedCartAgent.php\n";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
