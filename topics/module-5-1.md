# Модуль 5.1: Каталог товаров (уровень senior)

## Теория

### Архитектура каталога в Bitrix

Каталог — это не один инфоблок, а связка сущностей:

- **Инфоблок товаров** (`CATALOG`) — базовые товары, разделы, свойства.
- **Инфоблок торговых предложений (SKU, offers)** — дочерний инфоблок, связанный через `CATALOG_SKU_PROPERTY_ID` (свойство `CML2_LINK`).
- **b_catalog_product** — «товарная» часть: вес, габариты, количество (`QUANTITY`), тип товара (`TYPE`: 1 — простой, 3 — набор SKU, 4 — набор, 5 — комплект).
- **b_catalog_price** — цены по типам (`CATALOG_GROUP_ID`) и диапазонам количества (`QUANTITY_FROM/TO`).
- **b_catalog_store_product** — остатки по складам.
- **b_catalog_discount*** — скидки (простые и программируемые).
- **b_catalog_measure_ratio** — коэффициенты единиц измерения.

Ключевая мысль для собеседования: *элемент инфоблока ≠ товар*. Товар появляется только после записи в `b_catalog_product` (`\Bitrix\Catalog\ProductTable::add`), цена — отдельно (`\Bitrix\Catalog\PriceTable`), остаток по складам — отдельно.

### D7-API каталога (важно для senior)

```php
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\StoreProductTable;
use Bitrix\Catalog\GroupTable;
use Bitrix\Catalog\MeasureRatioTable;
use Bitrix\Iblock\ElementTable;

// Цена товара для конкретного типа цен и диапазона
PriceTable::getList([
    'filter' => [
        'PRODUCT_ID' => $productId,
        'CATALOG_GROUP_ID' => $priceTypeId,
        [
            'LOGIC' => 'OR',
            ['QUANTITY_FROM' => false],
            ['<=QUANTITY_FROM' => $qty],
        ],
    ],
    'order' => ['QUANTITY_FROM' => 'DESC'],
    'limit' => 1,
]);
```

Получение SKU товара через инфоблок-провайдер:

```php
$offersIblockId = \Bitrix\Catalog\OfferIblockTable::getIblockIdByProductIblockId($productIblockId);
// или через SKUInfo: \CCatalogSKU::GetInfoByProductIBlock($iblockId)
```

Развёртывание цены с учётом прав групп пользователя — не через прямой запрос к `b_catalog_price`, а через `\CCatalogGroup::GetListEx` + `\CCatalogProduct::GetOptimalPrice` либо `catalog.product.provider` / `\Bitrix\Catalog\Product\Price\Calculation` (новое ядро). Senior-ответ на «как правильно брать цену»: **не JOIN-ить b_catalog_price вручную с MAX/MIN**, а использовать `CCatalogProduct::GetOptimalPrice` или `Bitrix\Catalog\PriceTable` с фильтром по диапазону количества и типам цен группы пользователя.

### Резервирование и остатки

- `QUANTITY` — суммарный остаток; `QUANTITY_RESERVED` — зарезервировано.
- Складской учёт включается настройкой модуля catalog (`default_use_store_control`).
- При включённом СУ доступность считается через `\Bitrix\Catalog\StoreProductTable`, отгрузки — `b_catalog_store_barcode`/`Shipment`.
- Отрицательный остаток и «продажа при нулевом» — настройки `CAN_BUY_ZERO`, `NEGATIVE_AMOUNT_TRACE` (поля `b_catalog_product`).

### Производительность каталога (типичные ловушки)

1. **N+1 по ценам**: выбирать цены пакетно по массиву PRODUCT_ID, а не по одному.
2. **CIBlockElement::GetList с `PROPERTY_*`** в большом каталоге — деградирует. Senior-решение: свойства-фильтры выносить в отдельное поле/`catalog.sku` предвычисления, использовать faceted-индексы (`b_iblock_element_prop_s*` / `catalog.section` + `CIBlockSectionPropertyLink`), умный фильтр.
3. **GetList по SKU без учёта ACTIVE родителя** — получите «сирот». Джойнить родителя и проверять.
4. Кеширование: `tagged cache` по тегам `iblock_id_N`, `catalog_group`, `catalog_product_N`; инвалидировать в обработчиках `OnPriceUpdate`, `OnProductUpdate`.
5. Для больших импортов — отключать индексацию поиска и переиндексацию скидок на время импорта, батчи по 500–1000.

### Скидки

