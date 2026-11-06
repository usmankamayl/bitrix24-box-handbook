<?php
/**
 * Класс для работы с проектами через Factory API
 *
 * Демонстрирует использование:
 * - Container и Factory
 * - Item API
 * - Operation API (Add/Update/Delete)
 * - Работа с полями и привязками
 */

namespace MyCompany\ProjectManager;

use Bitrix\Main\Loader;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Factory;
use Bitrix\Crm\Item;

class ProjectService
{
    /** @var int ID типа "Проекты" (замените на свой) */
    private const ENTITY_TYPE_ID = 128; // Измените на актуальный ID вашего типа

    /** @var Factory */
    private $factory;

    /**
     * Конструктор
     * @throws \Exception
     */
    public function __construct()
    {
        if (!Loader::includeModule('crm')) {
            throw new \Exception('Модуль CRM не установлен');
        }

        $this->factory = Container::getInstance()->getFactory(self::ENTITY_TYPE_ID);

        if ($this->factory === null) {
            throw new \Exception('Фабрика для типа ' . self::ENTITY_TYPE_ID . ' не найдена');
        }
    }

    /**
     * Создать новый проект
     *
     * @param array $data Данные проекта
     * @return Result
     */
    public function createProject(array $data): Result
    {
        $result = new Result();

        try {
            // Создать новый Item
            $item = $this->factory->createItem();

            // Установить обязательные поля
            $item->setTitle($data['TITLE'] ?? 'Новый проект');

            // Установить стадию (по умолчанию NEW)
            $item->setStageId($data['STAGE_ID'] ?? 'NEW');

            // Установить ответственного
            if (!empty($data['ASSIGNED_BY_ID'])) {
                $item->setAssignedById($data['ASSIGNED_BY_ID']);
            } else {
                global $USER;
                $item->setAssignedById($USER->GetID());
            }

            // Установить даты
            if (!empty($data['BEGIN_DATE'])) {
                $item->setBegindate(new \Bitrix\Main\Type\DateTime($data['BEGIN_DATE']));
            }

            if (!empty($data['CLOSE_DATE'])) {
                $item->setClosedate(new \Bitrix\Main\Type\DateTime($data['CLOSE_DATE']));
            }

            // Установить пользовательские поля
            if (!empty($data['UF_PROJECT_BUDGET'])) {
                $item->set('UF_PROJECT_BUDGET', (float)$data['UF_PROJECT_BUDGET']);
            }

            if (!empty($data['UF_PRIORITY'])) {
                $item->set('UF_PRIORITY', $data['UF_PRIORITY']);
            }

            if (!empty($data['UF_PROJECT_MANAGER'])) {
                $item->set('UF_PROJECT_MANAGER', $data['UF_PROJECT_MANAGER']);
            }

            if (!empty($data['UF_PROJECT_TEAM'])) {
                $item->set('UF_PROJECT_TEAM', $data['UF_PROJECT_TEAM']);
            }

            if (!empty($data['UF_PROGRESS'])) {
                $item->set('UF_PROGRESS', (int)$data['UF_PROGRESS']);
            }

            if (!empty($data['UF_PROJECT_TYPE'])) {
                $item->set('UF_PROJECT_TYPE', $data['UF_PROJECT_TYPE']);
            }

            if (!empty($data['UF_DESCRIPTION'])) {
                $item->set('UF_DESCRIPTION', $data['UF_DESCRIPTION']);
            }

            // Установить контакты
            if (!empty($data['CONTACT_IDS']) && is_array($data['CONTACT_IDS'])) {
                $item->setContactIds($data['CONTACT_IDS']);
            }

            // Установить компанию
            if (!empty($data['COMPANY_ID'])) {
                $item->setCompanyId($data['COMPANY_ID']);
            }

            // Использовать Operation API для безопасного добавления
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
     * Обновить проект
     *
     * @param int $id ID проекта
     * @param array $data Данные для обновления
     * @return Result
     */
    public function updateProject(int $id, array $data): Result
    {
        $result = new Result();

        try {
            // Получить существующий Item
            $item = $this->factory->getItem($id);

            if ($item === null) {
                $result->addError(new Error("Проект #{$id} не найден"));
                return $result;
            }

            // Обновить поля (только те, что переданы)
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
            if (isset($data['UF_PROJECT_BUDGET'])) {
                $item->set('UF_PROJECT_BUDGET', (float)$data['UF_PROJECT_BUDGET']);
            }

            if (isset($data['UF_PRIORITY'])) {
                $item->set('UF_PRIORITY', $data['UF_PRIORITY']);
            }

            if (isset($data['UF_PROJECT_MANAGER'])) {
                $item->set('UF_PROJECT_MANAGER', $data['UF_PROJECT_MANAGER']);
            }

            if (isset($data['UF_PROJECT_TEAM'])) {
                $item->set('UF_PROJECT_TEAM', $data['UF_PROJECT_TEAM']);
            }

            if (isset($data['UF_PROGRESS'])) {
                $item->set('UF_PROGRESS', (int)$data['UF_PROGRESS']);
            }

            if (isset($data['UF_PROJECT_TYPE'])) {
                $item->set('UF_PROJECT_TYPE', $data['UF_PROJECT_TYPE']);
            }

            if (isset($data['UF_DESCRIPTION'])) {
                $item->set('UF_DESCRIPTION', $data['UF_DESCRIPTION']);
            }

            // Обновить контакты
            if (isset($data['CONTACT_IDS'])) {
                $item->setContactIds(is_array($data['CONTACT_IDS']) ? $data['CONTACT_IDS'] : []);
            }

            // Обновить компанию
            if (isset($data['COMPANY_ID'])) {
                $item->setCompanyId($data['COMPANY_ID']);
            }

            // Использовать Operation API для безопасного обновления
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
     * Удалить проект
     *
     * @param int $id ID проекта
     * @return Result
     */
    public function deleteProject(int $id): Result
    {
        $result = new Result();

        try {
            $item = $this->factory->getItem($id);

            if ($item === null) {
                $result->addError(new Error("Проект #{$id} не найден"));
                return $result;
            }

            // Использовать Operation API для безопасного удаления
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
     * Получить проект по ID
     *
     * @param int $id ID проекта
     * @return Item|null
     */
    public function getProject(int $id): ?Item
    {
        return $this->factory->getItem($id);
    }

    /**
     * Получить список проектов
     *
     * @param array $filter Фильтр
     * @param array $order Сортировка
     * @param int $limit Лимит
     * @param int $offset Смещение
     * @return array
     */
    public function getProjects(
        array $filter = [],
        array $order = ['ID' => 'DESC'],
        int $limit = 50,
        int $offset = 0
    ): array {
        $items = $this->factory->getItems([
            'filter' => $filter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $items;
    }

    /**
     * Получить количество проектов
     *
     * @param array $filter Фильтр
     * @return int
     */
    public function getProjectsCount(array $filter = []): int
    {
        return $this->factory->getItemsCount($filter);
    }

    /**
     * Изменить стадию проекта
     *
     * @param int $id ID проекта
     * @param string $stageId ID стадии
     * @return Result
     */
    public function changeStage(int $id, string $stageId): Result
    {
        return $this->updateProject($id, ['STAGE_ID' => $stageId]);
    }

    /**
     * Обновить прогресс проекта
     *
     * @param int $id ID проекта
     * @param int $progress Прогресс (0-100)
     * @return Result
     */
    public function updateProgress(int $id, int $progress): Result
    {
        $progress = max(0, min(100, $progress)); // Ограничить 0-100
        return $this->updateProject($id, ['UF_PROGRESS' => $progress]);
    }

    /**
     * Назначить менеджера проекта
     *
     * @param int $id ID проекта
     * @param int $managerId ID менеджера
     * @return Result
     */
    public function assignManager(int $id, int $managerId): Result
    {
        return $this->updateProject($id, [
            'UF_PROJECT_MANAGER' => $managerId,
            'ASSIGNED_BY_ID' => $managerId, // Также назначить ответственным
        ]);
    }

    /**
     * Добавить членов команды
     *
     * @param int $id ID проекта
     * @param array $teamIds ID сотрудников
     * @return Result
     */
    public function addTeamMembers(int $id, array $teamIds): Result
    {
        $item = $this->factory->getItem($id);

        if ($item === null) {
            $result = new Result();
            $result->addError(new Error("Проект #{$id} не найден"));
            return $result;
        }

        // Получить текущую команду
        $currentTeam = $item->get('UF_PROJECT_TEAM') ?? [];
        if (!is_array($currentTeam)) {
            $currentTeam = [$currentTeam];
        }

        // Объединить с новыми участниками
        $newTeam = array_unique(array_merge($currentTeam, $teamIds));

        return $this->updateProject($id, ['UF_PROJECT_TEAM' => $newTeam]);
    }

    /**
     * Получить проекты по менеджеру
     *
     * @param int $managerId ID менеджера
     * @return array
     */
    public function getProjectsByManager(int $managerId): array
    {
        return $this->getProjects([
            '=UF_PROJECT_MANAGER' => $managerId,
        ]);
    }

    /**
     * Получить активные проекты (не завершённые и не отменённые)
     *
     * @return array
     */
    public function getActiveProjects(): array
    {
        return $this->getProjects([
            '!@STAGE_SEMANTIC_ID' => ['S', 'F'], // Не SUCCESS и не FAILURE
        ]);
    }

    /**
     * Получить проекты с высоким приоритетом
     *
     * @return array
     */
    public function getHighPriorityProjects(): array
    {
        // Нужно получить ID значения "Высокий" из enumeration
        $fields = $this->factory->getFieldsInfo();
        $priorityField = $fields['UF_PRIORITY'] ?? null;

        if ($priorityField && isset($priorityField['ITEMS'])) {
            $highPriorityId = null;
            foreach ($priorityField['ITEMS'] as $item) {
                if ($item['VALUE'] === 'Высокий' || $item['XML_ID'] === 'HIGH') {
                    $highPriorityId = $item['ID'];
                    break;
                }
            }

            if ($highPriorityId) {
                return $this->getProjects([
                    '=UF_PRIORITY' => $highPriorityId,
                ]);
            }
        }

        return [];
    }

    /**
     * Получить статистику по проектам
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = [
            'total' => $this->getProjectsCount(),
            'active' => $this->getProjectsCount([
                '!@STAGE_SEMANTIC_ID' => ['S', 'F'],
            ]),
            'completed' => $this->getProjectsCount([
                '@STAGE_SEMANTIC_ID' => 'S',
            ]),
            'cancelled' => $this->getProjectsCount([
                '@STAGE_SEMANTIC_ID' => 'F',
            ]),
            'by_stage' => [],
        ];

        // Подсчёт по стадиям
        $allProjects = $this->getProjects();
        foreach ($allProjects as $project) {
            $stageId = $project->getStageId();
            if (!isset($stats['by_stage'][$stageId])) {
                $stats['by_stage'][$stageId] = 0;
            }
            $stats['by_stage'][$stageId]++;
        }

        return $stats;
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
}
