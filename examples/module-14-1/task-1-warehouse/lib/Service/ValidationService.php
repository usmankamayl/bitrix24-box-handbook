<?php

namespace Acme\Warehouse\Service;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Result;
use Bitrix\Main\Validation\ValidationService as BitrixValidationService;
use Acme\Warehouse\Dto\CreateProductDto;
use Acme\Warehouse\Model\ProductTable;

/**
 * Сервис валидации данных перед сохранением
 * Демонстрирует использование PHP-атрибутов для валидации
 */
class ValidationService
{
    private BitrixValidationService $validation;

    public function __construct()
    {
        $locator = ServiceLocator::getInstance();
        $this->validation = $locator->get('main.validation.service');
    }

    /**
     * Создание товара с валидацией через DTO
     *
     * @param CreateProductDto $dto
     * @return Result
     */
    public function createProduct(CreateProductDto $dto): Result
    {
        $result = new Result();

        // Валидация DTO через PHP-атрибуты
        $validationResult = $this->validation->validate($dto);

        if (!$validationResult->isSuccess()) {
            // Добавляем все ошибки валидации
            foreach ($validationResult->getErrors() as $error) {
                $result->addError($error);
            }
            return $result;
        }

        // Дополнительная бизнес-валидация
        $uniqueCheckResult = $this->checkSkuUnique($dto->getSku());
        if (!$uniqueCheckResult->isSuccess()) {
            $result->addErrors($uniqueCheckResult->getErrors());
            return $result;
        }

        // Сохранение в БД
        $addResult = ProductTable::add($dto->toArray());

        if (!$addResult->isSuccess()) {
            $result->addErrors($addResult->getErrors());
            return $result;
        }

        $result->setData([
            'id' => $addResult->getId(),
        ]);

        return $result;
    }

    /**
     * Проверка уникальности артикула
     */
    private function checkSkuUnique(string $sku): Result
    {
        $result = new Result();

        $existing = ProductTable::getList([
            'filter' => ['SKU' => $sku],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        if ($existing) {
            $result->addError(new \Bitrix\Main\Error(
                "Товар с артикулом {$sku} уже существует"
            ));
        }

        return $result;
    }

    /**
     * Массовая валидация товаров перед импортом
     *
     * @param array $items Массив данных товаров
     * @return Result
     */
    public function validateImport(array $items): Result
    {
        $result = new Result();
        $errors = [];
        $validItems = [];

        foreach ($items as $index => $item) {
            try {
                $dto = new CreateProductDto(
                    name: $item['name'] ?? '',
                    sku: $item['sku'] ?? '',
                    quantity: (int)($item['quantity'] ?? 0),
                    price: (float)($item['price'] ?? 0.0),
                    category: $item['category'] ?? null
                );

                $validationResult = $this->validation->validate($dto);

                if ($validationResult->isSuccess()) {
                    $validItems[] = $dto;
                } else {
                    foreach ($validationResult->getErrors() as $error) {
                        $errors[] = "Строка {$index}: " . $error->getMessage();
                    }
                }

            } catch (\Exception $e) {
                $errors[] = "Строка {$index}: неверный формат данных";
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $result->addError(new \Bitrix\Main\Error($error));
            }
        }

        $result->setData([
            'valid_items' => $validItems,
            'total' => count($items),
            'valid' => count($validItems),
            'invalid' => count($errors),
        ]);

        return $result;
    }
}
