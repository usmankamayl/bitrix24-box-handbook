<?php
/**
 * Установка почтового события для еженедельного дайджеста
 *
 * Создает тип события и шаблон для рассылки новостей
 */

use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\Mail\Internal\EventMessageTable;
use Bitrix\Main\Loader;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

Loader::includeModule('main');

// 1. Создаем тип почтового события
$eventTypeResult = EventTypeTable::add([
    'LID' => 'ru',
    'EVENT_NAME' => 'WEEKLY_DIGEST',
    'NAME' => 'Еженедельный дайджест новостей',
    'DESCRIPTION' =>
        "#USER_NAME# - Имя пользователя\n" .
        "#USER_EMAIL# - Email пользователя\n" .
        "#NEWS_LIST# - Список новостей (HTML)\n" .
        "#NEWS_COUNT# - Количество новостей\n" .
        "#WEEK_PERIOD# - Период (например: 1-7 декабря)\n" .
        "#UNSUBSCRIBE_LINK# - Ссылка для отписки\n" .
        "#SITE_NAME# - Название сайта\n" .
        "#SERVER_NAME# - Адрес сайта"
]);

if ($eventTypeResult->isSuccess()) {
    echo "✓ Тип события WEEKLY_DIGEST создан\n";
} else {
    echo "✗ Ошибка: " . implode(', ', $eventTypeResult->getErrorMessages()) . "\n";
}

// 2. Создаем шаблон письма
$templateResult = EventMessageTable::add([
    'EVENT_NAME' => 'WEEKLY_DIGEST',
    'LID' => 's1',
    'ACTIVE' => 'Y',
    'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
    'EMAIL_TO' => '#USER_EMAIL#',
    'SUBJECT' => '📰 Еженедельный дайджест: главные новости #WEEK_PERIOD#',
    'BODY_TYPE' => 'html',
    'MESSAGE' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 650px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 40px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header .subtitle { margin-top: 10px; opacity: 0.9; font-size: 14px; }
        .greeting { padding: 30px; background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
        .greeting p { margin: 0; color: #495057; font-size: 16px; }
        .content { padding: 30px; }
        .stats { background: #e7f3ff; padding: 20px; border-radius: 8px; margin-bottom: 30px; text-align: center; }
        .stats .number { font-size: 36px; font-weight: bold; color: #1e3c72; }
        .stats .label { color: #6c757d; font-size: 14px; margin-top: 5px; }
        .news-item { margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e9ecef; }
        .news-item:last-child { border-bottom: none; margin-bottom: 0; }
        .news-image { width: 100%; height: 250px; object-fit: cover; border-radius: 8px; margin-bottom: 15px; }
        .news-category { display: inline-block; background: #1e3c72; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .news-title { font-size: 22px; font-weight: 600; color: #212529; margin: 10px 0; line-height: 1.3; }
        .news-date { color: #6c757d; font-size: 13px; margin-bottom: 10px; }
        .news-preview { color: #495057; font-size: 15px; line-height: 1.6; margin: 15px 0; }
        .news-link { display: inline-block; color: #1e3c72; text-decoration: none; font-weight: 600; padding: 10px 20px; border: 2px solid #1e3c72; border-radius: 5px; transition: all 0.3s; }
        .news-link:hover { background: #1e3c72; color: white; }
        .footer { background: #212529; color: #adb5bd; padding: 30px; font-size: 13px; }
        .footer-links { margin: 20px 0; }
        .footer-links a { color: #adb5bd; text-decoration: none; margin: 0 10px; }
        .footer-links a:hover { color: white; }
        .unsubscribe { margin-top: 20px; padding-top: 20px; border-top: 1px solid #495057; color: #6c757d; font-size: 12px; }
        .unsubscribe a { color: #6c757d; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📰 Еженедельный дайджест</h1>
            <div class="subtitle">#WEEK_PERIOD#</div>
        </div>

        <div class="greeting">
            <p>Здравствуйте, <strong>#USER_NAME#</strong>!</p>
        </div>

        <div class="content">
            <div class="stats">
                <div class="number">#NEWS_COUNT#</div>
                <div class="label">Главных новостей за неделю</div>
            </div>

            <p style="margin-bottom: 30px; color: #6c757d; font-size: 14px;">
                Мы подобрали для вас самые интересные материалы за прошедшую неделю.
                Приятного чтения! ☕
            </p>

            #NEWS_LIST#

            <div style="text-align: center; margin-top: 40px; padding: 30px; background: #f8f9fa; border-radius: 8px;">
                <p style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">
                    <strong>Хотите получать больше новостей?</strong>
                </p>
                <p style="margin: 0; color: #6c757d; font-size: 14px;">
                    Подпишитесь на наш Telegram-канал и будьте в курсе всех событий!
                </p>
                <a href="http://#SERVER_NAME#/subscribe/" style="display: inline-block; margin-top: 15px; background: #0088cc; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: 600;">
                    Подписаться
                </a>
            </div>
        </div>

        <div class="footer">
            <div style="text-align: center;">
                <p style="margin: 0 0 10px 0; font-size: 16px; color: white;">
                    <strong>#SITE_NAME#</strong>
                </p>
                <div class="footer-links">
                    <a href="http://#SERVER_NAME#">Главная</a> |
                    <a href="http://#SERVER_NAME#/news/">Все новости</a> |
                    <a href="http://#SERVER_NAME#/about/">О проекте</a> |
                    <a href="http://#SERVER_NAME#/contacts/">Контакты</a>
                </div>
            </div>

            <div class="unsubscribe">
                <p style="margin: 0; text-align: center;">
                    Вы получили это письмо, потому что подписаны на еженедельный дайджест.<br>
                    <a href="http://#SERVER_NAME##UNSUBSCRIBE_LINK#">Отписаться от рассылки</a>
                </p>
            </div>
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
echo "Далее настройте агент через WeeklyDigestAgent.php\n";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
