<?php
/**
 * Синхронизация данных Bitrix24 с внешней системой через вебхуки
 *
 * Функционал:
 * - Отправка данных о сделках в 1C при изменении статуса
 * - Синхронизация контактов с внешней CRM
 * - Отправка уведомлений в Slack/Telegram
 * - Обработка ошибок интеграции с повторными попытками
 * - Логирование всех операций синхронизации
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ExternalSyncLogger.php';
require_once __DIR__ . '/ExternalSystemClient.php';

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
    $logger = new ExternalSyncLogger();

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
    $externalClient = new ExternalSystemClient();

    switch ($eventName) {
        case 'ONCRMDEALUPDATE':
        case 'onCrmDealUpdate':
            syncDealToExternalSystem($eventData, $externalClient, $logger);
            break;

        case 'ONCRMDEALSTAGECHANGE':
        case 'onCrmDealStageChange':
            handleDealStageChangeSync($eventData, $externalClient, $logger);
            break;

        case 'ONCRMCONTACTADD':
        case 'onCrmContactAdd':
            syncContactToExternalSystem($eventData, $externalClient, $logger);
            break;

        case 'ONCRMCONTACTUPDATE':
        case 'onCrmContactUpdate':
            updateContactInExternalSystem($eventData, $externalClient, $logger);
            break;

        default:
            $logger->logWarning("Unhandled event: $eventName");
    }

} catch (Exception $e) {
    $logger->logError('Exception in sync handler', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * Синхронизация сделки с внешней системой
 */
function syncDealToExternalSystem($data, ExternalSystemClient $client, ExternalSyncLogger $logger)
{
    $dealId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $dealTitle = $data['FIELDS']['TITLE'] ?? $data['title'] ?? 'Без названия';
    $stageId = $data['FIELDS']['STAGE_ID'] ?? $data['stage_id'] ?? null;
    $dealSum = $data['FIELDS']['OPPORTUNITY'] ?? $data['sum'] ?? 0;

    $logger->logInfo("Syncing deal #$dealId to external system");

    // Подготавливаем данные для внешней системы
    $externalData = [
        'bitrix_id' => $dealId,
        'title' => $dealTitle,
        'stage' => $stageId,
        'amount' => $dealSum,
        'currency' => $data['FIELDS']['CURRENCY_ID'] ?? 'RUB',
        'synced_at' => date('Y-m-d H:i:s')
    ];

    // Отправляем в 1C
    if (ENABLE_1C_SYNC) {
        $result = $client->sendTo1C('deals/update', $externalData);

        if ($result['success']) {
            $logger->logInfo("Deal #$dealId synced to 1C successfully");
        } else {
            $logger->logError("Failed to sync deal #$dealId to 1C", [
                'error' => $result['error']
            ]);

            // Добавляем в очередь повторной отправки
            queueForRetry('1C', 'deal', $dealId, $externalData, $logger);
        }
    }

    // Отправляем в другую CRM (опционально)
    if (ENABLE_EXTERNAL_CRM_SYNC) {
        $result = $client->sendToExternalCRM('deals', $externalData);

        if ($result['success']) {
            $logger->logInfo("Deal #$dealId synced to external CRM");
        } else {
            $logger->logError("Failed to sync deal #$dealId to external CRM", [
                'error' => $result['error']
            ]);
        }
    }
}

/**
 * Обработка изменения стадии сделки для синхронизации
 */
function handleDealStageChangeSync($data, ExternalSystemClient $client, ExternalSyncLogger $logger)
{
    $dealId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $stageId = $data['FIELDS']['STAGE_ID'] ?? $data['stage_id'] ?? null;
    $oldStageId = $data['FIELDS']['OLD_STAGE_ID'] ?? null;

    $logger->logInfo("Deal #$dealId stage changed: $oldStageId → $stageId");

    // Синхронизируем сделку
    syncDealToExternalSystem($data, $client, $logger);

    // Отправляем уведомление в Slack
    if ($stageId === 'C1:WON' && ENABLE_SLACK_NOTIFICATIONS) {
        $dealTitle = $data['FIELDS']['TITLE'] ?? 'Без названия';
        $dealSum = $data['FIELDS']['OPPORTUNITY'] ?? 0;

        $message = "🎉 *Сделка выиграна!*\n\n" .
                   "Сделка: $dealTitle\n" .
                   "Сумма: " . number_format($dealSum, 0, '.', ' ') . " руб.\n" .
                   "ID: #$dealId";

        $client->sendToSlack($message, SLACK_CHANNEL_SALES);
        $logger->logInfo("Slack notification sent for deal #$dealId");
    }

    // Отправляем в Telegram
    if ($stageId === 'C1:WON' && ENABLE_TELEGRAM_NOTIFICATIONS) {
        $dealTitle = $data['FIELDS']['TITLE'] ?? 'Без названия';
        $dealSum = $data['FIELDS']['OPPORTUNITY'] ?? 0;

        $message = "🎉 Сделка выиграна!\n\n" .
                   "Сделка: $dealTitle\n" .
                   "Сумма: " . number_format($dealSum, 0, '.', ' ') . " руб.";

        $client->sendToTelegram($message);
        $logger->logInfo("Telegram notification sent for deal #$dealId");
    }
}

