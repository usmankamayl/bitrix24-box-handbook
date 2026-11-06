<?php

/**
 * Точка входа для агента Bitrix (cron). Регистрируется так:
 *
 * \CAgent::AddAgent(
 *     '\BitrixCourse\PriceImport\ImportAgent::run();',
 *     'bitrix_course',
 *     'N',
 *     3600 * 8,              // раз в 8 часов
 *     '',
 *     'Y',
 *     ConvertTimeStamp(strtotime('03:00:00') + CTimeZone::GetOffset(), 'FULL')
 * );
 *
 * Функция ДОЛЖНА возвращать строку с собственным вызовом — так работает
 * механизм агентов Bitrix (следующий запуск регистрируется этой же строкой).
 */

namespace BitrixCourse\PriceImport;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once __DIR__ . '/lib/ImportLogger.php';
require_once __DIR__ . '/lib/PriceStockImporter.php';

class ImportAgent
{
    public static function run(): string
    {
        $csvPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/import/prices_stock.csv';
        $logDir  = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/logs/price_import';

        $logger = new ImportLogger($logDir);

        try {
            $importer = new PriceStockImporter($logger);
            $stats = $importer->run($csvPath);
            $logger->info('Agent run complete: ' . json_encode($stats, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $logger->errorRow(0, 'Fatal: ' . $e->getMessage());
        }

        return '\BitrixCourse\PriceImport\ImportAgent::run();';
    }
}
