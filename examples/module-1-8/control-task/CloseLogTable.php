<?php

/**
 * Журнал действий по заявке.
 *
 * Путь в проекте: /local/php_interface/classes/Requests/CloseLogTable.php
 */

namespace MyCompany\Requests;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class CloseLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'mycompany_request_log';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new IntegerField('REQUEST_ID'))->configureRequired(),
            (new StringField('ACTION'))->configureRequired()->configureSize(32),
            (new TextField('COMMENT')),
            (new DatetimeField('CREATED_AT'))->configureRequired(),
        ];
    }
}
