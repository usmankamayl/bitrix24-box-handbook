# Кейс 1: Smart Catalog — товары с SKU, оптовыми ценами и остатками

## Что решает

Типичная senior-задача: вывести каталог с торговыми предложениями (цвет/размер), показать оптовую сетку цен и наличие по складам — **без N+1 запросов** и с ajax-подгрузкой цены при смене SKU.

## Структура

```
smart-catalog/
├── .description.php
├── .parameters.php
├── class.php              # вся логика выборки и ajax-экшен
├── component.php
└── templates/.default/
    ├── template.php
    ├── style.css
    └── script.js
```

## Ключевые приёмы (на что обратить внимание на собеседовании)

1. **Пакетная загрузка.** Цены (`PriceTable`) и остатки (`StoreProductTable`) выбираются **одним запросом на весь набор ID**, а не в цикле по каждому товару — классическая проблема N+1 в Bitrix-компонентах.

2. **ORM вместо `CIBlockElement::GetList`.** Используется `ElementTable::getList` с `runtime`-полями (`ReferenceField`) для JOIN'а с `b_catalog_product` — это быстрее и позволяет фильтровать/сортировать по полям каталога напрямую в SQL.

3. **Связь товар ↔ SKU через `CML2_LINK`.** Находим инфоблок предложений через `CCatalogSKU::GetInfoByProductIBlock`, затем джойним `ElementPropertyTable` по ID свойства-привязки.

4. **`GetOptimalPrice` только там, где нужно.** Полный расчёт цены с учётом скидок, групп и валюты — дорогая операция, поэтому вызывается один раз на «отображаемое» предложение, а не на каждую строку матрицы цен.

5. **Ajax без перезагрузки списка.** При смене SKU в `<select>` дергаем `configureActions()`/`getOfferPriceAction()` — пересчитываем только цену и наличие конкретного предложения, а не весь каталог.

## Как использовать

1. Скопировать папку в `/local/components/bitrix_course/smart.catalog/`.
2. Подключить в шаблоне сайта:

```php
$APPLICATION->IncludeComponent(
    'bitrix_course:smart.catalog',
    '.default',
    [
        'IBLOCK_ID'  => 21,
        'SECTION_ID' => 0,
        'SITE_ID'    => SITE_ID,
    ]
);
```

3. Убедиться, что модули `iblock` и `catalog` установлены, инфоблок товаров связан с инфоблоком предложений через SKU-свойство (`CML2_LINK`).

## Возможные вопросы на собеседовании по этому коду

- Почему цены выбираются одним запросом, а не через `GetOptimalPrice` в цикле? *(производительность: один SQL против N запросов + N пересчётов скидок)*
- Что будет, если у товара есть SKU, но нет записи в `b_catalog_product`? *(SKU не попадёт в выборку, т.к. JOIN с `ProductTable` через `INNER`-подобную логику ReferenceField вернёт NULL — на практике стоит проверять `PRODUCT_QUANTITY !== null`)*
- Как добавить кеширование результата компонента целиком? *(managed cache / `$this->StartResultCache()` с тегами по `iblock_id_N` и `catalog_group`)*
