<?php
namespace Vettich;

/**
 * Класс для синхронизации сделок между Bitrix24 и внешней системой через batch
 *
 * Использование:
 * $sync = new DealSyncManager($bitrix24Client, $externalApi);
 * $sync->syncModifiedDeals(['since' => '-7 days']);
 * $status = $sync->getSyncStatus();
 */
class DealSyncManager
{
    /** @var object REST API клиент Bitrix24 */
    private $client;

    /** @var object API внешней системы */
    private $externalApi;

    /** @var int Размер batch-пакета */
    private $batchSize = 50;

    /** @var array Статистика синхронизации */
    private $stats = [
        'total' => 0,
        'synced' => 0,
        'conflicts' => 0,
        'errors' => 0,
        'rolled_back' => false,
        'start_time' => null,
        'end_time' => null,
        'log' => []
    ];

    /** @var array Маппинг стадий между системами */
    private $stageMapping = [
        // Bitrix24 => Внешняя система
        'NEW' => 'new_deal',
        'PREPARATION' => 'preparation',
        'PREPAYMENT_INVOICE' => 'invoice_sent',
        'EXECUTING' => 'in_progress',
        'FINAL_INVOICE' => 'final_invoice',
        'WON' => 'closed_won',
        'LOSE' => 'closed_lost'
    ];

    /** @var array Backup для отката */
    private $backup = [];

    /**
     * @param object $client REST API клиент Bitrix24
     * @param object $externalApi API внешней системы (например, 1C)
     */
    public function __construct($client, $externalApi = null)
    {
        $this->client = $client;
        $this->externalApi = $externalApi;
        $this->stats['start_time'] = microtime(true);
    }

    /**
     * Установить размер batch-пакета
     *
     * @param int $size Размер пакета
     * @return self
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
    }

    /**
     * Установить маппинг стадий
     *
     * @param array $mapping Массив соответствий ['BITRIX_STAGE' => 'external_stage']
     * @return self
     */
    public function setStageMapping(array $mapping): self
    {
        $this->stageMapping = $mapping;
        return $this;
    }

    /**
     * Синхронизировать измененные сделки
     *
     * @param array $options Опции синхронизации
     *   - since: string - дата начала (например, '-7 days', '2024-01-01')
     *   - external_system: string - ID внешней системы
     *   - rollback_on_error: bool - откатывать при критических ошибках
     *   - map_stage: bool - преобразовывать стадии (default: true)
     *   - sync_documents: bool - синхронизировать документы (default: false)
     * @return array Результаты синхронизации
     */
    public function syncModifiedDeals(array $options = []): array
    {
        $options = array_merge([
            'since' => '-1 day',
            'external_system' => 'external_crm',
            'rollback_on_error' => true,
            'map_stage' => true,
            'sync_documents' => false
        ], $options);

        echo "Начало синхронизации сделок...\n";
        echo "Период: {$options['since']}\n";
        echo "Внешняя система: {$options['external_system']}\n\n";

        // Получаем сделки для синхронизации
        $filter = [
            '>DATE_MODIFY' => date('Y-m-d H:i:s', strtotime($options['since']))
        ];

        $deals = $this->getDeals($filter);
        $this->stats['total'] = count($deals);

        if (empty($deals)) {
            echo "Нет сделок для синхронизации\n";
            return [];
        }

        echo "Найдено сделок: {$this->stats['total']}\n";

        // Создаем backup для возможности отката
        if ($options['rollback_on_error']) {
            echo "Создание резервной копии...\n";
            $this->createBackup($deals);
        }

        // Синхронизируем пакетами
        echo "Начинаем синхронизацию...\n\n";
        $results = $this->syncDealsBatch($deals, $options);

        // Проверяем критические ошибки
        if ($options['rollback_on_error'] && $this->hasCriticalErrors()) {
            echo "\n⚠ Обнаружены критические ошибки. Выполняется откат...\n";
            $this->rollback();
        }

        $this->stats['end_time'] = microtime(true);

        return $results;
    }

    /**
     * Синхронизировать сделки по фильтру
     *
     * @param array $filter Фильтр для поиска сделок
     * @param array $options Опции синхронизации
     * @return array Результаты
     */
    public function syncByFilter(array $filter, array $options = []): array
    {
        $deals = $this->getDeals($filter);
        $this->stats['total'] = count($deals);

        if (empty($deals)) {
            echo "Нет сделок, соответствующих фильтру\n";
            return [];
        }

        echo "Найдено сделок: {$this->stats['total']}\n";

        return $this->syncDealsBatch($deals, $options);
    }

