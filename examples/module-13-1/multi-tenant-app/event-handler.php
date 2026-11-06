<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/InstallationRepository.php';

$event = $_POST['event'] ?? null;
$authPayload = $_POST['auth'] ?? [];
$memberId = $authPayload['member_id'] ?? null;

if (!$event || !$memberId) {
    http_response_code(400);
    die('Missing event or member_id');
}

$repo = InstallationRepository::create();
$installation = $repo->findActiveByMemberId($memberId);

if (!$installation) {
    // Портала с таким member_id нет в активных установках —
    // событие либо для уже удалённого приложения, либо попытка подделки
    http_response_code(404);
    die('Installation not found');
}

try {
    Security::assertApplicationToken($installation['application_token'], $authPayload);
} catch (RuntimeException $e) {
    http_response_code(403);
    error_log('[event-handler.php] ' . $e->getMessage());
    die($e->getMessage());
}

switch ($event) {
    case 'ONAPPUNINSTALL':
        // Soft-delete: помечаем, не удаляем сразу — вдруг переустановят через 5 минут.
        // Физическая очистка персональных данных — отдельным cron-воркером
        // (см. InstallationRepository::findDueForPurge / purge), не здесь:
        // обработчик события должен ответить Bitrix24 быстро (обычно < 5 сек).
        $repo->markUninstalled($memberId, purgeAfterDays: 14);
        break;

    default:
        // Остальные события (ONCRMDEALADD и т.д.) обрабатываются своими handler'ами —
        // этот файл отвечает только за жизненный цикл установки приложения
        break;
}

http_response_code(200);
