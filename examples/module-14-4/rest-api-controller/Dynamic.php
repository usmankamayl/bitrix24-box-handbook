<?php

namespace MyCompany\RestApi\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Loader;
use Bitrix\Main\Error;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Factory;
use Bitrix\Crm\Item;

/**
 * REST API контроллер для работы с динамическим типом через Factory API
 *
 * Endpoint: /rest/mycompany.restapi.dynamic.{method}
 *
 * Примеры использования:
 * - /rest/mycompany.restapi.dynamic.list?entityTypeId=128
 * - /rest/mycompany.restapi.dynamic.get?entityTypeId=128&id=1
 * - /rest/mycompany.restapi.dynamic.add
 * - /rest/mycompany.restapi.dynamic.update
 * - /rest/mycompany.restapi.dynamic.delete
 */
class Dynamic extends Controller
{
    /**
     * Настройка фильтров для контроллера
     *
     * @return array
     */
    public function configureActions(): array
    {
        return [
            'list' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET, ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
            'get' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET, ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
            'add' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
            'update' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
            'delete' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                ],
            ],
            'fields' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET]),
                ],
            ],
            'stages' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET]),
                ],
            ],
        ];
    }

    /**
     * Получить список элементов
     *
     * @param int $entityTypeId ID типа сущности
     * @param array $filter Фильтр
     * @param array $select Выбираемые поля
     * @param array $order Сортировка
     * @param int $limit Лимит
     * @param int $offset Смещение
     * @return array
     */
    public function listAction(
        int $entityTypeId,
        array $filter = [],
        array $select = [],
        array $order = ['ID' => 'DESC'],
        int $limit = 50,
        int $offset = 0
    ): array {
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error('Модуль CRM не установлен'));
            return [];
        }

        $factory = $this->getFactory($entityTypeId);
        if ($factory === null) {
            $this->addError(new Error("Фабрика для типа {$entityTypeId} не найдена"));
            return [];
        }

        // Получить элементы
        $items = $factory->getItems([
            'select' => !empty($select) ? $select : ['*', 'UF_*'],
            'filter' => $filter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        // Получить общее количество
        $total = $factory->getItemsCount($filter);

        // Сериализовать элементы
        $result = [];
        foreach ($items as $item) {
            $result[] = $this->serializeItem($item);
        }

        return [
            'items' => $result,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Получить элемент по ID
     *
     * @param int $entityTypeId ID типа сущности
     * @param int $id ID элемента
     * @return array|null
     */
    public function getAction(int $entityTypeId, int $id): ?array
    {
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error('Модуль CRM не установлен'));
            return null;
        }

        $factory = $this->getFactory($entityTypeId);
        if ($factory === null) {
            $this->addError(new Error("Фабрика для типа {$entityTypeId} не найдена"));
            return null;
        }

        $item = $factory->getItem($id);
        if ($item === null) {
            $this->addError(new Error("Элемент #{$id} не найден"));
            return null;
        }

        return [
            'item' => $this->serializeItem($item),
        ];
    }

    /**
     * Добавить элемент
     *
     * @param int $entityTypeId ID типа сущности
     * @param array $fields Поля элемента
     * @return array|null
     */
    public function addAction(int $entityTypeId, array $fields): ?array
    {
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error('Модуль CRM не установлен'));
            return null;
        }

        $factory = $this->getFactory($entityTypeId);
        if ($factory === null) {
            $this->addError(new Error("Фабрика для типа {$entityTypeId} не найдена"));
            return null;
        }

        // Создать новый элемент
        $item = $factory->createItem();

        // Заполнить поля
        $this->fillItem($item, $fields);

        // Сохранить через Operation API
        $addOperation = $factory->getAddOperation($item);
        $result = $addOperation->launch();

        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError($error);
            }
            return null;
        }

        $itemId = $result->getId();

        return [
            'item' => $this->serializeItem($factory->getItem($itemId)),
        ];
    }

    /**
     * Обновить элемент
     *
     * @param int $entityTypeId ID типа сущности
     * @param int $id ID элемента
     * @param array $fields Поля для обновления
     * @return array|null
     */
    public function updateAction(int $entityTypeId, int $id, array $fields): ?array
    {
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error('Модуль CRM не установлен'));
            return null;
        }

        $factory = $this->getFactory($entityTypeId);
        if ($factory === null) {
            $this->addError(new Error("Фабрика для типа {$entityTypeId} не найдена"));
            return null;
        }

        $item = $factory->getItem($id);
        if ($item === null) {
            $this->addError(new Error("Элемент #{$id} не найден"));
            return null;
        }

        // Обновить поля
        $this->fillItem($item, $fields);

        // Сохранить через Operation API
        $updateOperation = $factory->getUpdateOperation($item);
        $result = $updateOperation->launch();

        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError($error);
            }
            return null;
        }

        return [
            'item' => $this->serializeItem($factory->getItem($id)),
        ];
    }

    /**
     * Удалить элемент
     *
     * @param int $entityTypeId ID типа сущности
     * @param int $id ID элемента
     * @return array|null
     */
    public function deleteAction(int $entityTypeId, int $id): ?array
    {
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error('Модуль CRM не установлен'));
            return null;
        }

        $factory = $this->getFactory($entityTypeId);
        if ($factory === null) {
            $this->addError(new Error("Фабрика для типа {$entityTypeId} не найдена"));
            return null;
        }

        $item = $factory->getItem($id);
        if ($item === null) {
            $this->addError(new Error("Элемент #{$id} не найден"));
            return null;
        }

        // Удалить через Operation API
        $deleteOperation = $factory->getDeleteOperation($item);
        $result = $deleteOperation->launch();

        if (!$result->isSuccess()) {
            foreach ($result->getErrors() as $error) {
                $this->addError($error);
            }
            return null;
        }

        return [
            'success' => true,
            'id' => $id,
        ];
    }

    /**
     * Получить описание полей
     *
     * @param int $entityTypeId ID типа сущности
     * @return array|null
     */
    public function fieldsAction(int $entityTypeId): ?array
    {
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error('Модуль CRM не установлен'));
            return null;
        }

        $factory = $this->getFactory($entityTypeId);
        if ($factory === null) {
            $this->addError(new Error("Фабрика для типа {$entityTypeId} не найдена"));
            return null;
        }

        $fieldsInfo = $factory->getFieldsInfo();

        // Форматировать информацию о полях
        $fields = [];
        foreach ($fieldsInfo as $fieldName => $fieldInfo) {
            $fields[$fieldName] = [
                'title' => $fieldInfo['TITLE'] ?? $fieldName,
                'type' => $fieldInfo['TYPE'] ?? 'string',
                'required' => !empty($fieldInfo['REQUIRED']),
                'multiple' => !empty($fieldInfo['MULTIPLE']),
                'items' => $fieldInfo['ITEMS'] ?? null,
            ];
        }

        return [
            'fields' => $fields,
        ];
    }

    /**
     * Получить список стадий
     *
     * @param int $entityTypeId ID типа сущности
     * @param int $categoryId ID категории (по умолчанию 0)
     * @return array|null
     */
    public function stagesAction(int $entityTypeId, int $categoryId = 0): ?array
    {
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error('Модуль CRM не установлен'));
            return null;
        }

        $factory = $this->getFactory($entityTypeId);
        if ($factory === null) {
            $this->addError(new Error("Фабрика для типа {$entityTypeId} не найдена"));
            return null;
        }

        $stages = $factory->getStages($categoryId);

        // Сериализовать стадии
        $result = [];
        foreach ($stages as $stage) {
            $result[] = [
                'STATUS_ID' => $stage->getStatusId(),
                'NAME' => $stage->getName(),
                'SORT' => $stage->getSort(),
                'COLOR' => $stage->getColor(),
                'SEMANTICS' => $stage->getSemantics(),
            ];
        }

        return [
            'stages' => $result,
        ];
    }

    /**
     * Получить фабрику по ID типа
     *
     * @param int $entityTypeId
     * @return Factory|null
     */
    private function getFactory(int $entityTypeId): ?Factory
    {
        return Container::getInstance()->getFactory($entityTypeId);
    }

    /**
     * Сериализовать Item в массив
     *
     * @param Item $item
     * @return array
     */
    private function serializeItem(Item $item): array
    {
        $data = [
            'ID' => $item->getId(),
            'TITLE' => $item->getTitle(),
            'STAGE_ID' => $item->getStageId(),
            'ASSIGNED_BY_ID' => $item->getAssignedById(),
            'CREATED_BY_ID' => $item->getCreatedBy(),
            'UPDATED_BY_ID' => $item->getUpdatedBy(),
            'CREATED_TIME' => $item->getCreatedTime() ? $item->getCreatedTime()->toString() : null,
            'UPDATED_TIME' => $item->getUpdatedTime() ? $item->getUpdatedTime()->toString() : null,
            'BEGINDATE' => $item->getBegindate() ? $item->getBegindate()->toString() : null,
            'CLOSEDATE' => $item->getClosedate() ? $item->getClosedate()->toString() : null,
            'COMPANY_ID' => $item->getCompanyId(),
            'CONTACT_IDS' => $item->getContactIds(),
        ];

        // Добавить пользовательские поля
        $fieldsInfo = $item->getFields();
        foreach ($fieldsInfo as $fieldName => $fieldValue) {
            if (strpos($fieldName, 'UF_') === 0) {
                $data[$fieldName] = $fieldValue;
            }
        }

        return $data;
    }

    /**
     * Заполнить Item из массива полей
     *
     * @param Item $item
     * @param array $fields
     */
    private function fillItem(Item $item, array $fields): void
    {
        // Основные поля
        if (isset($fields['TITLE'])) {
            $item->setTitle($fields['TITLE']);
        }

        if (isset($fields['STAGE_ID'])) {
            $item->setStageId($fields['STAGE_ID']);
        }

        if (isset($fields['ASSIGNED_BY_ID'])) {
            $item->setAssignedById($fields['ASSIGNED_BY_ID']);
        }

        if (isset($fields['BEGINDATE'])) {
            $item->setBegindate(
                $fields['BEGINDATE'] ? new \Bitrix\Main\Type\DateTime($fields['BEGINDATE']) : null
            );
        }

        if (isset($fields['CLOSEDATE'])) {
            $item->setClosedate(
                $fields['CLOSEDATE'] ? new \Bitrix\Main\Type\DateTime($fields['CLOSEDATE']) : null
            );
        }

        if (isset($fields['COMPANY_ID'])) {
            $item->setCompanyId($fields['COMPANY_ID']);
        }

        if (isset($fields['CONTACT_IDS'])) {
            $item->setContactIds(is_array($fields['CONTACT_IDS']) ? $fields['CONTACT_IDS'] : []);
        }

        // Пользовательские поля
        foreach ($fields as $fieldName => $fieldValue) {
            if (strpos($fieldName, 'UF_') === 0) {
                $item->set($fieldName, $fieldValue);
            }
        }
    }
}
