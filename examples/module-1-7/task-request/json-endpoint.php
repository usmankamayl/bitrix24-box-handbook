<?php

/**
 * JSON-эндпоинт регистрации.
 *
 * Путь в проекте: /local/api/registration.php
 *
 * Что здесь показано:
 *   - точка входа без шаблона сайта: подключается пролог, а не header.php;
 *   - тело JSON читается из php://input, потому что $_POST его не разбирает;
 *   - CSRF проверяется вручную: check_bitrix_sessid() читает sessid из $_POST,
 *     а в JSON-теле его нет — сравниваем с bitrix_sessid() через hash_equals;
 *   - ответ отдаётся с явными статусом и Content-Type, один раз.
 *
 * Границы применимости: это не REST-фреймворк. Для набора эндпоинтов в проекте
 * обычно появляется свой роутер/контроллер; здесь — минимальный корректный
 * контракт одного эндпоинта.
 */

define('NO_KEEP_STATISTIC', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Web\Json;
use MyCompany\Forms\RegistrationValidator;

/**
 * Отправить JSON и завершить выполнение.
 *
 * Заголовки ставятся до первого вывода — здесь вывода ещё не было, поэтому
 * http_response_code()/header() работают. Если бы страница уже что-то
 * напечатала, заголовки были бы отправлены и статус не изменился бы.
 */
function respond(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo Json::encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'error' => 'Метод не поддерживается.']);
}

$rawBody = file_get_contents('php://input');
$payload = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;

if (!is_array($payload)) {
    respond(400, ['success' => false, 'error' => 'Тело запроса должно быть JSON-объектом.']);
}

// sessid можно передать в теле или заголовком X-Bitrix-Sessid.
$sessid = $payload['sessid'] ?? ($_SERVER['HTTP_X_BITRIX_SESSID'] ?? '');

// hash_equals, а не ===: сравнение не даёт измерить время посимвольного совпадения.
if (!is_string($sessid) || $sessid === '' || !hash_equals((string)bitrix_sessid(), $sessid)) {
    respond(403, ['success' => false, 'error' => 'Сессия устарела.']);
}

$input = [
    'name' => $payload['name'] ?? null,
    'email' => $payload['email'] ?? null,
    'age' => $payload['age'] ?? null,
    'phone' => $payload['phone'] ?? null,
];

$result = (new RegistrationValidator($input))->validate();

if (!$result->isSuccess()) {
    $errors = [];
    foreach ($result->getErrors() as $error) {
        // Код ошибки — для клиента, сообщение — для человека.
        $errors[] = ['code' => $error->getCode(), 'message' => $error->getMessage()];
    }

    respond(422, ['success' => false, 'errors' => $errors]);
}

// Здесь был бы вызов сервиса регистрации: сервис принимает нормализованные
// данные, сам пишет в БД и сам сбрасывает связанный кеш.
respond(200, ['success' => true, 'data' => $result->getData()]);