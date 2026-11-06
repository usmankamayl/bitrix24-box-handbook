<?php
namespace Custom\Catalog;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Таблица категорий товаров
 *
 * Демонстрирует:
 * - Древовидную структуру (parent-child)
 * - Связи между таблицами через ReferenceField
 */
class CategoryTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'custom_categories';
    }

    public static function getMap()
    {
        return [
            // ID категории
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID',
            ]),

            // Название категории
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

            // ID родительской категории
            new Entity\IntegerField('PARENT_ID', [
                'title' => 'Родительская категория',
            ]),

            // Активность
            new Entity\BooleanField('ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'Y',
                'title' => 'Активна',
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

            // Связь с родительской категорией (One-to-One)
            new Entity\ReferenceField(
                'PARENT',
                self::class,
                ['=this.PARENT_ID' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
        ];
    }

    /**
     * Получить корневые категории (без родителя)
     *
     * @return array
     */
    public static function getRootCategories()
    {
        $result = self::getList([
            'select' => ['ID', 'NAME', 'CODE', 'SORT'],
            'filter' => [
                '=ACTIVE' => 'Y',
                '=PARENT_ID' => false, // NULL
            ],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить дерево категорий с подсчетом товаров
     *
     * @return array
     */
    public static function getCategoriesTree()
    {
        $result = self::getList([
            'select' => [
                'ID',
                'NAME',
                'CODE',
                'PARENT_ID',
                'SORT',
                'PRODUCTS_COUNT',
            ],
            'filter' => ['=ACTIVE' => 'Y'],
            'runtime' => [
                new Entity\ReferenceField(
                    'PRODUCTS',
                    ProductTable::class,
                    ['=this.ID' => 'ref.CATEGORY_ID'],
                    ['join_type' => 'LEFT']
                ),
                new Entity\ExpressionField(
                    'PRODUCTS_COUNT',
                    'COUNT(DISTINCT %s)',
                    ['PRODUCTS.ID']
                ),
            ],
            'group' => ['ID'],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);

        $categories = $result->fetchAll();

        // Построение дерева
        return self::buildTree($categories);
    }

    /**
     * Построить древовидную структуру из плоского массива
     *
     * @param array $elements
     * @param int|null $parentId
     * @return array
     */
    private static function buildTree(array $elements, $parentId = null)
    {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['PARENT_ID'] == $parentId) {
                $children = self::buildTree($elements, $element['ID']);
                if ($children) {
                    $element['CHILDREN'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }
}
