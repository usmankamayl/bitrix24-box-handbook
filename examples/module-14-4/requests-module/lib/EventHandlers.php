<?php

namespace MyCompany\Requests;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

/**
 * Обработчики событий для заявок
 */
class EventHandlers
{
    /**
     * Перед добавлением заявки
     *
     * @param Event $event
     * @return EventResult
     */
    public static function onBeforeItemAdd(Event $event): EventResult
    {
        $fields = $event->getParameter('fields');
        $entityTypeId = $event->getParameter('entityTypeId');

        // Проверить, что это наш тип
        $ourTypeId = (int)Option::get('mycompany.requests', 'entity_type_id');
        if ($entityTypeId !== $ourTypeId) {
            return new EventResult(EventResult::SUCCESS);
        }

        // Валидация: проверить обязательные поля
        if (empty($fields['TITLE'])) {
            return new EventResult(
                EventResult::ERROR,
                ['error' => 'Название заявки обязательно для заполнения']
            );
        }

        if (empty($fields['UF_REQUEST_TYPE'])) {
            return new EventResult(
                EventResult::ERROR,
                ['error' => 'Укажите тип заявки']
            );
        }

        if (empty($fields['UF_DESCRIPTION'])) {
            return new EventResult(
                EventResult::ERROR,
                ['error' => 'Описание проблемы обязательно для заполнения']
            );
        }

        // Установить значения по умолчанию
        if (empty($fields['STAGE_ID'])) {
            $fields['STAGE_ID'] = 'NEW';
        }

        if (empty($fields['ASSIGNED_BY_ID'])) {
            global $USER;
            $fields['ASSIGNED_BY_ID'] = $USER->GetID();
        }

        // Автоматически установить дедлайн в зависимости от приоритета
        if (empty($fields['UF_DEADLINE']) && !empty($fields['UF_PRIORITY'])) {
            $deadlineHours = self::getDeadlineByPriority($fields['UF_PRIORITY']);
            $fields['UF_DEADLINE'] = date('Y-m-d H:i:s', strtotime("+{$deadlineHours} hours"));
        }

        return new EventResult(
            EventResult::SUCCESS,
            ['fields' => $fields]
        );
    }

    /**
     * После добавления заявки
     *
     * @param Event $event
     */
    public static function onAfterItemAdd(Event $event): void
    {
        $id = $event->getParameter('id');
        $fields = $event->getParameter('fields');
        $entityTypeId = $event->getParameter('entityTypeId');

        // Проверить, что это наш тип
        $ourTypeId = (int)Option::get('mycompany.requests', 'entity_type_id');
        if ($entityTypeId !== $ourTypeId) {
            return;
        }

        // Отправить уведомление ответственному
        if (!empty($fields['ASSIGNED_BY_ID'])) {
            self::sendNotification(
                $fields['ASSIGNED_BY_ID'],
                "Создана новая заявка #{$id}: {$fields['TITLE']}"
            );
        }

        // Если приоритет критический - отправить уведомление всем администраторам
        if (self::isCriticalPriority($fields['UF_PRIORITY'] ?? null)) {
            self::notifyAdmins(
                "КРИТИЧЕСКАЯ заявка #{$id}: {$fields['TITLE']}"
            );
        }

        // Записать в лог
        self::logAction('create', $id, $fields);
    }

    /**
     * Перед обновлением заявки
     *
     * @param Event $event
     * @return EventResult
     */
    public static function onBeforeItemUpdate(Event $event): EventResult
    {
        $fields = $event->getParameter('fields');
        $entityTypeId = $event->getParameter('entityTypeId');
        $id = $event->getParameter('id');

        // Проверить, что это наш тип
        $ourTypeId = (int)Option::get('mycompany.requests', 'entity_type_id');
        if ($entityTypeId !== $ourTypeId) {
            return new EventResult(EventResult::SUCCESS);
        }

        // При переводе в "Решена" проверить комментарий исполнителя
        if (isset($fields['STAGE_ID']) && $fields['STAGE_ID'] === 'RESOLVED') {
            if (empty($fields['UF_EXECUTOR_COMMENT'])) {
                return new EventResult(
                    EventResult::ERROR,
                    ['error' => 'При решении заявки необходимо указать комментарий']
                );
            }
        }

        // При назначении исполнителя изменить стадию
        if (isset($fields['UF_EXECUTOR']) && !empty($fields['UF_EXECUTOR'])) {
            if (empty($fields['STAGE_ID'])) {
                $fields['STAGE_ID'] = 'IN_WORK';
            }
        }

        return new EventResult(
            EventResult::SUCCESS,
            ['fields' => $fields]
        );
    }

