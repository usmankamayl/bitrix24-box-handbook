<?php

/**
 * ORM-сущность заявки.
 *
 * Путь в проекте: /local/php_interface/classes/Requests/OrderTable.php
 * Namespace MyCompany\Requests — автозагрузка как в 1.6:
 *
 *     Loader::registerNamespace('MyCompany\\', '/local/php_interface/classes/');
 *
 * Таблица: install.sql из этой же папки (mycompany_request).
 *
 * Сущность намеренно тонкая: вся логика — в OrderStatusService. Здесь только
 * схема и типы, чтобы ORM не превращалась в место, где «заодно» живут правила.
 */

namespace MyCompany\Requests;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class OrderTable extends DataManager
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_WORK = 'in_work';
    public const STATUS_CLOSED = 'closed';

    public static function getTableName(): string
    {
        return 'mycompany_request';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new StringField('TITLE'))
                ->configureRequired()
                ->configureSize(255),

            (new StringField('STATUS'))
                ->configureRequired()
                ->configureDefaultValue(self::STATUS_NEW)
                ->configureSize(20),

            (new IntegerField('AUTHOR_ID'))
                ->configureRequired(),

            (new DatetimeField('CLOSED_AT')),

            (new TextField('CLOSE_COMMENT')),

            (new DatetimeField('UPDATED_AT'))
                ->configureRequired(),
        ];
    }
}
