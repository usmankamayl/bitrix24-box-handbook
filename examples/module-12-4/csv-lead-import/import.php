#!/usr/bin/env php
<?php
/**
 * Консольный скрипт массового импорта лидов из CSV в CRM.
 *
 * Запуск: php import.php leads.csv
 *
 * Особенности:
 *  - импорт батчами по 50 команд за HTTP-запрос (rate limit облачного
 *    вебхука — обычно 2 запроса/сек);
 *  - идемпотентность: external_id → ID лида сохраняется в mapping.json,
 *    при повторном запуске уже импортированные строки пропускаются;
 *  - перед добавлением проверяется дубль по email (crm.duplicate.findbycomm) —
 *    если лид с таким email уже есть в CRM (создан вручную или другой
 *    интеграцией), новый не создаётся, а в mapping сохраняется ID найденного;
 *  - прогресс сохраняется после каждого батча — при обрыве скрипт можно
 *    просто перезапустить, он продолжит с того места, где остановился.
 */

require_once __DIR__ . '/crest.php';

const BATCH_SIZE          = 50;
const SLEEP_BETWEEN_BATCH = 1; // секунда — не упираться в rate limit вебхука

function loadMapping(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    return json_decode(file_get_contents($path), true) ?: [];
}

function saveMapping(string $path, array $mapping): void
{
    file_put_contents($path, json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function readCsvRows(string $path): array
{
    $rows   = [];
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);

    while (($line = fgetcsv($handle)) !== false) {
        $rows[] = array_combine($header, $line);
    }

    fclose($handle);
    return $rows;
}

// --- Точка входа ---

$csvPath = $argv[1] ?? null;
if (!$csvPath || !file_exists($csvPath)) {
    fwrite(STDERR, "Использование: php import.php путь/к/файлу.csv\n");
    exit(1);
}

$mappingPath = __DIR__ . '/mapping.json';
$mapping     = loadMapping($mappingPath);

$allRows = readCsvRows($csvPath);
$newRows = array_values(array_filter(
    $allRows,
    fn(array $row) => !isset($mapping[$row['external_id']])
));

$skippedAlready = count($allRows) - count($newRows);
if ($skippedAlready > 0) {
    echo "Пропущено {$skippedAlready} уже импортированных строк (найдены в mapping.json)\n";
}

$imported  = 0;
$duplicates = 0;
$errors    = 0;

foreach (array_chunk($newRows, BATCH_SIZE) as $batchIndex => $batch) {
    echo 'Батч ' . ($batchIndex + 1) . ': ' . count($batch) . " строк\n";

    // Шаг 1: проверяем дубли по email одним батч-запросом
    $checkCommands = [];
    foreach ($batch as $i => $row) {
        $checkCommands["check_{$i}"] = [
            'method' => 'crm.duplicate.findbycomm',
            'params' => ['type' => 'EMAIL', 'values' => [$row['email']]],
        ];
    }

    $checkResult = CRest::callBatch($checkCommands);
    if (!empty($checkResult['error'])) {
        fwrite(STDERR, 'Ошибка batch-проверки дублей: ' . ($checkResult['error_description'] ?? $checkResult['error']) . "\n");
        $errors += count($batch);
        continue;
    }

    // Шаг 2: формируем команды добавления только для тех, у кого дубля нет
    $addCommands  = [];
    $rowByAddKey  = [];
    foreach ($batch as $i => $row) {
        $existingLeadId = $checkResult['result']['result']["check_{$i}"]['LEAD'][0] ?? null;

        if ($existingLeadId) {
            $mapping[$row['external_id']] = (int)$existingLeadId;
            $duplicates++;
            echo "  {$row['external_id']}: дубль, уже есть лид #{$existingLeadId}\n";
            continue;
        }

        $addKey = "add_{$i}";
        $addCommands[$addKey] = [
            'method' => 'crm.lead.add',
            'params' => [
                'fields' => [
                    'TITLE'      => $row['title'],
                    'NAME'       => $row['name'],
                    'LAST_NAME'  => $row['last_name'],
                    'EMAIL'      => [['VALUE' => $row['email'], 'VALUE_TYPE' => 'WORK']],
                    'PHONE'      => [['VALUE' => $row['phone'], 'VALUE_TYPE' => 'WORK']],
                    'SOURCE_ID'  => 'IMPORT',
                ],
            ],
        ];
        $rowByAddKey[$addKey] = $row;
    }

    if (!empty($addCommands)) {
        $addResult = CRest::callBatch($addCommands);

        if (!empty($addResult['error'])) {
            fwrite(STDERR, 'Ошибка batch-добавления: ' . ($addResult['error_description'] ?? $addResult['error']) . "\n");
            $errors += count($addCommands);
        } else {
            foreach ($addCommands as $key => $command) {
                $row    = $rowByAddKey[$key];
                $leadId = $addResult['result']['result'][$key] ?? null;

                if ($leadId) {
                    $mapping[$row['external_id']] = (int)$leadId;
                    $imported++;
                    echo "  {$row['external_id']}: создан лид #{$leadId}\n";
                } else {
                    $errorText = $addResult['result']['result_error'][$key] ?? 'неизвестная ошибка';
                    fwrite(STDERR, "  {$row['external_id']}: ошибка — {$errorText}\n");
                    $errors++;
                }
            }
        }
    }

    // Сохраняем прогресс после каждого батча — при обрыве скрипт
    // продолжит именно отсюда при повторном запуске
    saveMapping($mappingPath, $mapping);

    sleep(SLEEP_BETWEEN_BATCH);
}

echo "\nГотово. Создано: {$imported}, дублей пропущено: {$duplicates}, ошибок: {$errors}\n";
