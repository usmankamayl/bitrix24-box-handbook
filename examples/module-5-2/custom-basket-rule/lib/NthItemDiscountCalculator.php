<?php

namespace BitrixCourse\BasketDiscount;

/**
 * Чистый калькулятор "каждая N-я единица товара дешевле на X%".
 * Не знает про Bitrix\Sale — только считает цену, поэтому легко
 * покрывается unit-тестами без поднятия ядра.
 */
class NthItemDiscountCalculator
{
    /**
     * @param float $basePrice базовая цена за единицу (без скидки)
     * @param int   $quantity  количество в корзине
     * @param int   $everyNth  например 3 — скидка на каждый 3-й товар
     * @param float $percent   процент скидки на "особую" единицу, 0..100
     *
     * @return float итоговая усреднённая цена ЗА ЕДИНИЦУ — именно это
     *               значение проставляется в BasketItem::PRICE при
     *               CUSTOM_PRICE = 'Y' (в Bitrix цена позиции хранится
     *               как цена за единицу, а не общая сумма строки)
     */
    public function calculateUnitPrice(float $basePrice, int $quantity, int $everyNth, float $percent): float
    {
        if ($basePrice <= 0 || $quantity <= 0 || $everyNth <= 1 || $percent <= 0) {
            return $basePrice;
        }

        $discountedUnits = intdiv($quantity, $everyNth);
        $fullPriceUnits = $quantity - $discountedUnits;

        $discountedUnitPrice = $basePrice * (1 - $percent / 100);
        $total = $fullPriceUnits * $basePrice + $discountedUnits * $discountedUnitPrice;

        return round($total / $quantity, 4);
    }
}
