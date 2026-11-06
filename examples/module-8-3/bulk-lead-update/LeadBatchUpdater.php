<?php
namespace Vettich;

/**
 * Класс для массового обновления лидов через batch-запросы
 *
 * Использование:
 * $updater = new LeadBatchUpdater($bitrix24Client);
 * $updater->updateLeadStatusByFilter(['STATUS_ID' => 'NEW'], 'IN_PROCESS');
 * $report = $updater->getReport();
 */
class LeadBatchUpdater
{
    /** @var object REST API клиент */
    private $client;

    /** @var int Размер batch-пакета (количество операций за раз) */
    private $batchSize = 50;

    /** @var array Статистика выполнения */
    private $stats = [
        'total' => 0,
        'success' => 0,
        'errors' => 0,
        'start_time' => null,
        'end_time' => null,
        'error_details' => []
    ];

    /**
     * @param object $client REST API клиент (например, Bitrix24\SDK\Core\CoreBuilder)
     */
    public function __construct($client)
    {
        $this->client = $client;
        $this->stats['start_time'] = microtime(true);
    }

    /**
     * Установить размер batch-пакета
     *
     * @param int $size Количество операций в одном пакете (рекомендуется 30-50)
     * @return self
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
    }

    /**
     * Обновить конкретные лиды по ID
     *
     * @param array $leadIds Массив ID лидов
     * @param array $fields Поля для обновления
     * @return array Результаты обновления
     */
    public function updateLeads(array $leadIds, array $fields): array
    {
        $this->stats['total'] = count($leadIds);
        $results = [];

        // Разбиваем на пакеты
        $chunks = array_chunk($leadIds, $this->batchSize);
        $processed = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            // Формируем batch-запрос
            $batch = [];
            foreach ($chunk as $i => $leadId) {
                $batch["update_lead_$i"] = [
                    'method' => 'crm.lead.update',
                    'params' => [
                        'ID' => $leadId,
                        'fields' => $fields
                    ]
                ];
            }

            // Выполняем batch-запрос
            try {
                $response = $this->client->call('batch', ['cmd' => $batch]);

                // Обрабатываем результаты
                if (isset($response['result']['result'])) {
                    foreach ($response['result']['result'] as $key => $result) {
                        $processed++;

                        if (isset($result['error'])) {
                            // Ошибка обновления
                            $this->stats['errors']++;
                            $this->stats['error_details'][] = [
                                'lead_id' => $chunk[substr($key, 12)], // Извлекаем индекс из ключа
                                'error' => $result['error']
                            ];
                        } else {
                            // Успешное обновление
                            $this->stats['success']++;
                            $results[] = [
                                'lead_id' => $result['result'],
                                'updated' => true
                            ];
                        }

                        // Выводим прогресс
                        $this->logProgress($processed, $this->stats['total']);
                    }
                }
            } catch (\Exception $e) {
                // Ошибка всего batch-запроса
                $this->stats['errors'] += count($chunk);
                $this->stats['error_details'][] = [
                    'chunk' => $chunkIndex,
                    'error' => $e->getMessage()
                ];

                echo "Ошибка batch #{$chunkIndex}: " . $e->getMessage() . "\n";
            }
        }

        $this->stats['end_time'] = microtime(true);
        return $results;
    }

    /**
     * Обновить лиды по фильтру
     *
     * @param array $filter Фильтр для поиска лидов
     * @param string $newStatus Новый статус для установки
     * @param array $additionalFields Дополнительные поля для обновления
     * @return array Результаты обновления
     */
    public function updateLeadStatusByFilter(array $filter, string $newStatus, array $additionalFields = []): array
    {
        echo "Получение лидов по фильтру...\n";

        // Получаем все ID лидов, удовлетворяющих фильтру
        $leadIds = $this->getLeadIdsByFilter($filter);

        if (empty($leadIds)) {
            echo "Лиды не найдены\n";
            return [];
        }

        echo "Найдено лидов: " . count($leadIds) . "\n";
        echo "Начинаем обновление...\n";

        // Формируем поля для обновления
        $fields = array_merge(['STATUS_ID' => $newStatus], $additionalFields);

        // Обновляем лиды
        return $this->updateLeads($leadIds, $fields);
    }

    /**
     * Получить ID всех лидов по фильтру (с пагинацией)
     *
     * @param array $filter Фильтр для поиска
     * @return array Массив ID лидов
     */
    private function getLeadIdsByFilter(array $filter): array
    {
        $leadIds = [];
        $start = 0;
        $limit = 50;

        do {
            $response = $this->client->call('crm.lead.list', [
                'filter' => $filter,
                'select' => ['ID'],
                'start' => $start
            ]);

            if (isset($response['result'])) {
                foreach ($response['result'] as $lead) {
                    $leadIds[] = $lead['ID'];
                }

                $start += $limit;

                // Проверяем, есть ли еще результаты
                $hasMore = isset($response['next']) || count($response['result']) === $limit;
            } else {
                break;
            }
        } while ($hasMore);

        return $leadIds;
    }

    /**
     * Вывести прогресс выполнения
     *
     * @param int $processed Обработано записей
     * @param int $total Всего записей
     */
    private function logProgress(int $processed, int $total): void
    {
        $percentage = round(($processed / $total) * 100, 2);
        $bar = str_repeat('=', (int)($percentage / 2)) . str_repeat(' ', 50 - (int)($percentage / 2));

        echo "\rПрогресс: [$bar] $processed/$total ($percentage%)";

        if ($processed === $total) {
            echo "\n";
        }
    }

    /**
     * Получить отчет о выполнении
     *
     * @return array Статистика выполнения
     */
    public function getReport(): array
    {
        $executionTime = $this->stats['end_time']
            ? round($this->stats['end_time'] - $this->stats['start_time'], 2)
            : 0;

        return [
            'total' => $this->stats['total'],
            'success' => $this->stats['success'],
            'errors' => $this->stats['errors'],
            'success_rate' => $this->stats['total'] > 0
                ? round(($this->stats['success'] / $this->stats['total']) * 100, 2)
                : 0,
            'execution_time' => $executionTime,
            'error_details' => $this->stats['error_details']
        ];
    }

    /**
     * Вывести отчет в консоль
     */
    public function printReport(): void
    {
        $report = $this->getReport();

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "ОТЧЕТ О ВЫПОЛНЕНИИ\n";
        echo str_repeat('=', 50) . "\n";
        echo "Всего лидов: {$report['total']}\n";
        echo "Успешно обновлено: {$report['success']}\n";
        echo "Ошибок: {$report['errors']}\n";
        echo "Процент успеха: {$report['success_rate']}%\n";
        echo "Время выполнения: {$report['execution_time']} сек\n";

        if (!empty($report['error_details'])) {
            echo "\nДетали ошибок:\n";
            foreach ($report['error_details'] as $error) {
                if (isset($error['lead_id'])) {
                    echo "  - Лид #{$error['lead_id']}: {$error['error']}\n";
                } else {
                    echo "  - Chunk #{$error['chunk']}: {$error['error']}\n";
                }
            }
        }

        echo str_repeat('=', 50) . "\n";
    }
}
