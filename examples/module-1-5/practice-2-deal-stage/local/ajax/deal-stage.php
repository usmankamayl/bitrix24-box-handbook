<?php
/**
 * Задача 2 — эталонное решение: смена стадии сделки через служебную точку входа.
 *
 * Файлы:
 *   local/ajax/deal-stage.php                              → /local/ajax/deal-stage.php
 *   local/php_interface/init.php                           → /local/php_interface/init.php
 *   local/php_interface/classes/DealStage/Journal.php      → .../classes/DealStage/Journal.php
 *   local/php_interface/classes/DealStage/JournalTable.php → .../classes/DealStage/JournalTable.php
 *   install.sql                                            → выполнить один раз в БД
 *
 * Контракт:
 *   POST deal_id, stage_id, request_id, sessid, [expected_stage]
 *   200 — стадия изменена (или уже была изменена этим request_id)
 *   400 — неверные параметры / неизвестная стадия
 *   401 — не авторизован
 *   403 — нет sessid
 *   404 — сделки нет или нет прав на изменение
 *   405 — не POST
 *   409 — конфликт стадии (expected_stage) или запрос с этим request_id ещё в работе
 *   422 — Update отказал по бизнес-правилам
 *   500 — внутренняя ошибка (детали только в логе)
 *
 * Инфраструктурные ошибки (fatal в прологе, падение PHP-FPM) — вне контракта:
 * их обрабатывает веб-сервер, и JSON-формат для них не гарантируется.
 */
declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use MyCompany\DealStage\Journal;

define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', true);
define('BX_SKIP_SESSION_EXPAND', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

global $APPLICATION, $USER;

// Пролог мог успеть что-то вывести (BOM, warning при display_errors=On,
// echo в init.php). Сбрасываем буфер ДО заголовков, иначе JSON получит
// текстовый префикс и клиент не сможет его разобрать.
$APPLICATION->RestartBuffer();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/**
 * Кодирование в одном месте: при повторе клиент должен получить тот же
 * ответ байт в байт, поэтому в журнал сохраняется именно эта строка.
 */
$json = static function (array $payload): string {
    return json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
    );
};

/**
 * Единственная точка выхода.
 *
 * FinalActions() собирает буфер, отдаёт ответ и завершает приложение:
 * внутри Application::end() -> terminate() стоит exit(). Поэтому второй JSON
 * в тело уйти не может, отдельный exit не нужен, а OnAfterEpilog,
 * session_write_close(), background jobs и закрытие соединений выполняются.
 */
$respond = static function (string $body, string $status) use ($APPLICATION): void {
    CHTTP::SetStatus($status);
    echo $body;
    $APPLICATION->FinalActions();
};

$request = Application::getInstance()->getContext()->getRequest();

// 1. Метод — первым: GET на write-эндпоинт это ошибка клиента или CSRF-проба.
if (!$request->isPost()) {
    header('Allow: POST');
    $respond($json(['error' => 'method_not_allowed']), '405 Method Not Allowed');
}

// 2. Кто ты.
if (!$USER->IsAuthorized()) {
    $respond($json(['error' => 'unauthorized']), '401 Unauthorized');
}

// 3. Докажи, что запрос твой. CSRF проверяем после авторизации: иначе анонимный
//    клиент различает «нет sessid» и «нет прав» и перебирает параметры даром.
if (!check_bitrix_sessid()) {
    $respond($json(['error' => 'invalid_sessid']), '403 Forbidden');
}

$rawDealId    = $request->getPost('deal_id');
$rawStageId   = $request->getPost('stage_id');
$rawRequestId = $request->getPost('request_id');
$rawExpected  = $request->getPost('expected_stage');

// filter_var отсекает массивы, мусор и переполнение: (int) превратил бы
// '12abc' в 12, а '99999999999999999999' — в PHP_INT_MAX.
$dealId = is_string($rawDealId)
    ? filter_var($rawDealId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ])
    : false;

// Стадии в CRM — верхний регистр: клиент может прислать 'won' или ' WON '.
$stageId       = is_string($rawStageId) ? strtoupper(trim($rawStageId)) : '';
$requestId     = is_string($rawRequestId) ? trim($rawRequestId) : '';
$expectedStage = is_string($rawExpected) ? strtoupper(trim($rawExpected)) : '';

if ($dealId === false || $stageId === '') {
    $respond($json(['error' => 'bad_request']), '400 Bad Request');
}

