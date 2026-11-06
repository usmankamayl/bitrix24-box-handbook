<?php
/**
 * Агент синхронизации остатков со внешней системой
 *
 * Автоматически запускается каждые 24 часа для:
 * - Синхронизации остатков с внешним API (например, 1C)
 * - Проверки товаров с низким остатком
 * - Отправки уведомлений менеджерам
 */

namespace Acme\Warehouse\Agents;

use Acme\Warehouse\Model\ProductTable;
use Acme\Warehouse\Service\ProductService;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;

class SyncAgent
{
    /**
     * Запуск агента синхронизации
     *
     * @return string Строка для следующего запуска агента
     */
    public static function run(): string
    {
        // Проверяем, включена ли синхронизация в настройках
        $syncEnabled = Option::get('acme.warehouse', 'sync_enabled', 'N');

        if ($syncEnabled !== 'Y') {
            AddMessage2Log('SyncAgent: Синхронизация отключена в настройках', 'acme.warehouse');
            return '\Acme\Warehouse\Agents\SyncAgent::run();';
        }

        try {
            // 1. Синхронизация с внешним API
            self::syncWithExternalApi();

            // 2. Проверка товаров с низким остатком
            self::checkLowStock();

            // 3. Отправка отчета менеджерам
            self::sendDailyReport();

            AddMessage2Log('SyncAgent: Синхронизация завершена успешно', 'acme.warehouse');
        } catch (\Exception $e) {
            AddMessage2Log(
                'SyncAgent: Ошибка синхронизации - ' . $e->getMessage(),
                'acme.warehouse'
            );
        }

        // Возвращаем строку для следующего запуска
        return '\Acme\Warehouse\Agents\SyncAgent::run();';
    }

    /**
     * Синхронизация остатков с внешним API
     */
    protected static function syncWithExternalApi(): void
    {
        $apiUrl = Option::get('acme.warehouse', 'api_url', '');
        $apiKey = Option::get('acme.warehouse', 'api_key', '');

        if (empty($apiUrl) || empty($apiKey)) {
            AddMessage2Log('SyncAgent: API не настроен', 'acme.warehouse');
            return;
        }

        // Получаем данные из внешнего API
        $httpClient = new HttpClient();
        $httpClient->setHeader('Authorization', 'Bearer ' . $apiKey);
        $httpClient->setHeader('Content-Type', 'application/json');

        $response = $httpClient->get($apiUrl . '/api/warehouse/products');

        if ($httpClient->getStatus() !== 200) {
            throw new \Exception('API вернул ошибку: ' . $httpClient->getStatus());
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['products'])) {
            throw new \Exception('Некорректный формат ответа API');
        }

        // Обновляем остатки
        $updated = 0;
        foreach ($data['products'] as $item) {
            if (empty($item['sku']) || !isset($item['quantity'])) {
                continue;
            }

            $product = ProductTable::getList([
                'filter' => ['SKU' => $item['sku']],
                'limit' => 1,
            ])->fetch();

            if ($product) {
                // Обновляем только если остаток изменился
                if ($product['QUANTITY'] != $item['quantity']) {
                    ProductTable::update($product['ID'], [
                        'QUANTITY' => (int)$item['quantity'],
                    ]);

                    AddMessage2Log(sprintf(
                        'SyncAgent: Обновлен остаток %s: %d -> %d',
                        $item['sku'],
                        $product['QUANTITY'],
                        $item['quantity']
                    ), 'acme.warehouse');

                    $updated++;
                }
            }
        }

        AddMessage2Log("SyncAgent: Обновлено товаров: {$updated}", 'acme.warehouse');
    }

    /**
     * Проверка товаров с низким остатком
     */
    protected static function checkLowStock(): void
    {
        $threshold = (int)Option::get('acme.warehouse', 'low_stock_threshold', 5);

        $service = new ProductService();
        $lowStockProducts = $service->getLowStockProducts($threshold);

        if (empty($lowStockProducts)) {
            return;
        }

        // Формируем список товаров для уведомления
        $message = "Товары с низким остатком (менее {$threshold} шт.):\n\n";

        foreach ($lowStockProducts as $product) {
            $message .= sprintf(
                "- %s (SKU: %s) - остаток: %d шт.\n",
                $product['NAME'],
                $product['SKU'],
                $product['QUANTITY']
            );
        }

        // Отправляем уведомление (можно через почтовое событие)
        AddMessage2Log(
            'SyncAgent: Найдено товаров с низким остатком: ' . count($lowStockProducts),
            'acme.warehouse'
        );

        // TODO: Отправить email менеджерам
        // \CEvent::Send('WAREHOUSE_LOW_STOCK', SITE_ID, ['MESSAGE' => $message]);
    }

    /**
     * Отправка ежедневного отчета
     */
    protected static function sendDailyReport(): void
    {
        $service = new ProductService();
        $stats = $service->getStatistics();

        $message = "Ежедневный отчет по складу:\n\n";
        $message .= "Всего наименований: {$stats['total_products']}\n";
        $message .= "Всего единиц товара: {$stats['total_quantity']}\n";
        $message .= "Общая стоимость: " . number_format($stats['total_value'], 2, '.', ' ') . " руб.\n";
        $message .= "Товары с низким остатком: {$stats['low_stock']}\n";
        $message .= "Товары с нулевым остатком: {$stats['out_of_stock']}\n";

        AddMessage2Log('SyncAgent: ' . $message, 'acme.warehouse');

        // TODO: Отправить email менеджерам
        // \CEvent::Send('WAREHOUSE_DAILY_REPORT', SITE_ID, ['MESSAGE' => $message]);
    }
}
