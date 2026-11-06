<?php

declare(strict_types=1);

namespace MyCompany\Agents;

/**
 * Класс-пробник для проверки механики агентов. Ничего полезного не делает:
 * пишет строку в журнал событий и возвращает имя следующего вызова.
 */
final class ProbeAgents
{
    public const PREFIX = 'PROBE-1.3-';

    public static function work(string $tag = 'default'): string
    {
        \CEventLog::Add([
            'SEVERITY' => 'INFO',
            'AUDIT_TYPE_ID' => 'PROBE_AGENT_WORK',
            'MODULE_ID' => 'main',
            'ITEM_ID' => $tag,
            'DESCRIPTION' => 'Выполнен work(' . $tag . ')',
        ]);

        return "\\" . __METHOD__ . '(' . var_export($tag, true) . ');';
    }

    /**
     * ДЕФЕКТ: имя строит вспомогательный метод, поэтому __METHOD__ указывает на
     * сам вспомогательный метод, а не на рабочую точку входа.
     */
    public static function nextNameBad(): string
    {
        return __METHOD__ . '();';
    }

    /**
     * Правильно: имя рабочей точки входа, а не того метода, который его отдаёт.
     */
    public static function nextNameGood(): string
    {
        return "\\" . self::class . "::work('good');";
    }
}