- Простые скидки каталога: `catalog.discount` (админка + `\Bitrix\Catalog\DiscountTable`).
- Программируемые правила корзины (`sale.discount`) — модуль sale, но действуют на корзину.
- Своё условие скидки — наследник `CSaleActionCtrlAction`/`ExtendCondition` через событие `OnCondSaleActionsControlBuildList` / `OnCondSaleControlBuildList`.
- Кастомный «тип цены» = `catalog.group` + права через `catalog.group.access` (`\CCatalogGroup::GetListEx`).

## Вопросы для собеседования

1. **Чем отличается товар, торговое предложение и элемент инфоблока?**
   Элемент инфоблока — контентная сущность (название, картинки, SEO). Товар — запись в `b_catalog_product` (остатки, тип, доступность). SKU — элемент дочернего инфоблока со своей записью в `b_catalog_product`, связанный с родителем через свойство `CML2_LINK`.

2. **Как получить минимальную цену товара для текущего пользователя?**
   `CCatalogProduct::GetOptimalPrice($productId, $quantity=1, $userGroups, $renewal='N', $prices=[], $siteId, $discounts)` — учитывает типы цен групп пользователя, диапазоны количества и скидки. Пакетный вариант — `\CCatalogProduct::GetOptimalPrice` на массиве или `catalog.product.price` API.

3. **Как организовать оптовые цены «от 10 штук»?**
   Тип цены `catalog.group` + записи в `b_catalog_price` с `QUANTITY_FROM=10`. Выборка — `GetOptimalPrice` с нужным `$quantity`.

4. **Как сделать свой тип цены и ограничить его группой?**
   `GroupTable::add` → запись в `b_catalog_group`; доступ — `CCatalogGroup::GetListEx`/`userAccess`; для юзера — включить тип цены в его видимые (`CUser::GetUserGroup` + `catalog_group` привязки) либо настраиваемый `GetOptimalPrice` с переопределённым массивом типов цен.

5. **Товар имеет 3 склада. Как считается доступность при включённом складском учёте?**
   `QUANTITY` в `b_catalog_product` — кеш суммы; реальные остатки в `b_catalog_store_product`. При списании пишется отгрузка (`sale.shipment_item` + `store_product`), `QUANTITY` пересчитывается.

6. **Как отключить резервирование для конкретного товара?**
   Поле `QUANTITY_TRACE`/`CAN_BUY_ZERO`/`SUBSCRIBE` в `b_catalog_product` (`\CCatalogProduct::Update`) + настройка модуля `enable_reservation`.

7. **Как массово обновить 200k цен и не уронить сайт?**
   Батчи, `Agent`/cron ночью, прямые `PriceTable::update`/`merge` (не через `CPrice::Update` в цикле — он дёргает пересчёт скидок и индексацию), отключить `OnPriceUpdate`-обработчики, переиндексировать фасеты после.

8. **Что делает событие `OnGetOptimalPrice`?**
   Позволяет подменить результат расчёта цены (кастомная валютная политика, внешний прайс). Возвращаемый массив — полный формат `GetOptimalPrice`.

9. **Как связать SKU с товаром программно?**
   Свойство `CML2_LINK` в инфоблоке предложений + `ProductTable::add` для SKU. Через `\CIBlockElement::SetPropertyValuesEx` (`CML2_LINK => parentId`).

10. **Чем faceted search отличается от фильтра по свойствам?**
    Умный фильтр строит предвычисленные индексы (`b_iblock_element_prop_sN`, `b_iblock_0N_index`) — запрос по готовым битовым/диапазонным таблицам вместо JOIN по `b_iblock_element_property`.

## Частые ошибки

- `CPrice::GetBasePrice`/`Add` в цикле по 50k товаров — таймаут и блокировки.
- Обновление цены без `PriceTable::update` → скидки/индексы не пересчитались (или наоборот — обновляют через лёгкие сущности и теряют события).
- Фильтрация по `CATALOG_QUANTITY > 0` без `CAN_BUY_ZERO` — пропустили товары «под заказ».
- Импорт SKU без `CML2_LINK` — предложения «сироты».
- Один и тот же `PRODUCT_ID` в нескольких типах цен — выбирать нужный `CATALOG_GROUP_ID`, иначе цена «не та».

## Практические задачи

- **Кейс 1**: `examples/module-5-1/smart-catalog` — компонент выборки товаров с развёрнутыми SKU, оптовыми диапазонами цен и пакетным GetOptimalPrice.
- **Кейс 2**: `examples/module-5-1/price-stock-import` — сервис ночного импорта цен/остатков из CSV через очередь + батчи + лог + транзакционная целостность.
- **Кейс 3**: `examples/module-5-1/custom-price-engine` — кастомный тип цены «Партнёр» с процентом наценки по категориям и перехватом `OnGetOptimalPrice`.

**Решения находятся в `examples/module-5-1/`**
