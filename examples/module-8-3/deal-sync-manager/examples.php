<?php
/**
 * Примеры использования DealSyncManager
 *
 * Расположение: /local/php_interface/examples/deal_sync_examples.php
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/DealSyncManager.php';

use Vettich\DealSyncManager;

// Инициализация REST клиента Bitrix24
$client = CRest::getInstance();

// ============================================================
// ПРИМЕР 1: Синхронизация сделок за последние 7 дней в 1C
// ============================================================

echo "=== ПРИМЕР 1: Синхронизация в 1C ===\n\n";

// Инициализация API внешней системы (1C)
class OneC_API
{
    private $apiUrl = 'https://1c.example.com/api/deals';
    private $token = 'your_1c_token';

    public function updateDeal($dealData)
    {
        // Отправляем данные в 1C
        $response = $this->sendRequest('POST', $this->apiUrl, $dealData);
        return $response;
    }

    private function sendRequest($method, $url, $data = [])
    {
        // Реализация HTTP-запроса
        // Это упрощенный пример
        return [
            'success' => true,
            'id' => $data['id'] . '_1c'
        ];
    }
}

$oneC = new OneC_API();
$sync = new DealSyncManager($client, $oneC);

// Синхронизация сделок за последние 7 дней
$result = $sync->syncModifiedDeals([
    'since' => '-7 days',
    'external_system' => '1c',
    'rollback_on_error' => true,
    'map_stage' => true
]);

$sync->printReport();

// ============================================================
// ПРИМЕР 2: Синхронизация выигранных сделок (отчет в аналитику)
// ============================================================

echo "\n=== ПРИМЕР 2: Синхронизация выигранных сделок ===\n\n";

$sync2 = new DealSyncManager($client);

// Синхронизируем только выигранные сделки за месяц
$result = $sync2->syncByFilter([
    'STAGE_ID' => 'WON',
    '>DATE_CREATE' => date('Y-m-d', strtotime('-1 month'))
], [
    'map_stage' => true
]);

$sync2->printReport();

// ============================================================
// ПРИМЕР 3: Синхронизация с откатом при ошибках
// ============================================================

echo "\n=== ПРИМЕР 3: Синхронизация с механизмом отката ===\n\n";

$sync3 = new DealSyncManager($client);

$result = $sync3->syncModifiedDeals([
    'since' => '-1 day',
    'rollback_on_error' => true,  // Если более 50% ошибок - откатываем
    'map_stage' => true,
    'sync_documents' => true
]);

$status = $sync3->getSyncStatus();

if ($status['rolled_back']) {
    echo "\n⚠ Внимание: Синхронизация была откачена из-за критических ошибок!\n";
    echo "Успешных синхронизаций: {$status['synced']}/{$status['total']}\n";
} else {
    echo "✓ Синхронизация успешно завершена!\n";
}

// ============================================================
// ПРИМЕР 4: Программная обработка результатов
// ============================================================

echo "\n=== ПРИМЕР 4: Обработка результатов ===\n\n";

$sync4 = new DealSyncManager($client);

$result = $sync4->syncModifiedDeals([
    'since' => '-1 day'
]);

$status = $sync4->getSyncStatus();

// Отправляем email-отчет администратору
if ($status['errors'] > 0) {
    $errorDetails = array_filter($status['log'], function($entry) {
        return strpos(strtolower($entry['message']), 'ошибка') !== false;
    });

    $message = "Результаты синхронизации сделок:\n";
    $message .= "- Всего: {$status['total']}\n";
    $message .= "- Синхронизировано: {$status['synced']}\n";
    $message .= "- Ошибок: {$status['errors']}\n";
    $message .= "- Процент успеха: {$status['success_rate']}%\n\n";

    if (!empty($errorDetails)) {
        $message .= "Ошибки:\n";
        foreach ($errorDetails as $error) {
            $message .= "  - {$error['message']}\n";
        }
    }

    // mail('admin@example.com', 'Отчет синхронизации сделок', $message);
    echo "Отчет подготовлен для отправки администратору\n";
}

// ============================================================
// ПРИМЕР 5: Интеграция через агент Bitrix
// ============================================================

/**
 * Агент для автоматической синхронизации с 1C
 *
 * Добавить в /bitrix/php_interface/init.php:
 * CAgent::AddAgent(
 *     "SyncDealsTo1C();",
 *     "",
 *     "N",
 *     3600, // Каждый час
 *     "",
 *     "Y",
 *     "",
 *     30
 * );
 */
