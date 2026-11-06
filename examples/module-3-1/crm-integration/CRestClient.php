<?php
/**
 * Клиент для работы с REST API Bitrix24
 * Упрощает выполнение запросов к API и обработку ответов
 */
class CRestClient
{
    private $webhookUrl;
    private $lastError = null;

    /**
     * @param string $webhookUrl URL входящего вебхука (с токеном)
     */
    public function __construct($webhookUrl)
    {
        // Убираем trailing slash если есть
        $this->webhookUrl = rtrim($webhookUrl, '/');
    }

    /**
     * Выполнить REST-запрос
     *
     * @param string $method Название метода API (например, 'crm.deal.list')
     * @param array $params Параметры запроса
     * @return array|false Результат или false при ошибке
     */
    public function call($method, array $params = [])
    {
        $url = $this->webhookUrl . '/' . $method;

        // Используем cURL для HTTP-запроса
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->lastError = curl_error($ch);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        // Декодируем JSON-ответ
        $result = json_decode($response, true);

        // Проверяем наличие ошибки в ответе
        if (isset($result['error'])) {
            $this->lastError = $result['error'] . ': ' . $result['error_description'];
            return false;
        }

        return $result;
    }

    /**
     * Batch-запрос (выполнение нескольких команд за раз)
     *
     * @param array $commands Массив команд ['key' => 'method?params']
     * @param bool $halt Остановить выполнение при ошибке
     * @return array|false
     */
    public function batch(array $commands, $halt = false)
    {
        return $this->call('batch', [
            'halt' => $halt ? 1 : 0,
            'cmd' => $commands
        ]);
    }

    /**
     * Получить все элементы с автоматической pagination
     *
     * @param string $method Метод списка (например, 'crm.deal.list')
     * @param array $params Параметры фильтрации/сортировки
     * @return array Все найденные элементы
     */
    public function fetchAll($method, array $params = [])
    {
        $allItems = [];
        $start = 0;

        do {
            $params['start'] = $start;
            $result = $this->call($method, $params);

            if ($result === false) {
                break;
            }

            if (!empty($result['result'])) {
                $allItems = array_merge($allItems, $result['result']);
            }

            $start += 50;
            $hasMore = !empty($result['next']);

        } while ($hasMore);

        return $allItems;
    }

    /**
     * Получить последнюю ошибку
     *
     * @return string|null
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}
