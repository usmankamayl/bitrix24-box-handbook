<?php
/**
 * Обработчики событий модуля складского учета
 *
 * Реагирует на события инфоблоков для автоматической синхронизации
 * товаров между инфоблоками и таблицей склада
 */

namespace Acme\Warehouse;

use Acme\Warehouse\Model\ProductTable;
use Acme\Warehouse\Service\ProductService;

class EventHandlers
{
    /**
     * Обработчик добавления элемента инфоблока
     *
     * При добавлении товара в инфоблок автоматически создается
     * запись в таблице склада
     *
     * @param array $arFields Поля элемента
     */
    public static function onIBlockElementAdd(&$arFields)
    {
        // Проверяем, что это нужный инфоблок (например, каталог товаров)
        $targetIBlockIds = [10, 11]; // ID инфоблоков каталогов

        if (!in_array($arFields['IBLOCK_ID'], $targetIBlockIds)) {
            return;
        }

        // Получаем артикул из свойств
        $sku = self::getSku($arFields['ID']);

        if (empty($sku)) {
            return;
        }

        // Проверяем, не существует ли уже такой товар
        $service = new ProductService();
        $existing = $service->getProductBySku($sku);

        if ($existing) {
            // Товар уже есть, обновляем название
            ProductTable::update($existing['ID'], [
                'NAME' => $arFields['NAME'],
            ]);

            AddMessage2Log(sprintf(
                'EventHandler: Товар обновлен при добавлении в ИБ. SKU=%s, NAME=%s',
                $sku,
                $arFields['NAME']
            ), 'acme.warehouse');
        } else {
            // Создаем новый товар на складе
            $result = ProductTable::add([
                'NAME' => $arFields['NAME'],
                'SKU' => $sku,
                'QUANTITY' => 0, // начальный остаток
                'PRICE' => self::getPrice($arFields['ID']),
            ]);

            if ($result->isSuccess()) {
                AddMessage2Log(sprintf(
                    'EventHandler: Товар создан при добавлении в ИБ. SKU=%s, NAME=%s',
                    $sku,
                    $arFields['NAME']
                ), 'acme.warehouse');
            }
        }
    }

    /**
     * Обработчик обновления элемента инфоблока
     *
     * При изменении товара в инфоблоке обновляются данные в таблице склада
     *
     * @param array $arFields Поля элемента
     */
    public static function onIBlockElementUpdate(&$arFields)
    {
        // Проверяем, что это нужный инфоблок
        $targetIBlockIds = [10, 11];

        if (!in_array($arFields['IBLOCK_ID'], $targetIBlockIds)) {
            return;
        }

        // Получаем артикул
        $sku = self::getSku($arFields['ID']);

        if (empty($sku)) {
            return;
        }

        // Находим товар на складе
        $service = new ProductService();
        $product = $service->getProductBySku($sku);

        if (!$product) {
            return;
        }

        // Обновляем данные
        $updateData = [];

        if (isset($arFields['NAME'])) {
            $updateData['NAME'] = $arFields['NAME'];
        }

        // Обновляем цену, если изменилась
        $newPrice = self::getPrice($arFields['ID']);
        if ($newPrice !== null && $newPrice != $product['PRICE']) {
            $updateData['PRICE'] = $newPrice;
        }

        if (!empty($updateData)) {
            ProductTable::update($product['ID'], $updateData);

            AddMessage2Log(sprintf(
                'EventHandler: Товар обновлен при изменении в ИБ. SKU=%s',
                $sku
            ), 'acme.warehouse');
        }
    }

    /**
     * Получить артикул товара из свойств инфоблока
     *
     * @param int $elementId ID элемента
     * @return string|null
     */
    protected static function getSku(int $elementId): ?string
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return null;
        }

        // Получаем свойство SKU/ARTICLE (код свойства может быть разным)
        $props = \CIBlockElement::GetProperty(
            0,
            $elementId,
            ['sort' => 'asc'],
            ['CODE' => ['SKU', 'ARTICLE', 'CML2_ARTICLE']]
        );

        while ($prop = $props->Fetch()) {
            if (!empty($prop['VALUE'])) {
                return trim($prop['VALUE']);
            }
        }

        return null;
    }

    /**
     * Получить цену товара
     *
     * @param int $elementId ID элемента
     * @return float|null
     */
    protected static function getPrice(int $elementId): ?float
    {
        if (!\Bitrix\Main\Loader::includeModule('catalog')) {
            return null;
        }

        // Получаем базовую цену
        $price = \CPrice::GetBasePrice($elementId);

        if ($price && isset($price['PRICE'])) {
            return (float)$price['PRICE'];
        }

        return null;
    }
}
