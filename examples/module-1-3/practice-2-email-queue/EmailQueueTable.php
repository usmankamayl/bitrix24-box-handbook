<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * Очередь писем.
 *
 * Статусы: new -> processing -> sent | error (с повторами через new).
 * DEDUP_KEY уникален: повторная постановка того же письма не создаёт дубль.
 */
final class EmailQueueTable extends DataManager
{
    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_ERROR = 'error';

    public static function getTableName(): string
    {
        return 'mycompany_agents_queue';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new StringField('DEDUP_KEY', [
                'required' => true,
                'size' => 128,
            ]),
            new StringField('EMAIL', [
                'required' => true,
                'size' => 255,
            ]),
            new StringField('SUBJECT', [
                'required' => true,
                'size' => 255,
            ]),
            new TextField('BODY', [
                'required' => true,
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
            new DatetimeField('NEXT_ATTEMPT_AT'),
            new DatetimeField('LOCKED_UNTIL'),
            new TextField('LAST_ERROR'),
            new DatetimeField('CREATED_AT', [
                'required' => true,
            ]),
            new DatetimeField('UPDATED_AT', [
                'required' => true,
            ]),
            new DatetimeField('SENT_AT'),
        ];
    }
}
