<?php
/**
 * Отчёт "сумма и количество успешно закрытых сделок по менеджерам за месяц".
 *
 * STAGE_SEMANTIC_ID = 'S' — вместо списка конкретных STAGE_ID, чтобы отчёт
 * не ломался при переименовании стадий или добавлении новых воронок.
 * 'start' => -1 — автопагинация (Bitrix24 сам итерирует страницы,
 * не нужен самописный цикл по next/cursor).
 */

require_once __DIR__ . '/crest.php';

$from = $argv[1] ?? date('Y-m-01');                       // начало месяца
$to = $argv[2] ?? date('Y-m-01', strtotime('+1 month'));  // начало следующего

$deals = CRest::call('crm.deal.list', [
    'filter' => [
        'STAGE_SEMANTIC_ID' => 'S',   // только успешные — не "все закрытые"
        '>=CLOSEDATE' => $from,
        '<CLOSEDATE' => $to,
    ],
    'select' => ['ASSIGNED_BY_ID', 'OPPORTUNITY', 'CURRENCY_ID'],
    'start' => -1,                    // автопагинация нового API
]);

if (!empty($deals['error'])) {
    exit('crm.deal.list failed: ' . ($deals['error_description'] ?? 'unknown') . "\n");
}

$byManager = [];
foreach ($deals['result'] as $d) {
    $managerId = (int) $d['ASSIGNED_BY_ID'];
    $byManager[$managerId]['count'] = ($byManager[$managerId]['count'] ?? 0) + 1;
    $byManager[$managerId]['sum'] = ($byManager[$managerId]['sum'] ?? 0) + (float) $d['OPPORTUNITY'];
}

// Подтягиваем имена менеджеров одним batch, а не N user.get
$cmd = [];
foreach (array_keys($byManager) as $id) {
    $cmd["user{$id}"] = 'user.get?' . http_build_query(['id' => $id]);
}
$users = CRest::call('batch', ['halt' => 0, 'cmd' => $cmd]);

$names = [];
foreach ($users['result']['result'] ?? [] as $key => $u) {
    $names[(int) $u[0]['ID']] = trim($u[0]['NAME'] . ' ' . $u[0]['LAST_NAME']);
}

// Вывод
printf("%-30s %10s %15s\n", 'Менеджер', 'Сделок', 'Сумма');
str_repeat('-', 60) . "\n";
foreach ($byManager as $managerId => $stat) {
    printf("%-30s %10d %15.2f\n",
        $names[$managerId] ?? "ID {$managerId}",
        $stat['count'],
        $stat['sum']
    );
}
