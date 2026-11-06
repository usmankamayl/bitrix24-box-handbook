<?php
/**
 * Менеджер для работы со сделками через Bitrix24 REST API
 *
 * Расположение в проекте:
 * /local/php_interface/classes/DealManager.php
 *
 * Подключение в init.php:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/DealManager.php';
 */

namespace Local\Bitrix24;

class DealManager
{
    /** @var string URL входящего webhook */
    private $webhookUrl;

    /** @var int Лимит записей на страницу */
    private const PAGE_SIZE = 50;

    /**
     * @param string $webhookUrl URL входящего webhook
     */
    public function __construct(string $webhookUrl)
    {
        $this->webhookUrl = rtrim($webhookUrl, '/');
    }

    /**
     * Создание новой сделки
     *
     * @param array $fields Поля сделки
     * @return int|false ID созданной сделки или false при ошибке
     *
     * @example
     * $dealId = $manager->createDeal([
     *     'TITLE' => 'Продажа CRM системы',
     *     'STAGE_ID' => 'NEW',
     *     'OPPORTUNITY' => 150000,
     *     'CURRENCY_ID' => 'RUB',
     *     'CONTACT_ID' => 123,
     *     'COMPANY_ID' => 456,
     *     'ASSIGNED_BY_ID' => 1
     * ]);
     */
    public function createDeal(array $fields)
    {
        $response = $this->callMethod('crm.deal.add', [
            'fields' => $fields
        ]);

        if (!$response || isset($response['error'])) {
            error_log('Ошибка создания сделки: ' . ($response['error_description'] ?? 'Unknown error'));
            return false;
        }

        return $response['result'];
    }

    /**
     * Обновление сделки
     *
     * @param int $dealId ID сделки
     * @param array $fields Поля для обновления
     * @return bool Успешность операции
     *
     * @example
     * $manager->updateDeal(123, [
     *     'STAGE_ID' => 'PREPARATION',
     *     'PROBABILITY' => 50
     * ]);
     */
    public function updateDeal(int $dealId, array $fields): bool
    {
        $response = $this->callMethod('crm.deal.update', [
            'id' => $dealId,
            'fields' => $fields
        ]);

        if (!$response || isset($response['error'])) {
            error_log("Ошибка обновления сделки #{$dealId}: " . ($response['error_description'] ?? 'Unknown error'));
            return false;
        }

        return (bool)$response['result'];
    }

    /**
     * Получение сделки по ID
     *
     * @param int $dealId ID сделки
     * @return array|false Данные сделки или false при ошибке
     */
    public function getDeal(int $dealId)
    {
        $response = $this->callMethod('crm.deal.get', [
            'id' => $dealId
        ]);

        if (!$response || isset($response['error'])) {
            return false;
        }

        return $response['result'];
    }

    /**
     * Получение списка сделок с фильтрацией
     *
     * @param array $filter Условия фильтрации
     * @param array $select Поля для выборки
     * @param array $order Сортировка
     * @return array Массив сделок
     *
     * @example
     * // Все активные сделки в стадии "Новая"
     * $deals = $manager->listDeals([
     *     'STAGE_ID' => 'NEW',
     *     'CLOSED' => 'N'
     * ]);
     */
    public function listDeals(
        array $filter = [],
        array $select = ['*'],
        array $order = ['DATE_CREATE' => 'DESC']
    ): array
    {
        $allDeals = [];
        $start = 0;

        do {
            $response = $this->callMethod('crm.deal.list', [
                'filter' => $filter,
                'select' => $select,
                'order' => $order,
                'start' => $start
            ]);

            if (!$response || isset($response['error'])) {
                break;
            }

            $deals = $response['result'] ?? [];
            $allDeals = array_merge($allDeals, $deals);

            $start += self::PAGE_SIZE;

        } while (count($deals) === self::PAGE_SIZE);

        return $allDeals;
    }

    /**
     * Перемещение сделки на следующую стадию воронки
     *
     * @param int $dealId ID сделки
     * @return bool|string Новая стадия или false при ошибке
     */
    public function moveToNextStage(int $dealId)
    {
        // Получаем текущую стадию
        $deal = $this->getDeal($dealId);
        if (!$deal) {
            return false;
        }

        $currentStage = $deal['STAGE_ID'];

        // Получаем список всех стадий
        $stages = $this->getStages();
        if (empty($stages)) {
            return false;
        }

        // Находим текущую стадию и берем следующую
        $currentIndex = array_search($currentStage, array_column($stages, 'STATUS_ID'));
        if ($currentIndex === false || $currentIndex === count($stages) - 1) {
            // Уже на последней стадии
            return false;
        }

        $nextStage = $stages[$currentIndex + 1]['STATUS_ID'];

        // Обновляем сделку
        $updated = $this->updateDeal($dealId, [
            'STAGE_ID' => $nextStage
        ]);

        return $updated ? $nextStage : false;
    }

