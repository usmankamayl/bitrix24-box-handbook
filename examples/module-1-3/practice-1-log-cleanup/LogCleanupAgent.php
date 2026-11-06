<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

/**
 * Агент очистки проектного журнала: удаляет строки старше RETENTION_DAYS.
 *
 * Требования задачи и как они закрыты:
 *   - ограниченная порция за вызов        -> BATCH_SIZE + MAX_BATCHES,
 *                                            плюс бюджет времени MAX_RUNTIME_SECONDS;
 *   - явный срок хранения                 -> RETENTION_DAYS (одна константа, не
 *                                            «магическое» число в запросе);
 *   - лог числа удалённых                 -> JSONL-отчёт + CEventLog при ошибках;
 *   - корректный повторный запуск         -> удаление по primary key и фильтр
 *                                            `< cutoff`: второй прогон удаляет 0;
 *   - чужие (свежие) строки не трогаются  -> фильтр по CREATED_AT, граница
 *                                            включена в «оставить».
 *
 * Класс не знает о cron и о хитах: он вызывается механизмом агентов.
 */
final class LogCleanupAgent
{
    /** Строка, которую ядро хранит в b_agent.NAME и выполняет через eval. */
    public const AGENT_NAME = '\\MyCompany\\Agents\\LogCleanupAgent::execute();';

    /** Срок хранения записей журнала. */
    public const RETENTION_DAYS = 30;

    /** Сколько строк удаляем одним запросом выборки. */
    public const BATCH_SIZE = 200;

    /** Сколько порций максимум за один запуск (догон разросшегося журнала). */
    public const MAX_BATCHES = 10;

    /** Жёсткий бюджет времени: агент не должен занимать процесс минутами. */
    public const MAX_RUNTIME_SECONDS = 20;

    /** Блокировка от параллельного запуска (второй cron, ручной прогон). */
    public const LOCK_FILE = '/local/var/agents/log-cleanup.lock';

    /** Куда пишем отчёт по запуску. */
    public const REPORT_FILE = '/local/var/agents/log-cleanup.jsonl';

    /**
     * Точка входа для механизма агентов.
     *
     * Любой исход (ошибка, конкурентный запуск, пустой журнал) заканчивается
     * возвратом собственного имени: агент продолжает жить, а не исчезает из-за
     * исключения или случайного `return;`.
     */
    public static function execute(): string
    {
        try {
            self::run();
        } catch (\Throwable $error) {
            error_log(
                '[LogCleanupAgent] unhandled: ' . get_class($error)
                . ': ' . $error->getMessage()
            );
        }

        return self::AGENT_NAME;
    }

