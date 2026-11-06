<?php
/**
 * Автоматизация работы со сделками через вебхуки
 *
 * Функционал:
 * - Автоматическое создание сделки при создании лида
 * - Перемещение сделки по стадиям при обновлении
 * - Уведомления при важных событиях
 * - Автоматическое назначение ответственного
 * - Создание активностей (звонки, встречи)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DealAutomationLogger.php';
require_once __DIR__ . '/Bitrix24Client.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Проверка безопасности
    $token = $_GET['token'] ?? '';
    if ($token !== WEBHOOK_SECRET_TOKEN) {
        http_response_code(403);
        exit(json_encode(['error' => 'Forbidden']));
    }

    // 2. Получение данных события
    $input = file_get_contents('php://input');
    $event = json_decode($input, true);

    if (empty($event) || !isset($event['event'])) {
        http_response_code(400);
        exit(json_encode(['error' => 'Bad request']));
    }

    // 3. Проверка webhook_id
    $webhookId = $_SERVER['HTTP_X_BITRIX24_WEBHOOK_ID'] ?? md5($input . time());
    $logger = new DealAutomationLogger();

    if ($logger->isWebhookProcessed($webhookId)) {
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }

    // 4. Быстрый ответ
    http_response_code(200);
    echo json_encode(['status' => 'received', 'webhook_id' => $webhookId]);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        flush();
        ob_flush();
    }

    // 5. Обработка события
    $eventName = $event['event'];
    $eventData = $event['data'] ?? [];

    $logger->logEvent($webhookId, $eventName, $eventData);
    $bitrix = new Bitrix24Client();

    switch ($eventName) {
        case 'ONCRMLEADADD':
        case 'onCrmLeadAdd':
            handleLeadAddForDeal($eventData, $bitrix, $logger);
            break;

        case 'ONCRMLEADUPDATE':
        case 'onCrmLeadUpdate':
            handleLeadUpdateForDeal($eventData, $bitrix, $logger);
            break;

        case 'ONCRMDEALUPDATE':
        case 'onCrmDealUpdate':
            handleDealUpdate($eventData, $bitrix, $logger);
            break;

        case 'ONCRMDEALSTAGECHANGE':
        case 'onCrmDealStageChange':
            handleDealStageChange($eventData, $bitrix, $logger);
            break;

        default:
            $logger->logWarning("Unknown event: $eventName");
    }

} catch (Exception $e) {
    $logger->logError('Exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * Обработка создания лида - автоматическое создание сделки
 */
function handleLeadAddForDeal($data, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $leadId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $leadTitle = $data['FIELDS']['TITLE'] ?? $data['title'] ?? 'Без названия';
    $responsibleId = $data['FIELDS']['ASSIGNED_BY_ID'] ?? $data['assigned_by_id'] ?? 1;
    $sourceId = $data['FIELDS']['SOURCE_ID'] ?? $data['source_id'] ?? 'OTHER';

    $logger->logInfo("Processing lead #$leadId for deal creation");

    // Проверяем, не создана ли уже сделка для этого лида
    if (isDealAlreadyCreatedForLead($leadId, $bitrix)) {
        $logger->logInfo("Deal already exists for lead #$leadId");
        return;
    }

    // Создаем сделку на основе лида
    $dealData = [
        'TITLE' => $leadTitle,
        'TYPE_ID' => 'SALE', // Тип сделки: продажа
        'STAGE_ID' => 'C1:NEW', // Начальная стадия
        'ASSIGNED_BY_ID' => $responsibleId,
        'SOURCE_ID' => $sourceId,
        'COMMENTS' => "Автоматически создано из лида #$leadId",
        'UF_CRM_LEAD_ID' => $leadId, // Связь с лидом (пользовательское поле)
    ];

    $result = $bitrix->createDeal($dealData);

    if ($result['success']) {
        $dealId = $result['id'];
        $logger->logInfo("Deal #$dealId created from lead #$leadId");

        // Создаем активность "Позвонить клиенту"
        createCallActivity($dealId, $responsibleId, $bitrix, $logger);

        // Отправляем уведомление
        $bitrix->sendNotification($responsibleId,
            "Создана сделка #$dealId",
            "Автоматически создана сделка из лида #$leadId\n" .
            "Название: $leadTitle\n\n" .
            "Перейти к сделке: " . BITRIX24_DOMAIN . "/crm/deal/details/$dealId/"
        );
    } else {
        $logger->logError("Failed to create deal from lead #$leadId", $result);
    }
}

