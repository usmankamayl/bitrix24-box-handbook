<?php

namespace BitrixCourse\Basket;

use Bitrix\Main\Loader;
use Bitrix\Sale;

/**
 * Сервисный слой для корзины. Все ajax-экшены компонента вызывают его,
 * а не работают с Sale\Basket напрямую — так бизнес-правила
 * (проверки, купоны, лимиты) живут в одном месте и тестируются отдельно.
 *
 * Принципы:
 *  - только через объектную модель Sale\Basket, никаких прямых
 *    UPDATE в b_sale_basket;
 *  - CSRF-проверка сессии на уровне экшена (см. basket.php);
 *  - цена всегда пересчитывается на бэкенде — клиентское значение не принимаем;
 *  - купоны применяются через CouponsManager, а не ручным сетом полей.
 */
class BasketService
{
    private const MAX_POSITION_QTY = 999;
    private const MAX_BASKET_POSITIONS = 100;

    private ?Sale\Basket $basket = null;
    private int $fuserId;
    private string $siteId;

    public function __construct(?int $fuserId = null, ?string $siteId = null)
    {
        $this->siteId  = $siteId  ?: SITE_ID;
        $this->fuserId = $fuserId ?: Sale\Fuser::getId();
    }

    /**
     * Добавление товара в корзину (или +N к существующей позиции того же SKU).
     *
     * @param int    $productId  ID товара/SKU (элемент инфоблока)
     * @param float  $quantity   количество (дробное допустимо для весовых)
     * @param string $provider   класс провайдера товара
     */
    public function addProduct(int $productId, float $quantity = 1.0, string $provider = ''): array
    {
        if ($productId <= 0) {
            return ['success' => false, 'error' => 'Некорректный ID товара'];
        }
        if ($quantity <= 0 || $quantity > self::MAX_POSITION_QTY) {
            return ['success' => false, 'error' => 'Некорректное количество'];
        }

        $basket = $this->getBasket();

        // Лимит разных позиций в корзине — защита от спама
        if ($basket->count() >= self::MAX_BASKET_POSITIONS && !$this->findItem($productId)) {
            return ['success' => false, 'error' => 'Слишком много позиций в корзине'];
        }

        $existingItem = $this->findItem($productId);
        if ($existingItem) {
            $newQty = $existingItem->getQuantity() + $quantity;
            if ($newQty > self::MAX_POSITION_QTY) {
                return ['success' => false, 'error' => 'Превышен лимит на одну позицию'];
            }
            $existingItem->setField('QUANTITY', $newQty);
        } else {
            $item = $basket->createItem('catalog', $productId);
            $item->setFields([
                'QUANTITY'               => $quantity,
                'CURRENCY'               => \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
                'LID'                    => $this->siteId,
                'PRODUCT_PROVIDER_CLASS' => $provider ?: $this->resolveProvider($productId),
            ]);
        }

        // save() внутри пересчитает правила корзины, проверит остаток
        // и применит купоны — не делаем этого вручную.
        $result = $basket->save();
        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return ['success' => true, 'basket' => $this->getBasketSummary()];
    }

    /**
     * Изменение количества существующей позиции.
     */
    public function updateQuantity(int $basketItemId, float $quantity): array
    {
        if ($quantity < 0 || $quantity > self::MAX_POSITION_QTY) {
            return ['success' => false, 'error' => 'Некорректное количество'];
        }

        $basket = $this->getBasket();
        $item   = $basket->getItemByBasketCode($basketItemId)
            ?: $basket->getItemById($basketItemId);

        if (!$item) {
            return ['success' => false, 'error' => 'Позиция не найдена в корзине'];
        }

        if ($quantity == 0) {
            $item->delete();
        } else {
            $item->setField('QUANTITY', $quantity);
        }

        $result = $basket->save();
        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        return ['success' => true, 'basket' => $this->getBasketSummary()];
    }

    /**
     * Удаление позиции по её basket_item id.
     */
    public function removeItem(int $basketItemId): array
    {
        $basket = $this->getBasket();
        $item   = $basket->getItemById($basketItemId);
        if (!$item) {
            return ['success' => false, 'error' => 'Позиция не найдена'];
        }

        $item->delete();
        $result = $basket->save();

        return $result->isSuccess()
            ? ['success' => true, 'basket' => $this->getBasketSummary()]
            : ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
    }

    /**
     * Применение купона. Идём через CouponsManager — купон привязывается
     * к fuser-корзине, проверяется на валидность и применяет правила скидок.
     */
    public function applyCoupon(string $couponCode): array
    {
        $couponCode = trim($couponCode);
        if ($couponCode === '') {
            return ['success' => false, 'error' => 'Пустой купон'];
        }

        $basket = $this->getBasket();

        $coupons = Sale\DiscountCouponsManager::get($basket);
        if (in_array($couponCode, $coupons, true)) {
            return ['success' => false, 'error' => 'Купон уже применён'];
        }

        Sale\DiscountCouponsManager::add($couponCode);

        // Пересчитываем корзину — правила корзины сработают на новый купон
        $result = $basket->save();
        if (!$result->isSuccess()) {
            return ['success' => false, 'error' => implode('; ', $result->getErrorMessages())];
        }

        // После save() проверяем, что купон реально применился —
        // DiscountCouponsManager молча игнорирует невалидные коды
        $appliedCoupons = Sale\DiscountCouponsManager::get($basket);
        if (!in_array($couponCode, $appliedCoupons, true)) {
            return ['success' => false, 'error' => 'Купон не действителен или не применим'];
        }

        return ['success' => true, 'basket' => $this->getBasketSummary()];
    }

