<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * Очередь для контрольного задания. Минимальный набор полей: по ним видно,
 * доходит ли строка до статуса и сколько было попыток.
 */
final class ControlQueueTable extends DataManager
{
    public const STATUS_NEW = 'new';
    public const STATUS_SENT = 'sent';

    public static function getTableName(): string
    {
        return 'mycompany_agents_control_queue';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new StringField('EMAIL', [
                'required' => true,
                'size' => 255,
            ]),
            new StringField('STATUS', [
                'required' => true,
                'size' => 16,
                'default_value' => self::STATUS_NEW,
            ]),
            new IntegerField('ATTEMPTS', [
                'required' => true,
                'default_value' => 0,
            ]),
            new TextField('LAST_ERROR'),
            new DatetimeField('UPDATED_AT'),
        ];
    }
}
