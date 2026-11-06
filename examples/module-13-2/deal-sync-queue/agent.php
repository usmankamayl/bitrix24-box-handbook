<?php

require_once __DIR__ . '/SyncQueue.php';
require_once __DIR__ . '/SyncService.php';

// Регистрируется как агент Bitrix24 (CAgent::AddAgent), интервал — например, 60 секунд.
// Обрабатывает очередь, накопленную обработчиками событий CRM (event_handler.php).
function processDealSyncQueueAgent(): string
{
    $queue = new SyncQueue();
    $service = new SyncService(new EntityMapper(), new AmoCrmClient());

    foreach ($queue->getPending(limit: 20) as $task) {
        try {
            $service->syncDeal((int) $task['entity_id']);
            $queue->markDone((int) $task['id']);
        } catch (Throwable $e) {
            $queue->markFailed((int) $task['id'], (int) $task['attempts'], $e->getMessage());
        }
    }

    // Агент должен вернуть строку с вызовом самого себя — так Bitrix24
    // перепланирует следующий запуск с тем же интервалом
    return '\\processDealSyncQueueAgent();';
}
