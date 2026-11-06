<?php

namespace BitrixCourse\BasketDiscount;

use Bitrix\Sale;

/**
 * Применяет правило "каждый N-й товар из списка участников акции —
 * дешевле на X%" ко всей корзине.
 *
 * Вызывается ЯВНО перед $basket->save() (см. demo.php и README —
 * "Интеграция"), а не через событие движка скидок — и это осознанный
 * выбор архитектуры, а не недоработка (объяснение — в README).
 */
class NthItemDiscountRule
{
    private const EVERY_NTH = 3;
    private const PERCENT = 50.0;

    /** @var int[] */
    private array $eligibleProductIds;

    public function __construct(array $eligibleProductIds)
    {
        $this->eligibleProductIds = $eligibleProductIds;
    }

    /**
     * @return bool true, если хотя бы одна позиция была изменена
     *              (вызывающий код решает, нужно ли после этого save())
     */
    public function apply(Sale\Basket $basket): bool
    {
        $calculator = new NthItemDiscountCalculator();
        $changed = false;

        foreach ($basket as $item) {
            /** @var Sale\BasketItem $item */
            $productId = (int)$item->getProductId();
            if (!in_array($productId, $this->eligibleProductIds, true)) {
                continue;
            }

            $basePrice = (float)$item->getField('BASE_PRICE');
            if ($basePrice <= 0) {
                $basePrice = (float)$item->getPrice();
            }

            $newUnitPrice = $calculator->calculateUnitPrice(
                $basePrice,
                (int)$item->getQuantity(),
                self::EVERY_NTH,
                self::PERCENT
            );

            // Сравниваем с текущей ценой, а не пишем всегда — иначе
            // basket->save() будет считать позицию "изменённой" на каждом
            // вызове, даже когда скидка уже применена и ничего не поменялось.
            if (abs($newUnitPrice - (float)$item->getPrice()) > 0.0001) {
                $item->setFields([
                    'CUSTOM_PRICE'   => 'Y',
                    'PRICE'          => $newUnitPrice,
                    'DISCOUNT_PRICE' => round(($basePrice - $newUnitPrice) * $item->getQuantity(), 4),
                ]);
                $changed = true;
            }
        }

        return $changed;
    }
}
