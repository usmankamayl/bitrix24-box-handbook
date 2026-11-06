<?php
/**
 * ПРИМЕР ИСПОЛЬЗОВАНИЯ ContactManager
 *
 * Расположение этого файла в проекте:
 * /admin/contacts_example.php
 *
 * Как использовать:
 * 1. Скопировать ContactManager.php в /local/php_interface/classes/
 * 2. В /local/php_interface/init.php добавить:
 *    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/ContactManager.php';
 * 3. Открыть /admin/contacts_example.php в браузере
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Local\Bitrix24\ContactManager;

// URL входящего webhook
$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/входящий_webhook_токен';

$manager = new ContactManager($webhookUrl);

// ============================================================================
// ПРИМЕР 1: Создание контакта вручную
// ============================================================================
echo '<h3>Пример 1: Создание контакта</h3>';

$contactId = $manager->createContact([
    'NAME' => 'Иван',
    'LAST_NAME' => 'Петров',
    'SECOND_NAME' => 'Сергеевич',
    'TYPE_ID' => 'CLIENT',
    'SOURCE_ID' => 'WEB',
    'PHONE' => [
        ['VALUE' => '+7 (999) 123-45-67', 'VALUE_TYPE' => 'WORK'],
        ['VALUE' => '+7 (999) 987-65-43', 'VALUE_TYPE' => 'MOBILE']
    ],
    'EMAIL' => [
        ['VALUE' => 'petrov@example.com', 'VALUE_TYPE' => 'WORK']
    ],
    'ASSIGNED_BY_ID' => 1,
    'COMMENTS' => 'Клиент с сайта'
]);

if ($contactId) {
    echo "✅ Контакт создан с ID: $contactId\n";
} else {
    echo "❌ Ошибка при создании контакта\n";
}

// ============================================================================
// ПРИМЕР 2: Получение контакта
// ============================================================================
echo '<h3>Пример 2: Получение контакта</h3>';

if ($contactId) {
    $contact = $manager->getContact($contactId);

    if ($contact) {
        echo "ID: {$contact['ID']}\n";
        echo "Имя: {$contact['NAME']} {$contact['LAST_NAME']}\n";
        echo "Тип: {$contact['TYPE_ID']}\n";

        if (!empty($contact['PHONE'])) {
            echo "Телефоны:\n";
            foreach ($contact['PHONE'] as $phone) {
                echo "  - {$phone['VALUE']} ({$phone['VALUE_TYPE']})\n";
            }
        }

        if (!empty($contact['EMAIL'])) {
            echo "Email:\n";
            foreach ($contact['EMAIL'] as $email) {
                echo "  - {$email['VALUE']}\n";
            }
        }
    }
}

// ============================================================================
// ПРИМЕР 3: Поиск контакта по телефону
// ============================================================================
echo '<h3>Пример 3: Поиск по телефону</h3>';

$foundByPhone = $manager->findByPhone('+7 (999) 123-45-67');

if ($foundByPhone) {
    echo "✅ Контакт найден: {$foundByPhone['NAME']} {$foundByPhone['LAST_NAME']}\n";
} else {
    echo "❌ Контакт не найден\n";
}

// ============================================================================
// ПРИМЕР 4: Поиск контакта по email
// ============================================================================
echo '<h3>Пример 4: Поиск по email</h3>';

$foundByEmail = $manager->findByEmail('petrov@example.com');

if ($foundByEmail) {
    echo "✅ Контакт найден: #{$foundByEmail['ID']}\n";
} else {
    echo "❌ Контакт не найден\n";
}

// ============================================================================
// ПРИМЕР 5: Поиск контактов с фильтром
// ============================================================================
echo '<h3>Пример 5: Поиск контактов</h3>';

$contacts = $manager->searchContacts(
    ['TYPE_ID' => 'CLIENT'],
    ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL'],
    ['DATE_CREATE' => 'DESC']
);

echo "Найдено контактов типа CLIENT: " . count($contacts) . "\n";

foreach (array_slice($contacts, 0, 5) as $contact) {
    echo "- #{$contact['ID']}: {$contact['NAME']} {$contact['LAST_NAME']}\n";
}

// ============================================================================
// ПРИМЕР 6: Обновление контакта
// ============================================================================
echo '<h3>Пример 6: Обновление контакта</h3>';

if ($contactId) {
    $updated = $manager->updateContact($contactId, [
        'ASSIGNED_BY_ID' => 2,
        'COMMENTS' => 'Переведен другому менеджеру'
    ]);

    if ($updated) {
        echo "✅ Контакт обновлен\n";
    }
}

// ============================================================================
// ПРИМЕР 7: Импорт контактов из CSV
// ============================================================================
echo '<h3>Пример 7: Импорт из CSV</h3>';

// Путь к CSV файлу (в текущей директории)
$csvPath = __DIR__ . '/example.csv';

if (file_exists($csvPath)) {
    $result = $manager->importFromCsv($csvPath, true);

    echo "Результаты импорта:\n";
    echo "- Создано: {$result['created']}\n";
    echo "- Обновлено: {$result['updated']}\n";
    echo "- Пропущено: {$result['skipped']}\n";

    if (!empty($result['errors'])) {
        echo "Ошибки:\n";
        foreach (array_slice($result['errors'], 0, 5) as $error) {
            echo "  - $error\n";
        }
    }
} else {
    echo "❌ Файл CSV не найден\n";
}

// ============================================================================
// ПРИМЕР 8: Экспорт контактов в CSV
// ============================================================================
echo '<h3>Пример 8: Экспорт в CSV</h3>';

$exportPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/contacts_export.csv';

if ($manager->exportToCsv($exportPath, ['TYPE_ID' => 'CLIENT'])) {
    echo "✅ Контакты экспортированы в /upload/contacts_export.csv\n";
    echo "Файл размер: " . filesize($exportPath) . " байт\n";
} else {
    echo "❌ Ошибка при экспорте\n";
}

// ============================================================================
// ПРИМЕР 9: Удаление контакта
// ============================================================================
echo '<h3>Пример 9: Удаление контакта</h3>';

// Раскомментировать для теста
/*
if ($contactId) {
    $deleted = $manager->deleteContact($contactId);

    if ($deleted) {
        echo "✅ Контакт удален\n";
    }
}
*/

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
?>
