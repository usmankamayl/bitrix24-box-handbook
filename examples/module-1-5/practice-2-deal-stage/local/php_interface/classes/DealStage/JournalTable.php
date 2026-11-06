<?php

/**
 * ORM-сущность журнала идемпотентности.
 *
 * Путь в проекте: /local/php_interface/classes/DealStage/JournalTable.php
 * Namespace регистрируется в /local/php_interface/init.php (как в 1.6):
 *
 *     Loader::registerNamespace('MyCompany\\', '/local/php_interface/classes/');
 *
 * Таблица: install.sql из этой же папки (mycompany_deal_stage_request).
 * Сама ORM подробно — в модуле 2; здесь она нужна только как типизированный
 * доступ к таблице и как источник DuplicateEntryException по UNIQUE-ключу.
 */

namespace MyCompany\DealStage;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class JournalTable extends DataManager
{
    public const PENDING_CODE = 0;

    public static function getTableName(): string
    {
        return 'mycompany_deal_stage_request';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),

            (new IntegerField('USER_ID'))
                ->configureRequired(),

            (new StringField('REQUEST_ID'))
                ->configureRequired()
                ->configureSize(64),

            (new IntegerField('DEAL_ID'))
                ->configureRequired(),

            (new StringField('STAGE_ID'))
                ->configureRequired()
                ->configureSize(50),

            (new IntegerField('HTTP_CODE'))
                ->configureRequired()
                ->configureDefaultValue(self::PENDING_CODE),

            (new TextField('RESPONSE'))
                ->configureNullable(),

            (new DatetimeField('CREATED_AT'))
                ->configureRequired(),
        ];
    }
}
