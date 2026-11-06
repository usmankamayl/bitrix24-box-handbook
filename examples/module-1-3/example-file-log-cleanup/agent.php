<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

final class CleanOldLogsAgent
{
    public const AGENT_NAME =
        '\\MyCompany\\Agents\\CleanOldLogsAgent::execute();';

    public const DAYS_TO_KEEP = 30;

    /**
     * Каталог относительно document root.
     * Предназначен исключительно для очищаемых логов.
     */
    public const LOGS_DIR = '/upload/logs';

    /**
     * Абсолютный путь вне document root.
     *
     * Каталог должен быть создан заранее и быть доступен
     * на запись пользователю, от которого работает PHP.
     */
    public const RUNTIME_DIR = '/var/lib/bitrix/clean-old-logs';

    private const NOTIFICATION_TAG = 'mycompany_clean_old_logs_error';

    private const MAX_ERROR_SAMPLES = 20;

    /**
     * Вызов из механизма агентов:
     *
     * \MyCompany\Agents\CleanOldLogsAgent::execute();
     *
     * Проверка без удаления:
     *
     * \MyCompany\Agents\CleanOldLogsAgent::execute(true);
     */
    public static function execute(bool $dryRun = false): string
    {
        $startedAt = microtime(true);
        $runtimeDirectory = null;
        $lockHandle = null;
        $locked = false;

        $report = [
            'event' => 'clean_old_logs',
            'started_at' => date(DATE_ATOM),
            'dry_run' => $dryRun,
            'days_to_keep' => self::DAYS_TO_KEEP,
            'examined' => 0,
            'skipped' => 0,
            'eligible_count' => 0,
            'eligible_bytes' => 0,
            'deleted_count' => 0,
            'deleted_bytes' => 0,
            'error_count' => 0,
            'errors' => [],
        ];

        try {
            $root = self::getDocumentRoot();
            $runtimeDirectory = self::getRuntimeDirectory($root);

            $lockPath = $runtimeDirectory . '/cleanup.lock';

            if (is_link($lockPath)) {
                throw new \RuntimeException(
                    'Файл блокировки не должен быть символической ссылкой.'
                );
            }

            $lockHandle = @fopen($lockPath, 'c');

            if ($lockHandle === false) {
                throw new \RuntimeException(
                    'Не удалось открыть файл блокировки: ' . $lockPath
                );
            }

            $wouldBlock = 0;

            if (!flock($lockHandle, LOCK_EX | LOCK_NB, $wouldBlock)) {
                if ($wouldBlock) {
                    // Другой процесс уже выполняет очистку.
                    return self::AGENT_NAME;
                }

                throw new \RuntimeException(
                    'Не удалось установить блокировку очистки.'
                );
            }

            $locked = true;

            $logsDirectory = self::getLogsDirectory($root);
            $cutoff = time() - self::DAYS_TO_KEEP * 86400;

            $report['directory'] = $logsDirectory;
            $report['cutoff'] = date(DATE_ATOM, $cutoff);

            $directory = new Directory($logsDirectory);

            foreach ($directory->getChildren() as $entry) {
                $report['examined']++;

                $path = $entry->getPath();

                try {
                    self::processEntry(
                        $entry,
                        $path,
                        $logsDirectory,
                        $cutoff,
                        $dryRun,
                        $report
                    );
                } catch (\Throwable $exception) {
                    self::addError(
                        $report,
                        $path . ': ' . $exception->getMessage()
                    );
                }
            }
        } catch (\Throwable $exception) {
            self::addError($report, $exception->getMessage());
        } finally {
            if (is_resource($lockHandle)) {
                if ($locked) {
                    flock($lockHandle, LOCK_UN);
                }

                fclose($lockHandle);
            }
        }

        $report['finished_at'] = date(DATE_ATOM);
        $report['duration_seconds'] = round(
            microtime(true) - $startedAt,
            3
        );
        $report['deleted_size'] = self::formatBytes(
            $report['deleted_bytes']
        );
        $report['eligible_size'] = self::formatBytes(
            $report['eligible_bytes']
        );

        /*
         * Отчётность не должна мешать возврату имени агента.
         */
        try {
            self::publishReport($runtimeDirectory, $report);
        } catch (\Throwable $exception) {
            error_log(
                '[CleanOldLogsAgent] Reporting failure: '
                . $exception->getMessage()
            );
        }

        return self::AGENT_NAME;
    }

