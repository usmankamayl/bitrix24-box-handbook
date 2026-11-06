<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * Контрольная точка синхронизации: одна строка на провайдера.
 *
 * CURSOR — курсор, который вернул внешний API. Он сохраняется после успешной
 * фиксации порции, поэтому падение на следующей странице не теряет прогресс.
 */
final class SyncStateTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'mycompany_agents_sync_state';
    }

    public static function getMap(): array
    {
        return [
            new StringField('CODE', [
                'primary' => true,
                'required' => true,
                'size' => 64,
            ]),
            new StringField('CURSOR', [
                'size' => 255,
            ]),
            new IntegerField('PAGES_DONE', [
                'required' => true,
                'default_value' => 0,
            ]),
            new IntegerField('ITEMS_DONE', [
                'required' => true,
                'default_value' => 0,
            ]),
            new DatetimeField('UPDATED_AT'),
        ];
    }
}