    /**
     * Один цикл очистки. Возвращает отчёт — это же использует check.php,
     * чтобы показать, что именно произошло.
     *
     * @return array<string, mixed>
     */
    public static function run(): array
    {
        $startedAt = microtime(true);

        $report = [
            'event' => 'log_cleanup',
            'started_at' => date(DATE_ATOM),
            'retention_days' => self::RETENTION_DAYS,
            'cutoff' => '',
            'batches' => 0,
            'examined' => 0,
            'deleted' => 0,
            'failed' => 0,
            'skipped_locked' => false,
            'stop_reason' => '',
            'errors' => [],
        ];

        $lock = null;

        try {
            $cutoff = self::getCutoff();
            $report['cutoff'] = $cutoff->format('Y-m-d H:i:s');

            $lock = self::acquireLock();

            if ($lock === null) {
                /*
                 * Другой процесс уже чистит журнал. Это не ошибка: возвращаем
                 * имя агента и выходим. Иначе два cron-процесса удаляют одни и
                 * те же ID и пишут друг другу ошибки «строки нет».
                 */
                $report['skipped_locked'] = true;
                $report['stop_reason'] = 'locked';

                return self::finish($report, $startedAt);
            }

            $report = self::clean($cutoff, $startedAt, $report);
        } catch (\Throwable $error) {
            $report['errors'][] = get_class($error) . ': ' . $error->getMessage();

            if ($report['stop_reason'] === '') {
                $report['stop_reason'] = 'error';
            }
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        return self::finish($report, $startedAt);
    }

    /**
     * Порционный цикл удаления.
     *
     * @param array<string, mixed> $report
     *
     * @return array<string, mixed>
     */
    private static function clean(DateTime $cutoff, float $startedAt, array $report): array
    {
        for ($batch = 1; $batch <= self::MAX_BATCHES; $batch++) {
            if (microtime(true) - $startedAt > self::MAX_RUNTIME_SECONDS) {
                $report['stop_reason'] = 'time_budget';

                return $report;
            }

            $ids = self::selectBatch($cutoff);

            if ($ids === []) {
                $report['stop_reason'] = 'empty';

                return $report;
            }

            $report['batches']++;
            $report['examined'] += count($ids);

            foreach ($ids as $id) {
                try {
                    $result = LogTable::delete($id);
                } catch (\Throwable $error) {
                    $report['failed']++;
                    $report['errors'][] = 'ID ' . $id . ': ' . $error->getMessage();
                    continue;
                }

                if (!$result->isSuccess()) {
                    /*
                     * Удаление не прошло: строку могли удалить между выборкой
                     * и delete(), либо БД вернула ошибку. Прогон не падает —
                     * остальные строки удаляются, а счётчик честно растёт.
                     */
                    $report['failed']++;
                    $report['errors'][] = 'ID ' . $id . ': '
                        . implode('; ', $result->getErrorMessages());
                    continue;
                }

                $report['deleted']++;
            }

            if (count($ids) < self::BATCH_SIZE) {
                // Журнал вычищен за меньшее число строк — догонять нечего.
                $report['stop_reason'] = 'empty';

                return $report;
            }
        }

        /*
         * Порции кончились, а строки ещё есть: следующая доза — при следующем
         * запуске. Так тяжёлый догон не превращается в один длинный запрос.
         */
        $report['stop_reason'] = 'batch_limit';

        return $report;
    }

    /**
     * @return int[]
     */
    private static function selectBatch(DateTime $cutoff): array
    {
        $rows = LogTable::getList([
            'select' => ['ID'],
            /*
             * Строгое «старше»: строка ровно на границе срока остаётся.
             * Проверка границы — в check.php, сценарий 4.
             */
            'filter' => ['<CREATED_AT' => $cutoff],
            'order' => ['ID' => 'ASC'],
            'limit' => self::BATCH_SIZE,
        ])->fetchAll();

        $ids = [];

        foreach ($rows as $row) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    /**
     * Граница срока: всё, что старше — удаляем.
     */
    private static function getCutoff(): DateTime
    {
        $timestamp = time() - self::RETENTION_DAYS * 86400;

        return new DateTime(date('Y-m-d H:i:s', $timestamp), 'Y-m-d H:i:s');
    }

    /**
     * @return resource|null null — блокировку держит другой процесс
     */
    private static function acquireLock()
    {
        $path = Application::getDocumentRoot() . self::LOCK_FILE;
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Не удалось создать каталог для lock-файла: ' . $directory);
        }

        if (is_link($path)) {
            throw new \RuntimeException('Lock-файл является символической ссылкой: ' . $path);
        }

        $handle = @fopen($path, 'c');

        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть lock-файл: ' . $path);
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * Дописывает отчёт и, если были ошибки, дублирует их в журнал событий.
     *
     * @param array<string, mixed> $report
     *
     * @return array<string, mixed>
     */
    private static function finish(array $report, float $startedAt): array
    {
        $report['duration_seconds'] = round(microtime(true) - $startedAt, 3);
        $report['finished_at'] = date(DATE_ATOM);

        $encodingError = null;
        $line = '';

        try {
            $line = json_encode(
                $report,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
        } catch (\Throwable $error) {
            $encodingError = $error->getMessage();
        }

        if ($encodingError === null) {
            try {
                $path = Application::getDocumentRoot() . self::REPORT_FILE;
                $directory = dirname($path);

                if (!is_dir($directory)) {
                    @mkdir($directory, 0700, true);
                }

                /*
                 * FILE_APPEND|LOCK_EX — отчёт пишется одной строкой, чтобы
                 * JSONL не разъехался при параллельной записи.
                 */
                @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
            } catch (\Throwable $error) {
                error_log('[LogCleanupAgent] report: ' . $error->getMessage());
            }
        } else {
            error_log('[LogCleanupAgent] report encode: ' . $encodingError);
        }

        if ($report['errors'] !== []) {
            try {
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'LOG_CLEANUP_AGENT',
                    'MODULE_ID' => 'main',
                    'ITEM_ID' => self::class,
                    'DESCRIPTION' => 'Удалено: ' . $report['deleted']
                        . ', ошибок: ' . $report['failed']
                        . '. Первая ошибка: ' . $report['errors'][0],
                ]);
            } catch (\Throwable $error) {
                error_log('[LogCleanupAgent] event log: ' . $error->getMessage());
            }
        }

        return $report;
    }
}
