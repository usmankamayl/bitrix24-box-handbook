<?php
require_once __DIR__ . '/CRestClient.php';

/**
 * Менеджер для работы со сделками через REST API
 * Демонстрирует основные операции CRUD с сделками
 */
class DealManager
{
    private $client;

    public function __construct(CRestClient $client)
    {
        $this->client = $client;
    }

    /**
     * Создать новую сделку
     *
     * @param array $fields Поля сделки
     * @return int|false ID созданной сделки или false
     */
    public function createDeal(array $fields)
    {
        // Обязательные поля для сделки
        $requiredFields = [
            'TITLE' => $fields['TITLE'] ?? 'Новая сделка',
            'STAGE_ID' => $fields['STAGE_ID'] ?? 'NEW',
            'CURRENCY_ID' => $fields['CURRENCY_ID'] ?? 'RUB'
        ];

        // Объединяем с дополнительными полями
        $dealFields = array_merge($requiredFields, $fields);

        $result = $this->client->call('crm.deal.add', [
            'fields' => $dealFields
        ]);

        if ($result === false) {
            return false;
        }

        return $result['result'] ?? false;
    }

    /**
     * Получить сделку по ID
     *
     * @param int $dealId ID сделки
     * @return array|false Данные сделки или false
     */
    public function getDeal($dealId)
    {
        $result = $this->client->call('crm.deal.get', [
            'ID' => $dealId
        ]);

        return $result['result'] ?? false;
    }

    /**
     * Обновить сделку
     *
     * @param int $dealId ID сделки
     * @param array $fields Поля для обновления
     * @return bool Успех операции
     */
    public function updateDeal($dealId, array $fields)
    {
        $result = $this->client->call('crm.deal.update', [
            'ID' => $dealId,
            'fields' => $fields
        ]);

        return $result !== false;
    }

    /**
     * Удалить сделку
     *
     * @param int $dealId ID сделки
     * @return bool Успех операции
     */
    public function deleteDeal($dealId)
    {
        $result = $this->client->call('crm.deal.delete', [
            'ID' => $dealId
        ]);

        return $result !== false;
    }

    /**
     * Получить список сделок с фильтрацией
     *
     * @param array $filter Фильтр (например, ['STAGE_ID' => 'NEW'])
     * @param array $order Сортировка (например, ['DATE_CREATE' => 'DESC'])
     * @param array $select Поля для выборки
     * @return array Список сделок
     */
    public function getDeals(array $filter = [], array $order = [], array $select = [])
    {
        $params = [];

        if (!empty($filter)) {
            $params['filter'] = $filter;
        }

        if (!empty($order)) {
            $params['order'] = $order;
        }

        if (!empty($select)) {
            $params['select'] = $select;
        }

        // Используем fetchAll для автоматической pagination
        return $this->client->fetchAll('crm.deal.list', $params);
    }

    /**
     * Перевести сделку на другую стадию
     *
     * @param int $dealId ID сделки
     * @param string $stageId Код стадии
     * @return bool
     */
    public function moveToStage($dealId, $stageId)
    {
        return $this->updateDeal($dealId, [
            'STAGE_ID' => $stageId
        ]);
    }

    /**
     * Добавить комментарий к сделке
     *
     * @param int $dealId ID сделки
     * @param string $comment Текст комментария
     * @return bool
     */
    public function addComment($dealId, $comment)
    {
        $result = $this->client->call('crm.timeline.comment.add', [
            'fields' => [
                'ENTITY_ID' => $dealId,
                'ENTITY_TYPE' => 'deal',
                'COMMENT' => $comment
            ]
        ]);

        return $result !== false;
    }

    /**
     * Получить сделки по диапазону суммы
     *
     * @param float $minAmount Минимальная сумма
     * @param float $maxAmount Максимальная сумма
     * @return array
     */
    public function getDealsByAmountRange($minAmount, $maxAmount)
    {
        return $this->getDeals([
            '>=OPPORTUNITY' => $minAmount,
            '<=OPPORTUNITY' => $maxAmount
        ]);
    }

    /**
     * Получить статистику по сделкам
     *
     * @return array Массив со статистикой
     */
    public function getStatistics()
    {
        $allDeals = $this->getDeals();

        $stats = [
            'total_count' => count($allDeals),
            'total_amount' => 0,
            'by_stage' => []
        ];

        foreach ($allDeals as $deal) {
            // Суммируем сумму сделок
            $stats['total_amount'] += floatval($deal['OPPORTUNITY'] ?? 0);

            // Группируем по стадиям
            $stageId = $deal['STAGE_ID'] ?? 'UNKNOWN';
            if (!isset($stats['by_stage'][$stageId])) {
                $stats['by_stage'][$stageId] = [
                    'count' => 0,
                    'amount' => 0
                ];
            }
            $stats['by_stage'][$stageId]['count']++;
            $stats['by_stage'][$stageId]['amount'] += floatval($deal['OPPORTUNITY'] ?? 0);
        }

        return $stats;
    }

    /**
     * Массовое создание сделок
     *
     * @param array $dealsData Массив с данными сделок
     * @return array Результаты создания
     */
    public function createBatch(array $dealsData)
    {
        $commands = [];

        foreach ($dealsData as $index => $dealFields) {
            $fields = array_merge([
                'TITLE' => 'Сделка ' . ($index + 1),
                'STAGE_ID' => 'NEW',
                'CURRENCY_ID' => 'RUB'
            ], $dealFields);

            $commands['deal_' . $index] = 'crm.deal.add?fields[TITLE]=' . urlencode($fields['TITLE'])
                . '&fields[STAGE_ID]=' . $fields['STAGE_ID']
                . '&fields[CURRENCY_ID]=' . $fields['CURRENCY_ID']
                . '&fields[OPPORTUNITY]=' . ($fields['OPPORTUNITY'] ?? 0);
        }

        $result = $this->client->batch($commands);

        return $result['result']['result'] ?? [];
    }
}