/**
 * Обработка обновления лида
 */
function handleLeadUpdateForDeal($data, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $leadId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $statusId = $data['FIELDS']['STATUS_ID'] ?? $data['status_id'] ?? null;

    // Если лид конвертирован - создаем сделку
    if ($statusId === 'CONVERTED') {
        $logger->logInfo("Lead #$leadId converted - creating deal");
        handleLeadAddForDeal($data, $bitrix, $logger);
    }
}

/**
 * Обработка обновления сделки
 */
function handleDealUpdate($data, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $dealId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $stageId = $data['FIELDS']['STAGE_ID'] ?? $data['stage_id'] ?? null;
    $probability = $data['FIELDS']['PROBABILITY'] ?? $data['probability'] ?? null;

    $logger->logInfo("Deal #$dealId updated", [
        'stage' => $stageId,
        'probability' => $probability
    ]);

    // Если вероятность низкая - уведомляем руководителя
    if ($probability !== null && $probability < 30) {
        $responsibleId = $data['FIELDS']['ASSIGNED_BY_ID'] ?? 1;

        $bitrix->sendNotification(SALES_MANAGER_ID ?? 1,
            "⚠️ Сделка с низкой вероятностью",
            "Сделка #$dealId имеет вероятность $probability%\n" .
            "Возможно, требуется помощь руководителя."
        );
    }
}

/**
 * Обработка изменения стадии сделки
 */
function handleDealStageChange($data, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $dealId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $stageId = $data['FIELDS']['STAGE_ID'] ?? $data['stage_id'] ?? null;
    $oldStageId = $data['FIELDS']['OLD_STAGE_ID'] ?? null;
    $responsibleId = $data['FIELDS']['ASSIGNED_BY_ID'] ?? 1;

    $logger->logInfo("Deal #$dealId stage changed: $oldStageId → $stageId");

    // Автоматические действия в зависимости от стадии
    switch ($stageId) {
        case 'C1:PREPARATION':
            // Стадия "Подготовка коммерческого предложения"
            createTaskForProposal($dealId, $responsibleId, $bitrix, $logger);
            break;

        case 'C1:PREPAYMENT_INVOICE':
            // Стадия "Выставлен счет"
            createPaymentReminder($dealId, $responsibleId, $bitrix, $logger);
            break;

        case 'C1:EXECUTING':
            // Стадия "Выполняется"
            notifyProductionTeam($dealId, $bitrix, $logger);
            break;

        case 'C1:WON':
            // Сделка выиграна
            handleDealWon($dealId, $data, $bitrix, $logger);
            break;

        case 'C1:LOSE':
            // Сделка проиграна
            handleDealLost($dealId, $data, $bitrix, $logger);
            break;
    }
}

/**
 * Проверка, существует ли уже сделка для лида
 */
function isDealAlreadyCreatedForLead($leadId, Bitrix24Client $bitrix)
{
    $deals = $bitrix->getDeals([
        'filter' => ['UF_CRM_LEAD_ID' => $leadId],
        'select' => ['ID']
    ]);

    return !empty($deals['items']);
}

/**
 * Создание активности "Позвонить клиенту"
 */
function createCallActivity($dealId, $responsibleId, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $activityData = [
        'OWNER_TYPE_ID' => 2, // 2 = Сделка
        'OWNER_ID' => $dealId,
        'TYPE_ID' => 2, // 2 = Звонок
        'SUBJECT' => 'Первичный звонок клиенту',
        'DESCRIPTION' => 'Позвонить клиенту для уточнения деталей',
        'RESPONSIBLE_ID' => $responsibleId,
        'PRIORITY' => 2, // Высокий приоритет
        'DEADLINE' => date('Y-m-d\TH:i:s', strtotime('+1 day')),
    ];

    $result = $bitrix->createActivity($activityData);

    if ($result['success']) {
        $logger->logInfo("Call activity created for deal #$dealId");
    }
}

/**
 * Создание задачи на подготовку коммерческого предложения
 */
