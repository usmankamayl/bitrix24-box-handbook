<?php
/**
 * Примеры использования ContactBatchImporter
 *
 * Расположение: /local/php_interface/examples/batch_contact_import_examples.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/ContactBatchImporter.php';

use Vettich\ContactBatchImporter;

// Инициализация REST клиента
$client = CRest::getInstance();

// ============================================================
// ПРИМЕР 1: Простой импорт из CSV
// ============================================================

echo "=== ПРИМЕР 1: Простой импорт ===\n\n";

$importer = new ContactBatchImporter($client);

$result = $importer->importFromCsv(
    $_SERVER['DOCUMENT_ROOT'] . '/upload/contacts.csv'
);

$importer->printReport();

// ============================================================
// ПРИМЕР 2: Импорт с обновлением существующих контактов
// ============================================================

echo "\n=== ПРИМЕР 2: Импорт с обновлением дублей ===\n\n";

$importer2 = new ContactBatchImporter($client);

$result = $importer2->importFromCsv(
    $_SERVER['DOCUMENT_ROOT'] . '/upload/contacts_update.csv',
    [
        'skip_duplicates' => false,      // Не пропускать дубли
        'update_duplicates' => true,     // Обновлять существующие
        'normalize_phones' => true       // Нормализовать телефоны
    ]
);

$importer2->printReport();

// ============================================================
// ПРИМЕР 3: Импорт из файла в кодировке Windows-1251
// ============================================================

echo "\n=== ПРИМЕР 3: Импорт с конвертацией кодировки ===\n\n";

$importer3 = new ContactBatchImporter($client);

$result = $importer3->importFromCsv(
    $_SERVER['DOCUMENT_ROOT'] . '/upload/contacts_win1251.csv',
    [
        'encoding' => 'Windows-1251',    // Указываем кодировку
        'delimiter' => ';',               // Разделитель точка с запятой
        'normalize_phones' => true
    ]
);

$importer3->printReport();

// ============================================================
// ПРИМЕР 4: Импорт с настройкой размера batch-пакета
// ============================================================

echo "\n=== ПРИМЕР 4: Импорт с маленькими пакетами ===\n\n";

$importer4 = new ContactBatchImporter($client);
$importer4->setBatchSize(20); // Меньший размер для медленного соединения

$result = $importer4->importFromCsv(
    $_SERVER['DOCUMENT_ROOT'] . '/upload/large_contacts.csv',
    [
        'skip_duplicates' => true,
        'normalize_phones' => true
    ]
);

$importer4->printReport();

// ============================================================
// ПРИМЕР 5: Программная обработка результатов
// ============================================================

echo "\n=== ПРИМЕР 5: Обработка результатов ===\n\n";

$importer5 = new ContactBatchImporter($client);

$result = $importer5->importFromCsv(
    $_SERVER['DOCUMENT_ROOT'] . '/upload/contacts.csv'
);

$report = $importer5->getReport();

// Отправляем email-уведомление администратору
if ($report['imported'] > 0 || $report['errors'] > 0) {
    $message = "Импорт контактов завершен:\n";
    $message .= "- Импортировано: {$report['imported']}\n";
    $message .= "- Обновлено: {$report['updated']}\n";
    $message .= "- Ошибок: {$report['errors']}\n";
    $message .= "- Время: {$report['execution_time']} сек\n";

    // Отправка email
    mail('admin@example.com', 'Импорт контактов завершен', $message);
}

// Сохраняем лог в файл
if (!empty($report['log'])) {
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/import_log_' . date('Y-m-d_H-i-s') . '.txt';
    $logContent = "Импорт контактов " . date('Y-m-d H:i:s') . "\n";
    $logContent .= str_repeat('=', 60) . "\n\n";

    foreach ($report['log'] as $entry) {
        $logContent .= "[{$entry['time']}] {$entry['message']}\n";
    }

    file_put_contents($logFile, $logContent);
    echo "Лог сохранен в: $logFile\n";
}

// ============================================================
// ПРИМЕР 6: CLI скрипт для запуска из командной строки
// ============================================================

/**
 * Запуск из командной строки:
 * php contact_import_cli.php --file=/path/to/contacts.csv --update-duplicates
 */