    /**
     * Перенос корзины анонимного fuserId на текущего пользователя.
     * Вызывается из обработчика OnUserLogin (см. events.php).
     *
     * Принцип: не «переписываем FUSER_ID у строк» (это сломает резервы
     * и правила), а загружаем обе корзины и перекладываем BasketItem.
     */
    public function mergeAnonymousBasket(int $anonymousFuserId): void
    {
        if ($anonymousFuserId <= 0 || $anonymousFuserId === $this->fuserId) {
            return;
        }

        $source = Sale\Basket::loadItemsForFUser($anonymousFuserId, $this->siteId);
        if ($source->count() === 0) {
            return;
        }

        $target = $this->getBasket();

        foreach ($source as $item) {
            /** @var Sale\BasketItem $item */
            $productId = (int)$item->getProductId();
            $quantity  = $item->getQuantity();

            $existing = $this->findItem($productId, $target);
            if ($existing) {
                // Если товар уже есть в корзине пользователя — суммируем количество
                $existing->setField('QUANTITY', $existing->getQuantity() + $quantity);
            } else {
                $newItem = $target->createItem('catalog', $productId);
                $newItem->setFields([
                    'QUANTITY'               => $quantity,
                    'CURRENCY'               => $item->getCurrency(),
                    'LID'                    => $this->siteId,
                    'PRODUCT_PROVIDER_CLASS' => $item->getField('PRODUCT_PROVIDER_CLASS'),
                    // CUSTOM_PRICE и BASE_PRICE переносим как есть —
                    // это могут быть договорные цены конкретного клиента
                    'CUSTOM_PRICE'           => $item->getField('CUSTOM_PRICE'),
                    'BASE_PRICE'             => $item->getField('BASE_PRICE'),
                    'PRICE'                  => $item->getPrice(),
                ]);
            }
        }

        // Очищаем и сохраняем старую корзину — иначе она будет висеть как «зомби»
        foreach ($source as $item) {
            $item->delete();
        }
        $source->save();

        $target->save();
    }

    /**
     * Краткая сводка корзины для ajax-ответа.
     */
    public function getBasketSummary(): array
    {
        $basket = $this->getBasket();

        $items = [];
        $totalQty = 0.0;
        $totalPrice = 0.0;
        $totalDiscount = 0.0;

        foreach ($basket as $item) {
            /** @var Sale\BasketItem $item */
            $qty = (float)$item->getQuantity();
            $price = (float)$item->getPrice();
            $basePrice = (float)$item->getBasePrice();

            $items[] = [
                'ID'             => $item->getId(),
                'BASKET_CODE'    => $item->getBasketCode(),
                'PRODUCT_ID'     => (int)$item->getProductId(),
                'NAME'           => $item->getField('NAME'),
                'QUANTITY'       => $qty,
                'PRICE'          => $price,
                'BASE_PRICE'     => $basePrice,
                'DISCOUNT_PRICE' => max(0.0, $basePrice - $price),
                'CURRENCY'       => $item->getCurrency(),
                'CAN_BUY'        => $item->canBuy(),
            ];

            $totalQty   += $qty;
            $totalPrice += $price * $qty;
            $totalDiscount += max(0.0, $basePrice - $price) * $qty;
        }

        return [
            'ITEMS'          => $items,
            'TOTAL_QUANTITY' => $totalQty,
            'TOTAL_PRICE'    => $totalPrice,
            'TOTAL_DISCOUNT' => $totalDiscount,
            'CURRENCY'       => $items[0]['CURRENCY'] ?? \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
            'COUPONS'        => Sale\DiscountCouponsManager::get($basket),
        ];
    }

    private function getBasket(): Sale\Basket
    {
        if ($this->basket === null) {
            $this->basket = Sale\Basket::loadItemsForFUser($this->fuserId, $this->siteId);
        }
        return $this->basket;
    }

    private function findItem(int $productId, ?Sale\Basket $basket = null): ?Sale\BasketItem
    {
        $basket = $basket ?: $this->getBasket();
        foreach ($basket as $item) {
            if ((int)$item->getProductId() === $productId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Для товаров каталога провайдер стандартный; в проекте с кастомными
     * типами товаров сюда добавляется маппинг по модулю.
     */
    private function resolveProvider(int $productId): string
    {
        return \Bitrix\Catalog\Product\BasketProvider::class;
    }
}
