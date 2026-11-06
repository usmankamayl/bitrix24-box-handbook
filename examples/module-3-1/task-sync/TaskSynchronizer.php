<?php
/**
 * Синхронизатор задач между внешней системой и Bitrix24
 * Двусторонняя синхронизация с отслеживанием изменений
 */
require_once __DIR__ . '/TaskManager.php';

class TaskSynchronizer
{
    private $taskManager;
    private $syncLogFile;
    private $mappingFile;

    /**
     * @param TaskManager $taskManager Менеджер задач Bitrix24
     * @param string $syncLogFile Путь к файлу лога синхронизации
     * @param string $mappingFile Путь к файлу связей ID (внешняя система <-> Bitrix24)
     */
    public function __construct(TaskManager $taskManager, $syncLogFile, $mappingFile)
    {
        $this->taskManager = $taskManager;
        $this->syncLogFile = $syncLogFile;
        $this->mappingFile = $mappingFile;
    }

    /**
     * Синхронизировать задачи из внешней системы в Bitrix24
     *
     * @param array $externalTasks Задачи из внешней системы
     * @return array Результаты синхронизации
     */
    public function syncFromExternal(array $externalTasks)
    {
        $results = [
            'created' => [],
            'updated' => [],
            'errors' => []
        ];

        $mapping = $this->loadMapping();

        foreach ($externalTasks as $externalTask) {
            $externalId = $externalTask['id'];

            // Проверяем, есть ли связь с задачей в Bitrix24
            if (isset($mapping[$externalId])) {
                // Задача уже существует - обновляем
                $bitrixId = $mapping[$externalId];
                $result = $this->updateTaskInBitrix($bitrixId, $externalTask);

                if ($result) {
                    $results['updated'][] = [
                        'external_id' => $externalId,
                        'bitrix_id' => $bitrixId
                    ];
                    $this->log("Updated task: external=$externalId, bitrix=$bitrixId");
                } else {
                    $results['errors'][] = [
                        'external_id' => $externalId,
                        'error' => $this->taskManager->getLastError()
                    ];
                }
            } else {
                // Новая задача - создаем
                $bitrixId = $this->createTaskInBitrix($externalTask);

                if ($bitrixId) {
                    $mapping[$externalId] = $bitrixId;
                    $results['created'][] = [
                        'external_id' => $externalId,
                        'bitrix_id' => $bitrixId
                    ];
                    $this->log("Created task: external=$externalId, bitrix=$bitrixId");
                } else {
                    $results['errors'][] = [
                        'external_id' => $externalId,
                        'error' => $this->taskManager->getLastError()
                    ];
                }
            }
        }

        // Сохраняем обновленные связи
        $this->saveMapping($mapping);

        return $results;
    }

    /**
     * Синхронизировать задачи из Bitrix24 во внешнюю систему
     *
     * @param string $sinceDate Дата начала выборки (Y-m-d H:i:s)
     * @return array Измененные задачи для внешней системы
     */
    public function syncToBitrix($sinceDate = null)
    {
        if ($sinceDate === null) {
            $sinceDate = $this->getLastSyncTime();
        }

        // Получаем измененные задачи из Bitrix24
        $changedTasks = $this->taskManager->getTasksModifiedSince($sinceDate);

        $mapping = $this->loadMapping();
        $reverseMapping = array_flip($mapping);

        $externalTasks = [];

        foreach ($changedTasks as $bitrixTask) {
            $bitrixId = $bitrixTask['id'];

            // Находим ID во внешней системе
            $externalId = $reverseMapping[$bitrixId] ?? null;

            $externalTasks[] = [
                'external_id' => $externalId,
                'bitrix_id' => $bitrixId,
                'action' => $externalId ? 'update' : 'create',
                'data' => $this->convertBitrixToExternal($bitrixTask)
            ];
        }

        // Обновляем время последней синхронизации
        $this->setLastSyncTime(date('Y-m-d H:i:s'));

        return $externalTasks;
    }

    /**
     * Создать задачу в Bitrix24 из внешних данных
     *
     * @param array $externalTask Данные задачи из внешней системы
     * @return int|false ID созданной задачи
     */
    private function createTaskInBitrix(array $externalTask)
    {
        $fields = [
            'TITLE' => $externalTask['title'] ?? 'Без названия',
            'DESCRIPTION' => $externalTask['description'] ?? '',
            'RESPONSIBLE_ID' => $externalTask['responsible_id'] ?? 1,
            'DEADLINE' => $externalTask['deadline'] ?? null
        ];

        // Удаляем null значения
        $fields = array_filter($fields, function($value) {
            return $value !== null;
        });

        return $this->taskManager->createTask($fields);
    }

