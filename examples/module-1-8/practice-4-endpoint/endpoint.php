<?php

/**
 * Эндпоинт закрытия заявки: транспорт вокруг доменного сервиса.
 *
 * Путь в проекте: /local/api/request-close.php
 *
 * Разделение слоёв — весь смысл файла:
 *   HTTP-проверки (метод, авторизация, CSRF, разбор тела) — здесь;
 *   правила и коды — в OrderStatusService, который про HTTP не знает;
 *   коды → статусы — в ResultResponder.
 *
 * Порядок проверок выбран не случайно: метод → авторизация → CSRF → тело.
 * Разбирать тело от неавторизованного запроса бессмысленно, а сообщать
 * «неверный JSON» раньше «нет сессии» — раскрывать обработку до проверки прав.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$autoload = $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// Резерв для запуска без composer: пример должен работать в том виде, как лежит.
foreach (['OrderTable', 'OrderStatusService'] as $class) {
    $fqcn = 'MyCompany\\Requests\\' . $class;
    if (!class_exists($fqcn)) {
        require_once __DIR__ . '/' . $class . '.php';
    }
}
if (!class_exists(\MyCompany\Api\ResultResponder::class)) {
    require_once __DIR__ . '/ResultResponder.php';
}

use Bitrix\Main\Context;
use Bitrix\Main\Web\Json;
use MyCompany\Api\ResultResponder;
use MyCompany\Requests\OrderStatusService;

/** @var CUser $USER */
global $USER;

$request = Context::getCurrent()->getRequest();

// 1. Метод. GET на POST-only эндпоинт — 405 с Allow, а не 401.
if ($request->getRequestMethod() !== 'POST') {
    header('Allow: POST');
    ResultResponder::sendError(405, 'METHOD_NOT_ALLOWED', 'Используйте POST.');
}

// 2. Авторизация. Без неё дальше идти некуда: сервису нужен user id.
if (!$USER->IsAuthorized()) {
    ResultResponder::sendError(401, 'NOT_AUTHORIZED', 'Требуется авторизация.');
}

// 3. CSRF. check_bitrix_sessid() читает sessid из $_POST, а здесь тело JSON —
// поэтому сравниваем сами, через hash_equals (сравнение за постоянное время).
$sessid = (string)($request->getHeader('X-Bitrix-Sessid') ?? '');
if ($sessid === '' || !hash_equals((string)bitrix_sessid(), $sessid)) {
    ResultResponder::sendError(403, 'CSRF_FAILED', 'Сессия устарела. Обновите страницу.');
}

// 4. Тело. JSON не попадает в $_POST — читаем php://input.
$rawBody = (string)file_get_contents('php://input');
if ($rawBody === '') {
    ResultResponder::sendError(400, 'EMPTY_BODY', 'Тело запроса пустое.');
}

try {
    $payload = Json::decode($rawBody);
} catch (\Bitrix\Main\ArgumentException $exception) {
    // Json::decode бросает на битом JSON — одно исключение вместо
    // json_decode + json_last_error().
    ResultResponder::sendError(400, 'INVALID_JSON', 'Тело должно быть корректным JSON.');
}

if (!is_array($payload)) {
    ResultResponder::sendError(400, 'INVALID_JSON', 'Тело должно быть JSON-объектом.');
}

// 5. Типы. Приведение здесь запрещено: (int)'12abc' === 12, (int)[] === 1.
$rawId = $payload['id'] ?? null;
$id = null;

if (is_int($rawId)) {
    $id = $rawId;
} elseif (is_string($rawId) && $rawId !== '' && ctype_digit($rawId)) {
    $id = (int)$rawId;
}

if ($id === null) {
    ResultResponder::sendError(400, 'REQUEST_ID_INVALID', 'Поле id должно быть целым числом.');
}

$comment = $payload['comment'] ?? '';
if (!is_string($comment)) {
    ResultResponder::sendError(400, 'COMMENT_INVALID', 'Поле comment должно быть строкой.');
}

// 6. Домен. Непредвиденное — единственный catch на всю точку входа:
// \Throwable, а не \Exception (TypeError в PHP 8 не Exception).
try {
    $result = (new OrderStatusService())->closeRequest($id, (int)$USER->GetID(), $comment);
} catch (\Throwable $exception) {
    ResultResponder::sendUnexpected($exception);
}

// 7. Ответ. Один JSON, один статус, exit внутри respond.
ResultResponder::sendResult($result);
