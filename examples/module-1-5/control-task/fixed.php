<?php
/**
 * Контрольное задание — эталонное решение.
 * Тот же сценарий, что в broken.php, но без пяти дефектов.
 *
 * Контракт:
 *   POST deal_id, stage_id, sessid
 *   200 — стадия изменена
 *   400 — неверные параметры / неизвестная стадия
 *   401 — не авторизован
 *   403 — нет sessid
 *   404 — сделки нет или нет прав
 *   405 — не POST
 *   422 — Update отказал по бизнес-правилам
 *   500 — внутренняя ошибка (детали только в логе)
 *
 * Инфраструктурные ошибки (fatal в прологе) — вне контракта.
 */
declare(strict_types=1);

use Bitrix\Main\Application;
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
 * FinalActions() собирает буфер, отдаёт ответ и завершает приложение
 * (Application::end() -> terminate() -> exit). Отдельный exit не нужен.
 */
$respond = static function (array $payload, string $status) use ($APPLICATION, &$responded): void {
    if ($responded) {
        return;
    }
    $responded = true;

    CHTTP::SetStatus($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
    );

    // Ответ, эпилог, закрытие сессии и БД, background jobs — всё внутри ядра.
    $APPLICATION->FinalActions();
};

$request = Application::getInstance()->getContext()->getRequest();

if (!$request->isPost()) {
    header('Allow: POST');
    $respond(['error' => 'method_not_allowed'], '405 Method Not Allowed');
}

if (!$USER->IsAuthorized()) {
    $respond(['error' => 'unauthorized'], '401 Unauthorized');
}

if (!check_bitrix_sessid()) {
    $respond(['error' => 'invalid_sessid'], '403 Forbidden');
}

$rawDealId = $request->getPost('deal_id');
$rawStage  = $request->getPost('stage_id');

// filter_var отсекает массивы, мусор и переполнение.
$dealId = is_string($rawDealId)
    ? filter_var($rawDealId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ])
    : false;

$stageId = is_string($rawStage) ? strtoupper(trim($rawStage)) : '';

if ($dealId === false || $stageId === '') {
    $respond(['error' => 'bad_request'], '400 Bad Request');
}

// Для файловых сессий это снимает блокировку файла.
// Штатный D7-способ: Application::getInstance()->getSession()->save().
Application::getInstance()->getSession()->save();

try {
    if (!Loader::includeModule('crm')) {
        $respond(['error' => 'crm_not_installed'], '500 Internal Server Error');
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

    if (!$deal || !\CCrmDeal::CheckUpdatePermission($dealId)) {
        $respond(['error' => 'not_found'], '404 Not Found');
    }

    if (!\CCrmDeal::Update($dealId, ['STAGE_ID' => $stageId])) {
        AddMessage2Log(
            sprintf('update failed user=%d deal=%d stage=%s', (int)$USER->GetID(), $dealId, $stageId),
            'control-task'
        );
        $respond(['error' => 'update_rejected'], '422 Unprocessable Entity');
    }

    $respond(['ok' => true, 'id' => $dealId, 'stage' => $stageId], '200 OK');
} catch (\Throwable $e) {
    AddMessage2Log(
        sprintf(
            'user=%d deal=%d %s @ %s:%d',
            (int)$USER->GetID(),
            (int)$dealId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ),
        'control-task'
    );

    $respond(['error' => 'internal'], '500 Internal Server Error');
}
