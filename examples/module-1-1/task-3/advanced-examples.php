<?php
/**
 * Расширенные примеры работы с UserTable
 * Демонстрация различных вариантов фильтрации и выборки
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

use Bitrix\Main\UserTable;
use Bitrix\Main\Type\DateTime;

// ============================================
// 1. ПОИСК ПОЛЬЗОВАТЕЛЯ ПО EMAIL
// ============================================

function findUserByEmail(string $email): ?array
{
    $result = UserTable::getList([
        'select' => ['ID', 'LOGIN', 'EMAIL', 'NAME', 'LAST_NAME'],
        'filter' => ['=EMAIL' => $email],
        'limit' => 1
    ]);

    return $result->fetch() ?: null;
}

$user = findUserByEmail('admin@example.com');
if ($user) {
    echo "Найден пользователь: {$user['LOGIN']}<br><br>";
}

// ============================================
// 2. ПОЛУЧЕНИЕ ПОЛЬЗОВАТЕЛЕЙ ИЗ ГРУППЫ
// ============================================

function getUsersFromGroup(int $groupId): array
{
    $result = UserTable::getList([
        'select' => ['ID', 'LOGIN', 'EMAIL'],
        'filter' => [
            'ACTIVE' => 'Y',
            'GROUPS.GROUP_ID' => $groupId
        ],
        'order' => ['ID' => 'ASC']
    ]);

    $users = [];
    while ($user = $result->fetch()) {
        $users[] = $user;
    }

    return $users;
}

$admins = getUsersFromGroup(1); // Администраторы
echo "Администраторов: " . count($admins) . "<br><br>";

// ============================================
// 3. ПОИСК ПО ЧАСТИЧНОМУ СОВПАДЕНИЮ (LIKE)
// ============================================

$result = UserTable::getList([
    'select' => ['ID', 'LOGIN', 'EMAIL'],
    'filter' => [
        'ACTIVE' => 'Y',
        [
            'LOGIC' => 'OR',
            ['%LOGIN' => 'admin'],
            ['%EMAIL' => '@example.com']
        ]
    ]
]);

echo "Пользователи с 'admin' в логине или '@example.com' в email:<br>";
while ($user = $result->fetch()) {
    echo "{$user['LOGIN']} - {$user['EMAIL']}<br>";
}
echo "<br>";

// ============================================
// 4. ПОЛЬЗОВАТЕЛИ, ЗАХОДИВШИЕ ЗА ПОСЛЕДНИЙ МЕСЯЦ
// ============================================

$monthAgo = (new DateTime())->add('-1 month');

$activeUsers = UserTable::getList([
    'select' => ['ID', 'LOGIN', 'LAST_LOGIN'],
    'filter' => [
        'ACTIVE' => 'Y',
        '>LAST_LOGIN' => $monthAgo
    ],
    'order' => ['LAST_LOGIN' => 'DESC']
]);

echo "Активность за последний месяц:<br>";
while ($user = $activeUsers->fetch()) {
    $lastLogin = $user['LAST_LOGIN'] ? $user['LAST_LOGIN']->format('d.m.Y H:i') : 'никогда';
    echo "{$user['LOGIN']} - последний вход: {$lastLogin}<br>";
}
echo "<br>";

// ============================================
// 5. СОЗДАНИЕ НОВОГО ПОЛЬЗОВАТЕЛЯ
// ============================================

function createUser(array $fields): int
{
    $result = UserTable::add([
        'LOGIN' => $fields['LOGIN'],
        'EMAIL' => $fields['EMAIL'],
        'PASSWORD' => password_hash($fields['PASSWORD'], PASSWORD_DEFAULT),
        'NAME' => $fields['NAME'] ?? '',
        'LAST_NAME' => $fields['LAST_NAME'] ?? '',
        'ACTIVE' => 'Y',
        'DATE_REGISTER' => new DateTime()
    ]);

    if ($result->isSuccess()) {
        return $result->getId();
    }

    throw new \Exception(implode(', ', $result->getErrorMessages()));
}

// Пример использования (закомментировано):
// $newUserId = createUser([
//     'LOGIN' => 'newuser',
//     'EMAIL' => 'newuser@example.com',
//     'PASSWORD' => 'secure_password',
//     'NAME' => 'Иван',
//     'LAST_NAME' => 'Иванов'
// ]);

// ============================================
// 6. ОБНОВЛЕНИЕ ДАННЫХ ПОЛЬЗОВАТЕЛЯ
// ============================================

function updateUserEmail(int $userId, string $newEmail): bool
{
    $result = UserTable::update($userId, [
        'EMAIL' => $newEmail
    ]);

    return $result->isSuccess();
}

// ============================================
// 7. ПОЛУЧЕНИЕ ОДНОГО ПОЛЬЗОВАТЕЛЯ ПО ID
// ============================================

$user = UserTable::getById(1)->fetch();
if ($user) {
    echo "<h3>Пользователь #{$user['ID']}:</h3>";
    echo "Логин: {$user['LOGIN']}<br>";
    echo "Email: {$user['EMAIL']}<br>";
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
