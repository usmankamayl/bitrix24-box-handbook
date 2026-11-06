<?php

namespace Acme\Warehouse\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Error;
use Bitrix\Main\DI\ServiceLocator;
use Acme\Warehouse\Service\ProductService;
use Acme\Warehouse\Dto\CreateProductDto;

/**
 * REST API контроллер для управления товарами
 *
 * Вызов из JavaScript:
 * BX.ajax.runAction('acme:warehouse.Product.delete', {
 *     data: { id: 5, sessid: BX.bitrix_sessid() }
 * })
 */
class ProductController extends Controller
{
    /**
     * Конфигурация действий и фильтров безопасности
     */
    public function configureActions(): array
    {
        return [
            'delete' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                ],
            ],
            'update' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\Csrf(),
                ],
            ],
            'list' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                ],
            ],
            'statistics' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                ],
            ],
        ];
    }

    /**
     * Удаление товара
     *
     * ⚠️ Важно: Authentication и Csrf проверяют только авторизацию,
     * но НЕ проверяют права на конкретную запись!
     */
    public function deleteAction(int $id): array
    {
        // Проверка прав на удаление конкретного товара
        if (!$this->canDeleteProduct($id)) {
            $this->addError(new Error(
                'Недостаточно прав для удаления товара',
                'ACCESS_DENIED'
            ));
            return [];
        }

        try {
            $locator = ServiceLocator::getInstance();

            if ($locator->has('acme.warehouse.product.service')) {
                $service = $locator->get('acme.warehouse.product.service');
            } else {
                $service = new ProductService();
            }

            $result = $service->writeOff($id, 999999);

            if ($result->isSuccess()) {
                return ['deleted' => true];
            }

            $this->addErrors($result->getErrors());
            return [];

        } catch (\Exception $e) {
            // Логируем ошибку на сервере, клиенту отдаём нейтральное сообщение
            \Bitrix\Main\Application::getInstance()
                ->getExceptionHandler()
                ->writeToLog($e);

            $this->addError(new Error(
                'Ошибка при удалении товара',
                'DELETE_ERROR'
            ));
            return [];
        }
    }

    /**
     * Обновление остатков товара
     */
    public function updateAction(string $sku, int $quantity): array
    {
        if (!$this->canUpdateProducts()) {
            $this->addError(new Error(
                'Недостаточно прав для изменения товаров',
                'ACCESS_DENIED'
            ));
            return [];
        }

        try {
            $locator = ServiceLocator::getInstance();
            $service = $locator->has('acme.warehouse.product.service')
                ? $locator->get('acme.warehouse.product.service')
                : new ProductService();

            $result = $service->receive($sku, $quantity);

            if ($result->isSuccess()) {
                return [
                    'success' => true,
                    'data' => $result->getData(),
                ];
            }

            $this->addErrors($result->getErrors());
            return [];

        } catch (\Exception $e) {
            \Bitrix\Main\Application::getInstance()
                ->getExceptionHandler()
                ->writeToLog($e);

            $this->addError(new Error(
                'Ошибка при обновлении товара',
                'UPDATE_ERROR'
            ));
            return [];
        }
    }

    /**
     * Список товаров с фильтрацией
     *
     * Защита: используем allowlist для фильтров
     */
    public function listAction(array $filter = []): array
    {
        // Allowlist разрешённых полей для фильтрации
        $allowedFilters = ['CATEGORY', 'NAME', 'SKU'];
        $safeFilter = array_intersect_key(
            $filter,
            array_flip($allowedFilters)
        );

        try {
            $locator = ServiceLocator::getInstance();
            $service = $locator->has('acme.warehouse.product.service')
                ? $locator->get('acme.warehouse.product.service')
                : new ProductService();

            // Ограничение PAGE_SIZE для защиты от перегрузки
            $products = \Acme\Warehouse\Model\ProductTable::getList([
                'filter' => $safeFilter,
                'limit' => 50,
                'order' => ['NAME' => 'ASC'],
            ])->fetchAll();

            return ['items' => $products];

        } catch (\Exception $e) {
            \Bitrix\Main\Application::getInstance()
                ->getExceptionHandler()
                ->writeToLog($e);

            $this->addError(new Error(
                'Ошибка при получении списка',
                'LIST_ERROR'
            ));
            return [];
        }
    }

    /**
     * Получение статистики склада
     */
    public function statisticsAction(): array
    {
        try {
            $locator = ServiceLocator::getInstance();
            $service = $locator->has('acme.warehouse.product.service')
                ? $locator->get('acme.warehouse.product.service')
                : new ProductService();

            $stats = $service->getStatistics();

            return ['data' => $stats];

        } catch (\Exception $e) {
            \Bitrix\Main\Application::getInstance()
                ->getExceptionHandler()
                ->writeToLog($e);

            $this->addError(new Error(
                'Ошибка при получении статистики',
                'STATS_ERROR'
            ));
            return [];
        }
    }

    /**
     * Проверка прав на удаление товара
     */
    private function canDeleteProduct(int $id): bool
    {
        global $USER;

        // Только администраторы могут удалять
        if ($USER->IsAdmin()) {
            return true;
        }

        // Здесь может быть дополнительная логика:
        // - проверка по отделу
        // - проверка владельца записи
        // - проверка специальных прав

        return false;
    }

    /**
     * Проверка прав на изменение товаров
     */
    private function canUpdateProducts(): bool
    {
        global $USER;

        if ($USER->IsAdmin()) {
            return true;
        }

        // Проверка по группам пользователей
        $userGroups = $USER->GetUserGroupArray();

        // ID группы "Менеджеры склада" (пример)
        $warehouseManagerGroupId = 10;

        return in_array($warehouseManagerGroupId, $userGroups);
    }
}
