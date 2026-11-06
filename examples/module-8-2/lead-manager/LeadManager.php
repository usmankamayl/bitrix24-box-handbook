<?php
/**
 * Менеджер для работы с лидами через Bitrix24 REST API
 *
 * Расположение в проекте:
 * /local/php_interface/classes/LeadManager.php
 *
 * Подключение в init.php:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/LeadManager.php';
 */

namespace Local\Bitrix24;

class LeadManager
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
     * Создание нового лида
     *
     * @param array $fields Поля лида
     * @return array|false Результат создания или false при ошибке
     *
     * @example
     * $leadId = $manager->createLead([
     *     'TITLE' => 'Клиент из формы',
     *     'NAME' => 'Иван',
     *     'LAST_NAME' => 'Иванов',
     *     'PHONE' => [
     *         ['VALUE' => '+7 (999) 123-45-67', 'VALUE_TYPE' => 'WORK']
     *     ],
     *     'EMAIL' => [
     *         ['VALUE' => 'ivanov@example.com', 'VALUE_TYPE' => 'WORK']
     *     ],
     *     'SOURCE_ID' => 'WEB',
     *     'STATUS_ID' => 'NEW'
     * ]);
     */
    public function createLead(array $fields)
    {
        $response = $this->callMethod('crm.lead.add', [
            'fields' => $fields
        ]);

        if (!$response || isset($response['error'])) {
            error_log('Ошибка создания лида: ' . ($response['error_description'] ?? 'Unknown error'));
            return false;
        }

        return $response['result']; // ID созданного лида
    }

    /**
     * Обновление существующего лида
     *
     * @param int $leadId ID лида
     * @param array $fields Поля для обновления
     * @return bool Успешность операции
     *
     * @example
     * $manager->updateLead(123, [
     *     'STATUS_ID' => 'IN_PROCESS',
     *     'ASSIGNED_BY_ID' => 5,
     *     'COMMENTS' => 'Клиент заинтересован'
     * ]);
     */
    public function updateLead(int $leadId, array $fields): bool
    {
        $response = $this->callMethod('crm.lead.update', [
            'id' => $leadId,
            'fields' => $fields
        ]);

        if (!$response || isset($response['error'])) {
            error_log("Ошибка обновления лида #{$leadId}: " . ($response['error_description'] ?? 'Unknown error'));
            return false;
        }

        return (bool)$response['result'];
    }

    /**
     * Получение лида по ID
     *
     * @param int $leadId ID лида
     * @return array|false Данные лида или false при ошибке
     */
    public function getLead(int $leadId)
    {
        $response = $this->callMethod('crm.lead.get', [
            'id' => $leadId
        ]);

        if (!$response || isset($response['error'])) {
            return false;
        }

        return $response['result'];
    }

    /**
     * Поиск лидов по фильтру
     *
     * @param array $filter Условия фильтрации
     * @param array $select Поля для выборки (по умолчанию все)
     * @param array $order Сортировка
     * @return array Массив лидов
     *
     * @example
     * // Найти новые лиды за последнюю неделю
     * $leads = $manager->searchLeads([
     *     'STATUS_ID' => 'NEW',
     *     '>=DATE_CREATE' => date('Y-m-d', strtotime('-7 days'))
     * ], ['ID', 'TITLE', 'NAME', 'PHONE']);
     */
    public function searchLeads(
        array $filter = [],
        array $select = ['*'],
        array $order = ['DATE_CREATE' => 'DESC']
    ): array
    {
        $allLeads = [];
        $start = 0;

        do {
            $response = $this->callMethod('crm.lead.list', [
                'filter' => $filter,
                'select' => $select,
                'order' => $order,
                'start' => $start
            ]);

            if (!$response || isset($response['error'])) {
                break;
            }

            $leads = $response['result'] ?? [];
            $allLeads = array_merge($allLeads, $leads);

            $start += self::PAGE_SIZE;

            // Если вернулось меньше PAGE_SIZE, значит это последняя страница
        } while (count($leads) === self::PAGE_SIZE);

        return $allLeads;
    }

    /**
     * Добавление телефона к лиду
     *
     * @param int $leadId ID лида
     * @param string $phone Номер телефона
     * @param string $type Тип телефона (WORK, MOBILE, HOME)
     * @return bool Успешность операции
     */
    public function addPhone(int $leadId, string $phone, string $type = 'WORK'): bool
    {
        // Получаем текущие данные лида
        $lead = $this->getLead($leadId);
        if (!$lead) {
            return false;
        }

        // Получаем существующие телефоны
        $phones = $lead['PHONE'] ?? [];

        // Добавляем новый телефон
        $phones[] = [
            'VALUE' => $phone,
            'VALUE_TYPE' => $type
        ];

        // Обновляем лид
        return $this->updateLead($leadId, [
            'PHONE' => $phones
        ]);
    }

    /**
     * Удаление лида
     *
     * @param int $leadId ID лида
     * @return bool Успешность операции
     */
    public function deleteLead(int $leadId): bool
    {
        $response = $this->callMethod('crm.lead.delete', [
            'id' => $leadId
        ]);

        if (!$response || isset($response['error'])) {
            return false;
        }

        return (bool)$response['result'];
    }

    /**
     * Получение списка доступных полей лида
     *
     * @return array Массив с описанием полей
     */
    public function getFields(): array
    {
        $response = $this->callMethod('crm.lead.fields');

        if (!$response || isset($response['error'])) {
            return [];
        }

        return $response['result'];
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
