<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * Проектный журнал: строки, которые копятся от прикладного кода
 * (загрузки, интеграции, бизнес-события) и подлежат удалению по сроку.
 */
final class LogTable extends DataManager
{
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    public static function getTableName(): string
    {
        return 'mycompany_agents_log';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new StringField('CODE', [
                'required' => true,
                'size' => 64,
            ]),
            new StringField('LEVEL', [
                'required' => true,
                'size' => 16,
                'default_value' => self::LEVEL_INFO,
            ]),
            new TextField('MESSAGE', [
                'required' => true,
            ]),
            new DatetimeField('CREATED_AT', [
                'required' => true,
            ]),
        ];
    }
}
