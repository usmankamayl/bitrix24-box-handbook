<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InstallationRepository.php';

// Запускать раз в сутки через Bitrix24 cron-приложения или системный crontab.
// Окончательно удаляет персональные данные порталов, у которых прошёл
// срок хранения после ONAPPUNINSTALL (см. event-handler.php).
$repo = InstallationRepository::create();

foreach ($repo->findDueForPurge() as $memberId) {
    try {
        $repo->purge($memberId);
        error_log("[purge-cron.php] purged {$memberId}");
    } catch (Throwable $e) {
        error_log("[purge-cron.php] failed to purge {$memberId}: " . $e->getMessage());
    }
}
