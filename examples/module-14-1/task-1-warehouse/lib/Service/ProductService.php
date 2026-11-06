<?php
/**
 * Сервис для работы с товарами на складе
 *
 * Бизнес-логика управления складом:
 * - Оприходование/списание товаров
 * - Проверка наличия
 * - Поиск товаров с низким остатком
 * - Получение статистики
 */

namespace Acme\Warehouse\Service;

use Acme\Warehouse\Model\ProductTable;
use Bitrix\Main\Result;
use Bitrix\Main\Error;

class ProductService
{
    /**
     * Получить товар по артикулу
     *
     * @param string $sku Артикул товара
     * @return array|null
     */
    public function getProductBySku(string $sku): ?array
    {
        $sku = strtoupper(trim($sku));

        $product = ProductTable::getList([
            'filter' => ['SKU' => $sku],
            'limit' => 1,
        ])->fetch();

        return $product ?: null;
    }

    /**
     * Получить товары с низким остатком
     *
     * @param int $threshold Пороговое значение
     * @return array
     */
    public function getLowStockProducts(int $threshold = 5): array
    {
        return ProductTable::getList([
            'filter' => [
                '<=QUANTITY' => $threshold,
                '>QUANTITY' => 0, // исключаем товары с нулевым остатком
            ],
            'order' => ['QUANTITY' => 'ASC'],
        ])->fetchAll();
    }

    /**
     * Получить товары с нулевым остатком
     *
     * @return array
     */
    public function getOutOfStockProducts(): array
    {
        return ProductTable::getList([
            'filter' => ['QUANTITY' => 0],
            'order' => ['NAME' => 'ASC'],
        ])->fetchAll();
    }

    /**
     * Проверить доступность товара
     *
     * @param string $sku Артикул
     * @param int $quantity Требуемое количество
     * @return bool
     */
    public function checkAvailability(string $sku, int $quantity = 1): bool
    {
        $product = $this->getProductBySku($sku);

        if (!$product) {
            return false;
        }

        return $product['QUANTITY'] >= $quantity;
    }

    /**
     * Оприходовать товар на склад (увеличить остаток)
     *
     * @param string $sku Артикул
     * @param int $quantity Количество для добавления
     * @return Result
     */
    public function receive(string $sku, int $quantity): Result
    {
        $result = new Result();

        if ($quantity <= 0) {
            $result->addError(new Error('Количество должно быть больше нуля'));
            return $result;
        }

        $product = $this->getProductBySku($sku);

        if (!$product) {
            $result->addError(new Error("Товар с артикулом {$sku} не найден"));
            return $result;
        }

        // Увеличиваем остаток
        $newQuantity = $product['QUANTITY'] + $quantity;

        $updateResult = ProductTable::update($product['ID'], [
            'QUANTITY' => $newQuantity,
        ]);

        if (!$updateResult->isSuccess()) {
            $result->addErrors($updateResult->getErrors());
            return $result;
        }

        // Логирование операции
        AddMessage2Log(sprintf(
            'Оприходование: SKU=%s, +%d, новый остаток=%d',
            $sku,
            $quantity,
            $newQuantity
        ), 'acme.warehouse');

        $result->setData(['new_quantity' => $newQuantity]);

        return $result;
    }

    /**
     * Списать товар со склада (уменьшить остаток)
     *
     * @param string $sku Артикул
     * @param int $quantity Количество для списания
     * @return Result
     */
    public function writeOff(string $sku, int $quantity): Result
    {
        $result = new Result();

        if ($quantity <= 0) {
            $result->addError(new Error('Количество должно быть больше нуля'));
            return $result;
        }

        $product = $this->getProductBySku($sku);

        if (!$product) {
            $result->addError(new Error("Товар с артикулом {$sku} не найден"));
            return $result;
        }

        // Проверка достаточности остатка
        if ($product['QUANTITY'] < $quantity) {
            $result->addError(new Error(
                "Недостаточно товара на складе. Доступно: {$product['QUANTITY']}, требуется: {$quantity}"
            ));
            return $result;
        }

        // Уменьшаем остаток
        $newQuantity = $product['QUANTITY'] - $quantity;

        $updateResult = ProductTable::update($product['ID'], [
            'QUANTITY' => $newQuantity,
        ]);

        if (!$updateResult->isSuccess()) {
            $result->addErrors($updateResult->getErrors());
            return $result;
        }

        // Логирование операции
        AddMessage2Log(sprintf(
            'Списание: SKU=%s, -%d, новый остаток=%d',
            $sku,
            $quantity,
            $newQuantity
        ), 'acme.warehouse');

        $result->setData(['new_quantity' => $newQuantity]);

        return $result;
    }

