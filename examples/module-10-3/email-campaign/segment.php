<?php
/**
 * segment.php — Формирование сегмента для email-рассылки.
 *
 * Задача: выбрать всех активных контактов с email-адресом,
 * у которых за последние 6 месяцев была выигранная сделка.
 *
 * Результат: массив получателей вида:
 * [
 *   ['ID' => 123, 'NAME' => 'Иван', 'LAST_NAME' => 'Петров', 'EMAIL' => 'ivan@client.com'],
 *   ...
 * ]
 *
 * Логика:
 * 1. Получаем выигранные сделки за период -> собираем CONTACT_ID
 * 2. Получаем контакты по этим ID + фильтруем только с EMAIL
 * 3. Проверяем черный список (отписавшиеся)
 * 4. Возвращаем готовый сегмент
 */

require_once __DIR__ . '/crest.php';

// --- Настройки сегмента -----------------------------------------

// Период "активности клиента": последние 6 месяцев
define('ACTIVE_PERIOD_DAYS', 180);

// Локальный черный список email (в реальном проекте — таблица БД)
// В коробочной версии можно читать BlackListTable модуля sender
$LOCAL_BLACKLIST = [
    'unsubscribed@client.com',
    'bounced@client.com',
];

// --- Шаг 1. Выбираем выигранные сделки за период ----------------

$dateFrom = date('Y-m-d', strtotime('-' . ACTIVE_PERIOD_DAYS . ' days'));

echo "Шаг 1: ищем выигранные сделки с {$dateFrom}...\n";

$wonDeals   = [];   // CONTACT_ID => true (используем как set для дедупликации)
$start      = 0;

// Постраничная выборка через параметр 'start'
// crm.deal.list возвращает максимум 50 записей за раз
do {
    $response = CRest::call('crm.deal.list', [
        'filter' => [
            'STAGE_ID'  => 'WON',           // Стадия "Выиграна" — зависит от воронки!
                                            // Часто это 'C1:WON', 'NEW' и т.д.
                                            // Точное значение смотри в crm.dealcategory.stage.list
            '>=CLOSEDATE' => $dateFrom,     // Закрыта не раньше dateFrom
            '!CONTACT_ID' => null,          // Только с привязанным контактом
        ],
        'select' => ['ID', 'CONTACT_ID', 'CLOSEDATE'],
        'order'  => ['ID' => 'ASC'],
        'start'  => $start,
    ]);

    if (isset($response['error'])) {
        die('Ошибка при получении сделок: ' . $response['error_description'] . "\n");
    }

    foreach ($response['result'] as $deal) {
        if (!empty($deal['CONTACT_ID'])) {
            $wonDeals[(int)$deal['CONTACT_ID']] = true;
        }
    }

    // start для следующей страницы
    $start = $response['next'] ?? -1;

} while ($start > 0);

$contactIds = array_keys($wonDeals);
echo 'Найдено контактов с выигранными сделками: ' . count($contactIds) . "\n";

if (empty($contactIds)) {
    die("Сегмент пуст — завершаем.\n");
}

// --- Шаг 2. Получаем данные контактов ---------------------------
//
// crm.contact.list не умеет фильтровать по списку ID напрямую,
// но поддерживает IN-фильтр: 'ID' => [1,2,3]
// Для больших списков разбиваем на пачки по 50 (лимит фильтра).

echo "Шаг 2: загружаем данные контактов...\n";

$contacts   = [];
$idChunks   = array_chunk($contactIds, 50);
$commands   = [];

// Формируем batch-команды — один вызов crm.contact.list на чанк ID
foreach ($idChunks as $i => $chunk) {
    $commands['contacts_' . $i] = 'crm.contact.list?' . http_build_query([
        'filter' => [
            'ID'      => $chunk,
            'HAS_EMAIL' => 'Y',          // Только с заполненным EMAIL-полем
            // Исключаем "не клиенты" — если используешь TYPE_ID
            // 'TYPE_ID' => 'CLIENT',
        ],
        'select' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL', 'ASSIGNED_BY_ID'],
    ]);
}

$batchResult = CRest::callBatch($commands);

// Собираем контакты из batch-ответа
foreach ($batchResult as $key => $rows) {
    if (!is_array($rows)) continue;
    foreach ($rows as $contact) {
        $contacts[] = $contact;
    }
}

echo 'Загружено контактов с email: ' . count($contacts) . "\n";

// --- Шаг 3. Фильтруем: выбираем email для рассылки --------------
//
// EMAIL — multifield (массив адресов).
// Для рассылки предпочитаем VALUE_TYPE = 'MAILING',
// иначе берём первый валидный адрес.

echo "Шаг 3: формируем список получателей...\n";

$segment = [];

foreach ($contacts as $contact) {
    $emails = $contact['EMAIL'] ?? [];
    if (empty($emails)) continue;

    $targetEmail = null;

    // Приоритет: адрес с типом MAILING (специально для рассылок)
    foreach ($emails as $e) {
        if (($e['VALUE_TYPE'] ?? '') === 'MAILING' && !empty($e['VALUE'])) {
            $targetEmail = $e['VALUE'];
            break;
        }
    }

    // Если MAILING-адреса нет — берём первый валидный
    if (!$targetEmail) {
        foreach ($emails as $e) {
            if (!empty($e['VALUE']) && filter_var($e['VALUE'], FILTER_VALIDATE_EMAIL)) {
                $targetEmail = $e['VALUE'];
                break;
            }
        }
    }

    if (!$targetEmail) continue;

    // Проверяем черный список
    if (in_array(strtolower($targetEmail), $LOCAL_BLACKLIST, true)) {
        echo "  Пропускаем {$targetEmail} — в черном списке\n";
        continue;
    }

    $segment[] = [
        'ID'        => $contact['ID'],
        'NAME'      => $contact['NAME'] ?? '',
        'LAST_NAME' => $contact['LAST_NAME'] ?? '',
        'EMAIL'     => $targetEmail,
    ];
}

// --- Результат --------------------------------------------------

echo "\n========== СЕГМЕНТ ДЛЯ РАССЫЛКИ ==========\n";
echo 'Получателей: ' . count($segment) . "\n\n";

foreach ($segment as $i => $row) {
    printf(
        "%3d. [%d] %s %s — %s\n",
        $i + 1,
        $row['ID'],
        $row['NAME'],
        $row['LAST_NAME'],
        $row['EMAIL']
    );
}

// Сохраняем сегмент в файл для send.php
file_put_contents(
    __DIR__ . '/segment.json',
    json_encode($segment, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

echo "\nСегмент сохранён в segment.json\n";
