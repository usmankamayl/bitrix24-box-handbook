<?php
/**
 * Скрипт импорта контактов из CSV через командную строку
 *
 * Расположение в проекте:
 * /local/scripts/import_contacts.php
 *
 * Использование:
 * php /local/scripts/import_contacts.php /path/to/contacts.csv
 *
 * С обновлением существующих:
 * php /local/scripts/import_contacts.php /path/to/contacts.csv --update
 */

$_SERVER["DOCUMENT_ROOT"] = realpath(__DIR__ . '/../..');
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('BX_NO_ACCELERATOR_RESET', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Local\Bitrix24\ContactManager;

// Проверка аргументов
if ($argc < 2) {
    echo "Использование: php import_contacts.php <путь_к_csv> [--update]\n";
    echo "\nПараметры:\n";
    echo "  <путь_к_csv>  - путь к CSV файлу с контактами\n";
    echo "  --update      - обновлять существующие контакты (по умолчанию пропускать)\n";
    exit(1);
}

$csvPath = $argv[1];
$updateExisting = in_array('--update', $argv);

// Проверка файла
if (!file_exists($csvPath)) {
    echo "❌ Файл не найден: $csvPath\n";
    exit(1);
}

echo "📄 Файл: $csvPath\n";
echo "🔄 Обновление существующих: " . ($updateExisting ? 'Да' : 'Нет') . "\n";
echo "⏳ Начинаем импорт...\n\n";

// URL webhook (можно вынести в конфиг)
$webhookUrl = 'https://вашподомен.bitrix24.ru/rest/1/webhook_токен';

$manager = new ContactManager($webhookUrl);

// Запускаем импорт
$startTime = microtime(true);
$result = $manager->importFromCsv($csvPath, $updateExisting);
$endTime = microtime(true);

$duration = round($endTime - $startTime, 2);

// Выводим результат
echo "\n";
echo "✅ Импорт завершен за {$duration} сек.\n\n";
echo "Статистика:\n";
echo "  📝 Создано контактов: {$result['created']}\n";
echo "  ✏️  Обновлено контактов: {$result['updated']}\n";
echo "  ⏭️  Пропущено записей: {$result['skipped']}\n";

if (!empty($result['errors'])) {
    echo "\n❌ Ошибки ({" . count($result['errors']) . "}):\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\n";