    /**
     * Обновить задачу в Bitrix24
     *
     * @param int $bitrixId ID задачи в Bitrix24
     * @param array $externalTask Новые данные из внешней системы
     * @return bool
     */
    private function updateTaskInBitrix($bitrixId, array $externalTask)
    {
        // Получаем текущую задачу
        $currentTask = $this->taskManager->getTask($bitrixId);

        if (!$currentTask) {
            return false;
        }

        // Определяем, что изменилось
        $updates = [];

        if (isset($externalTask['title']) && $currentTask['title'] !== $externalTask['title']) {
            $updates['TITLE'] = $externalTask['title'];
        }

        if (isset($externalTask['description']) && $currentTask['description'] !== $externalTask['description']) {
            $updates['DESCRIPTION'] = $externalTask['description'];
        }

        if (isset($externalTask['deadline']) && $currentTask['deadline'] !== $externalTask['deadline']) {
            $updates['DEADLINE'] = $externalTask['deadline'];
        }

        // Проверяем статус
        if (isset($externalTask['completed']) && $externalTask['completed']) {
            if ($currentTask['status'] != 5) { // 5 = завершено
                return $this->taskManager->completeTask($bitrixId);
            }
        }

        // Если есть изменения - обновляем
        if (!empty($updates)) {
            return $this->taskManager->updateTask($bitrixId, $updates);
        }

        return true;
    }

    /**
     * Конвертировать задачу Bitrix24 в формат внешней системы
     *
     * @param array $bitrixTask Задача из Bitrix24
     * @return array Задача в формате внешней системы
     */
    private function convertBitrixToExternal(array $bitrixTask)
    {
        return [
            'title' => $bitrixTask['title'] ?? '',
            'description' => $bitrixTask['description'] ?? '',
            'responsible_id' => $bitrixTask['responsibleId'] ?? null,
            'deadline' => $bitrixTask['deadline'] ?? null,
            'completed' => ($bitrixTask['status'] ?? 0) == 5,
            'created_at' => $bitrixTask['createdDate'] ?? null,
            'updated_at' => $bitrixTask['changedDate'] ?? null
        ];
    }

    /**
     * Загрузить связи ID из файла
     *
     * @return array Связи [external_id => bitrix_id]
     */
    private function loadMapping()
    {
        if (!file_exists($this->mappingFile)) {
            return [];
        }

        $json = file_get_contents($this->mappingFile);
        return json_decode($json, true) ?: [];
    }

    /**
     * Сохранить связи ID в файл
     *
     * @param array $mapping Связи для сохранения
     */
    private function saveMapping(array $mapping)
    {
        file_put_contents($this->mappingFile, json_encode($mapping, JSON_PRETTY_PRINT));
    }

    /**
     * Получить время последней синхронизации
     *
     * @return string Дата в формате Y-m-d H:i:s
     */
    private function getLastSyncTime()
    {
        if (!file_exists($this->syncLogFile)) {
            return date('Y-m-d H:i:s', strtotime('-1 day'));
        }

        $log = file_get_contents($this->syncLogFile);
        $lines = explode("\n", $log);

        foreach (array_reverse($lines) as $line) {
            if (preg_match('/\[([^\]]+)\] Last sync time:/', $line, $matches)) {
                return $matches[1];
            }
        }

        return date('Y-m-d H:i:s', strtotime('-1 day'));
    }

    /**
     * Установить время последней синхронизации
     *
     * @param string $time Дата в формате Y-m-d H:i:s
     */
    private function setLastSyncTime($time)
    {
        $this->log("Last sync time: $time");
    }

    /**
     * Записать сообщение в лог
     *
     * @param string $message Сообщение
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->syncLogFile, $logMessage, FILE_APPEND);
    }

    /**
     * Получить статистику синхронизации
     *
     * @return array Статистика
     */
    public function getStatistics()
    {
        $mapping = $this->loadMapping();

        return [
            'total_synced_tasks' => count($mapping),
            'last_sync_time' => $this->getLastSyncTime(),
            'mapping' => $mapping
        ];
    }
}
