<?php
/**
 * Импорт лидов из CSV с дедупликацией по телефону.
 *
 * 1. Перед добавлением каждого лида проверяется crm.duplicate.findbycomm —
 *    есть ли уже лид или контакт с таким номером в CRM (Bitrix24 сам
 *    нормализует формат: +7/8, скобки, пробелы — свой нормализатор не нужен).
 * 2. Уникальные лиды добавляются ПАЧКОЙ через batch (до 50 команд в одном
 *    запросе — см. topics/module-8-3.md) вместо N отдельных crm.lead.add.
 * 3. Дубли внутри самого CSV тоже отфильтровываются через массив seenPhones.
 */

require_once __DIR__ . '/crest.php';

$csvPath = $argv[1] ?? __DIR__ . '/leads.csv'; // csv: name;phone;email

if (!is_readable($csvPath)) {
    exit("File not readable: {$csvPath}\n");
}

$rows = [];
$seenPhones = [];

$handle = fopen($csvPath, 'r');
while (($data = fgetcsv($handle, 0, ';')) !== false) {
    [$name, $phone, $email] = array_map('trim', $data + [null, null, null]);
    if (!$phone) {
        continue;
    }

    // Нормализация только для дедупликации внутри CSV —
    // для запроса к crm.duplicate.findbycomm передаём исходный номер,
    // Bitrix24 сам приводит к единому виду
    $normPhone = preg_replace('/\D/', '', $phone);
    if (isset($seenPhones[$normPhone])) {
        echo "Duplicate inside CSV: {$phone} ({$name})\n";
        continue;
    }
    $seenPhones[$normPhone] = true;

    $rows[] = ['name' => $name, 'phone' => $phone, 'email' => $email];
}
fclose($handle);

$uniqueToAdd = [];

foreach ($rows as $row) {
    // Проверка дубля именно в CRM, а не только в CSV — классическая
    // ловушка, которая отличает ответ middle+ от junior на собеседовании
    $dup = CRest::call('crm.duplicate.findbycomm', [
        'entity_type' => 'LEAD',
        'type' => 'PHONE',
        'values' => [$row['phone']],
    ]);

    if (!empty($dup['result']['LEAD'])) {
        echo "Already exists in CRM: {$row['phone']} ({$row['name']}) — lead ID {$dup['result']['LEAD'][0]}\n";
        continue;
    }

    $fields = [
        'TITLE' => 'Лид: ' . $row['name'],
        'NAME' => $row['name'],
        'PHONE' => [['VALUE' => $row['phone'], 'VALUE_TYPE' => 'WORK']],
    ];
    if ($row['email']) {
        $fields['EMAIL'] = [['VALUE' => $row['email'], 'VALUE_TYPE' => 'WORK']];
    }

    $uniqueToAdd[] = $fields;
}

// Добавление пачками по 50 — batch считается ОДНИМ запросом с точки зрения
// rate limit (см. topics/module-8-3.md), поэтому 10 000 лидов импортируются
// ~200 batch-запросами, а не 10 000 отдельных crm.lead.add
$chunks = array_chunk($uniqueToAdd, 50);

foreach ($chunks as $i => $chunk) {
    $cmd = [];
    foreach ($chunk as $key => $fields) {
        $cmd["add{$key}"] = 'crm.lead.add?' . http_build_query(['fields' => $fields]);
    }

    $result = CRest::call('batch', ['halt' => 0, 'cmd' => $cmd]);
    $added = count($result['result']['result'] ?? []);
    echo "Batch {$i}: added {$added} leads\n";

    usleep(600_000); // ~600 мс между батчами — защита от QUERY_LIMIT_EXCEEDED
}
