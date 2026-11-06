<?php

require_once __DIR__ . '/SyncQueue.php';

// Подключать в init.php модуля-прослойки или через AddEventHandler в init-скрипте.
// Обработчик НЕ делает сетевых вызовов — только кладёт задачу в очередь,
// чтобы недоступность AmoCRM не тормозила сохранение сделки в Bitrix24.

AddEventHandler('crm', 'OnAfterCrmDealAdd', 'enqueueDealSync');
AddEventHandler('crm', 'OnAfterCrmDealUpdate', 'enqueueDealSync');

function enqueueDealSync(array &$fields): void
{
    (new SyncQueue())->enqueue('deal', (int) $fields['ID'], 'amocrm');
}
