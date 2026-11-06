<?php

/**
 * Заявки для контрольного задания.
 *
 * Путь в проекте: /local/php_interface/classes/Requests/OrderTable.php
 */

namespace MyCompany\Requests;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class OrderTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'mycompany_request';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new StringField('TITLE'))->configureRequired()->configureSize(255),
            (new StringField('STATUS'))->configureRequired()->configureSize(20),
            (new IntegerField('AUTHOR_ID'))->configureRequired(),
            (new DatetimeField('CLOSED_AT')),
            (new TextField('CLOSE_COMMENT')),
            (new DatetimeField('UPDATED_AT'))->configureRequired(),
        ];
    }
}