/**
 * Синхронизация контакта с внешней системой
 */
function syncContactToExternalSystem($data, ExternalSystemClient $client, ExternalSyncLogger $logger)
{
    $contactId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $contactName = $data['FIELDS']['NAME'] ?? $data['name'] ?? '';
    $contactLastName = $data['FIELDS']['LAST_NAME'] ?? $data['last_name'] ?? '';
    $contactPhone = $data['FIELDS']['PHONE'] ?? $data['phone'] ?? [];
    $contactEmail = $data['FIELDS']['EMAIL'] ?? $data['email'] ?? [];

    $logger->logInfo("Syncing contact #$contactId to external system");

    // Подготавливаем данные
    $externalData = [
        'bitrix_id' => $contactId,
        'first_name' => $contactName,
        'last_name' => $contactLastName,
        'phone' => extractFirstValue($contactPhone),
        'email' => extractFirstValue($contactEmail),
        'synced_at' => date('Y-m-d H:i:s')
    ];

    // Отправляем в 1C
    if (ENABLE_1C_SYNC) {
        $result = $client->sendTo1C('contacts/create', $externalData);

        if ($result['success']) {
            $logger->logInfo("Contact #$contactId synced to 1C");

            // Сохраняем external_id для дальнейшей синхронизации
            if (isset($result['external_id'])) {
                saveExternalMapping('contact', $contactId, '1C', $result['external_id']);
            }
        } else {
            $logger->logError("Failed to sync contact #$contactId to 1C", [
                'error' => $result['error']
            ]);
        }
    }
}

/**
 * Обновление контакта во внешней системе
 */
function updateContactInExternalSystem($data, ExternalSystemClient $client, ExternalSyncLogger $logger)
{
    $contactId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;

    $logger->logInfo("Updating contact #$contactId in external system");

    // Проверяем, синхронизирован ли контакт
    $externalId = getExternalMapping('contact', $contactId, '1C');

    if (!$externalId) {
        // Контакт еще не синхронизирован - создаем
        syncContactToExternalSystem($data, $client, $logger);
        return;
    }

    // Обновляем существующий контакт
    $externalData = [
        'external_id' => $externalId,
        'first_name' => $data['FIELDS']['NAME'] ?? '',
        'last_name' => $data['FIELDS']['LAST_NAME'] ?? '',
        'phone' => extractFirstValue($data['FIELDS']['PHONE'] ?? []),
        'email' => extractFirstValue($data['FIELDS']['EMAIL'] ?? []),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $result = $client->sendTo1C('contacts/update', $externalData);

    if ($result['success']) {
        $logger->logInfo("Contact #$contactId updated in 1C");
    } else {
        $logger->logError("Failed to update contact #$contactId in 1C", [
            'error' => $result['error']
        ]);
    }
}

/**
 * Извлечение первого значения из множественного поля
 */
function extractFirstValue($field)
{
    if (is_array($field) && !empty($field)) {
        return $field[0]['VALUE'] ?? null;
    }
    return null;
}

/**
 * Добавление в очередь повторной отправки
 */
function queueForRetry($system, $entityType, $entityId, $data, ExternalSyncLogger $logger)
{
    try {
        $db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASSWORD
        );

        $stmt = $db->prepare('
            INSERT INTO sync_retry_queue
            (system, entity_type, entity_id, data, retry_count, next_retry_at, created_at)
            VALUES (?, ?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW())
        ');

        $stmt->execute([
            $system,
            $entityType,
            $entityId,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);

        $logger->logInfo("Added to retry queue: $entityType #$entityId for $system");
    } catch (PDOException $e) {
        $logger->logError("Failed to add to retry queue", [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Сохранение маппинга между ID Bitrix и внешней системы
 */
function saveExternalMapping($entityType, $bitrixId, $externalSystem, $externalId)
{
    try {
        $db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASSWORD
        );

        $stmt = $db->prepare('
            INSERT INTO external_id_mapping
            (entity_type, bitrix_id, external_system, external_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE external_id = ?, updated_at = NOW()
        ');

        $stmt->execute([
            $entityType,
            $bitrixId,
            $externalSystem,
            $externalId,
            $externalId
        ]);
    } catch (PDOException $e) {
        error_log("Failed to save external mapping: " . $e->getMessage());
    }
}

/**
 * Получение external_id для сущности
 */
function getExternalMapping($entityType, $bitrixId, $externalSystem)
{
    try {
        $db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASSWORD
        );

        $stmt = $db->prepare('
            SELECT external_id FROM external_id_mapping
            WHERE entity_type = ? AND bitrix_id = ? AND external_system = ?
        ');

        $stmt->execute([$entityType, $bitrixId, $externalSystem]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['external_id'] ?? null;
    } catch (PDOException $e) {
        error_log("Failed to get external mapping: " . $e->getMessage());
        return null;
    }
}
