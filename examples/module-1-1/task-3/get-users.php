<?php
/**
 * Получение списка активных пользователей через D7 ORM
 *
 * Задача: получить активных пользователей с email, отсортированных по дате регистрации
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

use Bitrix\Main\UserTable;

// ============================================
// ПРОСТОЙ ЗАПРОС
// ============================================

$users = UserTable::getList([
    'select' => ['ID', 'LOGIN', 'EMAIL', 'DATE_REGISTER', 'NAME', 'LAST_NAME'],
    'filter' => ['ACTIVE' => 'Y'],
    'order' => ['DATE_REGISTER' => 'DESC'],
    'limit' => 20
]);

echo "<h3>Активные пользователи:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Логин</th><th>Email</th><th>ФИО</th><th>Дата регистрации</th></tr>";

while ($user = $users->fetch()) {
    $fullName = trim($user['NAME'] . ' ' . $user['LAST_NAME']);
    $dateRegister = $user['DATE_REGISTER'] ? $user['DATE_REGISTER']->format('d.m.Y H:i') : '-';

    echo "<tr>";
    echo "<td>{$user['ID']}</td>";
    echo "<td>{$user['LOGIN']}</td>";
    echo "<td>{$user['EMAIL']}</td>";
    echo "<td>{$fullName}</td>";
    echo "<td>{$dateRegister}</td>";
    echo "</tr>";
}

echo "</table><br>";

// ============================================
// ЗАПРОС С ДОПОЛНИТЕЛЬНЫМИ УСЛОВИЯМИ
// ============================================

$users = UserTable::getList([
    'select' => ['ID', 'LOGIN', 'EMAIL', 'DATE_REGISTER'],
    'filter' => [
        'ACTIVE' => 'Y',
        '!EMAIL' => false, // Email не пустой
        '>DATE_REGISTER' => new \Bitrix\Main\Type\DateTime('2024-01-01', 'Y-m-d')
    ],
    'order' => ['DATE_REGISTER' => 'DESC']
]);

echo "<h3>Пользователи с email, зарегистрированные после 01.01.2024:</h3>";
echo "<ul>";

while ($user = $users->fetch()) {
    $date = $user['DATE_REGISTER']->format('d.m.Y');
    echo "<li>{$user['LOGIN']} ({$user['EMAIL']}) - {$date}</li>";
}

echo "</ul><br>";

// ============================================
// ПОДСЧЕТ КОЛИЧЕСТВА
// ============================================

$totalUsers = UserTable::getCount(['ACTIVE' => 'Y']);
$usersWithEmail = UserTable::getCount([
    'ACTIVE' => 'Y',
    '!EMAIL' => false
]);

echo "<h3>Статистика:</h3>";
echo "Всего активных пользователей: {$totalUsers}<br>";
echo "Из них с email: {$usersWithEmail}<br><br>";

// ============================================
// ЗАПРОС С ГРУППИРОВКОЙ ПО ДАТЕ
// ============================================

$result = UserTable::getList([
    'runtime' => [
        new \Bitrix\Main\Entity\ExpressionField(
            'REG_DATE',
            'DATE(%s)',
            ['DATE_REGISTER']
        ),
        new \Bitrix\Main\Entity\ExpressionField(
            'CNT',
            'COUNT(*)'
        )
    ],
    'select' => ['REG_DATE', 'CNT'],
    'filter' => ['ACTIVE' => 'Y'],
    'group' => ['REG_DATE'],
    'order' => ['REG_DATE' => 'DESC'],
    'limit' => 10
]);

echo "<h3>Регистраций по дням (последние 10 дней):</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Дата</th><th>Количество</th></tr>";

while ($row = $result->fetch()) {
    echo "<tr>";
    echo "<td>{$row['REG_DATE']}</td>";
    echo "<td>{$row['CNT']}</td>";
    echo "</tr>";
}

echo "</table>";

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
