<?php
/**
 * send.php — Персонализированная массовая отправка через crm.message.add.
 *
 * Читает сегмент из segment.json (формируется в segment.php)
 * и отправляет каждому контакту персональное письмо.
 *
 * Ключевые техники:
 * - Подстановка полей: #NAME#, #LAST_NAME# -> реальные данные контакта
 * - batch-отправка: до 50 писем за один REST-запрос
 * - Паузы между батчами — защита от rate limit
 * - Логирование результатов
 *
 * ВАЖНО: crm.message.add — это письма из карточки CRM (транзакционные).
 * Для полноценных маркетинговых рассылок (трекинг, отписка, шаблоны)
 * используй CRM-маркетинг или внешний сервис (Unisender, Mailchimp).
 */

require_once __DIR__ . '/crest.php';

// --- Настройки кампании -----------------------------------------

// Адрес подключённого почтового ящика в Bitrix24.
// Должен точно совпадать с ящиком из CRM -> Настройки -> Почта.
define('EMAIL_FROM', 'sales@your-company.com');

// Тема письма (тоже поддерживает подстановку)
define('EMAIL_SUBJECT', '#NAME#, специальное предложение для вас');

// HTML-шаблон письма.
// Макросы: #NAME#, #LAST_NAME#, #UNSUBSCRIBE_LINK#
$EMAIL_TEMPLATE = <<<HTML
<html>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">
    <p>Здравствуйте, <b>#NAME# #LAST_NAME#</b>!</p>

    <p>Спасибо, что вы с нами. Подготовили для вас персональную скидку <b>20%</b>
    на весь каталог до конца месяца.</p>

    <p><a href="https://your-site.com/promo?utm_source=crm&utm_medium=email"
          style="background:#2fc6f6;color:#fff;padding:10px 20px;
                text-decoration:none;border-radius:4px;">
        Получить скидку
    </a></p>

    <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
    <p style="font-size:11px;color:#999;">
        Вы получили это письмо, потому что являетесь клиентом компании.<br>
        <a href="#UNSUBSCRIBE_LINK#" style="color:#999;">Отписаться от рассылки</a>
    </p>
</body>
</html>
HTML;

// URL отписки — должен быть рабочим!
// По клику адрес добавляется в черный список.
define('UNSUBSCRIBE_URL', 'https://your-site.com/unsubscribe.php?email=');

// --- Загружаем сегмент ------------------------------------------

$segmentFile = __DIR__ . '/segment.json';
if (!file_exists($segmentFile)) {
    die("segment.json не найден — сначала запусти segment.php\n");
}

$segment = json_decode(file_get_contents($segmentFile), true);
if (empty($segment)) {
    die("Сегмент пуст.\n");
}

echo 'Получателей в сегменте: ' . count($segment) . "\n\n";

// --- Функция персонализации шаблона -----------------------------

/**
 * Подставляет данные контакта в шаблон письма.
 */
function personalize(string $template, array $contact): string
{
    $unsubLink = UNSUBSCRIBE_URL . urlencode($contact['EMAIL']);

    return str_replace(
        ['#NAME#', '#LAST_NAME#', '#UNSUBSCRIBE_LINK#'],
        [
            htmlspecialchars($contact['NAME']),
            htmlspecialchars($contact['LAST_NAME']),
            $unsubLink,
        ],
        $template
    );
}

// --- Подготовка batch-команд ------------------------------------
//
// Вместо 100 отдельных вызовов crm.message.add делаем
// batch: до 50 команд за один HTTP-запрос.

echo "Формируем batch-команды...\n";

$commands  = [];
$cmdToCid  = []; // связь cmd_name -> ID контакта (для логирования результата)

foreach ($segment as $i => $contact) {
    $cmdName = 'msg_' . $contact['ID'];

    $subject = personalize(EMAIL_SUBJECT, $contact);
    $message = personalize($EMAIL_TEMPLATE, $contact);

    // Формируем строку команды для batch
    // Формат: method?param1=val1&fields[X]=valX
    $params = http_build_query([
        'fields' => [
            'ENTITY_TYPE_ID' => 3,                   // 3 = контакт
            'ENTITY_ID'      => $contact['ID'],
            'SUBJECT'        => $subject,
            'MESSAGE'        => $message,
            'FROM'           => EMAIL_FROM,
        ]
    ]);

    $commands[$cmdName]  = 'crm.message.add?' . $params;
    $cmdToCid[$cmdName]  = $contact;
}

// --- Отправка батчами -------------------------------------------

echo 'Команд к отправке: ' . count($commands) . "\n";
echo "Запускаем batch-отправку (пауза 0.5 сек между батчами)...\n\n";

$chunks    = array_chunk($commands, 50, true);
$results   = [];
$batchNum  = 0;

foreach ($chunks as $chunk) {
    $batchNum++;
    echo "Батч #{$batchNum} (" . count($chunk) . " писем)...\n";

    $response = CRest::call('batch', [
        'halt' => 0,    // не останавливать весь батч при ошибке одной команды
        'cmd'  => $chunk,
    ]);

    if (isset($response['error'])) {
        echo '  ОШИБКА batch: ' . ($response['error_description'] ?? $response['error']) . "\n";
        // Помечаем все письма чанка как ошибочные
        foreach ($chunk as $cmdName => $_) {
            $results[$cmdName] = ['ok' => false, 'error' => $response['error']];
        }
    } else {
        // result['result'] — ответы команд, result['result_error'] — ошибки команд
        $okResults  = $response['result']['result']       ?? [];
        $errResults = $response['result']['result_error'] ?? [];

        foreach ($chunk as $cmdName => $_) {
            if (isset($errResults[$cmdName])) {
                $results[$cmdName] = [
                    'ok'    => false,
                    'error' => $errResults[$cmdName]['error_description'] ?? 'unknown',
                ];
            } else {
                // crm.message.add возвращает ID созданного сообщения
                $results[$cmdName] = [
                    'ok'         => true,
                    'message_id' => $okResults[$cmdName] ?? null,
                ];
            }
        }
    }

    // Пауза между батчами — защита от rate limit (~2 запроса/сек)
    usleep(500000);
}

// --- Лог результатов ---------------------------------------------

$okCount  = count(array_filter($results, fn($r) => $r['ok']));
$errCount = count($results) - $okCount;

echo "\n========== РЕЗУЛЬТАТ ОТПРАВКИ ==========\n";
echo "Успешно: {$okCount}\n";
echo "Ошибок:  {$errCount}\n\n";

if ($errCount > 0) {
    echo "Ошибки:\n";
    foreach ($results as $cmdName => $r) {
        if (!$r['ok']) {
            $c = $cmdToCid[$cmdName];
            echo "  [{$c['ID']}] {$c['EMAIL']} — {$r['error']}\n";
        }
    }
}

// Сохраняем полный лог
file_put_contents(
    __DIR__ . '/send_log_' . date('Ymd_His') . '.json',
    json_encode(
        [
            'date'    => date('c'),
            'total'   => count($results),
            'success' => $okCount,
            'errors'  => $errCount,
            'details' => $results,
        ],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    )
);

echo "\nЛог сохранён в send_log_*.json\n";
echo "\nВАЖНО: crm.message.add пишет письма в timeline контакта.\n";
echo "Для маркетинговых рассылок с трекингом используй CRM-маркетинг\n";
echo "или внешний сервис (Unisender, Mailchimp).\n";
