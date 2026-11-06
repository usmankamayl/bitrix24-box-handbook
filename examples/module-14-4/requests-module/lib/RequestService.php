<?php

namespace MyCompany\Requests;

use Bitrix\Main\Loader;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Config\Option;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Factory;
use Bitrix\Crm\Item;

/**
 * Сервис для работы с заявками
 */
class RequestService
{
    /** @var string ID модуля */
    private const MODULE_ID = 'mycompany.requests';

    /** @var Factory */
    private $factory;

    /** @var int */
    private $entityTypeId;

    /**
     * Конструктор
     * @throws \Exception
     */
    public function __construct()
    {
        if (!Loader::includeModule('crm')) {
            throw new \Exception('Модуль CRM не установлен');
        }

        // Получить ID типа из настроек модуля
        $this->entityTypeId = (int)Option::get(self::MODULE_ID, 'entity_type_id');

        if ($this->entityTypeId <= 0) {
            throw new \Exception('Модуль не установлен или тип не создан');
        }

        $this->factory = Container::getInstance()->getFactory($this->entityTypeId);

        if ($this->factory === null) {
            throw new \Exception('Фабрика для типа не найдена');
        }
    }

    /**
     * Создать заявку
     *
     * @param array $data Данные заявки
     * @return Result
     */
    public function createRequest(array $data): Result
    {
        $result = new Result();

        try {
            $item = $this->factory->createItem();

            // Обязательные поля
            $item->setTitle($data['TITLE'] ?? 'Новая заявка');
            $item->setStageId($data['STAGE_ID'] ?? 'NEW');

            // Ответственный (по умолчанию текущий пользователь)
            if (!empty($data['ASSIGNED_BY_ID'])) {
                $item->setAssignedById($data['ASSIGNED_BY_ID']);
            } else {
                global $USER;
                $item->setAssignedById($USER->GetID());
            }

            // Даты
            if (!empty($data['BEGIN_DATE'])) {
                $item->setBegindate(new \Bitrix\Main\Type\DateTime($data['BEGIN_DATE']));
            }

            if (!empty($data['CLOSE_DATE'])) {
                $item->setClosedate(new \Bitrix\Main\Type\DateTime($data['CLOSE_DATE']));
            }

            // Пользовательские поля
            $this->setUserFields($item, $data);

            // Привязки
            if (!empty($data['CONTACT_IDS']) && is_array($data['CONTACT_IDS'])) {
                $item->setContactIds($data['CONTACT_IDS']);
            }

            if (!empty($data['COMPANY_ID'])) {
                $item->setCompanyId($data['COMPANY_ID']);
            }

            // Сохранить через Operation API
            $addOperation = $this->factory->getAddOperation($item);
            $operationResult = $addOperation->launch();

            if ($operationResult->isSuccess()) {
                $result->setData(['ID' => $operationResult->getId()]);
            } else {
                foreach ($operationResult->getErrors() as $error) {
                    $result->addError($error);
                }
            }
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }

        return $result;
    }

    /**
     * Обновить заявку
     *
     * @param int $id ID заявки
     * @param array $data Данные для обновления
     * @return Result
     */
    public function updateRequest(int $id, array $data): Result
    {
        $result = new Result();

        try {
            $item = $this->factory->getItem($id);

            if ($item === null) {
                $result->addError(new Error("Заявка #{$id} не найдена"));
                return $result;
            }

            // Обновить основные поля
            if (isset($data['TITLE'])) {
                $item->setTitle($data['TITLE']);
            }

            if (isset($data['STAGE_ID'])) {
                $item->setStageId($data['STAGE_ID']);
            }

            if (isset($data['ASSIGNED_BY_ID'])) {
                $item->setAssignedById($data['ASSIGNED_BY_ID']);
            }

            if (isset($data['BEGIN_DATE'])) {
                $item->setBegindate(
                    $data['BEGIN_DATE'] ? new \Bitrix\Main\Type\DateTime($data['BEGIN_DATE']) : null
                );
            }

            if (isset($data['CLOSE_DATE'])) {
                $item->setClosedate(
                    $data['CLOSE_DATE'] ? new \Bitrix\Main\Type\DateTime($data['CLOSE_DATE']) : null
                );
            }

            // Обновить пользовательские поля
            $this->setUserFields($item, $data);

            // Обновить привязки
            if (isset($data['CONTACT_IDS'])) {
                $item->setContactIds(is_array($data['CONTACT_IDS']) ? $data['CONTACT_IDS'] : []);
            }

            if (isset($data['COMPANY_ID'])) {
                $item->setCompanyId($data['COMPANY_ID']);
            }

            // Сохранить через Operation API
            $updateOperation = $this->factory->getUpdateOperation($item);
            $operationResult = $updateOperation->launch();

            if (!$operationResult->isSuccess()) {
                foreach ($operationResult->getErrors() as $error) {
                    $result->addError($error);
                }
            }
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }

        return $result;
    }

    /**
     * Удалить заявку
     *
     * @param int $id ID заявки
     * @return Result
     */
    public function deleteRequest(int $id): Result
    {
        $result = new Result();

        try {
            $item = $this->factory->getItem($id);

            if ($item === null) {
                $result->addError(new Error("Заявка #{$id} не найдена"));
                return $result;
            }

            $deleteOperation = $this->factory->getDeleteOperation($item);
            $operationResult = $deleteOperation->launch();

            if (!$operationResult->isSuccess()) {
                foreach ($operationResult->getErrors() as $error) {
                    $result->addError($error);
                }
            }
        } catch (\Exception $e) {
            $result->addError(new Error($e->getMessage()));
        }

        return $result;
    }

