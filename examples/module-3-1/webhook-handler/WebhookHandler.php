<?php
/**
 * Обработчик событий (webhooks) от Bitrix24
 * Принимает уведомления о событиях и обрабатывает их
 */
class WebhookHandler
{
    private $logFile;
    private $handlers = [];

    /**
     * @param string $logFile Путь к файлу логов
     */
    public function __construct($logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * Обработать входящий webhook от Bitrix24
     *
     * @return array Результат обработки
     */
    public function handle()
    {
        // Получаем данные из POST-запроса
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        // Если данные не в JSON, пробуем $_POST
        if (empty($data)) {
            $data = $_POST;
        }

        // Логируем входящий запрос
        $this->log('Incoming webhook: ' . json_encode($data));

        // Проверяем обязательные поля
        if (empty($data['event'])) {
            $this->log('ERROR: Missing event field');
            return ['status' => 'error', 'message' => 'Missing event field'];
        }

        $event = $data['event'];
        $eventData = $data['data'] ?? [];

        // Ищем зарегистрированный обработчик для события
        if (isset($this->handlers[$event])) {
            try {
                $result = call_user_func($this->handlers[$event], $eventData, $data);
                $this->log("Event $event handled successfully");
                return ['status' => 'success', 'result' => $result];
            } catch (Exception $e) {
                $this->log("ERROR handling $event: " . $e->getMessage());
                return ['status' => 'error', 'message' => $e->getMessage()];
            }
        } else {
            $this->log("WARNING: No handler for event $event");
            return ['status' => 'warning', 'message' => 'No handler registered'];
        }
    }

    /**
     * Зарегистрировать обработчик для события
     *
     * @param string $event Название события
     * @param callable $handler Функция-обработчик
     */
    public function on($event, callable $handler)
    {
        $this->handlers[$event] = $handler;
    }

    /**
     * Записать в лог
     *
     * @param string $message Сообщение
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Получить последние записи лога
     *
     * @param int $lines Количество строк
     * @return array
     */
    public function getLastLogEntries($lines = 10)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $logLines = file($this->logFile);
        return array_slice($logLines, -$lines);
    }

    /**
     * Очистить лог
     */
    public function clearLog()
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
}

/**
 * Менеджер регистрации событий в Bitrix24
 */
class EventManager
{
    private $webhookUrl;

    public function __construct($webhookUrl)
    {
        $this->webhookUrl = rtrim($webhookUrl, '/');
    }

    /**
     * Зарегистрировать обработчик события
     *
     * @param string $event Код события (например, ONCRMDEALADD)
     * @param string $handlerUrl URL вашего обработчика
     * @return bool
     */
    public function bind($event, $handlerUrl)
    {
        $result = $this->call('event.bind', [
            'event' => $event,
            'handler' => $handlerUrl
        ]);

        return $result !== false;
    }

    /**
     * Отменить регистрацию события
     *
     * @param string $event Код события
     * @param string $handlerUrl URL обработчика
     * @return bool
     */
    public function unbind($event, $handlerUrl)
    {
        $result = $this->call('event.unbind', [
            'event' => $event,
            'handler' => $handlerUrl
        ]);

        return $result !== false;
    }

    /**
     * Получить список зарегистрированных событий
     *
     * @return array|false
     */
    public function getList()
    {
        $result = $this->call('event.get');
        return $result['result'] ?? false;
    }

    /**
     * Выполнить REST-запрос
     */
    private function call($method, array $params = [])
    {
        $url = $this->webhookUrl . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            return false;
        }

        return $result;
    }
}
