<?php

/**
 * ORM-сущность «Заказы».
 *
 * Путь в проекте: /local/modules/mycompany.orders/lib/OrderTable.php
 *
 * Модульная автозагрузка: namespace Mycompany\Orders зарегистрирован на каталог
 * lib/ в include.php модуля (Loader::registerNamespace). Поэтому имя класса
 * OrderTable + этот файл = Mycompany\Orders\OrderTable без require_once.
 */

namespace Mycompany\Orders;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;

final class OrderTable extends DataManager
{
    /**
     * Полное имя таблицы в БД. Ядро префикс не дописывает:
     * UserTable возвращает 'b_user', этот класс — 'mycompany_orders'
     * (таблица создаётся миграцией модуля, см. install/db/install.sql).
     */
    public static function getTableName(): string
    {
        return 'mycompany_orders';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete()
                ->configureTitle('ID'),

            (new IntegerField('USER_ID'))
                ->configureRequired()
                ->configureTitle('ID пользователя'),

            (new DatetimeField('DATE_CREATE'))
                ->configureDefaultValue(static fn() => new DateTime())
                ->configureTitle('Дата создания'),

            (new FloatField('PRICE'))
                ->configureDefaultValue(0.0)
                ->configureTitle('Сумма'),

            // configureSize не только описывает колонку, но и обрезает значение
            // в StringField::cast() — защита от «пришло 10 КБ вместо статуса».
            (new StringField('STATUS'))
                ->configureSize(50)
                ->configureDefaultValue('NEW')
                ->configureTitle('Статус'),

            // LEFT JOIN к b_user. Join::on(...) вместо строкового массива:
            // так условие собирается билдером, а не угадывается по формату.
            (new Reference(
                'USER',
                UserTable::class,
                Join::on('this.USER_ID', 'ref.ID')
            ))->configureJoinType(Join::TYPE_LEFT),
        ];
    }
}
