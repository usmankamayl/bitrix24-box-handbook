<?php

/**
 * Журнал идемпотентности: захват ключа, повтор готового ответа, освобождение.
 *
 * Путь в проекте: /local/php_interface/classes/DealStage/Journal.php
 *
 * Идея, без которой всё остальное — украшение:
 *   атомарность даёт база, а не PHP. «Проверить, нет ли записи, и вставить» —
 *   это два шага, между ними второй запрос успевает прочитать «нет».
 *   Поэтому захват — один INSERT в таблицу с UNIQUE(USER_ID, REQUEST_ID):
 *   проигравший получает DuplicateEntryException, а не второй шанс.
 *
 * Состояния строки:
 *   HTTP_CODE = 0   запрос захватил ключ и ещё выполняется;
 *   HTTP_CODE > 0   готовый ответ сохранён, повтор получит его байт в байт.
 */

namespace MyCompany\DealStage;

use Bitrix\Main\DB\DuplicateEntryException;
use Bitrix\Main\Type\DateTime;

final class Journal
{
    /** Через сколько секунд «зависший» захват считается брошенным. */
    private const STALE_AFTER = 120;

    /**
     * @return array{state: string, id?: int, code?: int, body?: string, retry_after?: int}
     *         state: claimed | replay | in_progress
     */
    public static function claim(int $userId, string $requestId, int $dealId, string $stageId): array
    {
        $filter = ['=USER_ID' => $userId, '=REQUEST_ID' => $requestId];

        // Две итерации: первая может проиграть гонку на INSERT, вторая
        // гарантированно увидит строку победителя.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $row = JournalTable::getList([
                'select' => ['ID', 'HTTP_CODE', 'RESPONSE', 'CREATED_AT'],
                'filter' => $filter,
                'limit'  => 1,
            ])->fetch();

            if ($row === false) {
                try {
                    $id = (int)JournalTable::add([
                        'USER_ID'    => $userId,
                        'REQUEST_ID' => $requestId,
                        'DEAL_ID'    => $dealId,
                        'STAGE_ID'   => $stageId,
                        'HTTP_CODE'  => JournalTable::PENDING_CODE,
                        'CREATED_AT' => new DateTime(),
                    ])->getId();

                    return ['state' => 'claimed', 'id' => $id];
                } catch (DuplicateEntryException) {
                    // Гонка: строку успел вставить параллельный запрос. На второй
                    // итерации мы её увидим; если не увидели (транзакция соседа
                    // ещё не закоммичена) — это не 500, а «повтори позже».
                    if ($attempt === 1) {
                        return ['state' => 'in_progress', 'retry_after' => 2];
                    }
                    continue;
                }
            }

            if ((int)$row['HTTP_CODE'] > 0) {
                return [
                    'state' => 'replay',
                    'code'  => (int)$row['HTTP_CODE'],
                    'body'  => (string)$row['RESPONSE'],
                ];
            }

            $createdAt = $row['CREATED_AT'];
            $age = $createdAt instanceof DateTime
                ? time() - $createdAt->getTimestamp()
                : self::STALE_AFTER;

            if ($age < self::STALE_AFTER) {
                return ['state' => 'in_progress', 'retry_after' => self::STALE_AFTER - $age];
            }

            // Захват брошен (процесс умер, PHP-FPM убит по таймауту):
            // переиспользуем строку, иначе действие не выполнить уже никогда.
            JournalTable::update((int)$row['ID'], [
                'DEAL_ID'    => $dealId,
                'STAGE_ID'   => $stageId,
                'CREATED_AT' => new DateTime(),
            ]);

            return ['state' => 'claimed', 'id' => (int)$row['ID']];
        }

        throw new \RuntimeException('idempotency claim failed: row not created and not found');
    }

    /** Сохранить готовый ответ: с этого момента повтор получает его, а не действие. */
    public static function complete(int $id, string $body, int $code): void
    {
        JournalTable::update($id, [
            'HTTP_CODE' => $code,
            'RESPONSE'  => $body,
        ]);
    }

    /** Отказ до записи в CRM: ключ освобождается, клиент вправе повторить. */
    public static function release(int $id): void
    {
        JournalTable::delete($id);
    }
}