function createTaskForProposal($dealId, $responsibleId, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $taskData = [
        'TITLE' => "Подготовить КП для сделки #$dealId",
        'DESCRIPTION' => "Необходимо подготовить коммерческое предложение для клиента.\n\n" .
                         "Ссылка на сделку: " . BITRIX24_DOMAIN . "/crm/deal/details/$dealId/",
        'RESPONSIBLE_ID' => $responsibleId,
        'DEADLINE' => date('d.m.Y', strtotime('+2 days')),
        'PRIORITY' => 2
    ];

    $result = $bitrix->createTask($taskData);

    if ($result['success']) {
        $logger->logInfo("Task created for proposal: deal #$dealId");
    }
}

/**
 * Создание напоминания об оплате
 */
function createPaymentReminder($dealId, $responsibleId, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $activityData = [
        'OWNER_TYPE_ID' => 2,
        'OWNER_ID' => $dealId,
        'TYPE_ID' => 2,
        'SUBJECT' => 'Напомнить об оплате счета',
        'DESCRIPTION' => 'Связаться с клиентом и напомнить об оплате выставленного счета',
        'RESPONSIBLE_ID' => $responsibleId,
        'PRIORITY' => 2,
        'DEADLINE' => date('Y-m-d\TH:i:s', strtotime('+3 days')),
    ];

    $bitrix->createActivity($activityData);
    $logger->logInfo("Payment reminder created for deal #$dealId");
}

/**
 * Уведомление команды производства
 */
function notifyProductionTeam($dealId, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $productionTeamIds = PRODUCTION_TEAM_IDS ?? [1];

    foreach ($productionTeamIds as $userId) {
        $bitrix->sendNotification($userId,
            "🔧 Новый заказ в производство",
            "Сделка #$dealId передана в производство.\n" .
            "Перейти к сделке: " . BITRIX24_DOMAIN . "/crm/deal/details/$dealId/"
        );
    }

    $logger->logInfo("Production team notified for deal #$dealId");
}

/**
 * Обработка выигранной сделки
 */
function handleDealWon($dealId, $data, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $dealSum = $data['FIELDS']['OPPORTUNITY'] ?? $data['sum'] ?? 0;
    $dealTitle = $data['FIELDS']['TITLE'] ?? 'Без названия';
    $responsibleId = $data['FIELDS']['ASSIGNED_BY_ID'] ?? 1;

    $logger->logInfo("Deal #$dealId WON - sum: $dealSum");

    // Поздравляем ответственного
    $bitrix->sendNotification($responsibleId,
        "🎉 Поздравляем! Сделка выиграна!",
        "Сделка #$dealId \"$dealTitle\" успешно закрыта!\n" .
        "Сумма: " . number_format($dealSum, 0, '.', ' ') . " руб.\n\n" .
        "Отличная работа!"
    );

    // Уведомляем руководителя
    $bitrix->sendNotification(SALES_MANAGER_ID ?? 1,
        "💰 Сделка закрыта",
        "Менеджер закрыл сделку #$dealId на сумму " .
        number_format($dealSum, 0, '.', ' ') . " руб."
    );

    // Можно добавить интеграцию с бухгалтерией/складом
    // syncToAccounting($dealId, $dealSum);
}

/**
 * Обработка проигранной сделки
 */
function handleDealLost($dealId, $data, Bitrix24Client $bitrix, DealAutomationLogger $logger)
{
    $dealTitle = $data['FIELDS']['TITLE'] ?? 'Без названия';
    $responsibleId = $data['FIELDS']['ASSIGNED_BY_ID'] ?? 1;

    $logger->logInfo("Deal #$dealId LOST");

    // Создаем задачу на анализ причин
    $taskData = [
        'TITLE' => "Проанализировать причины проигрыша сделки #$dealId",
        'DESCRIPTION' => "Необходимо понять, почему сделка \"$dealTitle\" была проиграна.\n\n" .
                         "Ссылка: " . BITRIX24_DOMAIN . "/crm/deal/details/$dealId/",
        'RESPONSIBLE_ID' => $responsibleId,
        'DEADLINE' => date('d.m.Y', strtotime('+1 day')),
        'PRIORITY' => 1
    ];

    $bitrix->createTask($taskData);
}
