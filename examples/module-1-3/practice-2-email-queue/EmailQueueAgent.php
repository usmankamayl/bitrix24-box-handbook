<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Type\DateTime;

/**
 * Агент разбора очереди писем.
 *
 * Модель состояний:
 *
 *   new --(claim)--> processing --(send ok)----> sent
 *                        |
 *                        +--(send fail, attempts < MAX)---> new (с задержкой)
 *                        +--(send fail, attempts >= MAX)--> error
 *                        +--(процесс умер)-----------------> new/error по LOCKED_UNTIL
 *
 * Требования задачи и как они закрыты:
 *   - статусы new/processing/sent/error        -> константы таблицы;
 *   - ограниченная порция                     -> BATCH_SIZE + MAX_RUNTIME_SECONDS;
 *   - число попыток и время следующей         -> ATTEMPTS, NEXT_ATTEMPT_AT, backoff;
 *   - защита от конкурентного захвата         -> атомарный UPDATE ... WHERE STATUS='new';
 *   - восстановление зависших                 -> LOCKED_UNTIL + recoverStuck();
 *   - стратегия против дублей                 -> UNIQUE(DEDUP_KEY) + enqueue().
 *
 * `sent` означает «письмо передано в почтовую подсистему Bitrix», а не
 * «доставлено адресату»: фактическую доставку подтверждает только SMTP-сервер.
 */
final class EmailQueueAgent
{
    public const AGENT_NAME = '\\MyCompany\\Agents\\EmailQueueAgent::execute();';

    /** Имя почтового события; шаблон создаётся в админке. */
    public const EVENT_NAME = 'MYCOMPANY_QUEUE_MESSAGE';

    /** После какого числа неудач письмо уходит в error. */
    public const MAX_ATTEMPTS = 5;

    /** Сколько писем максимум за один запуск. */
    public const BATCH_SIZE = 20;

    /** Бюджет времени на запуск (агент может работать внутри HTTP-запроса). */
    public const MAX_RUNTIME_SECONDS = 20;

    /** Через сколько секунд захват считается зависшим. */
    public const LOCK_TTL_SECONDS = 300;

    public const BACKOFF_BASE_SECONDS = 60;
    public const BACKOFF_MAX_SECONDS = 3600;

    public const REPORT_FILE = '/local/var/agents/email-queue.jsonl';

    public static function execute(): string
    {
        try {
            self::run();
        } catch (\Throwable $error) {
            error_log(
                '[EmailQueueAgent] unhandled: ' . get_class($error)
                . ': ' . $error->getMessage()
            );
        }

        /*
         * Возвращаем имя всегда: очередь должна доживать до следующего запуска,
         * даже если этот прогон упал на подключении к БД.
         */
        return self::AGENT_NAME;
    }

    /**
     * Один цикл разбора очереди.
     *
     * @return array<string, mixed>
     */
    public static function run(): array
    {
        $startedAt = microtime(true);

        $report = [
            'event' => 'email_queue',
            'started_at' => date(DATE_ATOM),
            'recovered_new' => 0,
            'recovered_error' => 0,
            'candidates' => 0,
            'claimed' => 0,
            'contended' => 0,
            'sent' => 0,
            'retried' => 0,
            'dead' => 0,
            'stop_reason' => '',
            'errors' => [],
        ];

        try {
            [$report['recovered_new'], $report['recovered_error']] = self::recoverStuck();

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

                $report['candidates']++;

                /*
                 * Между выборкой и захватом строку мог забрать другой процесс.
                 * Захват условный: WHERE STATUS='new'. Кто первый обновил —
                 * тот и обрабатывает.
                 */
                if (!self::claim($id)) {
                    $report['contended']++;
                    continue;
                }

                $report['claimed']++;

                $outcome = self::process($id);

                if ($outcome === EmailQueueTable::STATUS_SENT) {
                    $report['sent']++;
                } elseif ($outcome === EmailQueueTable::STATUS_ERROR) {
                    $report['dead']++;
                } else {
                    $report['retried']++;
                }
            }
        } catch (\Throwable $error) {
            $report['errors'][] = get_class($error) . ': ' . $error->getMessage();

            if ($report['stop_reason'] === '') {
                $report['stop_reason'] = 'error';
            }
        }

        $report['duration_seconds'] = round(microtime(true) - $startedAt, 3);

        self::publish($report);

