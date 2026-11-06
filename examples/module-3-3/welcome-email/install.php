<?php
/**
 * Установка типа почтового события и шаблона для Welcome-письма
 *
 * Этот файл создает:
 * 1. Тип почтового события USER_WELCOME
 * 2. Шаблон письма с персонализированным контентом
 *
 * Запуск: включите этот код в миграцию или выполните один раз
 */

use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\Mail\Internal\EventMessageTable;
use Bitrix\Main\Loader;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

Loader::includeModule('main');

// 1. Создаем тип почтового события
$eventTypeResult = EventTypeTable::add([
    'LID' => 'ru',
    'EVENT_NAME' => 'USER_WELCOME',
    'NAME' => 'Welcome-письмо после регистрации',
    'DESCRIPTION' =>
        "#USER_ID# - ID пользователя\n" .
        "#USER_NAME# - Имя пользователя\n" .
        "#USER_LAST_NAME# - Фамилия пользователя\n" .
        "#USER_EMAIL# - Email пользователя\n" .
        "#USER_LOGIN# - Логин пользователя\n" .
        "#SITE_NAME# - Название сайта\n" .
        "#SERVER_NAME# - Адрес сайта\n" .
        "#REGISTER_DATE# - Дата регистрации\n" .
        "#BONUS_POINTS# - Бонусные баллы за регистрацию"
]);

if ($eventTypeResult->isSuccess()) {
    echo "✓ Тип события USER_WELCOME создан\n";
} else {
    echo "✗ Ошибка создания типа события: " . implode(', ', $eventTypeResult->getErrorMessages()) . "\n";
}

// 2. Создаем шаблон письма на русском
$templateResult = EventMessageTable::add([
    'EVENT_NAME' => 'USER_WELCOME',
    'LID' => 's1', // ID вашего сайта
    'ACTIVE' => 'Y',
    'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
    'EMAIL_TO' => '#USER_EMAIL#',
    'SUBJECT' => 'Добро пожаловать на #SITE_NAME#!',
    'BODY_TYPE' => 'html',
    'MESSAGE' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; }
        .bonus { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background: #333; color: #999; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Добро пожаловать, #USER_NAME#!</h1>
        </div>

        <div class="content">
            <p>Здравствуйте, <strong>#USER_NAME# #USER_LAST_NAME#</strong>!</p>

            <p>Спасибо за регистрацию на <strong>#SITE_NAME#</strong>. Мы рады видеть вас в нашем сообществе!</p>

            <div class="bonus">
                <strong>🎁 Подарок за регистрацию:</strong><br>
                На ваш счет начислено <strong>#BONUS_POINTS# бонусных баллов</strong>!<br>
                Используйте их при первой покупке.
            </div>

            <p><strong>Ваши данные для входа:</strong></p>
            <ul>
                <li>Логин: <strong>#USER_LOGIN#</strong></li>
                <li>Email: <strong>#USER_EMAIL#</strong></li>
                <li>Дата регистрации: <strong>#REGISTER_DATE#</strong></li>
            </ul>

            <p>Что делать дальше?</p>
            <ul>
                <li>Заполните профиль для получения персональных рекомендаций</li>
                <li>Подпишитесь на интересующие вас категории</li>
                <li>Сделайте первый заказ и получите дополнительную скидку 10%</li>
            </ul>

            <center>
                <a href="http://#SERVER_NAME#/personal/" class="button">Перейти в личный кабинет</a>
            </center>

            <p>Если у вас возникнут вопросы, наша служба поддержки всегда готова помочь!</p>
        </div>

        <div class="footer">
            <p>&copy; #SITE_NAME# | <a href="http://#SERVER_NAME#" style="color: #667eea;">Перейти на сайт</a></p>
            <p>Вы получили это письмо, потому что зарегистрировались на нашем сайте.</p>
        </div>
    </div>
</body>
</html>
    '
]);

if ($templateResult->isSuccess()) {
    echo "✓ Шаблон письма создан (ID: " . $templateResult->getId() . ")\n";
} else {
    echo "✗ Ошибка создания шаблона: " . implode(', ', $templateResult->getErrorMessages()) . "\n";
}

echo "\n=== Установка завершена ===\n";
echo "Теперь можно использовать отправку через UserRegistrationHandler.php\n";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
