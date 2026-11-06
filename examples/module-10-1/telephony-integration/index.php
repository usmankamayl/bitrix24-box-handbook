<?php
/**
 * Webhook для приёма события "входящий звонок" от сторонней АТС
 *
 * АТС вызывает этот URL в момент входящего звонка:
 *   https://your-domain.com/telephony/index.php?phone=+79991234567&ext=100&ats_call_id=ATS12345
 *
 * Что делает скрипт:
 *   1. Вызывает telephony.externalcall.register — регистрирует звонок в Bitrix24
 *   2. Сохраняет CALL_ID в файл-базу (чтобы finish.php потом нашёл звонок)
 *   3. Вызывает telephony.externalcall.show — всплывает карточка у менеджера
 */

// =============================================================================
// НАСТРОЙКИ — замени на свои значения
// =============================================================================

// Входящий вебхук Bitrix24 (раздел: Разработчикам → Другое → Входящий вебхук)
// Нужны права: telephony, crm, user
define('B24_WEBHOOK', 'https://your-domain.bitrix24.ru/rest/1/YOUR_WEBHOOK_KEY/');

// Файл для хранения активных звонков (простая "база" для примера)
// В реальном проекте — используй MySQL/Redis
define('CALLS_DB', __DIR__ . '/calls.json');


// =============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =============================================================================

/**
 * Вызов метода Bitrix24 REST API через входящий вебхук
 *
 * @param string $method  Например: 'telephony.externalcall.register'
 * @param array  $params  Параметры метода
 * @return array          Ответ API в виде массива
 */
function callB24(string $method, array $params = []): array
{
    $url = B24_WEBHOOK . $method . '.json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'CURL_ERROR', 'error_description' => $error];
    }

    return json_decode($response, true) ?? [];
}

/**
 * Сохранить CALL_ID звонка в файл-базу
 * Ключ — ID звонка на стороне АТС (ats_call_id)
 */
function saveCallId(string $atsCallId, string $b24CallId, int $userId): void
{
    $calls = file_exists(CALLS_DB)
        ? json_decode(file_get_contents(CALLS_DB), true)
        : [];

    $calls[$atsCallId] = [
        'b24_call_id' => $b24CallId,
        'user_id'     => $userId,
        'started_at'  => time(),
    ];

    file_put_contents(CALLS_DB, json_encode($calls, JSON_PRETTY_PRINT));
}


// =============================================================================
// ОБРАБОТКА ВХОДЯЩЕГО ЗАПРОСА ОТ АТС
// =============================================================================

// Параметры, которые передаёт АТС
// (названия параметров зависят от конкретной АТС — адаптируй под свою)
$phone     = $_GET['phone']       ?? $_GET['caller']    ?? null;  // номер клиента
$ext       = $_GET['ext']         ?? $_GET['inner']      ?? null;  // внутренний номер менеджера
$atsCallId = $_GET['ats_call_id'] ?? $_GET['call_id']    ?? null;  // ID звонка в АТС
$type      = (int)($_GET['type']  ?? 2);                          // 1-исх, 2-вх

// Валидация обязательных параметров
if (!$phone || !$ext || !$atsCallId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required params: phone, ext, ats_call_id']);
    exit;
}

// Нормализуем номер (убираем всё кроме цифр и +)
$phone = preg_replace('/[^\d+]/', '', $phone);

// Логируем входящий запрос (удобно для отладки)
file_put_contents(
    __DIR__ . '/calls.log',
    date('Y-m-d H:i:s') . " START phone={$phone} ext={$ext} ats_id={$atsCallId}\n",
    FILE_APPEND
);


// =============================================================================
// ШАГ 1: Найти ID пользователя Bitrix24 по внутреннему номеру
// =============================================================================

// USER_PHONE_INNER — внутренний номер, заданный в настройках телефонии пользователя
// Можно передать USER_ID напрямую, если он известен — тогда этот шаг не нужен
$userSearch = callB24('user.get', [
    'filter' => ['UF_PHONE_INNER' => $ext],
    'select' => ['ID', 'NAME', 'LAST_NAME'],
]);

$userId = $userSearch['result'][0]['ID'] ?? null;

if (!$userId) {
    // Если пользователь не найден — регистрируем звонок без привязки
    // (Bitrix24 всё равно создаст лид, но карточка никому не покажется)
    file_put_contents(
        __DIR__ . '/calls.log',
        date('Y-m-d H:i:s') . " WARN user not found for ext={$ext}\n",
        FILE_APPEND
    );
}


// =============================================================================
// ШАГ 2: Зарегистрировать звонок в Bitrix24
// =============================================================================

$registerResult = callB24('telephony.externalcall.register', [
    // Внутренний номер сотрудника (отображается в карточке звонка)
    'USER_PHONE_INNER'  => $ext,

    // ID пользователя Bitrix24 (если нашли выше)
    'USER_ID'           => $userId ?? 1,

    // Номер клиента (кто звонит)
    'PHONE_NUMBER'      => $phone,

    // Дата и время начала звонка
    'CALL_START_DATE'   => date('Y-m-d H:i:s'),

    // Тип: 1 = исходящий, 2 = входящий
    'TYPE'              => $type,

    // Создать лид автоматически, если номер не найден в CRM
    'CRM_CREATE'        => 1,

    // Источник для созданного лида (код источника из справочника CRM)
    'CRM_SOURCE'        => 'CALL',

    // Список пользователей, которым показать карточку (можно несколько через запятую)
    // 'LINE_NUMBER'    => '',  // внешний номер линии (если несколько линий)
]);


// =============================================================================
// ШАГ 3: Обработать ответ и сохранить CALL_ID
// =============================================================================

if (isset($registerResult['result']['CALL_ID'])) {

    $b24CallId  = $registerResult['result']['CALL_ID'];
    $crmLeadId  = $registerResult['result']['CRM_CREATED_LEAD'] ?? null;
    $entityType = $registerResult['result']['CRM_ENTITY_TYPE']  ?? null;
    $entityId   = $registerResult['result']['CRM_ENTITY_ID']    ?? null;

    // Сохраняем соответствие: ID звонка в АТС → CALL_ID в Bitrix24
    // Это нужно для finish.php — при завершении звонка найдём CALL_ID
    saveCallId($atsCallId, $b24CallId, $userId ?? 1);

    file_put_contents(
        __DIR__ . '/calls.log',
        date('Y-m-d H:i:s') . " REGISTERED b24_call_id={$b24CallId} entity={$entityType}:{$entityId}\n",
        FILE_APPEND
    );


    // =========================================================================
    // ШАГ 4: Показать карточку звонка менеджеру
    // =========================================================================

    // Всплывающее окно появится у пользователя в интерфейсе Bitrix24
    if ($userId) {
        callB24('telephony.externalcall.show', [
            'CALL_ID' => $b24CallId,
            'USER_ID' => $userId,
        ]);
    }

    // Ответ АТС — подтверждаем приём
    http_response_code(200);
    echo json_encode([
        'status'      => 'ok',
        'b24_call_id' => $b24CallId,
        'crm_entity'  => $entityType . ':' . $entityId,
    ]);

} else {
    // Ошибка регистрации — логируем и отвечаем АТС
    $error = $registerResult['error_description'] ?? 'Unknown error';

    file_put_contents(
        __DIR__ . '/calls.log',
        date('Y-m-d H:i:s') . " ERROR register failed: {$error}\n",
        FILE_APPEND
    );

    http_response_code(500);
    echo json_encode(['error' => 'register failed', 'description' => $error]);
}
