<?php
/**
 * Установка почтового события для еженедельной рассылки
 *
 * Запустите этот файл для создания почтового шаблона
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

$APPLICATION->SetTitle("Установка почтового события");

// Создаем тип почтового события
$eventType = new CEventType;
$eventTypeId = $eventType->Add([
    "LID" => "ru",
    "EVENT_NAME" => "WEEKLY_NEWSLETTER",
    "NAME" => "Еженедельная рассылка новостей",
    "DESCRIPTION" => "#EMAIL# - Email подписчика
#USER_NAME# - Имя пользователя
#NEWSLETTER_DATE# - Дата рассылки
#NEWS_LIST# - Список новостей (HTML)"
]);

if ($eventTypeId) {
    echo "Тип почтового события создан (ID: {$eventTypeId})<br>";
} else {
    echo "Ошибка создания типа события или он уже существует<br>";
}

// Создаем шаблон письма
$eventMessage = new CEventMessage;
$messageId = $eventMessage->Add([
    "ACTIVE" => "Y",
    "EVENT_NAME" => "WEEKLY_NEWSLETTER",
    "LID" => SITE_ID,
    "EMAIL_FROM" => "#DEFAULT_EMAIL_FROM#",
    "EMAIL_TO" => "#EMAIL#",
    "SUBJECT" => "Еженедельная рассылка новостей - #NEWSLETTER_DATE#",
    "MESSAGE" => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0066cc; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        a { color: #0066cc; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Еженедельная рассылка</h1>
            <p style="margin: 10px 0 0 0;">#NEWSLETTER_DATE#</p>
        </div>

        <div class="content">
            <p>Здравствуйте, #USER_NAME#!</p>

            <p>Представляем вам самые интересные новости за прошедшую неделю:</p>

            #NEWS_LIST#

            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

            <p style="font-size: 14px; color: #666;">
                Вы получили это письмо, потому что подписаны на нашу рассылку.<br>
                <a href="https://#SERVER_NAME#/personal/subscribe/">Управление подпиской</a>
            </p>
        </div>

        <div class="footer">
            <p>&copy; #SITE_NAME# - Все права защищены</p>
        </div>
    </div>
</body>
</html>',
    "BODY_TYPE" => "html"
]);

if ($messageId) {
    echo "Шаблон письма создан (ID: {$messageId})<br>";
} else {
    echo "Ошибка создания шаблона письма<br>";
}

echo "<br><a href='/bitrix/admin/event_type_list.php'>Перейти к типам почтовых событий</a>";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
