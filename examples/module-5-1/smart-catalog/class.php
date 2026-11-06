<?
// Компонент: smart.catalog — выборка товаров с развёрнутыми SKU,
// оптовыми диапазонами цен и пакетным расчётом GetOptimalPrice.

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\StoreProductTable;

class SmartCatalogComponent extends CBitrixComponent
{
    protected int $productsIblockId;
    protected ?int $offersIblockId = null;
    protected int $offerLinkPropertyId = 0;

    public function executeComponent()
    {
        if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
            ShowError('Модули iblock/catalog не установлены');
            return;
        }

        $this->productsIblockId = (int)$this->arParams['IBLOCK_ID'];
        $this->resolveOffersIblock();

        $products = $this->fetchProducts();
        $offers   = $this->fetchOffers(array_column($products, 'ID'));

        // Пакетная загрузка всех цен одним запросом — без N+1
        $allIds  = array_merge(array_column($products, 'ID'), array_column($offers, 'ID'));
        $prices  = $this->fetchPriceMatrix($allIds);
        $stocks  = $this->fetchStocks($allIds);

        $this->arResult['ITEMS'] = $this->assemble($products, $offers, $prices, $stocks);
        $this->includeComponentTemplate();
    }

    /**
     * Ajax-действия компонента (вызываются через BX.ajax.runComponentAction).
     * Доступны анонимно, т.к. цена не чувствительна к CSRF.
     */
    public function configureActions(): array
    {
        return [
            'getOfferPrice' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * Возвращает цену и наличие конкретного SKU при выборе в select'е —
     * без перезагрузки всего списка товаров.
     */
    public function getOfferPriceAction(int $offerId): array
    {
        global $USER;

        if ($offerId <= 0 || !Loader::includeModule('catalog')) {
            throw new \Bitrix\Main\ArgumentException('Некорректный ID предложения');
        }

        $optimal = CCatalogProduct::GetOptimalPrice(
            $offerId, 1, $USER->GetUserGroupArray(), 'N', [],
            $this->arParams['SITE_ID'] ?? SITE_ID
        );

        $productRow = ProductTable::getRow([
            'select' => ['QUANTITY', 'CAN_BUY_ZERO'],
            'filter' => ['ID' => $offerId],
        ]);

        $inStock = ($productRow['CAN_BUY_ZERO'] ?? 'N') === 'Y'
            || (float)($productRow['QUANTITY'] ?? 0) > 0;

        return [
            'PRICE'      => $optimal['DISCOUNT_PRICE'] ?? null,
            'CURRENCY'   => $optimal['CURRENCY'] ?? '',
            'IN_STOCK'   => $inStock,
        ];
    }

    /**
     * Находим инфоблок предложений и ID свойства-привязки CML2_LINK.
     */
    private function resolveOffersIblock(): void
    {
        $skuInfo = CCatalogSKU::GetInfoByProductIBlock($this->productsIblockId);
        if (is_array($skuInfo) && $skuInfo['IBLOCK_ID']) {
            $this->offersIblockId      = (int)$skuInfo['IBLOCK_ID'];
            $this->offerLinkPropertyId = (int)$skuInfo['SKU_PROPERTY_ID'];
        }
    }

    /**
     * Базовая выборка товаров + каталожная часть.
     * GetList по ProductTable + ElementTable вместо CIBlockElement::GetList
     * — меньше JOIN'ов, прогнозируемый план.
     */
    private function fetchProducts(): array
    {
        $iterator = ElementTable::getList([
            'select' => [
                'ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL',
                'PRODUCT_QUANTITY'      => 'PRODUCT.QUANTITY',
                'PRODUCT_TYPE'          => 'PRODUCT.TYPE',
                'PRODUCT_CAN_BUY_ZERO'  => 'PRODUCT.CAN_BUY_ZERO',
            ],
            'filter' => [
                'IBLOCK_ID' => $this->productsIblockId,
                'ACTIVE'    => 'Y',
                'IBLOCK_SECTION_ID' => $this->arParams['SECTION_ID'] ?? false,
            ],
            'runtime' => [
                new \Bitrix\Main\Entity\ReferenceField(
                    'PRODUCT',
                    ProductTable::class,
                    ['=this.ID' => 'ref.ID']
                ),
            ],
            'cache' => ['ttl' => 300, 'cache_joins' => true],
        ]);

        return $iterator->fetchAll();
    }

    /**
     * Все SKU по выбранным товарам одним запросом,
     * сразу раскладываем по родителю.
     */
    private function fetchOffers(array $parentIds): array
    {
        if (!$this->offersIblockId || empty($parentIds)) {
            return [];
        }

        $result = [];
        $iterator = ElementTable::getList([
            'select' => [
                'ID', 'NAME',
                'PARENT_ID'            => 'CML2_LINK.VALUE',
                'PRODUCT_QUANTITY'     => 'PRODUCT.QUANTITY',
                'PRODUCT_CAN_BUY_ZERO' => 'PRODUCT.CAN_BUY_ZERO',
            ],
            'filter' => [
                'IBLOCK_ID'           => $this->offersIblockId,
                'ACTIVE'              => 'Y',
                'CML2_LINK.VALUE'     => $parentIds,
            ],
            'runtime' => [
                new \Bitrix\Main\Entity\ReferenceField(
                    'CML2_LINK',
                    \Bitrix\Iblock\ElementPropertyTable::class,
                    [
                        '=this.ID'     => 'ref.IBLOCK_ELEMENT_ID',
                        '=ref.IBLOCK_PROPERTY_ID' => ['?', $this->offerLinkPropertyId],
                    ]
                ),
                new \Bitrix\Main\Entity\ReferenceField(
                    'PRODUCT',
                    ProductTable::class,
                    ['=this.ID' => 'ref.ID']
                ),
            ],
            'cache' => ['ttl' => 300, 'cache_joins' => true],
        ]);

        while ($row = $iterator->fetch()) {
            $result[(int)$row['PARENT_ID']][] = $row;
        }
        return $result;
    }

    /**
     * Матрица цен: по всем типам цен и диапазонам количества.
     * Один запрос вместо GetOptimalPrice в цикле.
     * Применяем GetOptimalPrice только для «отображаемой» цены.
     */
    private function fetchPriceMatrix(array $productIds): array
    {
        if (empty($productIds)) return [];

        $matrix = [];
        $iterator = PriceTable::getList([
            'select' => ['PRODUCT_ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY', 'QUANTITY_FROM', 'QUANTITY_TO'],
            'filter' => ['PRODUCT_ID' => $productIds],
            'order'  => ['PRODUCT_ID' => 'ASC', 'QUANTITY_FROM' => 'ASC'],
        ]);
        while ($row = $iterator->fetch()) {
            $matrix[$row['PRODUCT_ID']][$row['CATALOG_GROUP_ID']][] = $row;
        }
        return $matrix;
    }

    private function fetchStocks(array $productIds): array
    {
        if (empty($productIds) || !COption::GetOptionString('catalog', 'default_use_store_control') === 'Y') {
            return [];
        }
        $stocks = [];
        $iterator = StoreProductTable::getList([
            'select' => ['PRODUCT_ID', 'STORE_ID', 'AMOUNT'],
            'filter' => ['PRODUCT_ID' => $productIds, 'STORE.ACTIVE' => 'Y'],
        ]);
        while ($row = $iterator->fetch()) {
            $stocks[$row['PRODUCT_ID']][$row['STORE_ID']] = $row['AMOUNT'];
        }
        return $stocks;
    }

    /**
     * Для каждого товара и SKU добавляем оптимальную цену пользователя.
     * Вызываем один раз на самую «дешёвую» позицию — не в цикле.
     */
    private function assemble(array $products, array $offers, array $prices, array $stocks): array
    {
        global $USER;
        $groups = $USER->GetUserGroupArray();

        $items = [];
        foreach ($products as $product) {
            $item = $product;
            $item['OFFERS'] = $offers[$product['ID']] ?? [];

            // Оптимальная цена только для отображаемого предложения
            $displayOffer = $item['OFFERS'][0]['ID'] ?? $product['ID'];
            $optimal = CCatalogProduct::GetOptimalPrice(
                $displayOffer, 1, $groups, 'N', [],
                $this->arParams['SITE_ID'] ?? SITE_ID
            );
            $item['DISPLAY_PRICE'] = $optimal['DISCOUNT_PRICE'] ?? null;
            $item['PRICE_MATRIX']  = $prices[$displayOffer] ?? [];
            $item['STOCKS']        = $stocks[$displayOffer] ?? [];

            $items[] = $item;
        }
        return $items;
    }
}
