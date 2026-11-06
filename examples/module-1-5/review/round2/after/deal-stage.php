<?php
/**
 * Задача 2 — решение: смена стадии сделки через служебную точку входа.
 * Копировать в /local/ajax/deal-stage.php на коробке.
 *
 * Контракт:
 *   POST deal_id, stage_id, request_id, sessid
 *   200 — стадия изменена (или уже была изменена этим request_id)
 *   400 — неверные параметры / неизвестная стадия
 *   401 — не авторизован
 *   403 — нет sessid
 *   404 — сделки нет или нет прав на изменение
 *   405 — не POST
 *   409 — конфликт ожидаемой и фактической стадии
 *   422 — Update отказал по бизнес-правилам
 *   500 — внутренняя ошибка (детали только в логе)
 */
declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;

define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', true);
define('BX_SKIP_SESSION_EXPAND', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

global $APPLICATION, $USER;

// Сбрасываем возможный вывод пролога/init.php до заголовков и тела.
$APPLICATION->RestartBuffer();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$responded = false;

/**
 * Единственная точка выхода.
 * FinalActions() запускает эпилог, но не гарантирует остановку скрипта:
 * в ядре Bitrix после него всегда стоит die(). Поэтому exit обязателен.
 */
$respond = static function (array $payload, string $status) use ($APPLICATION, &$responded): void {
    if ($responded) {
        exit;
    }
    $responded = true;

    CHTTP::SetStatus($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
    );

    $APPLICATION->FinalActions();
    exit;
};

$request = Application::getInstance()->getContext()->getRequest();

if (!$request->isPost()) {
    header('Allow: POST');
    $respond(['error' => 'method_not_allowed'], '405 Method Not Allowed');
}

if (!$USER->IsAuthorized()) {
    $respond(['error' => 'unauthorized'], '401 Unauthorized');
}

// CSRF после авторизации: иначе неавторизованный клиент различает ответы
// и может перебирать параметры без сессии.
if (!check_bitrix_sessid()) {
    $respond(['error' => 'invalid_sessid'], '403 Forbidden');
}

$rawDealId    = $request->getPost('deal_id');
$rawStageId   = $request->getPost('stage_id');
$rawRequestId = $request->getPost('request_id');
$rawExpected  = $request->getPost('expected_stage');

$dealId = is_string($rawDealId)
    ? filter_var($rawDealId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ])
    : false;

// Стадии в CRM — верхний регистр. Клиент может прислать 'new' или ' new ':
// нормализуем здесь, иначе сверка со справочником даст ложный unknown_stage.
$stageId   = is_string($rawStageId) ? strtoupper(trim($rawStageId)) : '';
$requestId = is_string($rawRequestId) ? trim($rawRequestId) : '';

// Необязательный параметр оптимистичной блокировки.
$expectedStage = is_string($rawExpected) ? strtoupper(trim($rawExpected)) : '';

if ($dealId === false || $stageId === '') {
    $respond(['error' => 'bad_request'], '400 Bad Request');
}

// request_id — обязательный: без него идемпотентности нет.
if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $requestId)) {
    $respond(['error' => 'bad_request_id'], '400 Bad Request');
}

// Сессия больше не нужна: снимаем блокировку файла сессии.
Application::getInstance()->getSession()->save();

try {
    if (!Loader::includeModule('crm')) {
        $respond(['error' => 'crm_not_installed'], '500 Internal Server Error');
    }

    /*
     * Идемпотентность.
     *
     * Демо-вариант: кеш на 10 минут, ключ = пользователь + request_id.
     * Ограничение: кеш не переживает очистку и не защищает от гонки двух
     * одновременных запросов. В проде — таблица с UNIQUE(request_id):
     * вставка строки и есть «захват» запроса, второй INSERT падает по
     * уникальному индексу, и мы возвращаем сохранённый ответ.
     *
     * Здесь initCache() и startDataCache() используются правильно:
     * при попадании в кеш выполнение уходит в $respond() и завершается,
     * поэтому startDataCache() вызывается только при промахе.
     */
    $cache = Cache::createInstance();
    $cacheKey = 'stage_' . $USER->GetID() . '_' . $requestId;

    if ($cache->initCache(600, $cacheKey, '/local/deal-stage')) {
        $cached = $cache->getVars();
        $respond($cached['payload'], $cached['status']);
    }

    $stages = \CCrmStatus::GetStatusList('DEAL_STAGE');

    if (!isset($stages[$stageId])) {
        $respond(['error' => 'unknown_stage'], '400 Bad Request');
    }

    // Только нужные поля вместо getById().
    $deal = \Bitrix\Crm\DealTable::getList([
        'select' => ['ID', 'STAGE_ID'],
        'filter' => ['=ID' => $dealId],
        'limit'  => 1,
    ])->fetch();

    // Чужая и несуществующая сделка отвечают одинаково — 404.
    if (!$deal || !\CCrmDeal::CheckUpdatePermission($dealId)) {
        $respond(['error' => 'not_found'], '404 Not Found');
    }

    // Оптимистичная блокировка: клиент присылает стадию, которую видел.
    // Если её уже сменили — 409 вместо «молча перезаписали чужое изменение».
    if ($expectedStage !== '' && (string)$deal['STAGE_ID'] !== $expectedStage) {
        $respond([
            'error'  => 'stage_conflict',
            'actual' => (string)$deal['STAGE_ID'],
        ], '409 Conflict');
    }

    // CCrmDeal::Update, а не DealTable::update: сохраняются события CRM,
    // история, роботы и бизнес-процессы. Прямой ORM-апдейт их пропускает.
    $updated = \CCrmDeal::Update($dealId, ['STAGE_ID' => $stageId]);

    if (!$updated) {
        // false — это не сбой инфраструктуры, а отказ бизнес-правил или прав.
        // Поэтому 422, а не 500: повтор запроса ничего не изменит.
        AddMessage2Log(
            sprintf('update failed user=%d deal=%d stage=%s', (int)$USER->GetID(), $dealId, $stageId),
            'deal-stage'
        );
        $respond(['error' => 'update_rejected'], '422 Unprocessable Entity');
    }

    $payload = [
        'deal' => [
            'id'    => $dealId,
            'stage' => $stageId,
        ],
        'request_id' => $requestId,
    ];

    $cache->startDataCache(600, $cacheKey, '/local/deal-stage');
    $cache->endDataCache(['payload' => $payload, 'status' => '200 OK']);

    $respond($payload, '200 OK');
} catch (\Throwable $e) {
    AddMessage2Log(
        sprintf(
            'user=%d deal=%d stage=%s %s @ %s:%d',
            (int)$USER->GetID(),
            (int)$dealId,
            $stageId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ),
        'deal-stage'
    );

    $respond(['error' => 'internal'], '500 Internal Server Error');
}
