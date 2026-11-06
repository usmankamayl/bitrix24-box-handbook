<?php
/**
 * Защищённый обработчик входящих событий от OAuth-приложения Bitrix24.
 *
 * Проверяет ДВА признака, а не один:
 * 1. application_token — что запрос сформирован от имени вашего приложения
 *    (сохранён при ONAPPINSTALL, сравнивается через hash_equals).
 * 2. member_id — что это конкретный установленный портал, а не чужой запрос
 *    с похожим токеном (member_id не секретен, но связка с app_token
 *    подтверждает владельца запроса).
 *
 * IP-адреса Bitrix24 для этой цели использовать НЕ нужно — они
 * недокументированы и меняются; application_token — правильный механизм.
 */

require_once __DIR__ . '/config.php';

$event = $_POST['event'] ?? null;
$auth = $_POST['auth'] ?? [];
$memberId = $auth['member_id'] ?? null;
$applicationToken = $auth['application_token'] ?? null;

if (!$event || !$memberId || !$applicationToken) {
    http_response_code(400);
    exit('missing event/auth');
}

// 1. Находим установку приложения по member_id
$stmt = getPdo()->prepare(
    'SELECT application_token FROM app_installations
     WHERE member_id = ? AND status = "active"'
);
$stmt->execute([$memberId]);
$installation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$installation) {
    // Такого портала в активных установках нет — либо уже удалён,
    // либо попытка подделки чужого обработчика
    http_response_code(403);
    exit('unknown member_id');
}

// 2. Сверяем application_token — hash_equals против timing-атаки,
// а не обычное == (уязвимо к угадыванию по времени ответа)
if (!hash_equals($installation['application_token'], $applicationToken)) {
    http_response_code(403);
    error_log("[secure-webhook] app_token mismatch for member_id={$memberId}");
    exit('token mismatch');
}

// С этого момента запрос аутентичен: сформирован от имени нашего приложения
// для конкретного установленного портала, а не сгенерирован третьей стороной.
switch ($event) {
    case 'ONAPPUNINSTALL':
        // Реальная обработка удаления (soft-delete + отложенная очистка,
        // см. module-13-1/event-handler.php) — здесь только подтверждение приёма
        break;

    default:
        // Остальные события — свои обработчики
        break;
}

http_response_code(200);
echo '{"ok":true}';