if (php_sapi_name() === 'cli') {
    $options = getopt('', ['file:', 'update-duplicates', 'encoding:', 'batch-size:']);

    if (isset($options['file'])) {
        $importer = new ContactBatchImporter($client);

        // Настройка размера пакета
        if (isset($options['batch-size'])) {
            $importer->setBatchSize((int)$options['batch-size']);
        }

        $importOptions = [
            'update_duplicates' => isset($options['update-duplicates']),
            'encoding' => $options['encoding'] ?? 'UTF-8'
        ];

        echo "Импорт контактов из файла: {$options['file']}\n";
        echo "Опции: " . json_encode($importOptions, JSON_UNESCAPED_UNICODE) . "\n\n";

        $importer->importFromCsv($options['file'], $importOptions);
        $importer->printReport();
    } else {
        echo "Использование:\n";
        echo "  php contact_import_cli.php --file=/path/to/file.csv [--update-duplicates] [--encoding=UTF-8] [--batch-size=50]\n";
        echo "\nОпции:\n";
        echo "  --file            Путь к CSV-файлу (обязательно)\n";
        echo "  --update-duplicates  Обновлять существующие контакты\n";
        echo "  --encoding        Кодировка файла (по умолчанию UTF-8)\n";
        echo "  --batch-size      Размер batch-пакета (по умолчанию 50)\n";
    }
}

// ============================================================
// ПРИМЕР 7: Интеграция с Bitrix агентом
// ============================================================

/**
 * Автоматический импорт контактов из определенной папки
 *
 * Добавить в /bitrix/php_interface/init.php:
 * CAgent::AddAgent(
 *     "ImportContactsFromFolder();",
 *     "",
 *     "N",
 *     3600, // Каждый час
 *     "",
 *     "Y",
 *     "",
 *     30
 * );
 */
function ImportContactsFromFolder()
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/ContactBatchImporter.php';

    $client = CRest::getInstance();
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/auto_import/';

    // Ищем CSV файлы
    $files = glob($uploadDir . '*.csv');

    foreach ($files as $file) {
        echo "Импорт из файла: " . basename($file) . "\n";

        $importer = new ContactBatchImporter($client);
        $importer->importFromCsv($file, [
            'skip_duplicates' => true,
            'normalize_phones' => true
        ]);

        $report = $importer->getReport();

        // Перемещаем обработанный файл в архив
        $archiveDir = $uploadDir . 'archive/';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $newName = $archiveDir . date('Y-m-d_H-i-s') . '_' . basename($file);
        rename($file, $newName);

        // Логируем результат
        error_log("Imported {$report['imported']} contacts from " . basename($file));
    }

    return "ImportContactsFromFolder();";
}

// ============================================================
// ПРИМЕР 8: Компонент для загрузки через админку
// ============================================================

/**
 * Простой компонент для загрузки CSV через веб-интерфейс
 * Разместить в /local/components/custom/contact.import/
 */

// component.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/ContactBatchImporter.php';

    $client = CRest::getInstance();
    $importer = new ContactBatchImporter($client);

    // Загружаем файл
    $uploadFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/temp_' . time() . '.csv';
    move_uploaded_file($_FILES['csv_file']['tmp_name'], $uploadFile);

    // Импортируем
    $options = [
        'update_duplicates' => isset($_POST['update_duplicates']),
        'encoding' => $_POST['encoding'] ?? 'UTF-8'
    ];

    $importer->importFromCsv($uploadFile, $options);
    $report = $importer->getReport();

    // Удаляем временный файл
    unlink($uploadFile);

    // Выводим результат
    echo '<div class="import-report">';
    echo '<h3>Импорт завершен</h3>';
    echo '<p>Импортировано: ' . $report['imported'] . '</p>';
    echo '<p>Обновлено: ' . $report['updated'] . '</p>';
    echo '<p>Дублей пропущено: ' . $report['duplicates'] . '</p>';
    echo '<p>Ошибок: ' . $report['errors'] . '</p>';
    echo '<p>Время выполнения: ' . $report['execution_time'] . ' сек</p>';
    echo '</div>';
}

// template.php
?>
<form method="post" enctype="multipart/form-data">
    <h2>Импорт контактов из CSV</h2>

    <div class="form-group">
        <label>CSV файл:</label>
        <input type="file" name="csv_file" accept=".csv" required>
    </div>

    <div class="form-group">
        <label>Кодировка:</label>
        <select name="encoding">
            <option value="UTF-8">UTF-8</option>
            <option value="Windows-1251">Windows-1251</option>
        </select>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="update_duplicates">
            Обновлять существующие контакты
        </label>
    </div>

    <button type="submit">Импортировать</button>
</form>

<div class="csv-format-info">
    <h3>Формат CSV файла</h3>
    <pre>name,phone,email,company,position
Иван Иванов,89001234567,ivan@example.com,ООО Компания,Директор
Петр Петров,+79001234568,petr@example.com,ИП Петров,ИП</pre>
</div>