function SyncDealsTo1C()
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/DealSyncManager.php';

    // Инициализируем API 1C (например, через SOAP)
    $oneC = new OneC_API_SOAP('https://1c.example.com:1567');

    $client = CRest::getInstance();
    $sync = new DealSyncManager($client, $oneC);

    // Синхронизируем сделки за час
    $result = $sync->syncModifiedDeals([
        'since' => '-1 hour',
        'external_system' => '1c',
        'rollback_on_error' => false
    ]);

    $status = $sync->getSyncStatus();

    // Логируем результат
    error_log("Deal sync to 1C: {$status['synced']}/{$status['total']} synced, {$status['errors']} errors");

    return "SyncDealsTo1C();";
}

// ============================================================
// ПРИМЕР 6: Custom маппинг стадий
// ============================================================

echo "\n=== ПРИМЕР 6: Custom маппинг стадий ===\n\n";

$sync6 = new DealSyncManager($client);

// Устанавливаем собственный маппинг стадий
$sync6->setStageMapping([
    'NEW' => 'новая',
    'PREPARATION' => 'подготовка',
    'PREPAYMENT_INVOICE' => 'счет_выставлен',
    'EXECUTING' => 'исполнение',
    'FINAL_INVOICE' => 'финальный_счет',
    'WON' => 'выиграна',
    'LOSE' => 'проиграна'
]);

$result = $sync6->syncByFilter(
    ['!STAGE_ID' => 'LOSE'],
    ['map_stage' => true]
);

$sync6->printReport();

// ============================================================
// ПРИМЕР 7: Синхронизация определенного набора сделок
// ============================================================

echo "\n=== ПРИМЕР 7: Синхронизация по фильтру ===\n\n";

$sync7 = new DealSyncManager($client);
$sync7->setBatchSize(30); // Уменьшаем размер пакета

// Синхронизируем сделки с суммой более 100000 рублей
$result = $sync7->syncByFilter([
    '>OPPORTUNITY' => 100000,
    'CURRENCY_ID' => 'RUB',
    'STAGE_ID' => ['NEW', 'PREPARATION', 'PREPAYMENT_INVOICE']
], [
    'map_stage' => true
]);

$sync7->printReport();

// ============================================================
// ПРИМЕР 8: API класс для 1C (упрощенный пример)
// ============================================================

class OneC_API_SOAP
{
    private $soapClient;
    private $wsName = 'HttpService'; // Web-сервис 1C

    public function __construct($serverUrl)
    {
        try {
            $this->soapClient = new \SoapClient($serverUrl . '/ws?wsdl', [
                'trace' => 1,
                'exceptions' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE
            ]);
        } catch (\Exception $e) {
            error_log("Ошибка подключения к 1C: " . $e->getMessage());
        }
    }

    public function updateDeal($dealData)
    {
        try {
            // Преобразуем данные в формат 1C
            $soapData = [
                'Number' => $dealData['id'],
                'Name' => $dealData['title'],
                'Amount' => $dealData['amount'],
                'Stage' => $dealData['stage'],
                'DateModified' => $dealData['modified_at']
            ];

            // Отправляем SOAP-запрос
            $result = $this->soapClient->{$this->wsName}([
                'operation' => 'UpdateDeal',
                'data' => $soapData
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'id' => $dealData['id'],
                    'external_id' => $result
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// ============================================================
// ПРИМЕР 9: CLI скрипт для синхронизации
// ============================================================

/**
 * Запуск из командной строки:
 * php deal_sync_cli.php --system=1c --period=-1day --rollback
 */

if (php_sapi_name() === 'cli') {
    $options = getopt('', ['system:', 'period:', 'rollback', 'batch-size:']);

    if (isset($options['system'])) {
        $sync = new DealSyncManager($client);

        if (isset($options['batch-size'])) {
            $sync->setBatchSize((int)$options['batch-size']);
        }

        $syncOptions = [
            'external_system' => $options['system'],
            'since' => $options['period'] ?? '-1 day',
            'rollback_on_error' => isset($options['rollback'])
        ];

        echo "Синхронизация с системой: {$options['system']}\n";
        echo "Период: {$syncOptions['since']}\n";
        echo "Откат при ошибке: " . ($syncOptions['rollback_on_error'] ? 'ДА' : 'НЕТ') . "\n\n";

        $sync->syncModifiedDeals($syncOptions);
        $sync->printReport();
    } else {
        echo "Использование:\n";
        echo "  php deal_sync_cli.php --system=1c [--period=-1day] [--rollback] [--batch-size=50]\n";
        echo "\nОпции:\n";
        echo "  --system        ID системы для синхронизации (обязательно)\n";
        echo "  --period        Период поиска сделок (default: -1day)\n";
        echo "  --rollback      Откатывать при критических ошибках\n";
        echo "  --batch-size    Размер batch-пакета\n";
    }
}
