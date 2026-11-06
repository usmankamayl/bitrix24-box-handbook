<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\Mail\Event;
use Bitrix\Main\Type\DateTime;

/**
 * Эталон контрольного задания.
 *
 * Отличия от broken.php — по одному на каждый дефект:
 *   1. AGENT_NAME — константа, а не __METHOD__ вспомогательного метода;
 *   2. execute() всегда возвращает имя агента (никаких `return;` и `null`);
 *   3. пакет ограничен BATCH_SIZE и бюджетом времени;
 *   4. статус sent ставится только после успешного Event::send();
 *   5. результат Event::send() проверяется (false — это неудача, не успех);
 *   6. catch (\Throwable), ATTEMPTS, backoff, статус error; без sleep();
 *   7. захват строки условным UPDATE — два процесса не возьмут одну строку.
 */
final class QueueControlAgent
{
    public const AGENT_NAME = '\\MyCompany\\Agents\\QueueControlAgent::execute();';

    public const EVENT_NAME = 'CONTROL_QUEUE_MESSAGE';

    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_ERROR = 'error';

    public const BATCH_SIZE = 50;
    public const MAX_ATTEMPTS = 3;
    public const MAX_RUNTIME_SECONDS = 20;

    public static function execute(): string
    {
        try {
            self::run();
        } catch (\Throwable $error) {
            error_log('[QueueControlAgent] ' . get_class($error) . ': ' . $error->getMessage());
        }

        return self::AGENT_NAME;
    }

    /**
     * @return array<string, mixed>
     */
    public static function run(): array
    {
        $startedAt = microtime(true);

        $report = [
            'claimed' => 0,
            'sent' => 0,
            'retried' => 0,
            'dead' => 0,
            'contended' => 0,
            'stop_reason' => '',
            'errors' => [],
        ];

        try {
            for ($processed = 0; $processed < self::BATCH_SIZE; $processed++) {
                if (microtime(true) - $startedAt > self::MAX_RUNTIME_SECONDS) {
                    $report['stop_reason'] = 'time_budget';
                    break;
                }

                $id = self::nextCandidateId();

                if ($id === null) {
                    $report['stop_reason'] = 'empty';
                    break;
                }

                if (!self::claim($id)) {
                    $report['contended']++;
                    continue;
                }

                $report['claimed']++;

                $outcome = self::process($id);

                if ($outcome === self::STATUS_SENT) {
                    $report['sent']++;
                } elseif ($outcome === self::STATUS_ERROR) {
                    $report['dead']++;
                } else {
                    $report['retried']++;
                }
            }

            if ($report['stop_reason'] === '') {
                $report['stop_reason'] = 'batch_limit';
            }
        } catch (\Throwable $error) {
            $report['errors'][] = get_class($error) . ': ' . $error->getMessage();
            $report['stop_reason'] = 'error';
        }

        return $report;
    }

    private static function process(int $id): string
    {
        $row = ControlQueueTable::getById($id)->fetch();

        if ($row === false) {
            return self::STATUS_ERROR;
        }

        $attempts = (int)$row['ATTEMPTS'];

        try {
            self::send((string)$row['EMAIL']);
        } catch (\Throwable $error) {
            return self::fail($id, $attempts, $error->getMessage());
        }

        ControlQueueTable::update($id, [
            'STATUS' => self::STATUS_SENT,
            'LAST_ERROR' => null,
            'UPDATED_AT' => new DateTime(),
        ]);

        return self::STATUS_SENT;
    }

    private static function send(string $email): void
    {
        $result = Event::send([
            'EVENT_NAME' => self::EVENT_NAME,
            'LID' => defined('SITE_ID') ? SITE_ID : 's1',
            'C_FIELDS' => ['EMAIL' => $email],
        ]);

        /*
         * Новые сборки возвращают AddResult, старые — ID или false. Объект
         * всегда truthy, поэтому «if (!$result)» недостаточно.
         */
        if (is_object($result) && method_exists($result, 'isSuccess')) {
            if (!$result->isSuccess()) {
                throw new \RuntimeException(
                    'Event::send(): ' . implode('; ', $result->getErrorMessages())
                );
            }

            return;
        }

        if (!$result) {
            throw new \RuntimeException('Event::send() вернул false: нет шаблона ' . self::EVENT_NAME);
        }
    }

    private static function fail(int $id, int $attempts, string $message): string
    {
        $isDead = $attempts >= self::MAX_ATTEMPTS;

        ControlQueueTable::update($id, [
            'STATUS' => $isDead ? self::STATUS_ERROR : self::STATUS_NEW,
            'LAST_ERROR' => mb_substr($message, 0, 1000),
            'UPDATED_AT' => new DateTime(),
        ]);

        return $isDead ? self::STATUS_ERROR : self::STATUS_NEW;
    }

    private static function nextCandidateId(): ?int
    {
        $row = ControlQueueTable::getList([
            'select' => ['ID'],
            'filter' => ['=STATUS' => self::STATUS_NEW],
            'order' => ['ID' => 'ASC'],
            'limit' => 1,
        ])->fetch();

        return $row === false ? null : (int)$row['ID'];
    }

    /**
     * Условный UPDATE: строку заберёт ровно один процесс.
     */
    private static function claim(int $id): bool
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $now = $connection->getSqlHelper()->convertToDbDateTime(new DateTime());
        $table = ControlQueueTable::getTableName();

        $connection->queryExecute(
            "UPDATE {$table}
                SET STATUS = '" . self::STATUS_PROCESSING . "',
                    ATTEMPTS = ATTEMPTS + 1,
                    UPDATED_AT = {$now}
              WHERE ID = {$id}
                AND STATUS = '" . self::STATUS_NEW . "'"
        );

        return $connection->getAffectedRowsCount() === 1;
    }
}
