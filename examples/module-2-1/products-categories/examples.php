<?php
/**
 * Примеры работы со связями товаров и категорий
 *
 * Демонстрирует:
 * - ReferenceField для связи таблиц
 * - JOIN запросы через ORM
 * - Фильтрацию по связанным таблицам
 * - Получение данных из связанных таблиц
 * - Древовидные структуры
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

use Custom\Catalog\CategoryTable;
use Custom\Catalog\ProductTable;

// ============================================================
// Подготовка: создание тестовых данных
// ============================================================

echo "<h2>Создание тестовых категорий и товаров</h2>";

// Создаем категории
$categories = [
    ['NAME' => 'Электроника', 'CODE' => 'electronics', 'PARENT_ID' => null, 'SORT' => 100],
    ['NAME' => 'Смартфоны', 'CODE' => 'smartphones', 'PARENT_ID' => 1, 'SORT' => 100],
    ['NAME' => 'Ноутбуки', 'CODE' => 'laptops', 'PARENT_ID' => 1, 'SORT' => 200],
    ['NAME' => 'Одежда', 'CODE' => 'clothing', 'PARENT_ID' => null, 'SORT' => 200],
    ['NAME' => 'Мужская одежда', 'CODE' => 'mens-clothing', 'PARENT_ID' => 4, 'SORT' => 100],
    ['NAME' => 'Женская одежда', 'CODE' => 'womens-clothing', 'PARENT_ID' => 4, 'SORT' => 200],
    ['NAME' => 'Бытовая техника', 'CODE' => 'appliances', 'PARENT_ID' => null, 'SORT' => 300],
];

foreach ($categories as $category) {
    $result = CategoryTable::add($category);
    if ($result->isSuccess()) {
        echo "✅ Категория '{$category['NAME']}' создана<br>";
    }
}

// Создаем товары
$products = [
    ['NAME' => 'iPhone 15 Pro', 'CODE' => 'iphone-15-pro', 'CATEGORY_ID' => 2, 'PRICE' => 99990, 'OLD_PRICE' => 109990, 'QUANTITY' => 15, 'SORT' => 100],
    ['NAME' => 'Samsung Galaxy S24', 'CODE' => 'samsung-s24', 'CATEGORY_ID' => 2, 'PRICE' => 79990, 'OLD_PRICE' => 89990, 'QUANTITY' => 8, 'SORT' => 200],
    ['NAME' => 'MacBook Pro 16"', 'CODE' => 'macbook-pro-16', 'CATEGORY_ID' => 3, 'PRICE' => 249990, 'OLD_PRICE' => null, 'QUANTITY' => 5, 'SORT' => 100],
    ['NAME' => 'Dell XPS 15', 'CODE' => 'dell-xps-15', 'CATEGORY_ID' => 3, 'PRICE' => 159990, 'OLD_PRICE' => 179990, 'QUANTITY' => 3, 'SORT' => 200],
    ['NAME' => 'Lenovo ThinkPad X1', 'CODE' => 'lenovo-x1', 'CATEGORY_ID' => 3, 'PRICE' => 139990, 'OLD_PRICE' => null, 'QUANTITY' => 12, 'SORT' => 300],
    ['NAME' => 'Рубашка классическая', 'CODE' => 'classic-shirt', 'CATEGORY_ID' => 5, 'PRICE' => 2990, 'OLD_PRICE' => 3990, 'QUANTITY' => 50, 'SORT' => 100],
    ['NAME' => 'Джинсы Levi\'s 501', 'CODE' => 'levis-501', 'CATEGORY_ID' => 5, 'PRICE' => 6990, 'OLD_PRICE' => null, 'QUANTITY' => 25, 'SORT' => 200],
    ['NAME' => 'Платье вечернее', 'CODE' => 'evening-dress', 'CATEGORY_ID' => 6, 'PRICE' => 8990, 'OLD_PRICE' => 12990, 'QUANTITY' => 7, 'SORT' => 100],
    ['NAME' => 'Блузка шелковая', 'CODE' => 'silk-blouse', 'CATEGORY_ID' => 6, 'PRICE' => 4990, 'OLD_PRICE' => null, 'QUANTITY' => 18, 'SORT' => 200],
    ['NAME' => 'Холодильник LG', 'CODE' => 'lg-fridge', 'CATEGORY_ID' => 7, 'PRICE' => 54990, 'OLD_PRICE' => 64990, 'QUANTITY' => 4, 'SORT' => 100],
];

foreach ($products as $product) {
    $result = ProductTable::add($product);
    if ($result->isSuccess()) {
        echo "✅ Товар '{$product['NAME']}' создан<br>";
    }
}

echo "<br>";

// ============================================================
// 1. Простая выборка товаров с названием категории
// ============================================================

echo "<h2>1. Товары с информацией о категории</h2>";

$products = ProductTable::getList([
    'select' => [
        'ID',
        'NAME',
        'PRICE',
        'CATEGORY_NAME' => 'CATEGORY.NAME',
        'CATEGORY_CODE' => 'CATEGORY.CODE',
    ],
    'filter' => ['=ACTIVE' => 'Y'],
    'limit' => 5,
]);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Товар</th><th>Цена</th><th>Категория</th></tr>";

while ($product = $products->fetch()) {
    echo "<tr>";
    echo "<td>{$product['ID']}</td>";
    echo "<td>{$product['NAME']}</td>";
    echo "<td>" . number_format($product['PRICE'], 0, '.', ' ') . " ₽</td>";
    echo "<td>{$product['CATEGORY_NAME']}</td>";
    echo "</tr>";
}

echo "</table><br>";

// ============================================================
// 2. Фильтрация по связанной таблице
// ============================================================

echo "<h2>2. Товары из категории 'Смартфоны'</h2>";

$smartphones = ProductTable::getProductsByCategoryCode('smartphones');

echo "<ul>";
foreach ($smartphones as $product) {
    echo "<li><strong>{$product['NAME']}</strong> — " . number_format($product['PRICE'], 0, '.', ' ') . " ₽ ";
    echo "(в наличии: {$product['QUANTITY']} шт.)</li>";
}
echo "</ul>";

// ============================================================
// 3. Получение данных через метод-помощник
// ============================================================

echo "<h2>3. Все товары с категориями (через метод)</h2>";

$products = ProductTable::getProductsWithCategory([], 10);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Товар</th><th>Категория</th><th>Цена</th><th>Старая цена</th></tr>";

foreach ($products as $product) {
    $oldPrice = $product['OLD_PRICE'] ? number_format($product['OLD_PRICE'], 0, '.', ' ') . " ₽" : "—";
    echo "<tr>";
    echo "<td>{$product['NAME']}</td>";
    echo "<td>{$product['CATEGORY_NAME']}</td>";
    echo "<td>" . number_format($product['PRICE'], 0, '.', ' ') . " ₽</td>";
    echo "<td>{$oldPrice}</td>";
    echo "</tr>";
}

echo "</table><br>";

// ============================================================
// 4. Статистика по категориям
// ============================================================

echo "<h2>4. Статистика товаров по категориям</h2>";

$stats = ProductTable::getCategoryStatistics();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Категория</th><th>Товаров</th><th>Средняя цена</th><th>Min/Max цена</th><th>Всего на складе</th></tr>";

foreach ($stats as $stat) {
    echo "<tr>";
    echo "<td>{$stat['CATEGORY_NAME']}</td>";
    echo "<td>{$stat['PRODUCTS_COUNT']}</td>";
    echo "<td>" . number_format($stat['AVG_PRICE'], 0, '.', ' ') . " ₽</td>";
    echo "<td>" . number_format($stat['MIN_PRICE'], 0, '.', ' ') . " / " . number_format($stat['MAX_PRICE'], 0, '.', ' ') . " ₽</td>";
    echo "<td>{$stat['TOTAL_QUANTITY']} шт.</td>";
    echo "</tr>";
}

echo "</table><br>";

// ============================================================
// 5. Товары со скидкой
// ============================================================

echo "<h2>5. Товары со скидкой</h2>";

$discounted = ProductTable::getDiscountedProducts();

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Товар</th><th>Категория</th><th>Старая цена</th><th>Новая цена</th><th>Скидка</th></tr>";

foreach ($discounted as $product) {
    echo "<tr>";
    echo "<td>{$product['NAME']}</td>";
    echo "<td>{$product['CATEGORY_NAME']}</td>";
    echo "<td><s>" . number_format($product['OLD_PRICE'], 0, '.', ' ') . " ₽</s></td>";
    echo "<td><strong>" . number_format($product['PRICE'], 0, '.', ' ') . " ₽</strong></td>";
    echo "<td style='color: red;'><strong>-{$product['DISCOUNT_PERCENT']}%</strong></td>";
    echo "</tr>";
}

echo "</table><br>";

// ============================================================
// 6. Товары заканчивающиеся на складе
// ============================================================

echo "<h2>6. Товары заканчивающиеся на складе (остаток < 10 шт.)</h2>";

$lowStock = ProductTable::getLowStockProducts(10);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Товар</th><th>Категория</th><th>Осталось</th><th>Цена</th></tr>";

foreach ($lowStock as $product) {
    $color = $product['QUANTITY'] <= 5 ? 'red' : 'orange';
    echo "<tr>";
    echo "<td>{$product['NAME']}</td>";
    echo "<td>{$product['CATEGORY_NAME']}</td>";
    echo "<td style='color: {$color}; font-weight: bold;'>{$product['QUANTITY']} шт.</td>";
    echo "<td>" . number_format($product['PRICE'], 0, '.', ' ') . " ₽</td>";
    echo "</tr>";
}

echo "</table><br>";

// ============================================================
// 7. Поиск товаров
// ============================================================

echo "<h2>7. Поиск товаров по запросу 'Pro'</h2>";

$searchResults = ProductTable::searchProducts('Pro');

echo "<ul>";
foreach ($searchResults as $product) {
    echo "<li><strong>{$product['NAME']}</strong> ({$product['CATEGORY_NAME']}) — " . number_format($product['PRICE'], 0, '.', ' ') . " ₽</li>";
}
echo "</ul>";

// ============================================================
// 8. Дерево категорий с количеством товаров
// ============================================================

echo "<h2>8. Дерево категорий с количеством товаров</h2>";

$tree = CategoryTable::getCategoriesTree();

function printCategoryTree($categories, $level = 0) {
    foreach ($categories as $category) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $arrow = $level > 0 ? '↳ ' : '';
        echo "{$indent}{$arrow}<strong>{$category['NAME']}</strong> ({$category['PRODUCTS_COUNT']} товаров)<br>";

        if (!empty($category['CHILDREN'])) {
            printCategoryTree($category['CHILDREN'], $level + 1);
        }
    }
}

printCategoryTree($tree);

echo "<br>";

// ============================================================
// 9. Сложный запрос: товары из нескольких категорий
// ============================================================

echo "<h2>9. Товары из категорий 'Смартфоны' и 'Ноутбуки'</h2>";

$products = ProductTable::getList([
    'select' => [
        'ID',
        'NAME',
        'PRICE',
        'CATEGORY_NAME' => 'CATEGORY.NAME',
    ],
    'filter' => [
        '=ACTIVE' => 'Y',
        '@CATEGORY.CODE' => ['smartphones', 'laptops'],
    ],
    'order' => ['CATEGORY_NAME' => 'ASC', 'PRICE' => 'DESC'],
]);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Товар</th><th>Категория</th><th>Цена</th></tr>";

while ($product = $products->fetch()) {
    echo "<tr>";
    echo "<td>{$product['NAME']}</td>";
    echo "<td>{$product['CATEGORY_NAME']}</td>";
    echo "<td>" . number_format($product['PRICE'], 0, '.', ' ') . " ₽</td>";
    echo "</tr>";
}

echo "</table><br>";

// ============================================================
// 10. Корневые категории
// ============================================================

echo "<h2>10. Корневые категории (без родителя)</h2>";

$rootCategories = CategoryTable::getRootCategories();

echo "<ul>";
foreach ($rootCategories as $category) {
    echo "<li><strong>{$category['NAME']}</strong> (код: {$category['CODE']})</li>";
}
echo "</ul>";

// ============================================================
// 11. Товары дороже определенной суммы в активных категориях
// ============================================================

echo "<h2>11. Товары дороже 50 000 ₽ из активных категорий</h2>";

$expensiveProducts = ProductTable::getList([
    'select' => [
        'ID',
        'NAME',
        'PRICE',
        'CATEGORY_NAME' => 'CATEGORY.NAME',
    ],
    'filter' => [
        '=ACTIVE' => 'Y',
        '>PRICE' => 50000,
        '=CATEGORY.ACTIVE' => 'Y',
    ],
    'order' => ['PRICE' => 'DESC'],
]);

echo "<ol>";
while ($product = $expensiveProducts->fetch()) {
    echo "<li><strong>{$product['NAME']}</strong> — " . number_format($product['PRICE'], 0, '.', ' ') . " ₽ ({$product['CATEGORY_NAME']})</li>";
}
echo "</ol>";

// ============================================================
// 12. Самые дешевые товары в каждой категории
// ============================================================

echo "<h2>12. Самый дешевый товар в каждой категории</h2>";

$result = ProductTable::getList([
    'select' => [
        'CATEGORY_ID',
        'CATEGORY_NAME' => 'CATEGORY.NAME',
        'MIN_PRICE',
    ],
    'filter' => ['=ACTIVE' => 'Y'],
    'runtime' => [
        new \Bitrix\Main\Entity\ExpressionField('MIN_PRICE', 'MIN(%s)', ['PRICE']),
    ],
    'group' => ['CATEGORY_ID'],
]);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Категория</th><th>Минимальная цена</th></tr>";

while ($row = $result->fetch()) {
    echo "<tr>";
    echo "<td>{$row['CATEGORY_NAME']}</td>";
    echo "<td>" . number_format($row['MIN_PRICE'], 0, '.', ' ') . " ₽</td>";
    echo "</tr>";
}

echo "</table>";

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