    /**
     * Получить сделки по фильтру
     *
     * @param array $filter Фильтр
     * @return array Массив сделок
     */
    private function getDeals(array $filter): array
    {
        echo "Получение сделок из Bitrix24...\n";

        $deals = [];
        $start = 0;
        $limit = 50;

        do {
            $response = $this->client->call('crm.deal.list', [
                'filter' => $filter,
                'select' => [
                    'ID', 'TITLE', 'STAGE_ID', 'OPPORTUNITY', 'CURRENCY_ID',
                    'CONTACT_ID', 'COMPANY_ID', 'DATE_CREATE', 'DATE_MODIFY',
                    'ASSIGNED_BY_ID', 'COMMENTS', 'UF_*'
                ],
                'start' => $start
            ]);

            if (isset($response['result'])) {
                $deals = array_merge($deals, $response['result']);
                $start += $limit;
                $hasMore = isset($response['next']) || count($response['result']) === $limit;
            } else {
                break;
            }
        } while ($hasMore);

        return $deals;
    }

    /**
     * Синхронизировать сделки через batch
     *
     * @param array $deals Массив сделок
     * @param array $options Опции синхронизации
     * @return array Результаты
     */
    private function syncDealsBatch(array $deals, array $options): array
    {
        $results = [];
        $chunks = array_chunk($deals, $this->batchSize);
        $processed = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $syncData = [];

            foreach ($chunk as $deal) {
                $processed++;

                // Преобразуем данные сделки для внешней системы
                $externalData = $this->transformDealData($deal, $options);

                // Отправляем во внешнюю систему
                try {
                    if ($this->externalApi) {
                        $externalResult = $this->externalApi->updateDeal($externalData);

                        if ($externalResult['success']) {
                            $this->stats['synced']++;
                            $syncData[] = [
                                'bitrix_id' => $deal['ID'],
                                'external_id' => $externalResult['id'],
                                'status' => 'synced'
                            ];

                            $this->log("Сделка #{$deal['ID']} синхронизирована");
                        } else {
                            $this->stats['errors']++;
                            $this->log("Ошибка синхронизации сделки #{$deal['ID']}: " . $externalResult['error']);
                        }
                    } else {
                        // Режим тестирования без реального API
                        $this->stats['synced']++;
                        $this->log("Сделка #{$deal['ID']} подготовлена к синхронизации (тестовый режим)");
                    }
                } catch (\Exception $e) {
                    $this->stats['errors']++;
                    $this->log("Исключение при синхронизации сделки #{$deal['ID']}: " . $e->getMessage());
                }

                // Выводим прогресс
                $this->logProgress($processed, $this->stats['total']);
            }

            // Обновляем поле синхронизации в Bitrix24 batch-запросом
            if (!empty($syncData)) {
                $this->updateSyncStatus($syncData);
            }
        }

