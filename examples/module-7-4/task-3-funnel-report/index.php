<?php
/**
 * Задача 3: Отчет по конверсии воронки продаж
 *
 * Описание:
 * Создание детального отчета по воронке продаж с подсчетом количества сделок
 * на каждой стадии, расчетом конверсии между стадиями и экспортом в CSV.
 *
 * Сценарий использования:
 * Руководитель отдела продаж хочет видеть эффективность воронки:
 * - Сколько сделок на каждой стадии
 * - Какая конверсия между стадиями
 * - Где теряется больше всего клиентов
 * - Общая конверсия от начала до успешного завершения
 */

require_once(__DIR__ . '/crest.php');

class FunnelReportGenerator
{
    private $categoryId;
    private $dateFrom;
    private $dateTo;

    /**
     * @param int $categoryId ID воронки (0 = по умолчанию)
     * @param string|null $dateFrom Дата начала периода (Y-m-d)
     * @param string|null $dateTo Дата окончания периода (Y-m-d)
     */
    public function __construct($categoryId = 0, $dateFrom = null, $dateTo = null)
    {
        $this->categoryId = $categoryId;
        $this->dateFrom = $dateFrom ?? date('Y-m-01'); // Начало месяца
        $this->dateTo = $dateTo ?? date('Y-m-d'); // Сегодня
    }

    /**
     * Создать полный отчет по воронке
     *
     * @return array
     */
    public function generateReport()
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════╗\n";
        echo "║        Отчет по конверсии воронки продаж            ║\n";
        echo "╚══════════════════════════════════════════════════════╝\n\n";

        // Получить информацию о воронке
        $funnelInfo = $this->getFunnelInfo();

        if (!$funnelInfo['success']) {
            return $funnelInfo;
        }

        echo "📊 Воронка: {$funnelInfo['name']}\n";
        echo "📅 Период: {$this->dateFrom} — {$this->dateTo}\n\n";

        // Получить стадии
        $stages = $this->getStages();

        if (!$stages['success']) {
            return $stages;
        }

        echo "═══════════════════════════════════════════════════════\n\n";

        // Собрать статистику по каждой стадии
        $statistics = $this->collectStatistics($stages['data']);

        // Вывести отчет в консоль
        $this->printReport($statistics);

        // Рассчитать общую конверсию
        $overallConversion = $this->calculateOverallConversion($statistics);

        // Вывести общую конверсию
        $this->printOverallConversion($overallConversion);

        // Экспортировать в CSV
        $csvFile = $this->exportToCSV($statistics);

