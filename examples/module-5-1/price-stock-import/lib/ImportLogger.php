<?php

namespace BitrixCourse\PriceImport;

/**
 * Простой файловый логгер импорта с ротацией по дате.
 * В реальном проекте можно заменить на запись в HL-блок
 * (чтобы видеть историю импортов в админке).
 */
class ImportLogger
{
    private string $logDir;
    private string $runId;
    private array $stats = [
        'processed' => 0,
        'updated'   => 0,
        'skipped'   => 0,
        'errors'    => 0,
    ];
    /** @var array<int, string> построчные ошибки для финального отчёта */
    private array $errorRows = [];

    public function __construct(string $logDir)
    {
        $this->logDir = rtrim($logDir, '/');
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
        $this->runId = date('Ymd_His') . '_' . substr(md5((string)mt_rand()), 0, 6);
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->write('WARN', $message);
    }

    public function errorRow(int $line, string $message): void
    {
        $this->stats['errors']++;
        $this->errorRows[] = "line {$line}: {$message}";
        $this->write('ERROR', "line {$line}: {$message}");
    }

    public function incProcessed(): void { $this->stats['processed']++; }
    public function incUpdated(): void { $this->stats['updated']++; }
    public function incSkipped(): void { $this->stats['skipped']++; }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function finish(): void
    {
        $summary = sprintf(
            'Import finished: processed=%d updated=%d skipped=%d errors=%d',
            $this->stats['processed'],
            $this->stats['updated'],
            $this->stats['skipped'],
            $this->stats['errors']
        );
        $this->write('INFO', $summary);

        if (!empty($this->errorRows)) {
            $errorLogFile = $this->logDir . "/errors_{$this->runId}.log";
            file_put_contents($errorLogFile, implode(PHP_EOL, $this->errorRows));
        }
    }

    private function write(string $level, string $message): void
    {
        $line = sprintf('[%s] [%s] %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL);
        file_put_contents($this->logDir . "/import_{$this->runId}.log", $line, FILE_APPEND | LOCK_EX);
    }
}
