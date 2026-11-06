<?php
/**
 * Эталон: служебная точка входа без шаблона сайта.
 * Копировать в /local/ajax/deal-status.php на коробке.
 *
 * Что демонстрирует:
 *  - преамбула констант (не пишем статистику, не тянем js-расширитель сессии);
 *  - prolog_before.php вместо header.php — нет шаблона, нет HTML;
 *  - порядок проверок: метод → авторизация → CSRF → валидация;
 *  - коды ответов через CHTTP::SetStatus;
 *  - FinalActions() вместо die();
 *  - session_write_close() после чтения пользователя;
 *  - ошибки — в лог, клиенту — только код.
 */
declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', true);
define('BX_SKIP_SESSION_EXPAND', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/**
 * Единственная точка выхода: заголовок, тело, эпилог.
 * FinalActions() отправляет ответ и завершает приложение —
 * в отличие от die() он не пропускает эпилог (почтовые события, закрытие БД).
 */
$respond = static function (array $payload, string $status): void {
    global $APPLICATION;

    CHTTP::SetStatus($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
    );

    $APPLICATION->FinalActions();
};

$request = Application::getInstance()->getContext()->getRequest();

if (!$request->isPost()) {
    header('Allow: POST');
    $respond(['error' => 'method_not_allowed'], '405 Method Not Allowed');
}

global $USER;

if (!$USER->IsAuthorized()) {
    $respond(['error' => 'unauthorized'], '401 Unauthorized');
}

// CSRF: без sessid запрос не выполняем, даже если пользователь авторизован.
if (!check_bitrix_sessid()) {
    $respond(['error' => 'invalid_sessid'], '403 Forbidden');
}

/*
 * Ограничение частоты: 60 запросов в минуту на пользователя.
 * Окно фиксированное (ключ содержит номер минуты), поэтому кеш не продлевается
 * бесконечно повторными запросами. Проверка неатомарна: при идеальном
 * параллелизме лимит можно слегка превысить — для анти-перебора этого хватает,
 * для квот по деньгам нужен счётчик в БД.
 */
$window = (int)floor(time() / 60);
$rateKey = 'rl_' . $USER->GetID() . '_' . $window;
$rateCache = \Bitrix\Main\Data\Cache::createInstance();
$hits = 0;

if ($rateCache->initCache(120, $rateKey, '/local/deal-status')) {
    $hits = (int)($rateCache->getVars()['hits'] ?? 0);
}

$hits++;

if ($hits > 60) {
    header('Retry-After: 60');
    $respond(['error' => 'too_many_requests'], '429 Too Many Requests');
}

$rateCache->startDataCache(120, $rateKey, '/local/deal-status');
$rateCache->endDataCache(['hits' => $hits]);

$rawDealId = $request->getPost('deal_id');

// deal_id[]=1 даёт массив: (int) на массиве — warning и 1 вместо ID.
$dealId = (is_string($rawDealId) && ctype_digit($rawDealId)) ? (int)$rawDealId : 0;

if ($dealId <= 0) {
    $respond(['error' => 'bad_request'], '400 Bad Request');
}

// Сессия больше не нужна. Пока она открыта, её файл заблокирован,
// и второй запрос этого же пользователя ждёт завершения первого.
session_write_close();

try {
    if (!Loader::includeModule('crm')) {
        $respond(['error' => 'crm_not_installed'], '500 Internal Server Error');
    }

    $deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();

    // Чужой и несуществующий объект отвечают одинаково — 404.
    // 403 здесь был бы оракулом: «сделка есть, но не твоя».
    if (!$deal) {
        $respond(['error' => 'not_found'], '404 Not Found');
    }

    // [проверить] сигнатуру под свою версию CRM: в разных сборках
    // CheckReadPermission принимает ещё и $userPermissions.
    if (!\CCrmDeal::CheckReadPermission($dealId)) {
        $respond(['error' => 'not_found'], '404 Not Found');
    }

    $respond([
        'deal' => [
            'id'    => (int)$deal['ID'],
            'title' => (string)$deal['TITLE'],
            'stage' => (string)$deal['STAGE_ID'],
        ],
    ], '200 OK');
} catch (\Throwable $e) {
    // В логе — контекст: без user и deal инцидент не воспроизвести.
    AddMessage2Log(
        sprintf(
            'user=%d deal=%d %s @ %s:%d',
            (int)$USER->GetID(),
            $dealId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ),
        'deal-status'
    );

    // Никаких $e->getMessage() клиенту: это утечка путей и SQL.
    $respond(['error' => 'internal'], '500 Internal Server Error');
}
