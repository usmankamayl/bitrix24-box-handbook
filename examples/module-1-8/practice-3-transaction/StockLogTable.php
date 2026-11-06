<?php

/**
 * Журнал перемещений. Пишется в той же транзакции, что и остатки.
 *
 * Путь в проекте: /local/php_interface/classes/Stock/StockLogTable.php
 * UNIQUE(OP_KEY) — вторая линия защиты от повтора: если проверка «операция уже
 * была» не увидела чужую незакоммиченную транзакцию, уникальный индекс не даст
 * записать дубль.
 */

namespace MyCompany\Stock;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class StockLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'mycompany_stock_log';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('PRODUCT_ID'))
                ->configureRequired(),

            (new StringField('WAREHOUSE_FROM'))
                ->configureRequired()
                ->configureSize(20),

            (new StringField('WAREHOUSE_TO'))
                ->configureRequired()
                ->configureSize(20),

            (new IntegerField('QUANTITY'))
                ->configureRequired(),

            (new StringField('OP_KEY'))
                ->configureRequired()
                ->configureSize(64),

            (new DatetimeField('CREATED_AT'))
                ->configureRequired(),
        ];
    }
}
