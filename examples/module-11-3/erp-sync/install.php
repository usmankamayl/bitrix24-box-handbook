<?php
/**
 * Регистрация:
 *  1) активити "Отправить заказ в ERP" (асинхронно — ставит в очередь)
 *  2) события onCrmDealUpdate для защиты от петли и обратной синхронизации
 */

require_once __DIR__ . '/crest.php';

// 1. Активити: ставит заказ в очередь интеграции
$r1 = CRest::call('bizproc.activity.add', [
    'CODE'         => 'ERP_EXPORT_ORDER',
    'HANDLER'      => 'https://your-domain.com/bitrix-app/export.php',
    'AUTH_USER_ID' => 1,
    'NAME'         => ['ru' => 'ERP: экспорт заказа'],
    'TYPE'         => 'ROBOT',
    'PROPERTIES'   => [
        'ORDER_TYPE' => [
            'Name' => ['ru' => 'Тип заказа'],
            'Type' => 'select',
            'Options' => ['sale' => 'Продажа', 'purchase' => 'Закупка'],
            'Default' => 'sale',
        ],
    ],
    'RETURN_PROPERTIES' => [
        'ERP_ORDER_ID' => ['Name' => ['ru' => 'ID в ERP'], 'Type' => 'string'],
        'STATUS'       => ['Name' => ['ru' => 'Статус'], 'Type' => 'string'],
    ],
    'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL'],
]);

// 2. Событие: отслеживаем изменения сделки (для обратной синхронизации)
$r2 = CRest::call('event.bind', [
    'event'   => 'onCrmDealUpdate',
    'handler' => 'https://your-domain.com/bitrix-app/on_deal_update.php',
]);

echo '<pre>'; print_r([$r1, $r2]); echo '</pre>';
