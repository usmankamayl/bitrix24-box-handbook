<?php

/**
 * Остатки по складам.
 *
 * Путь в проекте: /local/php_interface/classes/Stock/StockTable.php
 * Таблица: install.sql этой же папки (mycompany_stock).
 */

namespace MyCompany\Stock;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class StockTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'mycompany_stock';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('PRODUCT_ID'))
                ->configureRequired(),

            (new StringField('WAREHOUSE_CODE'))
                ->configureRequired()
                ->configureSize(20),

            (new IntegerField('QUANTITY'))
                ->configureRequired()
                ->configureDefaultValue(0),
        ];
    }
}