    /**
     * Получение списка стадий воронки продаж
     *
     * @return array Массив стадий
     */
    public function getStages(): array
    {
        $response = $this->callMethod('crm.status.list', [
            'filter' => [
                'ENTITY_ID' => 'DEAL_STAGE'
            ]
        ]);

        if (!$response || isset($response['error'])) {
            return [];
        }

        return $response['result'];
    }

    /**
     * Привязка контакта к сделке
     *
     * @param int $dealId ID сделки
     * @param int $contactId ID контакта
     * @param bool $isPrimary Основной контакт
     * @return bool Успешность операции
     */
    public function addContact(int $dealId, int $contactId, bool $isPrimary = false): bool
    {
        $response = $this->callMethod('crm.deal.contact.add', [
            'id' => $dealId,
            'fields' => [
                'CONTACT_ID' => $contactId,
                'IS_PRIMARY' => $isPrimary ? 'Y' : 'N'
            ]
        ]);

        if (!$response || isset($response['error'])) {
            return false;
        }

        return (bool)$response['result'];
    }

    /**
     * Получение контактов сделки
     *
     * @param int $dealId ID сделки
     * @return array Массив контактов
     */
    public function getContacts(int $dealId): array
    {
        $response = $this->callMethod('crm.deal.contact.items.get', [
            'id' => $dealId
        ]);

        if (!$response || isset($response['error'])) {
            return [];
        }

        return $response['result'];
    }

    /**
     * Получение статистики по воронке продаж
     *
     * @return array Статистика по стадиям
     */
    public function getPipelineStats(): array
    {
        $stages = $this->getStages();
        $stats = [];

        foreach ($stages as $stage) {
            $stageId = $stage['STATUS_ID'];

            // Подсчитываем сделки на каждой стадии
            $deals = $this->listDeals(
                ['STAGE_ID' => $stageId],
                ['ID', 'OPPORTUNITY']
            );

            $totalAmount = array_sum(array_column($deals, 'OPPORTUNITY'));

            $stats[] = [
                'stage_id' => $stageId,
                'stage_name' => $stage['NAME'],
                'deals_count' => count($deals),
                'total_amount' => $totalAmount
            ];
        }

        return $stats;
    }

    /**
     * Закрытие сделки как выигранной
     *
     * @param int $dealId ID сделки
     * @return bool Успешность операции
     */
    public function markAsWon(int $dealId): bool
    {
        return $this->updateDeal($dealId, [
            'STAGE_ID' => 'WON',
            'CLOSED' => 'Y',
            'CLOSEDATE' => date('Y-m-d')
        ]);
    }

    /**
     * Закрытие сделки как проигранной
     *
     * @param int $dealId ID сделки
     * @param string $reason Причина проигрыша
     * @return bool Успешность операции
     */
    public function markAsLost(int $dealId, string $reason = ''): bool
    {
        $fields = [
            'STAGE_ID' => 'LOSE',
            'CLOSED' => 'Y',
            'CLOSEDATE' => date('Y-m-d')
        ];

        if ($reason) {
            $fields['COMMENTS'] = $reason;
        }

        return $this->updateDeal($dealId, $fields);
    }

    /**
     * Удаление сделки
     *
     * @param int $dealId ID сделки
     * @return bool Успешность операции
     */
    public function deleteDeal(int $dealId): bool
    {
        $response = $this->callMethod('crm.deal.delete', [
            'id' => $dealId
        ]);

        if (!$response || isset($response['error'])) {
            return false;
        }

        return (bool)$response['result'];
    }

    /**
     * Вызов REST API метода
     *
     * @param string $method Название метода
     * @param array $params Параметры запроса
     * @return array|false Ответ API или false при ошибке
     */
    private function callMethod(string $method, array $params = [])
    {
        $url = $this->webhookUrl . '/' . $method . '.json';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("HTTP error {$httpCode} при вызове {$method}");
            return false;
        }

        return json_decode($response, true);
    }
}
