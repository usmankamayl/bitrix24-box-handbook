<?php

namespace Acme\Warehouse\Dto;

use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\PositiveNumber;
use Bitrix\Main\Validation\Rule\RegExp;

/**
 * DTO для создания товара с валидацией через PHP-атрибуты
 *
 * Пример использования:
 *
 * $dto = new CreateProductDto(
 *     name: 'Ноутбук ASUS',
 *     sku: 'ASUS-001',
 *     quantity: 10,
 *     price: 45000.00
 * );
 *
 * $validationService = ServiceLocator::getInstance()->get('main.validation.service');
 * $result = $validationService->validate($dto);
 *
 * if (!$result->isSuccess()) {
 *     foreach ($result->getErrors() as $error) {
 *         echo $error->getMessage();
 *     }
 * }
 */
class CreateProductDto
{
    /**
     * Название товара
     * Обязательное, длина от 2 до 255 символов
     */
    #[NotEmpty(message: 'Название товара обязательно')]
    #[Length(min: 2, max: 255, message: 'Название должно быть от 2 до 255 символов')]
    private string $name;

    /**
     * Артикул (SKU)
     * Обязательный, соответствует формату: только заглавные латинские буквы, цифры и дефис
     */
    #[NotEmpty(message: 'Артикул обязателен')]
    #[RegExp(pattern: '/^[A-Z0-9\-]+$/', message: 'Артикул может содержать только заглавные буквы, цифры и дефис')]
    #[Length(min: 3, max: 50, message: 'Артикул должен быть от 3 до 50 символов')]
    private string $sku;

    /**
     * Количество на складе
     * Должно быть положительным числом или нулём
     */
    #[PositiveNumber(allowZero: true, message: 'Количество должно быть положительным числом или нулём')]
    private int $quantity;

    /**
     * Цена товара
     * Должна быть положительным числом
     */
    #[PositiveNumber(message: 'Цена должна быть положительным числом')]
    private float $price;

    /**
     * Категория товара (опционально)
     */
    #[Length(max: 100, message: 'Категория не должна превышать 100 символов')]
    private ?string $category = null;

    public function __construct(
        string $name,
        string $sku,
        int $quantity = 0,
        float $price = 0.0,
        ?string $category = null
    ) {
        $this->name = $name;
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->category = $category;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * Преобразование DTO в массив для ProductTable::add()
     */
    public function toArray(): array
    {
        return [
            'NAME' => $this->name,
            'SKU' => $this->sku,
            'QUANTITY' => $this->quantity,
            'PRICE' => $this->price,
            'CATEGORY' => $this->category,
        ];
    }
}
