<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * Локальная копия записей внешней системы.
 *
 * EXTERNAL_ID уникален — это и есть идемпотентность синхронизации:
 * повторная обработка той же страницы обновляет строку, а не создаёт дубль.
 */
final class SyncItemTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'mycompany_agents_sync_item';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new StringField('EXTERNAL_ID', [
                'required' => true,
                'size' => 64,
            ]),
            new StringField('TITLE', [
                'required' => true,
                'size' => 255,
            ]),
            new DatetimeField('CHANGED_AT'),
            new DatetimeField('SYNCED_AT', [
                'required' => true,
            ]),
        ];
    }
}