        return [
            'success' => true,
            'statistics' => $statistics,
            'overallConversion' => $overallConversion,
            'csvFile' => $csvFile
        ];
    }

    /**
     * Получить информацию о воронке
     *
     * @return array
     */
    private function getFunnelInfo()
    {
        if ($this->categoryId === 0) {
            return [
                'success' => true,
                'name' => 'Общая воронка'
            ];
        }

        $result = CRest::call('crm.dealcategory.get', [
            'id' => $this->categoryId
        ]);

        if (isset($result['result'])) {
            return [
                'success' => true,
                'name' => $result['result']['NAME']
            ];
        }

        return [
            'success' => false,
            'error' => 'Воронка не найдена'
        ];
    }

    /**
     * Получить все стадии воронки
     *
     * @return array
     */
    private function getStages()
    {
        $result = CRest::call('crm.dealcategory.stage.list', [
            'id' => $this->categoryId
        ]);

        if (isset($result['result'])) {
            // Сортировать по SORT
            $stages = $result['result'];
            usort($stages, function($a, $b) {
                return $a['SORT'] <=> $b['SORT'];
            });

            return [
                'success' => true,
                'data' => $stages
            ];
        }

        return [
            'success' => false,
            'error' => 'Не удалось получить стадии'
        ];
    }

    /**
     * Собрать статистику по всем стадиям
     *
     * @param array $stages
     * @return array
     */
    private function collectStatistics($stages)
    {
        $statistics = [];

        foreach ($stages as $stage) {
            $stageId = "C{$this->categoryId}:{$stage['STATUS_ID']}";

            echo "📍 Обрабатываю стадию: {$stage['NAME']}...\n";

            // Получить количество сделок на стадии
            $dealsCount = $this->getDealsCountOnStage($stageId);

            // Получить сумму сделок на стадии
            $dealsSum = $this->getDealsSumOnStage($stageId);

            $statistics[] = [
                'stageId' => $stageId,
                'statusId' => $stage['STATUS_ID'],
                'name' => $stage['NAME'],
                'semantics' => $stage['SEMANTICS'],
                'color' => $stage['COLOR'],
                'count' => $dealsCount,
                'sum' => $dealsSum,
                'conversion' => 0 // Будет рассчитана позже
            ];
        }

        // Рассчитать конверсию между стадиями
        for ($i = 0; $i < count($statistics) - 1; $i++) {
            if ($statistics[$i]['count'] > 0) {
                $statistics[$i]['conversion'] = round(
                    ($statistics[$i + 1]['count'] / $statistics[$i]['count']) * 100,
                    2
                );
            }
        }

        return $statistics;
    }

    /**
     * Получить количество сделок на стадии
     *
     * @param string $stageId
     * @return int
     */
    private function getDealsCountOnStage($stageId)
    {
        $result = CRest::call('crm.deal.list', [
            'filter' => [
                'CATEGORY_ID' => $this->categoryId,
                'STAGE_ID' => $stageId,
                '>=BEGINDATE' => $this->dateFrom,
                '<=BEGINDATE' => $this->dateTo . ' 23:59:59'
            ],
            'select' => ['ID']
        ]);

        return isset($result['result']) ? count($result['result']) : 0;
    }

    /**
     * Получить сумму сделок на стадии
     *
     * @param string $stageId
     * @return float
     */
    private function getDealsSumOnStage($stageId)
    {
        $result = CRest::call('crm.deal.list', [
            'filter' => [
                'CATEGORY_ID' => $this->categoryId,
                'STAGE_ID' => $stageId,
                '>=BEGINDATE' => $this->dateFrom,
                '<=BEGINDATE' => $this->dateTo . ' 23:59:59'
            ],
            'select' => ['OPPORTUNITY']
        ]);

        if (!isset($result['result'])) {
            return 0;
        }

        $sum = 0;
        foreach ($result['result'] as $deal) {
            $sum += (float)$deal['OPPORTUNITY'];
        }

        return $sum;
    }

    /**
     * Вывести отчет в консоль
     *
     * @param array $statistics
     * @return void
     */
    private function printReport($statistics)
    {
        echo "\n";
        echo "┌─────────────────────────────────────────────────────┐\n";
        echo "│              СТАТИСТИКА ПО СТАДИЯМ                  │\n";
        echo "└─────────────────────────────────────────────────────┘\n\n";

        foreach ($statistics as $stat) {
            $semanticIcon = $this->getSemanticIcon($stat['semantics']);

            echo "{$semanticIcon} {$stat['name']}\n";
            echo "   Сделок: {$stat['count']}\n";
            echo "   Сумма: " . number_format($stat['sum'], 0, '.', ' ') . " руб.\n";

            if ($stat['conversion'] > 0) {
                $conversionBar = $this->getConversionBar($stat['conversion']);
                echo "   Конверсия в след. стадию: {$stat['conversion']}% {$conversionBar}\n";
            }

            echo "\n";
        }
    }

    /**
     * Рассчитать общую конверсию воронки
     *
     * @param array $statistics
     * @return array
     */
    private function calculateOverallConversion($statistics)
    {
        // Найти первую и финальную успешную стадию
        $firstStage = $statistics[0] ?? null;
        $successStage = null;

        foreach ($statistics as $stat) {
            if ($stat['semantics'] === 'success') {
                $successStage = $stat;
                break;
            }
        }

        if (!$firstStage || !$successStage || $firstStage['count'] === 0) {
            return [
                'success' => false,
                'error' => 'Недостаточно данных для расчета'
            ];
        }

        $overallConversion = round(
            ($successStage['count'] / $firstStage['count']) * 100,
            2
        );

        return [
            'success' => true,
            'firstStageName' => $firstStage['name'],
            'firstStageCount' => $firstStage['count'],
            'successStageName' => $successStage['name'],
            'successStageCount' => $successStage['count'],
            'conversion' => $overallConversion
        ];
    }

    /**
     * Вывести общую конверсию
     *
     * @param array $conversion
     * @return void
     */
    private function printOverallConversion($conversion)
    {
        if (!$conversion['success']) {
            return;
        }

        echo "┌─────────────────────────────────────────────────────┐\n";
        echo "│              ОБЩАЯ КОНВЕРСИЯ ВОРОНКИ                │\n";
        echo "└─────────────────────────────────────────────────────┘\n\n";

        echo "🎯 {$conversion['firstStageName']} → {$conversion['successStageName']}\n";
        echo "   Начало: {$conversion['firstStageCount']} сделок\n";
        echo "   Успешно: {$conversion['successStageCount']} сделок\n";
        echo "   Конверсия: {$conversion['conversion']}%\n\n";

        $bar = $this->getConversionBar($conversion['conversion'], 50);
        echo "   {$bar}\n\n";
    }

    /**
     * Экспортировать в CSV
     *
     * @param array $statistics
     * @return string Путь к файлу
     */
    private function exportToCSV($statistics)
    {
        $filename = __DIR__ . '/funnel_report_' . date('Y-m-d_H-i-s') . '.csv';

        $fp = fopen($filename, 'w');

        // BOM для корректного отображения кириллицы в Excel
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

        // Заголовки
        fputcsv($fp, [
            'Стадия',
            'Количество сделок',
            'Сумма (руб.)',
            'Конверсия в след. стадию (%)',
            'Тип стадии'
        ], ';');

        // Данные
        foreach ($statistics as $stat) {
            fputcsv($fp, [
                $stat['name'],
                $stat['count'],
                $stat['sum'],
                $stat['conversion'] > 0 ? $stat['conversion'] : '-',
                $this->getSemanticName($stat['semantics'])
            ], ';');
        }

        fclose($fp);

        echo "💾 Отчет экспортирован в CSV: {$filename}\n\n";

        return $filename;
    }

    /**
     * Получить иконку для семантики
     *
     * @param string $semantics
     * @return string
     */
    private function getSemanticIcon($semantics)
    {
        return match($semantics) {
            'process' => '🔄',
            'success' => '✅',
            'failure' => '❌',
            default => '•'
        };
    }

    /**
     * Получить название семантики на русском
     *
     * @param string $semantics
     * @return string
     */
    private function getSemanticName($semantics)
    {
        return match($semantics) {
            'process' => 'Процесс',
            'success' => 'Успех',
            'failure' => 'Провал',
            default => 'Неизвестно'
        };
    }

    /**
     * Получить визуальную полоску конверсии
     *
     * @param float $percentage
     * @param int $length Длина полоски
     * @return string
     */
    private function getConversionBar($percentage, $length = 20)
    {
        $filled = round(($percentage / 100) * $length);
        $empty = $length - $filled;

        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);

        return $bar;
    }

    /**
     * Создать сравнительный отчет за два периода
     *
     * @param string $period1From
     * @param string $period1To
     * @param string $period2From
     * @param string $period2To
     * @return array
     */
    public function generateComparisonReport($period1From, $period1To, $period2From, $period2To)
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════╗\n";
        echo "║      Сравнительный отчет по двум периодам           ║\n";
        echo "╚══════════════════════════════════════════════════════╝\n\n";

        // Период 1
        echo "📅 Период 1: {$period1From} — {$period1To}\n";
        $this->dateFrom = $period1From;
        $this->dateTo = $period1To;
        $stages = $this->getStages();
        $stats1 = $this->collectStatistics($stages['data']);

        echo "\n";

        // Период 2
        echo "📅 Период 2: {$period2From} — {$period2To}\n";
        $this->dateFrom = $period2From;
        $this->dateTo = $period2To;
        $stats2 = $this->collectStatistics($stages['data']);

        echo "\n";
        echo "═══════════════════════════════════════════════════════\n\n";

        // Сравнение
        echo "┌─────────────────────────────────────────────────────┐\n";
        echo "│                  СРАВНЕНИЕ ПЕРИОДОВ                 │\n";
        echo "└─────────────────────────────────────────────────────┘\n\n";

        foreach ($stats1 as $index => $stat1) {
            $stat2 = $stats2[$index];
            $diff = $stat2['count'] - $stat1['count'];
            $diffPercent = $stat1['count'] > 0
                ? round((($stat2['count'] - $stat1['count']) / $stat1['count']) * 100, 2)
                : 0;

            $icon = $diff > 0 ? '📈' : ($diff < 0 ? '📉' : '➖');

            echo "{$this->getSemanticIcon($stat1['semantics'])} {$stat1['name']}\n";
            echo "   Период 1: {$stat1['count']} сделок\n";
            echo "   Период 2: {$stat2['count']} сделок\n";
            echo "   {$icon} Изменение: {$diff} ({$diffPercent}%)\n\n";
        }

        return [
            'success' => true,
            'period1' => $stats1,
            'period2' => $stats2
        ];
    }
}

// ==========================================
// ЗАПУСК
// ==========================================

// Пример 1: Отчет за текущий месяц
$generator = new FunnelReportGenerator(0, date('Y-m-01'), date('Y-m-d'));
$result = $generator->generateReport();

// Пример 2: Сравнительный отчет (раскомментируйте при необходимости)
/*
$generator2 = new FunnelReportGenerator(0);
$comparison = $generator2->generateComparisonReport(
    '2024-01-01', '2024-01-31',  // Период 1
    '2024-02-01', '2024-02-29'   // Период 2
);
*/

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║   ✅ Отчет успешно сформирован!                     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n";