    /**
     * Предварительная проверка для установщика.
     * Ничего не удаляет.
     */
    public static function validateEnvironment(): void
    {
        $root = self::getDocumentRoot();

        self::getRuntimeDirectory($root);
        self::getLogsDirectory($root);
    }

    private static function processEntry(
        mixed $entry,
        string $path,
        string $logsDirectory,
        int $cutoff,
        bool $dryRun,
        array &$report
    ): void {
        /*
         * lstat() проверяет сам объект, не переходя по симлинку.
         * Нативные функции здесь дополняют D7 проверками безопасности.
         */
        clearstatcache(true, $path);

        $initialStat = @lstat($path);

        if ($initialStat === false) {
            throw new \RuntimeException(
                'Не удалось получить сведения об объекте.'
            );
        }

        if (!self::isRegularFile($initialStat)) {
            $report['skipped']++;
            return;
        }

        if (!$entry instanceof File) {
            $report['skipped']++;
            return;
        }

        $realPath = realpath($path);

        if (
            $realPath === false
            || $realPath !== $path
            || dirname($realPath) !== $logsDirectory
        ) {
            throw new \RuntimeException(
                'Путь файла не соответствует разрешённому каталогу.'
            );
        }

        /*
         * Метаданные получаем через D7.
         * Затем сверяем их с результатом lstat().
         */
        $modifiedAt = $entry->getModificationTime();
        $size = $entry->getSize();

        if (
            $modifiedAt !== (int) $initialStat['mtime']
            || $size !== (int) $initialStat['size']
        ) {
            $report['skipped']++;
            return;
        }

        /*
         * Ровно 30 дней — ещё не "старше 30 дней".
         */
        if ($modifiedAt >= $cutoff) {
            $report['skipped']++;
            return;
        }

        /*
         * Повторная проверка непосредственно перед удалением:
         * объект мог измениться во время обработки.
         */
        clearstatcache(true, $path);

        $currentStat = @lstat($path);

        if (
            $currentStat === false
            || !self::isRegularFile($currentStat)
            || !self::isSameSnapshot($initialStat, $currentStat)
        ) {
            $report['skipped']++;
            return;
        }

        $report['eligible_count']++;
        $report['eligible_bytes'] += $size;

        if ($dryRun) {
            return;
        }

        /*
         * Не проверяем возвращаемое значение как bool:
         * File::delete() либо удаляет файл, либо бросает IOException.
         * Исключение при удалении обработает существующий
         * catch (\Throwable $exception) в execute().
         */
        $entry->delete();

        /*
         * Учитываем удаление только после нормального
         * завершения вызова delete().
         */
        $report['deleted_count']++;
        $report['deleted_bytes'] += $size;
    }

    private static function isRegularFile(array $stat): bool
    {
        // S_IFMT = 0170000, S_IFREG = 0100000.
        return ($stat['mode'] & 0170000) === 0100000;
    }

    private static function isSameSnapshot(
        array $before,
        array $after
    ): bool {
        foreach (
            ['dev', 'ino', 'mode', 'mtime', 'ctime', 'size'] as $field
        ) {
            if ($before[$field] !== $after[$field]) {
                return false;
            }
        }

        return true;
    }