        return $report;
    }

    /**
     * Постановка письма в очередь. Идемпотентна по DEDUP_KEY.
     *
     * Гонка «проверили — не нашли — вставили» закрыта уникальным индексом:
     * второй INSERT падает, и мы возвращаем существующую строку, а не ошибку
     * вызывающему коду.
     *
     * @return array{id: int, duplicate: bool}
     */
    public static function enqueue(
        string $dedupKey,
        string $email,
        string $subject,
        string $body
    ): array {
        $existing = self::findByDedupKey($dedupKey);

        if ($existing !== null) {
            return ['id' => (int)$existing['ID'], 'duplicate' => true];
        }

        $now = new DateTime();

        try {
            $result = EmailQueueTable::add([
                'DEDUP_KEY' => $dedupKey,
                'EMAIL' => $email,
                'SUBJECT' => $subject,
                'BODY' => $body,
                'STATUS' => EmailQueueTable::STATUS_NEW,
                'ATTEMPTS' => 0,
                'CREATED_AT' => $now,
                'UPDATED_AT' => $now,
            ]);
        } catch (SqlQueryException $error) {
            /*
             * Уникальный индекс сработал: между нашей проверкой и INSERT
             * кто-то вставил ту же строку. Это не ошибка — возвращаем её.
             */
            $existing = self::findByDedupKey($dedupKey);

            if ($existing !== null) {
                return ['id' => (int)$existing['ID'], 'duplicate' => true];
            }

            throw $error;
        }

        if (!$result->isSuccess()) {
            throw new \RuntimeException(
                'Не удалось поставить письмо в очередь: '
                . implode('; ', $result->getErrorMessages())
            );
        }

        return ['id' => (int)$result->getId(), 'duplicate' => false];
    }

    /**
     * Работа с одной захваченной строкой.
     */
    private static function process(int $id): string
    {
        $row = EmailQueueTable::getById($id)->fetch();

        if ($row === false) {
            // Строку удалили после захвата: ничего не делаем.
            return EmailQueueTable::STATUS_ERROR;
        }

        $attempts = (int)$row['ATTEMPTS'];

        try {
            self::send([
                'EMAIL' => (string)$row['EMAIL'],
                'SUBJECT' => (string)$row['SUBJECT'],
                'BODY' => (string)$row['BODY'],
            ]);
        } catch (\Throwable $error) {
            return self::fail($id, $attempts, $error->getMessage());
        }

        /*
         * Статус меняем только после успешной передачи в почтовую подсистему.
         * Обратный порядок («сначала sent, потом отправка») теряет письма при
         * падении процесса: строка уже sent, повторной попытки не будет.
         */
        EmailQueueTable::update($id, [
            'STATUS' => EmailQueueTable::STATUS_SENT,
            'LOCKED_UNTIL' => null,
            'LAST_ERROR' => null,
            'SENT_AT' => new DateTime(),
            'UPDATED_AT' => new DateTime(),
        ]);

        return EmailQueueTable::STATUS_SENT;
    }

    /**
     * Неудача: повтор с задержкой или окончательный error.
     */
    private static function fail(int $id, int $attempts, string $message): string
    {
        $isDead = $attempts >= self::MAX_ATTEMPTS;

        $fields = [
            'STATUS' => $isDead ? EmailQueueTable::STATUS_ERROR : EmailQueueTable::STATUS_NEW,
            'LOCKED_UNTIL' => null,
            'LAST_ERROR' => mb_substr($message, 0, 1000),
            'UPDATED_AT' => new DateTime(),
            'NEXT_ATTEMPT_AT' => $isDead ? null : self::nextAttemptAt($attempts),
        ];

        EmailQueueTable::update($id, $fields);

        /*
         * Ошибка отправки — не повод падать: очередь продолжает разбираться,
         * а строка вернётся в работу по NEXT_ATTEMPT_AT.
         */
        if ($isDead) {
            try {
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'EMAIL_QUEUE_DEAD',
                    'MODULE_ID' => 'main',
                    'ITEM_ID' => (string)$id,
                    'DESCRIPTION' => 'Письмо ID ' . $id . ' после '
                        . $attempts . ' попыток: ' . $message,
                ]);
            } catch (\Throwable $logError) {
                error_log('[EmailQueueAgent] event log: ' . $logError->getMessage());
            }
        }

        return $isDead ? EmailQueueTable::STATUS_ERROR : EmailQueueTable::STATUS_NEW;
    }

    /**
     * Экспоненциальная задержка: 60s, 120s, 240s... с потолком.
     */
    private static function nextAttemptAt(int $attempts): DateTime
    {
        $delay = (int)min(
            self::BACKOFF_MAX_SECONDS,
            self::BACKOFF_BASE_SECONDS * (2 ** max(0, $attempts - 1))
        );

        return new DateTime(date('Y-m-d H:i:s', time() + $delay), 'Y-m-d H:i:s');
    }

    /**
     * Восстановление строк, захваченных процессом, который не дошёл до конца.
     *
     * Без этого шага `processing` превращается в чёрную дыру: письмо никто не
     * отправляет и никто не повторяет.
     *
     * @return array{0: int, 1: int} [возвращено в new, переведено в error]
     */
    private static function recoverStuck(): array
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        $now = $helper->convertToDbDateTime(new DateTime());
        $table = EmailQueueTable::getTableName();

        // Исчерпавшие попытки: второй раз в работу не возвращаем.
        $connection->queryExecute(
            "UPDATE {$table}
                SET STATUS = '" . EmailQueueTable::STATUS_ERROR . "',
                    LOCKED_UNTIL = NULL,
                    LAST_ERROR = 'processing timeout',
                    UPDATED_AT = {$now}
              WHERE STATUS = '" . EmailQueueTable::STATUS_PROCESSING . "'
                AND LOCKED_UNTIL < {$now}
                AND ATTEMPTS >= " . self::MAX_ATTEMPTS
        );

        $toError = $connection->getAffectedRowsCount();

        $connection->queryExecute(
            "UPDATE {$table}
                SET STATUS = '" . EmailQueueTable::STATUS_NEW . "',
                    LOCKED_UNTIL = NULL,
                    NEXT_ATTEMPT_AT = NULL,
                    LAST_ERROR = 'processing timeout',
                    UPDATED_AT = {$now}
              WHERE STATUS = '" . EmailQueueTable::STATUS_PROCESSING . "'
                AND LOCKED_UNTIL < {$now}
                AND ATTEMPTS < " . self::MAX_ATTEMPTS
        );

        $toNew = $connection->getAffectedRowsCount();

        return [$toNew, $toError];
    }

    /**
     * Кандидат на обработку: new и время попытки наступило.
     */
    private static function nextCandidateId(): ?int
    {
        $row = EmailQueueTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=STATUS' => EmailQueueTable::STATUS_NEW,
                [
                    'LOGIC' => 'OR',
                    '=NEXT_ATTEMPT_AT' => null,
                    '<=NEXT_ATTEMPT_AT' => new DateTime(),
                ],
            ],
            'order' => ['ID' => 'ASC'],
            'limit' => 1,
        ])->fetch();

        return $row === false ? null : (int)$row['ID'];
    }

    /**
     * Атомарный захват строки.
     *
     * D7 ORM не умеет `UPDATE ... WHERE` с условием на другое поле, поэтому
     * здесь прямой запрос через Connection. Условие STATUS='new' — то, что
     * делает захват безопасным при нескольких процессах: обновит только один.
     */
    private static function claim(int $id): bool
    {
        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        $now = $helper->convertToDbDateTime(new DateTime());
        $lockedUntil = $helper->convertToDbDateTime(
            new DateTime(date('Y-m-d H:i:s', time() + self::LOCK_TTL_SECONDS), 'Y-m-d H:i:s')
        );

        $table = EmailQueueTable::getTableName();

        $connection->queryExecute(
            "UPDATE {$table}
                SET STATUS = '" . EmailQueueTable::STATUS_PROCESSING . "',
                    ATTEMPTS = ATTEMPTS + 1,
                    LOCKED_UNTIL = {$lockedUntil},
                    UPDATED_AT = {$now}
              WHERE ID = {$id}
                AND STATUS = '" . EmailQueueTable::STATUS_NEW . "'"
        );

        return $connection->getAffectedRowsCount() === 1;
    }

    /**
     * Передача письма в почтовую подсистему Bitrix.
     *
     * @param array{EMAIL: string, SUBJECT: string, BODY: string} $message
     */
    private static function send(array $message): void
    {
        $siteId = defined('SITE_ID') ? (string)SITE_ID : 's1';

        $result = Event::send([
            'EVENT_NAME' => self::EVENT_NAME,
            'LID' => $siteId,
            'C_FIELDS' => [
                'EMAIL' => $message['EMAIL'],
                'SUBJECT' => $message['SUBJECT'],
                'BODY' => $message['BODY'],
            ],
        ]);

        /*
         * Контракт отличается по версиям ядра: в новых сборках Event::send()
         * возвращает AddResult, в старых — ID или false (например, когда
         * шаблон письма не найден). Проверяем оба варианта, иначе пример
         * молча считает неуспех успехом: объект всегда truthy.
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
            throw new \RuntimeException(
                'Event::send() вернул false: нет шаблона ' . self::EVENT_NAME
                . ' для сайта ' . $siteId
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findByDedupKey(string $dedupKey): ?array
    {
        $row = EmailQueueTable::getList([
            'select' => ['ID', 'STATUS'],
            'filter' => ['=DEDUP_KEY' => $dedupKey],
            'limit' => 1,
        ])->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $report
     */
    private static function publish(array $report): void
    {
        try {
            $line = json_encode(
                $report,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            ) . PHP_EOL;

            $path = Application::getDocumentRoot() . self::REPORT_FILE;
            $directory = dirname($path);

            if (!is_dir($directory)) {
                @mkdir($directory, 0700, true);
            }

            @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $error) {
            error_log('[EmailQueueAgent] report: ' . $error->getMessage());
        }
    }
}
