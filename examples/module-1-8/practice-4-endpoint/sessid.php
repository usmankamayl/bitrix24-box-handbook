<?php

/**
 * Выдать текущий sessid для ручных запросов к эндпоинту.
 *
 * Путь в проекте: /local/tools/sessid.php (только для разработки)
 *
 * В реальном приложении sessid попадает в страницу через bitrix_sessid_post()
 * и уходит в заголовке X-Bitrix-Sessid из JS. Этот файл нужен только чтобы
 * сделать curl-сценарии из README без открытия формы.
 *
 * Удалить на проде: sessid — эквивалент CSRF-токена.
 */

define('NO_KEEP_STATISTIC', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

/** @var CUser $USER */
global $USER;

header('Content-Type: text/plain; charset=UTF-8');

if (!$USER->IsAuthorized() || !$USER->IsAdmin()) {
    http_response_code(403);
    echo "нужна авторизация администратора\n";
    exit;
}

echo bitrix_sessid() . "\n";