if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $requestId)) {
    $respond($json(['error' => 'bad_request_id']), '400 Bad Request');
}

// 4. Сессия больше не нужна: снимаем блокировку файла сессии. Пока она открыта,
//    второй AJAX этого же пользователя ждёт завершения первого.
Application::getInstance()->getSession()->save();

$userId  = (int)$USER->GetID();
$claimId = null;

try {
    if (!Loader::includeModule('crm')) {
        $respond($json(['error' => 'crm_not_installed']), '500 Internal Server Error');
    }

    $stages = CCrmStatus::GetStatusList('DEAL_STAGE');

    if (!isset($stages[$stageId])) {
        $respond($json(['error' => 'unknown_stage']), '400 Bad Request');
    }

    /*
     * 5. Захват ключа идемпотентности — ДО действия, одним INSERT в таблицу
     *    с UNIQUE(USER_ID, REQUEST_ID). Никакого «а есть ли уже такая запись»:
     *    между проверкой и записью второй запрос успевает прочитать «нет».
     */
    $claim = Journal::claim($userId, $requestId, $dealId, $stageId);

    if ($claim['state'] === 'replay') {
        // Действие уже выполнено этим же ключом — отдаём сохранённый ответ.
        $respond($claim['body'], '200 OK');
    }

    if ($claim['state'] === 'in_progress') {
        header('Retry-After: ' . max(1, (int)($claim['retry_after'] ?? 5)));
        $respond($json(['error' => 'request_in_progress']), '409 Conflict');
    }

    $claimId = $claim['id'];

    // Только нужные поля: getById() тянет всю строку, включая текстовые.
    $deal = Bitrix\Crm\DealTable::getList([
        'select' => ['ID', 'STAGE_ID'],
        'filter' => ['=ID' => $dealId],
        'limit'  => 1,
    ])->fetch();

    // Чужая и несуществующая сделка отвечают одинаково — 404.
    if (!$deal || !CCrmDeal::CheckUpdatePermission($dealId)) {
        Journal::release($claimId);
        $respond($json(['error' => 'not_found']), '404 Not Found');
    }

    // 6. Оптимистичная блокировка: клиент присылает стадию, которую видел.
    //    Если её уже сменили — 409 вместо «молча перезаписали чужое изменение».
    if ($expectedStage !== '' && (string)$deal['STAGE_ID'] !== $expectedStage) {
        Journal::release($claimId);
        $respond(
            $json(['error' => 'stage_conflict', 'actual' => (string)$deal['STAGE_ID']]),
            '409 Conflict'
        );
    }

    // CCrmDeal::Update, а не DealTable::update: сохраняются события CRM,
    // история, роботы и бизнес-процессы. Прямой ORM-апдейт их пропускает.
    if (!CCrmDeal::Update($dealId, ['STAGE_ID' => $stageId])) {
        // false — отказ бизнес-правил или прав, а не сбой инфраструктуры:
        // повтор запроса ничего не изменит, поэтому 422, а не 500.
        Journal::release($claimId);
        AddMessage2Log(
            sprintf('update failed user=%d deal=%d stage=%s', $userId, $dealId, $stageId),
            'deal-stage'
        );
        $respond($json(['error' => 'update_rejected']), '422 Unprocessable Entity');
    }

    $body = $json([
        'deal' => [
            'id'    => $dealId,
            'stage' => $stageId,
        ],
        'request_id' => $requestId,
    ]);

    // 7. Фиксируем ответ до отправки: повтор с этим request_id получит ровно
    //    эти байты, даже если стадию уже сменили руками.
    Journal::complete($claimId, $body, 200);

    $respond($body, '200 OK');
} catch (Throwable $e) {
    // Ключ освобождаем: 500 — сбой, клиент вправе повторить тот же запрос.
    if ($claimId !== null) {
        try {
            Journal::release($claimId);
        } catch (Throwable) {
            // Падение БД не должно маскировать исходную ошибку.
        }
    }

    AddMessage2Log(
        sprintf(
            'user=%d deal=%d stage=%s request=%s %s @ %s:%d',
            $userId,
            (int)$dealId,
            $stageId,
            $requestId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ),
        'deal-stage'
    );

    // Никаких деталей исключения клиенту: это утечка путей и SQL.
    $respond($json(['error' => 'internal']), '500 Internal Server Error');
}
