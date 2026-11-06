<?php
/**
 * Очень простая очередь на файлах.
 * В продакшене — БД таблица, Redis или RabbitMQ.
 */

const QUEUE_DIR  = __DIR__ . '/queue';
const MAPPING_DB = __DIR__ . '/mapping.json';

function queue_push(array $job): string
{
    if (!is_dir(QUEUE_DIR)) {
        mkdir(QUEUE_DIR, 0777, true);
    }
    $id = uniqid('job_', true);
    file_put_contents(QUEUE_DIR . "/{$id}.json", json_encode($job, JSON_UNESCAPED_UNICODE));
    return $id;
}

function queue_fetch_all(): array
{
    $jobs = [];
    foreach (glob(QUEUE_DIR . '/*.json') as $file) {
        $jobs[] = ['file' => $file, 'data' => json_decode(file_get_contents($file), true)];
    }
    return $jobs;
}

function queue_remove(string $file): void
{
    @unlink($file);
}

function mapping_get(int $dealId): ?string
{
    if (!file_exists(MAPPING_DB)) return null;
    $m = json_decode(file_get_contents(MAPPING_DB), true);
    return $m[$dealId]['erp_id'] ?? null;
}

function mapping_set(int $dealId, string $erpId, string $syncSource): void
{
    $m = file_exists(MAPPING_DB) ? json_decode(file_get_contents(MAPPING_DB), true) : [];
    $m[$dealId] = [
        'erp_id'      => $erpId,
        'sync_source' => $syncSource,   // 'bitrix' | 'external'
        'updated_at'  => date('c'),
    ];
    file_put_contents(MAPPING_DB, json_encode($m, JSON_UNESCAPED_UNICODE));
}
