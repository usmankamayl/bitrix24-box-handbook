<?php
/**
 * Примеры использования ReviewTable
 *
 * Демонстрирует все CRUD операции:
 * - CREATE: добавление отзывов
 * - READ: чтение с фильтрацией и сортировкой
 * - UPDATE: обновление отзывов
 * - DELETE: удаление отзывов
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

use Custom\Reviews\ReviewTable;
use Bitrix\Main\Type\DateTime;

// ============================================================
// CREATE - Создание новых отзывов
// ============================================================

echo "<h2>1. Создание отзывов</h2>";

// Пример 1: Простое добавление отзыва
$result = ReviewTable::add([
    'PRODUCT_ID' => 100,
    'USER_ID' => 1,
    'RATING' => 5,
    'REVIEW_TEXT' => 'Отличный товар! Полностью соответствует описанию. Рекомендую!',
    'ADVANTAGES' => 'Качественная сборка, быстрая доставка',
    'DISADVANTAGES' => 'Цена немного завышена',
    'ACTIVE' => 'Y',
]);

if ($result->isSuccess()) {
    $newId = $result->getId();
    echo "✅ Отзыв создан с ID: {$newId}<br>";
} else {
    echo "❌ Ошибки: " . implode(', ', $result->getErrorMessages()) . "<br>";
}

// Пример 2: Добавление с валидацией
$result2 = ReviewTable::add([
    'PRODUCT_ID' => 101,
    'USER_ID' => 2,
    'AUTHOR_NAME' => 'Иван Петров',
    'AUTHOR_EMAIL' => 'ivan@example.com',
    'RATING' => 3,
    'REVIEW_TEXT' => 'Средний товар. Есть как плюсы, так и минусы.',
    'ACTIVE' => 'N', // требует модерации
]);

if ($result2->isSuccess()) {
    echo "✅ Отзыв #{$result2->getId()} добавлен и ожидает модерации<br>";
}

// Пример 3: Попытка добавить некорректный отзыв (рейтинг вне диапазона)
$result3 = ReviewTable::add([
    'PRODUCT_ID' => 102,
    'USER_ID' => 3,
    'RATING' => 10, // Ошибка: рейтинг должен быть от 1 до 5
    'REVIEW_TEXT' => 'Текст отзыва',
]);

if (!$result3->isSuccess()) {
    echo "❌ Ожидаемая ошибка валидации: " . implode(', ', $result3->getErrorMessages()) . "<br>";
}

// ============================================================
// READ - Чтение отзывов
// ============================================================

echo "<h2>2. Чтение отзывов</h2>";

// Пример 4: Получить один отзыв по ID
$review = ReviewTable::getById(1)->fetch();
if ($review) {
    echo "📄 Отзыв #{$review['ID']}: рейтинг {$review['RATING']}, текст: {$review['REVIEW_TEXT']}<br>";
}

// Пример 5: Получить все активные отзывы
echo "<h3>Активные отзывы:</h3>";
$result = ReviewTable::getList([
    'select' => ['ID', 'PRODUCT_ID', 'RATING', 'REVIEW_TEXT', 'DATE_CREATE'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order' => ['DATE_CREATE' => 'DESC'],
]);

while ($review = $result->fetch()) {
    echo "✅ #{$review['ID']}: товар #{$review['PRODUCT_ID']}, рейтинг {$review['RATING']}<br>";
}

// Пример 6: Получить отзывы для конкретного товара с высоким рейтингом
echo "<h3>Отзывы товара #100 с рейтингом >= 4:</h3>";
$result = ReviewTable::getList([
    'select' => ['*'],
    'filter' => [
        '=PRODUCT_ID' => 100,
        '>=RATING' => 4,
        '=ACTIVE' => 'Y',
    ],
    'order' => ['RATING' => 'DESC', 'DATE_CREATE' => 'DESC'],
]);

while ($review = $result->fetch()) {
    echo "⭐ Рейтинг: {$review['RATING']}/5 - {$review['REVIEW_TEXT']}<br>";
}

// Пример 7: Поиск по тексту отзыва
echo "<h3>Поиск отзывов со словом 'качество':</h3>";
$result = ReviewTable::getList([
    'select' => ['ID', 'RATING', 'REVIEW_TEXT'],
    'filter' => [
        '%REVIEW_TEXT' => '%качество%',
        '=ACTIVE' => 'Y',
    ],
]);

while ($review = $result->fetch()) {
    echo "🔍 #{$review['ID']}: {$review['REVIEW_TEXT']}<br>";
}

// Пример 8: Получить отзывы с пагинацией
echo "<h3>Отзывы (страница 1, по 5 штук):</h3>";
$result = ReviewTable::getList([
    'select' => ['ID', 'PRODUCT_ID', 'RATING'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order' => ['ID' => 'DESC'],
    'limit' => 5,
    'offset' => 0, // для страницы 2 будет offset = 5
]);

$reviews = $result->fetchAll();
echo "📄 Найдено отзывов на странице: " . count($reviews) . "<br>";

// Пример 9: Подсчет количества отзывов
$count = ReviewTable::getCount([
    '=PRODUCT_ID' => 100,
    '=ACTIVE' => 'Y',
]);
echo "📊 Всего активных отзывов для товара #100: {$count}<br>";

// Пример 10: Получить отзывы за последнюю неделю
$weekAgo = new DateTime();
$weekAgo->add('-7 days');

$result = ReviewTable::getList([
    'select' => ['ID', 'DATE_CREATE', 'RATING'],
    'filter' => [
        '>DATE_CREATE' => $weekAgo,
        '=ACTIVE' => 'Y',
    ],
]);

echo "<h3>Отзывы за последнюю неделю:</h3>";
while ($review = $result->fetch()) {
    echo "📅 #{$review['ID']} от {$review['DATE_CREATE']->format('d.m.Y H:i')}<br>";
}

// ============================================================
// UPDATE - Обновление отзывов
// ============================================================

echo "<h2>3. Обновление отзывов</h2>";

// Пример 11: Модерация отзыва (активация)
$result = ReviewTable::update(1, [
    'ACTIVE' => 'Y',
    'DATE_MODERATED' => new DateTime(),
    'MODERATED_BY' => 1, // ID модератора
]);

if ($result->isSuccess()) {
    echo "✅ Отзыв #1 одобрен модератором<br>";
}

// Пример 12: Увеличение счетчика лайков
$review = ReviewTable::getById(1)->fetch();
if ($review) {
    $newLikesCount = $review['LIKES_COUNT'] + 1;

    ReviewTable::update(1, [
        'LIKES_COUNT' => $newLikesCount,
    ]);

    echo "👍 Лайк добавлен! Теперь лайков: {$newLikesCount}<br>";
}

// Пример 13: Редактирование текста отзыва
$result = ReviewTable::update(1, [
    'REVIEW_TEXT' => 'Обновленный текст отзыва после редактирования автором',
    'ADVANTAGES' => 'Дополнительные достоинства',
]);

if ($result->isSuccess()) {
    echo "✏️ Отзыв #1 отредактирован<br>";
}

// Пример 14: Массовое обновление (модерация всех отзывов пользователя)
$result = ReviewTable::getList([
    'select' => ['ID'],
    'filter' => [
        '=USER_ID' => 1,
        '=ACTIVE' => 'N',
    ],
]);

$approvedCount = 0;
while ($review = $result->fetch()) {
    $updateResult = ReviewTable::update($review['ID'], [
        'ACTIVE' => 'Y',
        'DATE_MODERATED' => new DateTime(),
        'MODERATED_BY' => 1,
    ]);

    if ($updateResult->isSuccess()) {
        $approvedCount++;
    }
}

echo "✅ Одобрено отзывов пользователя #1: {$approvedCount}<br>";

// ============================================================
// DELETE - Удаление отзывов
// ============================================================

echo "<h2>4. Удаление отзывов</h2>";

// Пример 15: Удаление одного отзыва
$result = ReviewTable::delete(5);

if ($result->isSuccess()) {
    echo "🗑️ Отзыв #5 удален<br>";
} else {
    echo "❌ Ошибка удаления: " . implode(', ', $result->getErrorMessages()) . "<br>";
}

// Пример 16: Удаление неактивных старых отзывов
$monthAgo = new DateTime();
$monthAgo->add('-30 days');

$result = ReviewTable::getList([
    'select' => ['ID'],
    'filter' => [
        '=ACTIVE' => 'N',
        '<DATE_CREATE' => $monthAgo,
    ],
]);

$deletedCount = 0;
while ($review = $result->fetch()) {
    $deleteResult = ReviewTable::delete($review['ID']);
    if ($deleteResult->isSuccess()) {
        $deletedCount++;
    }
}

echo "🗑️ Удалено старых неактивных отзывов: {$deletedCount}<br>";

// ============================================================
// Дополнительные примеры
// ============================================================

echo "<h2>5. Дополнительные примеры</h2>";

// Пример 17: Получить средний рейтинг товара
$result = ReviewTable::getList([
    'select' => ['AVG_RATING'],
    'filter' => [
        '=PRODUCT_ID' => 100,
        '=ACTIVE' => 'Y',
    ],
    'runtime' => [
        new \Bitrix\Main\Entity\ExpressionField(
            'AVG_RATING',
            'AVG(%s)',
            ['RATING']
        ),
    ],
]);

if ($avg = $result->fetch()) {
    echo "📊 Средний рейтинг товара #100: " . round($avg['AVG_RATING'], 2) . "/5<br>";
}

// Пример 18: Получить популярные отзывы (больше всего лайков)
echo "<h3>Топ-3 популярных отзыва:</h3>";
$result = ReviewTable::getList([
    'select' => ['ID', 'REVIEW_TEXT', 'LIKES_COUNT'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order' => ['LIKES_COUNT' => 'DESC'],
    'limit' => 3,
]);

$position = 1;
while ($review = $result->fetch()) {
    echo "{$position}. 👍 {$review['LIKES_COUNT']} лайков - {$review['REVIEW_TEXT']}<br>";
    $position++;
}

// Пример 19: Проверка существования отзыва пользователя для товара
$userReview = ReviewTable::getList([
    'select' => ['ID'],
    'filter' => [
        '=PRODUCT_ID' => 100,
        '=USER_ID' => 1,
    ],
])->fetch();

if ($userReview) {
    echo "ℹ️ Пользователь #1 уже оставлял отзыв на товар #100<br>";
} else {
    echo "➕ Пользователь #1 может оставить отзыв на товар #100<br>";
}

// Пример 20: Получить статистику по рейтингам
echo "<h3>Распределение рейтингов для товара #100:</h3>";
for ($rating = 5; $rating >= 1; $rating--) {
    $count = ReviewTable::getCount([
        '=PRODUCT_ID' => 100,
        '=RATING' => $rating,
        '=ACTIVE' => 'Y',
    ]);

    $stars = str_repeat('⭐', $rating);
    echo "{$stars} ({$rating}): {$count} отзывов<br>";
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
