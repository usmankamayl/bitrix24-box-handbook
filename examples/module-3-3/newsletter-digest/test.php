<?php
/**
 * Тестирование отправки дайджеста
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Bitrix\Main\Mail\Event;

echo "<h1>Тест отправки еженедельного дайджеста</h1>";
echo "<pre>";

$testEmail = 'test@example.com'; // ЗАМЕНИТЕ НА СВОЙ EMAIL

// Тестовый HTML с новостями
$newsHtml = '
<div class="news-item">
    <img src="https://via.placeholder.com/650x250" alt="Новость 1" class="news-image">
    <div class="news-category">Технологии</div>
    <div class="news-title">Вышла новая версия Bitrix Framework D7.5</div>
    <div class="news-date">📅 01.10.2026</div>
    <div class="news-preview">Компания 1С-Битрикс представила обновленную версию своего фреймворка с улучшенной производительностью и новыми возможностями для разработчиков...</div>
    <a href="http://example.com/news/1" class="news-link">Читать полностью →</a>
</div>

<div class="news-item">
    <img src="https://via.placeholder.com/650x250" alt="Новость 2" class="news-image">
    <div class="news-category">Разработка</div>
    <div class="news-title">10 лучших практик оптимизации производительности</div>
    <div class="news-date">📅 03.10.2026</div>
    <div class="news-preview">Эксперты поделились советами по ускорению работы веб-приложений на платформе 1С-Битрикс. Узнайте, как правильно использовать кеширование...</div>
    <a href="http://example.com/news/2" class="news-link">Читать полностью →</a>
</div>

<div class="news-item">
    <img src="https://via.placeholder.com/650x250" alt="Новость 3" class="news-image">
    <div class="news-category">Кейсы</div>
    <div class="news-title">Как мы увеличили конверсию интернет-магазина на 40%</div>
    <div class="news-date">📅 05.10.2026</div>
    <div class="news-preview">История успеха крупного ритейлера, который внедрил персонализированные рекомендации товаров и систему брошенных корзин...</div>
    <a href="http://example.com/news/3" class="news-link">Читать полностью →</a>
</div>
';

$fields = [
    'USER_NAME' => 'Тестовый Пользователь',
    'USER_EMAIL' => $testEmail,
    'NEWS_LIST' => $newsHtml,
    'NEWS_COUNT' => 3,
    'WEEK_PERIOD' => '1-7 октября',
    'UNSUBSCRIBE_LINK' => '/unsubscribe/?user_id=1&token=test123',
    'SITE_NAME' => 'Тестовый Портал',
    'SERVER_NAME' => $_SERVER['HTTP_HOST']
];

$result = Event::send([
    'EVENT_NAME' => 'WEEKLY_DIGEST',
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
