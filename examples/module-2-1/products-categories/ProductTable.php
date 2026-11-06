<?php
namespace Custom\Catalog;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Таблица товаров
 *
 * Демонстрирует:
 * - Связь Many-to-One с категориями
 * - Использование ReferenceField для JOIN
 * - Фильтрацию по связанным таблицам
 */
class ProductTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'custom_products';
    }

    public static function getMap()
    {
        return [
            // ID товара
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID',
            ]),

            // Название товара
            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
                'title' => 'Название',
            ]),

            // Символьный код
            new Entity\StringField('CODE', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
                'title' => 'Символьный код',
            ]),

            // ID категории
            new Entity\IntegerField('CATEGORY_ID', [
                'required' => true,
                'title' => 'ID категории',
            ]),

            // Цена
            new Entity\FloatField('PRICE', [
                'required' => true,
                'title' => 'Цена',
            ]),

            // Старая цена (для отображения скидки)
            new Entity\FloatField('OLD_PRICE', [
                'title' => 'Старая цена',
            ]),

            // Количество на складе
            new Entity\IntegerField('QUANTITY', [
                'default_value' => 0,
                'title' => 'Количество',
            ]),

            // Активность
            new Entity\BooleanField('ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y',
                'title' => 'Активен',
            ]),

            // Сортировка
            new Entity\IntegerField('SORT', [
                'default_value' => 500,
                'title' => 'Сортировка',
            ]),

            // Дата создания
            new Entity\DatetimeField('DATE_CREATE', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания',
            ]),

            // Связь с категорией (Many-to-One)
            new Entity\ReferenceField(
                'CATEGORY',
                CategoryTable::class,
                ['=this.CATEGORY_ID' => 'ref.ID'],
                ['join_type' => 'INNER']
            ),
        ];
    }

    /**
     * Получить товары с информацией о категории
     *
     * @param array $filter
     * @param int $limit
     * @return array
     */
    public static function getProductsWithCategory(array $filter = [], $limit = 50)
    {
        $result = self::getList([
            'select' => [
                'ID',
                'NAME',
                'CODE',
                'PRICE',
                'OLD_PRICE',
                'QUANTITY',
                'CATEGORY_ID',
                'CATEGORY_NAME' => 'CATEGORY.NAME',
                'CATEGORY_CODE' => 'CATEGORY.CODE',
            ],
            'filter' => array_merge([
                '=ACTIVE' => 'Y',
                '=CATEGORY.ACTIVE' => 'Y',
            ], $filter),
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
            'limit' => $limit,
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить товары по символьному коду категории
     *
     * @param string $categoryCode
     * @return array
     */
    public static function getProductsByCategoryCode($categoryCode)
    {
        $result = self::getList([
            'select' => ['ID', 'NAME', 'PRICE', 'QUANTITY'],
            'filter' => [
                '=ACTIVE' => 'Y',
                '=CATEGORY.CODE' => $categoryCode,
                '=CATEGORY.ACTIVE' => 'Y',
            ],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить статистику по категориям
     *
     * @return array
     */
    public static function getCategoryStatistics()
    {
        $result = self::getList([
            'select' => [
                'CATEGORY_ID',
                'CATEGORY_NAME' => 'CATEGORY.NAME',
                'PRODUCTS_COUNT',
                'AVG_PRICE',
                'MIN_PRICE',
                'MAX_PRICE',
                'TOTAL_QUANTITY',
            ],
            'filter' => [
                '=ACTIVE' => 'Y',
            ],
            'runtime' => [
                new Entity\ExpressionField('PRODUCTS_COUNT', 'COUNT(*)'),
                new Entity\ExpressionField('AVG_PRICE', 'AVG(%s)', ['PRICE']),
                new Entity\ExpressionField('MIN_PRICE', 'MIN(%s)', ['PRICE']),
                new Entity\ExpressionField('MAX_PRICE', 'MAX(%s)', ['PRICE']),
                new Entity\ExpressionField('TOTAL_QUANTITY', 'SUM(%s)', ['QUANTITY']),
            ],
            'group' => ['CATEGORY_ID'],
            'order' => ['PRODUCTS_COUNT' => 'DESC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить товары со скидкой
     *
     * @return array
     */
    public static function getDiscountedProducts()
    {
        $result = self::getList([
            'select' => [
                'ID',
                'NAME',
                'PRICE',
                'OLD_PRICE',
                'DISCOUNT_PERCENT',
                'CATEGORY_NAME' => 'CATEGORY.NAME',
            ],
            'filter' => [
                '=ACTIVE' => 'Y',
                '!OLD_PRICE' => false,
                '>OLD_PRICE' => 0,
            ],
            'runtime' => [
                new Entity\ExpressionField(
                    'DISCOUNT_PERCENT',
                    'ROUND((1 - %s / %s) * 100, 0)',
                    ['PRICE', 'OLD_PRICE']
                ),
            ],
            'order' => ['DISCOUNT_PERCENT' => 'DESC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить товары заканчивающиеся на складе
     *
     * @param int $threshold
     * @return array
     */
    public static function getLowStockProducts($threshold = 10)
    {
        $result = self::getList([
            'select' => [
                'ID',
                'NAME',
                'QUANTITY',
                'PRICE',
                'CATEGORY_NAME' => 'CATEGORY.NAME',
            ],
            'filter' => [
                '=ACTIVE' => 'Y',
                '>QUANTITY' => 0,
                '<QUANTITY' => $threshold,
            ],
            'order' => ['QUANTITY' => 'ASC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Поиск товаров по нескольким критериям
     *
     * @param string $query
     * @return array
     */
    public static function searchProducts($query)
    {
        $result = self::getList([
            'select' => [
                'ID',
                'NAME',
                'PRICE',
                'CATEGORY_NAME' => 'CATEGORY.NAME',
            ],
            'filter' => [
                '=ACTIVE' => 'Y',
                [
                    'LOGIC' => 'OR',
                    ['%NAME' => "%{$query}%"],
                    ['%CODE' => "%{$query}%"],
                    ['%CATEGORY.NAME' => "%{$query}%"],
                ],
            ],
            'order' => ['NAME' => 'ASC'],
        ]);

        return $result->fetchAll();
    }
}
