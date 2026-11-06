<?php
/**
 * Менеджер для работы с задачами через REST API Bitrix24
 * Реализует синхронизацию задач между внешней системой и Bitrix24
 */
class TaskManager
{
    private $webhookUrl;
    private $lastError = null;

    public function __construct($webhookUrl)
    {
        $this->webhookUrl = rtrim($webhookUrl, '/');
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
            $this->lastError = curl_error($ch);
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            $this->lastError = $result['error'] . ': ' . $result['error_description'];
            return false;
        }

        return $result;
    }

    /**
     * Создать задачу в Bitrix24
     *
     * @param array $fields Поля задачи
     * @return int|false ID созданной задачи
     */
    public function createTask(array $fields)
    {
        $result = $this->call('tasks.task.add', [
            'fields' => $fields
        ]);

        return $result['result']['task']['id'] ?? false;
    }

    /**
     * Получить задачу по ID
     *
     * @param int $taskId ID задачи
     * @return array|false Данные задачи
     */
    public function getTask($taskId)
    {
        $result = $this->call('tasks.task.get', [
            'taskId' => $taskId
        ]);

        return $result['result']['task'] ?? false;
    }

    /**
     * Обновить задачу
     *
     * @param int $taskId ID задачи
     * @param array $fields Поля для обновления
     * @return bool
     */
    public function updateTask($taskId, array $fields)
    {
        $result = $this->call('tasks.task.update', [
            'taskId' => $taskId,
            'fields' => $fields
        ]);

        return $result !== false;
    }

    /**
     * Удалить задачу
     *
     * @param int $taskId ID задачи
     * @return bool
     */
    public function deleteTask($taskId)
    {
        $result = $this->call('tasks.task.delete', [
            'taskId' => $taskId
        ]);

        return $result !== false;
    }

    /**
     * Получить список задач с фильтрацией
     *
     * @param array $filter Фильтр
     * @param array $select Поля для выборки
     * @param array $order Сортировка
     * @return array Список задач
     */
    public function getTasks(array $filter = [], array $select = [], array $order = [])
    {
        $params = [];

        if (!empty($filter)) {
            $params['filter'] = $filter;
        }

        if (!empty($select)) {
            $params['select'] = $select;
        }

        if (!empty($order)) {
            $params['order'] = $order;
        }

        // Получение всех задач с pagination
        $allTasks = [];
        $start = 0;

        do {
            $params['start'] = $start;
            $result = $this->call('tasks.task.list', $params);

            if ($result === false) {
                break;
            }

            if (!empty($result['result']['tasks'])) {
                $allTasks = array_merge($allTasks, $result['result']['tasks']);
            }

            $start += 50;
            $hasMore = !empty($result['next']);

        } while ($hasMore);

        return $allTasks;
    }

    /**
     * Завершить задачу
     *
     * @param int $taskId ID задачи
     * @return bool
     */
    public function completeTask($taskId)
    {
        $result = $this->call('tasks.task.complete', [
            'taskId' => $taskId
        ]);

        return $result !== false;
    }

    /**
     * Возобновить задачу
     *
     * @param int $taskId ID задачи
     * @return bool
     */
    public function renewTask($taskId)
    {
        $result = $this->call('tasks.task.renew', [
            'taskId' => $taskId
        ]);

        return $result !== false;
    }

    /**
     * Добавить комментарий к задаче
     *
     * @param int $taskId ID задачи
     * @param string $text Текст комментария
     * @return int|false ID комментария
     */
    public function addComment($taskId, $text)
    {
        $result = $this->call('task.commentitem.add', [
            'TASKID' => $taskId,
            'FIELDS' => [
                'POST_MESSAGE' => $text
            ]
        ]);

        return $result['result'] ?? false;
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

    /**
     * Получить измененные задачи с определенной даты
     *
     * @param string $dateFrom Дата в формате Y-m-d H:i:s
     * @return array
     */
    public function getTasksModifiedSince($dateFrom)
    {
        return $this->getTasks([
            '>=CHANGED_DATE' => $dateFrom
        ]);
    }

    /**
     * Получить задачи по пользователю
     *
     * @param int $userId ID пользователя
     * @return array
     */
    public function getTasksByUser($userId)
    {
        return $this->getTasks([
            'RESPONSIBLE_ID' => $userId
        ]);
    }
}
