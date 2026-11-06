<?php
/**
 * CrmHelper.php - Класс-помощник для работы с REST API Bitrix24 CRM
 *
 * Инкапсулирует логику запросов к REST API и обработки ответов
 */

class CrmHelper
{
    /** @var string Домен портала Bitrix24 */
    private $domain;

    /** @var string Webhook-код для авторизации */
    private $webhook;

    /** @var array Настройки из config.php */
    private $config;

    /** @var string Базовый URL для REST API */
    private $baseUrl;

    /**
     * Конструктор
     *
     * @param array $config Массив настроек из config.php
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->domain = $config['domain'];
        $this->webhook = $config['webhook'];

        // Формируем базовый URL для всех запросов
        // Формат: https://domain/rest/webhook_code/
        $this->baseUrl = "https://{$this->domain}/rest/{$this->webhook}/";

        $this->log('CrmHelper инициализирован', 'info');
    }

    /**
     * Выполнить REST API запрос
     *
     * @param string $method Метод API (например, "crm.company.add")
     * @param array $params Параметры запроса
     * @return array Ответ от API
     * @throws Exception При ошибке запроса
     */
    private function request(string $method, array $params = []): array
    {
        $url = $this->baseUrl . $method;

        $this->log("Запрос: {$method}", 'debug', $params);

        // Инициализируем cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        // Проверяем успешность запроса
        if ($error) {
            $this->log("cURL ошибка: {$error}", 'error');
            throw new Exception("cURL ошибка: {$error}");
        }

        if ($httpCode !== 200) {
            $this->log("HTTP ошибка: {$httpCode}", 'error');
            throw new Exception("HTTP ошибка: код {$httpCode}");
        }

        // Декодируем JSON ответ
        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Ошибка парсинга JSON: ' . json_last_error_msg(), 'error');
            throw new Exception('Ошибка парсинга JSON: ' . json_last_error_msg());
        }

        // Проверяем наличие ошибки в ответе API
        if (isset($result['error'])) {
            $errorMsg = $result['error_description'] ?? $result['error'];
            $this->log("API ошибка: {$errorMsg}", 'error');

            if ($this->config['error']['throw_exceptions'] ?? true) {
                throw new Exception("Bitrix24 API ошибка: {$errorMsg}");
            }
        }

        $this->log("Ответ: {$method}", 'debug', $result);

        return $result;
    }

    /**
     * Найти компанию по названию
     *
     * @param string $title Название компании для поиска
     * @return array|null Массив с данными компании или null, если не найдена
     */
    public function findCompanyByTitle(string $title): ?array
    {
        $this->log("Поиск компании: {$title}", 'info');

        $response = $this->request('crm.company.list', [
            'filter' => [
                'TITLE' => $title,  // Точное совпадение по названию
            ],
            'select' => ['ID', 'TITLE', 'PHONE', 'EMAIL'],
        ]);

        // Если найдены результаты, возвращаем первую компанию
        if (!empty($response['result'])) {
            return $response['result'][0];
        }

        return null;
    }

    /**
     * Создать компанию
     *
     * @param array $fields Поля компании
     * @return int ID созданной компании
     */
    public function createCompany(array $fields): int
    {
        $this->log('Создание компании', 'info', $fields);

        $response = $this->request('crm.company.add', [
            'fields' => $fields,
        ]);

        if (!isset($response['result'])) {
            throw new Exception('Не удалось получить ID созданной компании');
        }

        $companyId = (int)$response['result'];
        $this->log("Компания создана: ID {$companyId}", 'info');

        return $companyId;
    }

    /**
     * Получить или создать компанию (дедупликация)
     *
     * @param array $fields Поля компании для создания
     * @return array ['id' => int, 'existed' => bool]
     */
    public function getOrCreateCompany(array $fields): array
    {
        $title = $fields['TITLE'] ?? null;

        if (!$title) {
            throw new Exception('Не указано название компании (TITLE)');
        }

        // Пытаемся найти существующую компанию
        $existing = $this->findCompanyByTitle($title);

        if ($existing) {
            $this->log("Компания уже существует: ID {$existing['ID']}", 'info');
            return [
                'id' => (int)$existing['ID'],
                'existed' => true,
            ];
        }

        // Если не найдена, создаем новую
        $companyId = $this->createCompany($fields);

        return [
            'id' => $companyId,
            'existed' => false,
        ];
    }

    /**
     * Создать контакт
     *
     * @param array $fields Поля контакта
     * @return int ID созданного контакта
     */
    public function createContact(array $fields): int
    {
        $this->log('Создание контакта', 'info', $fields);

        $response = $this->request('crm.contact.add', [
            'fields' => $fields,
        ]);

        if (!isset($response['result'])) {
            throw new Exception('Не удалось получить ID созданного контакта');
        }

        $contactId = (int)$response['result'];
        $this->log("Контакт создан: ID {$contactId}", 'info');

        return $contactId;
    }

    /**
     * Создать сделку
     *
     * @param array $fields Поля сделки
     * @return int ID созданной сделки
     */
    public function createDeal(array $fields): int
    {
        $this->log('Создание сделки', 'info', $fields);

        // Валидация: должна быть связь с компанией или контактом
        if (empty($fields['COMPANY_ID']) && empty($fields['CONTACT_IDS'])) {
            throw new Exception('Сделка должна быть связана с компанией (COMPANY_ID) или контактом (CONTACT_IDS)');
        }

        $response = $this->request('crm.deal.add', [
            'fields' => $fields,
        ]);

        if (!isset($response['result'])) {
            throw new Exception('Не удалось получить ID созданной сделки');
        }

        $dealId = (int)$response['result'];
        $this->log("Сделка создана: ID {$dealId}", 'info');

        return $dealId;
    }

    /**
     * Получить компанию по ID
     *
     * @param int $id ID компании
     * @return array Данные компании
     */
    public function getCompany(int $id): array
    {
        $response = $this->request('crm.company.get', [
            'id' => $id,
        ]);

        if (!isset($response['result'])) {
            throw new Exception("Компания с ID {$id} не найдена");
        }

        return $response['result'];
    }

    /**
     * Получить контакт по ID
     *
     * @param int $id ID контакта
     * @return array Данные контакта
     */
    public function getContact(int $id): array
    {
        $response = $this->request('crm.contact.get', [
            'id' => $id,
        ]);

        if (!isset($response['result'])) {
            throw new Exception("Контакт с ID {$id} не найден");
        }

        return $response['result'];
    }

    /**
     * Получить сделку по ID
     *
     * @param int $id ID сделки
     * @return array Данные сделки
     */
    public function getDeal(int $id): array
    {
        $response = $this->request('crm.deal.get', [
            'id' => $id,
        ]);

        if (!isset($response['result'])) {
            throw new Exception("Сделка с ID {$id} не найдена");
        }

        return $response['result'];
    }

    /**
     * Логирование
     *
     * @param string $message Сообщение
     * @param string $level Уровень (debug, info, warning, error)
     * @param array $context Дополнительный контекст
     */
    private function log(string $message, string $level = 'info', array $context = []): void
    {
        if (!($this->config['log']['enabled'] ?? false)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        $logFile = $this->config['log']['file'] ?? __DIR__ . '/crm_api.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