    private static function getDocumentRoot(): string
    {
        $documentRoot = Application::getDocumentRoot();

        if ($documentRoot === '') {
            throw new \RuntimeException(
                'Не определён document root.'
            );
        }

        $root = realpath($documentRoot);

        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException(
                'Document root не существует.'
            );
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private static function getLogsDirectory(string $root): string
    {
        $expectedPath = $root . self::LOGS_DIR;
        $realPath = realpath($expectedPath);

        /*
         * Сравнение запрещает в том числе перенаправление
         * /upload или /upload/logs через симлинк.
         */
        if (
            $realPath === false
            || $realPath !== $expectedPath
            || !Directory::isDirectoryExists($realPath)
        ) {
            throw new \RuntimeException(
                'Каталог логов отсутствует или содержит '
                . 'символические ссылки: ' . $expectedPath
            );
        }

        if (!is_readable($realPath) || !is_writable($realPath)) {
            throw new \RuntimeException(
                'Недостаточно прав для очистки: ' . $realPath
            );
        }

        return $realPath;
    }

    private static function getRuntimeDirectory(string $root): string
    {
        $configuredPath = rtrim(
            self::RUNTIME_DIR,
            DIRECTORY_SEPARATOR
        );

        $realPath = realpath($configuredPath);

        if (
            $realPath === false
            || $realPath !== $configuredPath
            || !is_dir($realPath)
        ) {
            throw new \RuntimeException(
                'Служебный каталог отсутствует или содержит '
                . 'символические ссылки: ' . $configuredPath
            );
        }

        if (
            $realPath === $root
            || str_starts_with(
                $realPath,
                $root . DIRECTORY_SEPARATOR
            )
        ) {
            throw new \RuntimeException(
                'Служебный каталог должен находиться вне document root.'
            );
        }

        if (!is_readable($realPath) || !is_writable($realPath)) {
            throw new \RuntimeException(
                'Нет доступа к служебному каталогу: ' . $realPath
            );
        }

        return $realPath;
    }

    private static function addError(
        array &$report,
        string $message
    ): void {
        $report['error_count']++;

        if (count($report['errors']) < self::MAX_ERROR_SAMPLES) {
            $report['errors'][] = $message;
        }
    }

    private static function publishReport(
        ?string $runtimeDirectory,
        array $report
    ): void {
        try {
            if ($runtimeDirectory === null) {
                throw new \RuntimeException(
                    'Служебный каталог недоступен.'
                );
            }

            $logPath = $runtimeDirectory . '/agent.jsonl';

            if (is_link($logPath)) {
                throw new \RuntimeException(
                    'Файл журнала не должен быть символической ссылкой.'
                );
            }

            $line = self::encodeReport($report) . PHP_EOL;

            $written = @file_put_contents(
                $logPath,
                $line,
                FILE_APPEND | LOCK_EX
            );

            if ($written === false || $written !== strlen($line)) {
                throw new \RuntimeException(
                    'Не удалось полностью записать отчёт: ' . $logPath
                );
            }
        } catch (\Throwable $exception) {
            self::addError(
                $report,
                'Ошибка журналирования: ' . $exception->getMessage()
            );

            error_log(
                '[CleanOldLogsAgent] ' . self::encodeReport($report)
            );
        }

        /*
         * В журнал событий пишем только ошибки.
         * Успешные ежедневные сводки остаются в JSONL.
         */
        if ($report['error_count'] === 0) {
            return;
        }

        try {
            $eventId = \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'CLEAN_LOGS_AGENT',
                'MODULE_ID' => 'main',
                'ITEM_ID' => self::class,
                'DESCRIPTION' => self::encodeReport($report),
            ]);

            if (!$eventId) {
                error_log(
                    '[CleanOldLogsAgent] Cannot write Bitrix event log.'
                );
            }
        } catch (\Throwable $exception) {
            error_log(
                '[CleanOldLogsAgent] Event log failure: '
                . $exception->getMessage()
            );
        }

        try {
            /*
             * Одно уведомление с постоянным тегом,
             * а не новое уведомление на каждый проблемный файл.
             */
            \CAdminNotify::DeleteByTag(self::NOTIFICATION_TAG);

            $notificationId = \CAdminNotify::Add([
                'MODULE_ID' => 'main',
                'TAG' => self::NOTIFICATION_TAG,
                'ENABLE_CLOSE' => 'Y',
                'MESSAGE' =>
                    'Очистка /upload/logs/ завершилась с ошибками. '
                    . 'Количество ошибок: '
                    . (int) $report['error_count']
                    . '. Подробности: журнал событий, '
                    . 'тип CLEAN_LOGS_AGENT, и служебный журнал агента.',
            ]);

            if (!$notificationId) {
                error_log(
                    '[CleanOldLogsAgent] Cannot create admin notification.'
                );
            }
        } catch (\Throwable $exception) {
            error_log(
                '[CleanOldLogsAgent] Notification failure: '
                . $exception->getMessage()
            );
        }
    }

    private static function encodeReport(array $report): string
    {
        return json_encode(
            $report,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_THROW_ON_ERROR
        );
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $value = (float) $bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, 2) . ' ' . $units[$index];
    }
}