    /**
     * Получить общую стоимость товаров на складе
     *
     * @return float
     */
    public function getTotalValue(): float
    {
        $total = 0;

        $products = ProductTable::getList([
            'select' => ['QUANTITY', 'PRICE'],
        ])->fetchAll();

        foreach ($products as $product) {
            $total += $product['QUANTITY'] * $product['PRICE'];
        }

        return $total;
    }

    /**
     * Получить статистику склада
     *
     * @return array
     */
    public function getStatistics(): array
    {
        // Общее количество товаров
        $totalProducts = ProductTable::getCount();

        // Товары с низким остатком
        $lowStock = count($this->getLowStockProducts());

        // Товары с нулевым остатком
        $outOfStock = count($this->getOutOfStockProducts());

        // Общая стоимость склада
        $totalValue = $this->getTotalValue();

        // Общее количество единиц товара
        $totalQuantity = 0;
        $products = ProductTable::getList([
            'select' => ['QUANTITY'],
        ])->fetchAll();

        foreach ($products as $product) {
            $totalQuantity += $product['QUANTITY'];
        }

        return [
            'total_products' => $totalProducts,
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
        ];
    }

    /**
     * Резервировать товар (логика для заказов)
     *
     * @param string $sku Артикул
     * @param int $quantity Количество для резерва
     * @return Result
     */
    public function reserve(string $sku, int $quantity): Result
    {
        // В реальном проекте здесь была бы логика резервирования
        // с созданием отдельной таблицы резервов
        // Пока просто уменьшаем остаток

        return $this->writeOff($sku, $quantity);
    }

    /**
     * Импорт товаров из массива
     *
     * @param array $items Массив товаров
     * @return Result
     */
    public function importProducts(array $items): Result
    {
        $result = new Result();
        $imported = 0;
        $errors = [];

        foreach ($items as $item) {
            // Проверка обязательных полей
            if (empty($item['SKU']) || empty($item['NAME'])) {
                $errors[] = 'Пропущена строка: отсутствует SKU или NAME';
                continue;
            }

            // Проверка существования товара
            $existing = $this->getProductBySku($item['SKU']);

            if ($existing) {
                // Обновляем существующий товар
                $updateResult = ProductTable::update($existing['ID'], [
                    'NAME' => $item['NAME'],
                    'QUANTITY' => $item['QUANTITY'] ?? 0,
                    'PRICE' => $item['PRICE'] ?? 0.00,
                ]);

                if ($updateResult->isSuccess()) {
                    $imported++;
                } else {
                    $errors[] = "Ошибка обновления {$item['SKU']}: " .
                                implode(', ', $updateResult->getErrorMessages());
                }
            } else {
                // Создаем новый товар
                $addResult = ProductTable::add([
                    'NAME' => $item['NAME'],
                    'SKU' => $item['SKU'],
                    'QUANTITY' => $item['QUANTITY'] ?? 0,
                    'PRICE' => $item['PRICE'] ?? 0.00,
                ]);

                if ($addResult->isSuccess()) {
                    $imported++;
                } else {
                    $errors[] = "Ошибка добавления {$item['SKU']}: " .
                                implode(', ', $addResult->getErrorMessages());
                }
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $result->addError(new Error($error));
            }
        }

        $result->setData([
            'imported' => $imported,
            'errors_count' => count($errors),
        ]);

        return $result;
    }
}