    /**
     * Получить заявку по ID
     *
     * @param int $id ID заявки
     * @return Item|null
     */
    public function getRequest(int $id): ?Item
    {
        return $this->factory->getItem($id);
    }

    /**
     * Получить список заявок
     *
     * @param array $filter Фильтр
     * @param array $order Сортировка
     * @param int $limit Лимит
     * @param int $offset Смещение
     * @return array
     */
    public function getRequests(
        array $filter = [],
        array $order = ['ID' => 'DESC'],
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->factory->getItems([
            'filter' => $filter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Получить количество заявок
     *
     * @param array $filter Фильтр
     * @return int
     */
    public function getRequestsCount(array $filter = []): int
    {
        return $this->factory->getItemsCount($filter);
    }

    /**
     * Изменить статус заявки
     *
     * @param int $id ID заявки
     * @param string $stageId ID стадии
     * @return Result
     */
    public function changeStatus(int $id, string $stageId): Result
    {
        return $this->updateRequest($id, ['STAGE_ID' => $stageId]);
    }

    /**
     * Назначить исполнителя
     *
     * @param int $id ID заявки
     * @param int $executorId ID исполнителя
     * @return Result
     */
    public function assignExecutor(int $id, int $executorId): Result
    {
        return $this->updateRequest($id, [
            'UF_EXECUTOR' => $executorId,
            'STAGE_ID' => 'IN_WORK',
        ]);
    }

    /**
     * Решить заявку
     *
     * @param int $id ID заявки
     * @param string $comment Комментарий исполнителя
     * @return Result
     */
    public function resolveRequest(int $id, string $comment = ''): Result
    {
        return $this->updateRequest($id, [
            'STAGE_ID' => 'RESOLVED',
            'UF_EXECUTOR_COMMENT' => $comment,
            'CLOSE_DATE' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Отклонить заявку
     *
     * @param int $id ID заявки
     * @param string $reason Причина отклонения
     * @return Result
     */
    public function rejectRequest(int $id, string $reason = ''): Result
    {
        return $this->updateRequest($id, [
            'STAGE_ID' => 'REJECTED',
            'UF_EXECUTOR_COMMENT' => $reason,
            'CLOSE_DATE' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Получить новые заявки
     *
     * @return array
     */
    public function getNewRequests(): array
    {
        return $this->getRequests(['=STAGE_ID' => 'NEW']);
    }

    /**
     * Получить заявки в работе
     *
     * @return array
     */
    public function getRequestsInWork(): array
    {
        return $this->getRequests(['=STAGE_ID' => 'IN_WORK']);
    }

    /**
     * Получить заявки исполнителя
     *
     * @param int $executorId ID исполнителя
     * @return array
     */
    public function getRequestsByExecutor(int $executorId): array
    {
        return $this->getRequests([
            '=UF_EXECUTOR' => $executorId,
            '!@STAGE_SEMANTIC_ID' => ['S', 'F'], // Активные
        ]);
    }

    /**
     * Получить просроченные заявки
     *
     * @return array
     */
    public function getOverdueRequests(): array
    {
        return $this->getRequests([
            '<UF_DEADLINE' => date('Y-m-d H:i:s'),
            '!@STAGE_SEMANTIC_ID' => ['S', 'F'], // Не завершённые
        ]);
    }

    /**
     * Получить статистику по заявкам
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->getRequestsCount(),
            'new' => $this->getRequestsCount(['=STAGE_ID' => 'NEW']),
            'in_work' => $this->getRequestsCount(['=STAGE_ID' => 'IN_WORK']),
            'pending' => $this->getRequestsCount(['=STAGE_ID' => 'PENDING']),
            'resolved' => $this->getRequestsCount(['=STAGE_ID' => 'RESOLVED']),
            'rejected' => $this->getRequestsCount(['=STAGE_ID' => 'REJECTED']),
            'overdue' => $this->getRequestsCount([
                '<UF_DEADLINE' => date('Y-m-d H:i:s'),
                '!@STAGE_SEMANTIC_ID' => ['S', 'F'],
            ]),
        ];
    }

    /**
     * Получить Factory
     *
     * @return Factory
     */
    public function getFactory(): Factory
    {
        return $this->factory;
    }

    /**
     * Получить Entity Type ID
     *
     * @return int
     */
    public function getEntityTypeId(): int
    {
        return $this->entityTypeId;
    }

    /**
     * Установить пользовательские поля
     *
     * @param Item $item
     * @param array $data
     */
    private function setUserFields(Item $item, array $data): void
    {
        $userFields = [
            'UF_REQUEST_TYPE',
            'UF_PRIORITY',
            'UF_DESCRIPTION',
            'UF_EXECUTOR',
            'UF_EXECUTOR_COMMENT',
            'UF_FILES',
            'UF_DEADLINE',
        ];

        foreach ($userFields as $fieldName) {
            if (isset($data[$fieldName])) {
                $item->set($fieldName, $data[$fieldName]);
            }
        }
    }
}
