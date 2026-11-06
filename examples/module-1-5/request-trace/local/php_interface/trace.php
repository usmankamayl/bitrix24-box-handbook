<?php
/**
 * Трассировка фаз запроса. Копировать в /local/php_interface/trace.php
 * и подключить одной строкой из /local/php_interface/init.php.
 *
 * Работает только при ?bx_trace=Y и только для администратора.
 * Ничего не пишет в вывод для обычных посетителей.
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (($_GET['bx_trace'] ?? '') !== 'Y') {
    return;
}

$GLOBALS['BX_TRACE'] = [];
$GLOBALS['BX_TRACE_T0'] = microtime(true);

$mark = static function (string $label): void {
    $GLOBALS['BX_TRACE'][] = [
        'label' => $label,
        'state' => $GLOBALS['BX_STATE'] ?? '-',
        'ms'    => round((microtime(true) - $GLOBALS['BX_TRACE_T0']) * 1000, 2),
        'mb'    => round(memory_get_usage(true) / 1048576, 1),
    ];
};

$mark('init.php (пролог идёт)');

AddEventHandler('main', 'OnBeforeProlog', static function () use ($mark): void {
    $mark('OnBeforeProlog');
});

AddEventHandler('main', 'OnProlog', static function () use ($mark): void {
    $mark('OnProlog');
});

AddEventHandler('main', 'OnEpilog', static function () use ($mark): void {
    $mark('OnEpilog');
});

AddEventHandler('main', 'OnBeforeEndBufferContent', static function () use ($mark): void {
    $mark('OnBeforeEndBufferContent');
});

/**
 * Единственная точка, где ещё можно изменить готовый HTML:
 * обработчик получает $content по ссылке.
 */
AddEventHandler('main', 'OnEndBufferContent', static function (&$content) use ($mark): void {
    $mark('OnEndBufferContent');

    global $USER;

    // Права проверяем здесь, а не при регистрации: init.php может выполниться
    // раньше, чем $USER восстановлен из сессии.
    if (!$USER->IsAdmin()) {
        return;
    }

    $rows = '';
    $prev = null;

    foreach ($GLOBALS['BX_TRACE'] as $m) {
        $delta = $prev === null ? $m['ms'] : round($m['ms'] - $prev, 2);
        $prev = $m['ms'];
        $rows .= '<tr>'
            . '<td style="padding:4px 8px;border-bottom:1px solid #ddd">' . htmlspecialcharsbx($m['label']) . '</td>'
            . '<td style="padding:4px 8px;border-bottom:1px solid #ddd">' . htmlspecialcharsbx($m['state']) . '</td>'
            . '<td style="padding:4px 8px;border-bottom:1px solid #ddd;text-align:right">' . $m['ms'] . '</td>'
            . '<td style="padding:4px 8px;border-bottom:1px solid #ddd;text-align:right">+' . $delta . '</td>'
            . '<td style="padding:4px 8px;border-bottom:1px solid #ddd;text-align:right">' . $m['mb'] . '</td>'
            . '</tr>';
    }

    $content .= '<div style="margin:24px auto;max-width:900px;font:13px/1.4 monospace;'
        . 'background:#fff;border:2px solid #2e7d32;border-radius:6px;padding:12px">'
        . '<div style="font:600 14px sans-serif;margin-bottom:8px">BX TRACE — фазы запроса</div>'
        . '<table style="border-collapse:collapse;width:100%">'
        . '<tr style="background:#e8f5e9">'
        . '<th style="padding:4px 8px;text-align:left">Фаза</th>'
        . '<th style="padding:4px 8px;text-align:left">BX_STATE</th>'
        . '<th style="padding:4px 8px;text-align:right">мс от старта</th>'
        . '<th style="padding:4px 8px;text-align:right">дельта</th>'
        . '<th style="padding:4px 8px;text-align:right">память, МБ</th>'
        . '</tr>' . $rows . '</table></div>';
});

AddEventHandler('main', 'OnAfterEpilog', static function () use ($mark): void {
    $mark('OnAfterEpilog');

    // Ответ уже отдан: в HTML это попасть не может. Пишем в файл — иначе
    // не увидеть, что событие вообще сработало.
    $log = $_SERVER['DOCUMENT_ROOT'] . '/upload/bx_trace_last.log';
    @file_put_contents($log, date('c') . ' ' . $_SERVER['REQUEST_URI'] . "\n" . var_export($GLOBALS['BX_TRACE'], true) . "\n", FILE_APPEND);
});
