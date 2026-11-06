<?php
namespace Custom\Orders;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Таблица заказов для демонстрации агрегирующих запросов
 *
 * Демонстрирует:
 * - Группировку данных (GROUP BY)
 * - Агрегатные функции (COUNT, SUM, AVG, MIN, MAX)
 * - Runtime-поля с ExpressionField
 * - Сложные запросы для статистики
 */
class OrderTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'custom_orders';
    }

    public static function getMap()
    {
        return [
            // ID заказа
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID',
            ]),

            // Номер заказа
            new Entity\StringField('ORDER_NUMBER', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
                'title' => 'Номер заказа',
            ]),

            // ID покупателя
            new Entity\IntegerField('USER_ID', [
                'required' => true,
                'title' => 'ID покупателя',
            ]),

            // Статус заказа
            new Entity\EnumField('STATUS', [
                'required' => true,
                'values' => ['NEW', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'],
                'default_value' => 'NEW',
                'title' => 'Статус',
            ]),

            // Сумма заказа
            new Entity\FloatField('TOTAL_AMOUNT', [
                'required' => true,
                'title' => 'Сумма заказа',
            ]),

            // Сумма скидки
            new Entity\FloatField('DISCOUNT_AMOUNT', [
                'default_value' => 0,
                'title' => 'Скидка',
            ]),

            // Итоговая сумма с учетом скидки
            new Entity\FloatField('FINAL_AMOUNT', [
                'required' => true,
                'title' => 'Итоговая сумма',
            ]),

            // Способ оплаты
            new Entity\StringField('PAYMENT_METHOD', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
                'title' => 'Способ оплаты',
            ]),

            // Оплачен?
            new Entity\BooleanField('IS_PAID', [
                'values' => ['N', 'Y'],
                'default_value' => 'N',
                'title' => 'Оплачен',
            ]),

            // Дата создания
            new Entity\DatetimeField('DATE_CREATE', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания',
            ]),

            // Дата оплаты
            new Entity\DatetimeField('DATE_PAID', [
                'title' => 'Дата оплаты',
            ]),

            // Дата доставки
            new Entity\DatetimeField('DATE_DELIVERED', [
                'title' => 'Дата доставки',
            ]),

            // Город доставки
            new Entity\StringField('DELIVERY_CITY', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 100),
                    ];
                },
                'title' => 'Город доставки',
            ]),

            // Регион
            new Entity\StringField('DELIVERY_REGION', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 100),
                    ];
                },
                'title' => 'Регион',
            ]),

            // Источник заказа
            new Entity\StringField('SOURCE', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 50),
                    ];
                },
                'title' => 'Источник',
                'default_value' => 'website',
            ]),
        ];
    }

    /**
     * Получить статистику продаж за период
     *
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     * @return array
     */
    public static function getSalesStatistics(DateTime $dateFrom, DateTime $dateTo)
    {
        $result = self::getList([
            'select' => [
                'TOTAL_COUNT',
                'TOTAL_REVENUE',
                'AVG_ORDER',
                'MIN_ORDER',
                'MAX_ORDER',
                'PAID_COUNT',
            ],
            'filter' => [
                '><DATE_CREATE' => [$dateFrom, $dateTo],
            ],
            'runtime' => [
                new Entity\ExpressionField('TOTAL_COUNT', 'COUNT(*)'),
                new Entity\ExpressionField('TOTAL_REVENUE', 'SUM(%s)', ['FINAL_AMOUNT']),
                new Entity\ExpressionField('AVG_ORDER', 'AVG(%s)', ['FINAL_AMOUNT']),
                new Entity\ExpressionField('MIN_ORDER', 'MIN(%s)', ['FINAL_AMOUNT']),
                new Entity\ExpressionField('MAX_ORDER', 'MAX(%s)', ['FINAL_AMOUNT']),
                new Entity\ExpressionField('PAID_COUNT', 'SUM(IF(%s = "Y", 1, 0))', ['IS_PAID']),
            ],
        ]);

        return $result->fetch();
    }

    /**
     * Получить статистику по статусам
     *
     * @return array
     */
    public static function getStatusStatistics()
    {
        $result = self::getList([
            'select' => [
                'STATUS',
                'COUNT',
                'TOTAL_AMOUNT',
            ],
            'runtime' => [
                new Entity\ExpressionField('COUNT', 'COUNT(*)'),
                new Entity\ExpressionField('TOTAL_AMOUNT', 'SUM(%s)', ['FINAL_AMOUNT']),
            ],
            'group' => ['STATUS'],
            'order' => ['COUNT' => 'DESC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить топ покупателей
     *
     * @param int $limit
     * @return array
     */
    public static function getTopBuyers($limit = 10)
    {
        $result = self::getList([
            'select' => [
                'USER_ID',
                'ORDERS_COUNT',
                'TOTAL_SPENT',
                'AVG_ORDER',
            ],
            'runtime' => [
                new Entity\ExpressionField('ORDERS_COUNT', 'COUNT(*)'),
                new Entity\ExpressionField('TOTAL_SPENT', 'SUM(%s)', ['FINAL_AMOUNT']),
                new Entity\ExpressionField('AVG_ORDER', 'AVG(%s)', ['FINAL_AMOUNT']),
            ],
            'group' => ['USER_ID'],
            'order' => ['TOTAL_SPENT' => 'DESC'],
            'limit' => $limit,
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить статистику по городам
     *
     * @return array
     */
    public static function getCityStatistics()
    {
        $result = self::getList([
            'select' => [
                'DELIVERY_CITY',
                'ORDERS_COUNT',
                'TOTAL_REVENUE',
                'AVG_ORDER',
            ],
            'filter' => [
                '!DELIVERY_CITY' => false, // исключаем NULL
            ],
            'runtime' => [
                new Entity\ExpressionField('ORDERS_COUNT', 'COUNT(*)'),
                new Entity\ExpressionField('TOTAL_REVENUE', 'SUM(%s)', ['FINAL_AMOUNT']),
                new Entity\ExpressionField('AVG_ORDER', 'AVG(%s)', ['FINAL_AMOUNT']),
            ],
            'group' => ['DELIVERY_CITY'],
            'order' => ['ORDERS_COUNT' => 'DESC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Получить конверсию оплаты по источникам
     *
     * @return array
     */
    public static function getPaymentConversionBySource()
    {
        $result = self::getList([
            'select' => [
                'SOURCE',
                'TOTAL_ORDERS',
                'PAID_ORDERS',
                'CONVERSION_RATE',
            ],
            'runtime' => [
                new Entity\ExpressionField('TOTAL_ORDERS', 'COUNT(*)'),
                new Entity\ExpressionField('PAID_ORDERS', 'SUM(IF(%s = "Y", 1, 0))', ['IS_PAID']),
                new Entity\ExpressionField(
                    'CONVERSION_RATE',
                    'ROUND(SUM(IF(%s = "Y", 1, 0)) / COUNT(*) * 100, 2)',
                    ['IS_PAID']
                ),
            ],
            'group' => ['SOURCE'],
            'order' => ['CONVERSION_RATE' => 'DESC'],
        ]);

        return $result->fetchAll();
    }
}
