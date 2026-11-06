<?php
/**
 * Эталон: служебная точка входа без шаблона сайта.
 * Копировать в /local/ajax/deal-status.php на коробке.
 *
 * Контракт:
 *   POST deal_id, sessid
 *   200 — сделка доступна, в теле только ID/TITLE/STAGE_ID
 *   400 — нет deal_id, массив, не число, 0, отрицательное, переполнение
 *   401 — не авторизован
 *   403 — нет sessid (CSRF)
 *   404 — сделки нет или нет прав на чтение (ответы неразличимы)
 *   405 — не POST
 *   429 — превышен лимит запросов, есть Retry-After
 *   500 — внутренняя ошибка (детали только в логе)
 *
 * Инфраструктурные ошибки (fatal в прологе, падение PHP-FPM) — вне контракта:
 * их обрабатывает веб-сервер, и JSON-формат для них не гарантируется.
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

/*
 * Пролог мог успеть что-то вывести (BOM, warning при display_errors=On,
 * echo в init.php). Сбрасываем буфер ДО любых заголовков и тела, иначе
 * JSON получит текстовый префикс и клиент не сможет его разобрать.
 * Если вывод уже ушёл в сокет, RestartBuffer не спасёт — поэтому ничего
 * не печатаем до этой строки.
 */
$APPLICATION->RestartBuffer();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$responded = false;

/**
 * Единственная точка выхода.
 *
 * FinalActions() собирает буфер, отдаёт ответ и завершает приложение:
 * внутри Application::end() -> terminate() стоит exit(). Поэтому второй JSON
 * в тело уйти не может и отдельный exit не нужен. Побочные эффекты при этом
 * не теряются: OnAfterEpilog, session_write_close(), background jobs
 * (check_events), ManagedCache::finalize(), закрытие соединений.
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

    $APPLICATION->FinalActions();   // ответ + эпилог + exit внутри ядра
};

$request = Application::getInstance()->getContext()->getRequest();

if (!$request->isPost()) {
    header('Allow: POST');
    $respond(['error' => 'method_not_allowed'], '405 Method Not Allowed');
}

/*
 * Ограничение частоты — до проверки авторизации.
 *
 * Иначе 401 и 403 не лимитируются: перебор sessid и DoS на сессионные запросы
 * остаются бесплатными. Для авторизованного пользователя ключ — его ID,
 * для анонимного — IP (иначе все анонимы делят один счётчик).
 *
 * ManagedCache::read()/get()/set() выбран потому, что Data\Cache после
 * успешного initCache() не даёт перезаписать значение: startDataCache()
 * вернёт false, endDataCache() ничего не запишет, и счётчик застрянет на 1.
 *
 * Проверка неатомарна (read-modify-write): при идеальном параллелизме лимит
 * можно слегка превысить. Для анти-перебора этого достаточно; для денежных
 * квот нужен атомарный INCR в Redis или счётчик в БД.
 */
$limit = 60;
$identity = $USER->IsAuthorized()
    ? 'u' . (int)$USER->GetID()
    : 'ip' . md5((string)$request->getRemoteAddress());

$window = (int)floor(time() / 60);
$rateKey = 'rl_' . $identity . '_' . $window;

$managedCache = Application::getInstance()->getManagedCache();
$hits = 0;

if ($managedCache->read(120, $rateKey)) {
    $hits = (int)$managedCache->get($rateKey);
}

$hits++;

if ($hits > $limit) {
    header('Retry-After: ' . (60 - (time() % 60)));
    $respond(['error' => 'too_many_requests'], '429 Too Many Requests');
}

$managedCache->set($rateKey, $hits);

if (!$USER->IsAuthorized()) {
    $respond(['error' => 'unauthorized'], '401 Unauthorized');
}

// CSRF: без sessid запрос не выполняем, даже если пользователь авторизован.
if (!check_bitrix_sessid()) {
    $respond(['error' => 'invalid_sessid'], '403 Forbidden');
}

$rawDealId = $request->getPost('deal_id');

// filter_var отсекает массивы, мусор и переполнение (ctype_digit пропускает
// длинную строку цифр, а (int) насыщает её до PHP_INT_MAX).
$dealId = is_string($rawDealId)
    ? filter_var($rawDealId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ])
    : false;

if ($dealId === false) {
    $respond(['error' => 'bad_request'], '400 Bad Request');
}

/*
 * Закрываем сессию после чтения пользователя и проверки sessid.
 * Для файловых сессий это снимает блокировку файла: пока она открыта, второй
 * AJAX этого же пользователя ждёт завершения первого.
 *
 * Штатный D7-способ — Application::getInstance()->getSession()->save().
 * Для файловых сессий он эквивалентен session_write_close(). При сессиях
 * в БД/Redis, а также в режимах readonly/virtual поведение зависит от
 * настроек — проверяйте под свою конфигурацию.
 */
Application::getInstance()->getSession()->save();

try {
    if (!Loader::includeModule('crm')) {
        $respond(['error' => 'crm_not_installed'], '500 Internal Server Error');
    }

    // Только нужные поля: getById() тянет всю строку, включая текстовые поля.
    $deal = \Bitrix\Crm\DealTable::getList([
        'select' => ['ID', 'TITLE', 'STAGE_ID'],
        'filter' => ['=ID' => $dealId],
        'limit'  => 1,
    ])->fetch();

    // Чужой и несуществующий объект отвечают одинаково — 404.
    // 403 здесь был бы оракулом: «сделка есть, но не твоя».
    if (!$deal) {
        $respond(['error' => 'not_found'], '404 Not Found');
    }

    /*
     * Проверка прав. Сигнатура стабильна в CRM 20.x–24.x:
     *   CCrmDeal::CheckReadPermission($id, $userPermissions = null, $categoryId = null)
     * Второй и третий аргументы опциональны; при вызове без них берётся
     * текущий пользователь. Если в вашей сборке сигнатура иная — сверьтесь
     * с crm_deal.php, но логика «нет прав → 404» не меняется.
     */
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
            (int)$dealId,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ),
        'deal-status'
    );

    // Никаких $e->getMessage() клиенту: это утечка путей и SQL.
    $respond(['error' => 'internal'], '500 Internal Server Error');
}