        return $results;
    }

    /**
     * Преобразовать данные сделки для внешней системы
     *
     * @param array $deal Данные сделки из Bitrix24
     * @param array $options Опции преобразования
     * @return array Данные для внешней системы
     */
    private function transformDealData(array $deal, array $options): array
    {
        $externalData = [
            'id' => $deal['ID'],
            'title' => $deal['TITLE'],
            'amount' => $deal['OPPORTUNITY'],
            'currency' => $deal['CURRENCY_ID'],
            'contact_id' => $deal['CONTACT_ID'],
            'company_id' => $deal['COMPANY_ID'],
            'created_at' => $deal['DATE_CREATE'],
            'modified_at' => $deal['DATE_MODIFY'],
            'assigned_to' => $deal['ASSIGNED_BY_ID'],
            'comments' => $deal['COMMENTS']
        ];

        // Преобразуем стадию
        if ($options['map_stage'] && isset($this->stageMapping[$deal['STAGE_ID']])) {
            $externalData['stage'] = $this->stageMapping[$deal['STAGE_ID']];
        } else {
            $externalData['stage'] = $deal['STAGE_ID'];
        }

        // Пользовательские поля
        foreach ($deal as $key => $value) {
            if (strpos($key, 'UF_') === 0) {
                $externalData['custom_fields'][$key] = $value;
            }
        }

        return $externalData;
    }

    /**
     * Обновить статус синхронизации в Bitrix24
     *
     * @param array $syncData Данные синхронизации
     */
    private function updateSyncStatus(array $syncData): void
    {
        $batch = [];

        foreach ($syncData as $i => $data) {
            $batch["update_$i"] = [
                'method' => 'crm.deal.update',
                'params' => [
                    'ID' => $data['bitrix_id'],
                    'fields' => [
                        'UF_CRM_SYNC_STATUS' => $data['status'],
                        'UF_CRM_EXTERNAL_ID' => $data['external_id'],
                        'UF_CRM_LAST_SYNC' => date('Y-m-d H:i:s')
                    ]
                ]
            ];
        }

        try {
            $response = $this->client->call('batch', ['cmd' => $batch]);

            // Проверяем результаты
            if (isset($response['result']['result'])) {
                foreach ($response['result']['result'] as $key => $result) {
                    if (isset($result['error'])) {
                        $this->log("Ошибка обновления статуса синхронизации: " . $result['error']);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log("Ошибка batch-обновления статусов: " . $e->getMessage());
        }
    }

    /**
     * Создать резервную копию сделок
     *
     * @param array $deals Сделки для backup
     */
    private function createBackup(array $deals): void
    {
        foreach ($deals as $deal) {
            $this->backup[$deal['ID']] = $deal;
        }

        $this->log("Создан backup для " . count($deals) . " сделок");
    }

    /**
     * Проверить наличие критических ошибок
     *
     * @return bool Есть ли критические ошибки
     */
    private function hasCriticalErrors(): bool
    {
        // Критические ошибки: более 50% неудач
        $failRate = $this->stats['total'] > 0
            ? ($this->stats['errors'] / $this->stats['total']) * 100
            : 0;

        return $failRate > 50;
    }

    /**
     * Откатить изменения
     */
    private function rollback(): void
    {
        if (empty($this->backup)) {
            $this->log("Нет данных для отката");
            return;
        }

        echo "Выполнение отката для " . count($this->backup) . " сделок...\n";

        $chunks = array_chunk($this->backup, $this->batchSize, true);
        $rolledBack = 0;

        foreach ($chunks as $chunk) {
            $batch = [];

            foreach ($chunk as $dealId => $originalData) {
                $batch["rollback_$dealId"] = [
                    'method' => 'crm.deal.update',
                    'params' => [
                        'ID' => $dealId,
                        'fields' => [
                            'STAGE_ID' => $originalData['STAGE_ID'],
                            'OPPORTUNITY' => $originalData['OPPORTUNITY'],
                            'UF_CRM_SYNC_STATUS' => 'rolled_back'
                        ]
                    ]
                ];
            }

            try {
                $response = $this->client->call('batch', ['cmd' => $batch]);

                if (isset($response['result']['result'])) {
                    foreach ($response['result']['result'] as $result) {
                        if (!isset($result['error'])) {
                            $rolledBack++;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->log("Ошибка при откате: " . $e->getMessage());
            }
        }

        $this->stats['rolled_back'] = true;
        $this->log("Откачено сделок: $rolledBack");

        echo "✓ Откат завершен. Восстановлено: $rolledBack сделок\n";
    }

    /**
     * Логирование
     *
     * @param string $message Сообщение
     */
    private function log(string $message): void
    {
        $this->stats['log'][] = [
            'time' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
    }

    /**
     * Вывести прогресс
     *
     * @param int $processed Обработано
     * @param int $total Всего
     */
    private function logProgress(int $processed, int $total): void
    {
        $percentage = round(($processed / $total) * 100, 2);
        $bar = str_repeat('=', (int)($percentage / 2)) . str_repeat(' ', 50 - (int)($percentage / 2));

        echo "\rПрогресс: [$bar] $processed/$total ($percentage%)";

        if ($processed === $total) {
            echo "\n\n";
        }
    }

    /**
     * Получить статус синхронизации
     *
     * @return array Статистика
     */
    public function getSyncStatus(): array
    {
        $executionTime = $this->stats['end_time']
            ? round($this->stats['end_time'] - $this->stats['start_time'], 2)
            : 0;

        return [
            'total' => $this->stats['total'],
            'synced' => $this->stats['synced'],
            'conflicts' => $this->stats['conflicts'],
            'errors' => $this->stats['errors'],
            'rolled_back' => $this->stats['rolled_back'],
            'success_rate' => $this->stats['total'] > 0
                ? round(($this->stats['synced'] / $this->stats['total']) * 100, 2)
                : 0,
            'execution_time' => $executionTime,
            'log' => $this->stats['log']
        ];
    }

    /**
     * Вывести отчет в консоль
     */
    public function printReport(): void
    {
        $status = $this->getSyncStatus();

        echo str_repeat('=', 60) . "\n";
        echo "ОТЧЕТ О СИНХРОНИЗАЦИИ СДЕЛОК\n";
        echo str_repeat('=', 60) . "\n";
        echo "Всего сделок: {$status['total']}\n";
        echo "Синхронизировано: {$status['synced']}\n";
        echo "Конфликтов: {$status['conflicts']}\n";
        echo "Ошибок: {$status['errors']}\n";

        if ($status['rolled_back']) {
            echo "⚠ ВЫПОЛНЕН ОТКАТ ИЗМЕНЕНИЙ\n";
        }

        echo "Процент успеха: {$status['success_rate']}%\n";
        echo "Время выполнения: {$status['execution_time']} сек\n";

        if (!empty($status['log'])) {
            echo "\nЛог операций:\n";
            foreach ($status['log'] as $entry) {
                echo "  [{$entry['time']}] {$entry['message']}\n";
            }
        }

        echo str_repeat('=', 60) . "\n";
    }
}
