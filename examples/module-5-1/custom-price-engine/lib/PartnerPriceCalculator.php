<?php

namespace BitrixCourse\PartnerPricing;

use Bitrix\Main\Config\Option;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionElementTable;

/**
 * Кастомный движок цен «Партнёр»: наценка на базовую цену по проценту,
 * который зависит от (раздела каталога, группы пользователя).
 *
 * Перехватываем catalog::OnGetOptimalPrice — это единственная официальная
 * точка расширения итоговой цены без форка ядра каталога. Подключение
 * обработчика — см. events.php в этом же примере.
 */
class PartnerPriceCalculator
{
    /** @var array<string, float|null> кэш "productId:groupsHash" => markup на время хита */
    private static array $requestCache = [];

    /**
     * Сигнатура соответствует вызову события в \CCatalogProduct::GetOptimalPrice():
     * ExecuteModuleEventEx($arEvent, [&$arPrice, $arAllPrices, $canAccess,
     *     $arUserGroups, $productID, $quantity, $renewal, $arDiscountCoupons, $siteID]);
     *
     * @param array      $price        текущая рассчитанная цена (меняем по ссылке)
     * @param array      $allPrices    все цены товара по всем типам
     * @param bool       $canAccess
     * @param int[]      $userGroups
     * @param int        $productId
     * @param int|string $quantity
     * @param string     $renewal
     * @param array      $coupons
     * @param string     $siteId
     */
    public static function onGetOptimalPriceHandler(
        &$price,
        $allPrices,
        $canAccess,
        array $userGroups,
        $productId,
        $quantity = 1,
        $renewal = 'N',
        array $coupons = [],
        $siteId = SITE_ID
    ): void {
        if (empty($price['PRICE']) || !self::hasPartnerGroup($userGroups)) {
            return;
        }

        $markup = self::resolveMarkup((int)$productId, $userGroups);
        if ($markup === null || $markup <= 0) {
            return;
        }

        $basePrice = (float)$price['PRICE'];
        $newPrice = round($basePrice * (1 + $markup / 100), 2);

        $price['PRICE'] = $newPrice;
        if (isset($price['DISCOUNT_PRICE'])) {
            $price['DISCOUNT_PRICE'] = $newPrice;
        }
        // Явно помечаем, что цена модифицирована кастомной логикой —
        // полезно для отладки и для шаблонов, которые хотят показать "цена для партнёра".
        $price['PARTNER_MARKUP_APPLIED'] = $markup;
    }

    private static function hasPartnerGroup(array $userGroups): bool
    {
        $partnerGroupIds = self::getPartnerGroupIds();
        return !empty(array_intersect($userGroups, $partnerGroupIds));
    }

    /**
     * ID групп, которые считаются «партнёрскими» — задаются через настройки
     * модуля (админка), а не хардкодом, чтобы не трогать код при заведении
     * новой партнёрской группы.
     */
    private static function getPartnerGroupIds(): array
    {
        static $ids = null;
        if ($ids === null) {
            $raw = Option::get('bitrix_course', 'partner_group_ids', '');
            $ids = array_filter(array_map('intval', explode(',', $raw)));
        }
        return $ids;
    }

    private static function resolveMarkup(int $productId, array $userGroups): ?float
    {
        $cacheKey = $productId . ':' . implode(',', $userGroups);
        if (array_key_exists($cacheKey, self::$requestCache)) {
            return self::$requestCache[$cacheKey];
        }

        $sectionId = self::resolveSectionId($productId);
        $markup = $sectionId
            ? PartnerMarkupTable::getMarkup($sectionId, $userGroups)
            : null;

        self::$requestCache[$cacheKey] = $markup;
        return $markup;
    }

    /**
     * Для SKU берём раздел родительского товара (у предложений обычно
     * нет собственной привязки к разделу витрины).
     */
    private static function resolveSectionId(int $elementId): ?int
    {
        $element = ElementTable::getRow([
            'select' => ['ID', 'IBLOCK_SECTION_ID'],
            'filter' => ['ID' => $elementId],
        ]);

        if (!$element) {
            return null;
        }

        if ((int)$element['IBLOCK_SECTION_ID'] > 0) {
            return (int)$element['IBLOCK_SECTION_ID'];
        }

        $parentLink = \CCatalogSKU::GetProductInfo($elementId);
        if ($parentLink && !empty($parentLink['ID'])) {
            $parent = ElementTable::getRow([
                'select' => ['IBLOCK_SECTION_ID'],
                'filter' => ['ID' => $parentLink['ID']],
            ]);
            return $parent ? (int)$parent['IBLOCK_SECTION_ID'] : null;
        }

        return null;
    }
}
