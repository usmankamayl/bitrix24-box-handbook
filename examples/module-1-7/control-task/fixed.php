<?php

/**
 * Эталон к control-task/broken.php — тот же эндпоинт, переписанный по
 * контракту подраздела 1.7.
 *
 * Путь в проекте: /local/ajax/request-status.php
 *
 * Что изменилось по сравнению с broken.php:
 *   1. параметр читается из POST через Request, тип проверяется, число — через
 *      filter_var с диапазоном (а не (int) от $_REQUEST);
 *   2. входные данные не экранируются: в БД пишется нормализованное значение,
 *      экранирование — в контексте вывода;
 *   3. CSRF проверяется штатным check_bitrix_sessid(); isAjaxRequest() остаётся
 *      диагностическим признаком, но не защитой;
 *   4. ответ отдаётся один раз, после заголовков; никаких echo до header()
 *      и никаких die() в середине логики;
 *   5. заявка ищется с фильтром CREATED_BY — чужая неотличима от несуществующей.
 *
 * Границы: остался легаси-API инфоблоков, потому что задача — про слой входа,
 * а не про переписывание на ORM (модуль 2).
 */

define('NO_KEEP_STATISTIC', true);

// NOT_CHECK_PERMISSIONS здесь не ставится: публичный эндпоинт не должен
// отключать проверки прав. Он не «оптимизация», а способ отдать чужие данные.

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

const REQUEST_IBLOCK_ID = 12;
const REQUEST_COMMENT_MAX = 500;
const REQUEST_STATUSES = ['new', 'in_work', 'done'];

/**
 * Единственная точка вывода. Заголовки ставятся до первого байта тела,
 * выполнение завершается здесь же.
 */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo Json::encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$request = Context::getCurrent()->getRequest();

if (!$request->isPost()) {
    header('Allow: POST');
    respond(405, ['success' => false, 'error' => 'Метод не поддерживается.']);
}

// Порядок проверок: метод → кто → CSRF → данные → объект. Пользователя читаем
// из $USER, а не из Request: Request про HTTP, авторизация — про сессию.
$userId = (int)(($GLOBALS['USER'] ?? null)?->GetID() ?? 0);

if ($userId <= 0) {
    respond(401, ['success' => false, 'error' => 'Требуется авторизация.']);
}

if (!check_bitrix_sessid()) {
    respond(403, ['success' => false, 'error' => 'Сессия устарела, обновите страницу.']);
}

$rawId = $request->getPost('id');

if ($rawId === null || !is_string($rawId)) {
    // null — параметра нет; массив — ?id[]=1; и то и другое не число.
    respond(400, ['success' => false, 'error' => 'Не указан идентификатор заявки.']);
}

$id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($id === false) {
    respond(400, ['success' => false, 'error' => 'Идентификатор заявки должен быть целым числом.']);
}

$status = $request->getPost('status');

if (!is_string($status) || !in_array($status, REQUEST_STATUSES, true)) {
    respond(422, ['success' => false, 'error' => 'Недопустимый статус заявки.']);
}

$rawComment = $request->getPost('comment');
$comment = is_string($rawComment) ? trim($rawComment) : '';

if (mb_strlen($comment) > REQUEST_COMMENT_MAX) {
    respond(422, [
        'success' => false,
        'error' => 'Комментарий длиннее ' . REQUEST_COMMENT_MAX . ' символов.',
    ]);
}

if (!Loader::includeModule('iblock')) {
    respond(500, ['success' => false, 'error' => 'Внутренняя ошибка.']);
}

$element = CIBlockElement::GetList(
    [],
    [
        'IBLOCK_ID' => REQUEST_IBLOCK_ID,
        'ID' => $id,
        // Чужая заявка не находится: 404 вместо 403, чтобы не подтверждать
        // существование объекта.
        'CREATED_BY' => $userId,
    ],
    false,
    ['nTopCount' => 1],
    ['ID', 'NAME', 'CREATED_BY']
)->Fetch();

if (!$element) {
    respond(404, ['success' => false, 'error' => 'Заявка не найдена.']);
}

$ok = (new CIBlockElement())->Update($id, [
    'PROPERTY_STATUS' => $status,
    // Нормализованный текст, без HTML-сущностей. Экранирует шаблон.
    'PREVIEW_TEXT' => $comment,
]);

if (!$ok) {
    // Детали — в лог, клиенту — только факт.
    AddMessage2Log(
        'request-status: не удалось обновить заявку #' . $id . ' (user ' . $userId . ')',
        'local.ajax'
    );

    respond(500, ['success' => false, 'error' => 'Не удалось сохранить изменения.']);
}

respond(200, [
    'success' => true,
    'data' => ['id' => $id, 'status' => $status],
]);