    /**
     * После обновления заявки
     *
     * @param Event $event
     */
    public static function onAfterItemUpdate(Event $event): void
    {
        $id = $event->getParameter('id');
        $fields = $event->getParameter('fields');
        $entityTypeId = $event->getParameter('entityTypeId');

        // Проверить, что это наш тип
        $ourTypeId = (int)Option::get('mycompany.requests', 'entity_type_id');
        if ($entityTypeId !== $ourTypeId) {
            return;
        }

        // Если изменилась стадия
        if (isset($fields['STAGE_ID'])) {
            self::onStageChanged($id, $fields['STAGE_ID'], $fields);
        }

        // Если назначен исполнитель
        if (isset($fields['UF_EXECUTOR'])) {
            self::sendNotification(
                $fields['UF_EXECUTOR'],
                "Вам назначена заявка #{$id}"
            );
        }

        // Записать в лог
        self::logAction('update', $id, $fields);
    }

    /**
     * Обработка изменения стадии
     *
     * @param int $id ID заявки
     * @param string $stageId ID стадии
     * @param array $fields Поля заявки
     */
    private static function onStageChanged(int $id, string $stageId, array $fields): void
    {
        Loader::includeModule('crm');

        $service = new RequestService();
        $item = $service->getRequest($id);

        if (!$item) {
            return;
        }

        // Уведомить ответственного
        $assignedId = $item->getAssignedById();

        switch ($stageId) {
            case 'IN_WORK':
                self::sendNotification(
                    $assignedId,
                    "Заявка #{$id} взята в работу"
                );
                break;

            case 'PENDING':
                self::sendNotification(
                    $assignedId,
                    "Заявка #{$id} ожидает вашего ответа"
                );
                break;

            case 'RESOLVED':
                self::sendNotification(
                    $assignedId,
                    "Заявка #{$id} решена: {$fields['UF_EXECUTOR_COMMENT'] ?? ''}"
                );
                break;

            case 'REJECTED':
                self::sendNotification(
                    $assignedId,
                    "Заявка #{$id} отклонена: {$fields['UF_EXECUTOR_COMMENT'] ?? ''}"
                );
                break;
        }
    }

    /**
     * Получить срок выполнения в часах по приоритету
     *
     * @param mixed $priorityId ID приоритета
     * @return int Часы
     */
    private static function getDeadlineByPriority($priorityId): int
    {
        // Упрощённая логика - в реальности нужно получить XML_ID из enumeration
        // и проверить его значение (LOW, MEDIUM, HIGH, CRITICAL)

        // По умолчанию 48 часов (средний приоритет)
        $deadlines = [
            'LOW' => 72,       // 3 дня
            'MEDIUM' => 48,    // 2 дня
            'HIGH' => 24,      // 1 день
            'CRITICAL' => 4,   // 4 часа
        ];

        return $deadlines['MEDIUM'] ?? 48;
    }

    /**
     * Проверить, является ли приоритет критическим
     *
     * @param mixed $priorityId ID приоритета
     * @return bool
     */
    private static function isCriticalPriority($priorityId): bool
    {
        if (empty($priorityId)) {
            return false;
        }

        // В реальности нужно получить значение из enumeration и проверить XML_ID
        return false; // Упрощённая проверка
    }

    /**
     * Отправить уведомление пользователю
     *
     * @param int $userId ID пользователя
     * @param string $message Сообщение
     */
    private static function sendNotification(int $userId, string $message): void
    {
        if (!Loader::includeModule('im')) {
            return;
        }

        // Отправить через модуль IM
        \CIMNotify::Add([
            'TO_USER_ID' => $userId,
            'FROM_USER_ID' => 0,
            'NOTIFY_TYPE' => IM_NOTIFY_SYSTEM,
            'NOTIFY_MODULE' => 'mycompany.requests',
            'NOTIFY_TAG' => 'REQUEST_NOTIFICATION',
            'NOTIFY_MESSAGE' => $message,
        ]);
    }

    /**
     * Уведомить всех администраторов
     *
     * @param string $message Сообщение
     */
    private static function notifyAdmins(string $message): void
    {
        if (!Loader::includeModule('main')) {
            return;
        }

        // Получить всех администраторов
        $rsUsers = \CUser::GetList(
            'id',
            'asc',
            ['GROUPS_ID' => [1]], // Группа администраторов
            ['SELECT' => ['ID']]
        );

        while ($user = $rsUsers->Fetch()) {
            self::sendNotification((int)$user['ID'], $message);
        }
    }

    /**
     * Записать действие в лог
     *
     * @param string $action Действие (create, update, delete)
     * @param int $id ID заявки
     * @param array $fields Поля
     */
    private static function logAction(string $action, int $id, array $fields): void
    {
        // Простейший лог в файл
        $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/logs/requests.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        global $USER;
        $userId = $USER ? $USER->GetID() : 0;

        $logEntry = sprintf(
            "[%s] User #%d %s request #%d: %s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $action,
            $id,
            json_encode($fields, JSON_UNESCAPED_UNICODE)
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
