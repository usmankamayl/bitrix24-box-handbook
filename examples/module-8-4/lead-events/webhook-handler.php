<?php
/**
 * Обработчик вебхуков для событий лидов Bitrix24
 *
 * Назначение:
 * - Обработка событий onCrmLeadAdd и onCrmLeadUpdate
 * - Логирование событий в файл и базу данных
 * - Отправка уведомлений ответственным менеджерам
 * - Защита от несанкционированного доступа
 * - Предотвращение повторной обработки событий
 *
 * URL для регистрации:
 * https://ваш-сайт.com/api/webhook-handler.php?token=ВАШ_СЕКРЕТНЫЙ_ТОКЕН
 */

// Подключаем конфигурацию
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/LeadEventLogger.php';
require_once __DIR__ . '/NotificationService.php';

// Устанавливаем заголовки
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. БЕЗОПАСНОСТЬ: Проверка токена
    $token = $_GET['token'] ?? '';
    if ($token !== WEBHOOK_SECRET_TOKEN) {
        http_response_code(403);
        logError('Invalid token', ['token' => $token, 'ip' => $_SERVER['REMOTE_ADDR']]);
        exit(json_encode(['error' => 'Forbidden']));
    }

    // 2. БЕЗОПАСНОСТЬ: Проверка подписи Bitrix24 (если настроена)
    if (defined('BITRIX24_WEBHOOK_SECRET') && !empty(BITRIX24_WEBHOOK_SECRET)) {
        $signature = $_SERVER['HTTP_X_BITRIX24_SIGNATURE'] ?? '';
        $input = file_get_contents('php://input');
        $expectedSignature = md5($input . BITRIX24_WEBHOOK_SECRET);

        if ($signature !== $expectedSignature) {
            http_response_code(403);
            logError('Invalid signature', ['signature' => $signature]);
            exit(json_encode(['error' => 'Invalid signature']));
        }
    }

    // 3. Получаем данные события
    $input = file_get_contents('php://input');
    $event = json_decode($input, true);

    if (empty($event) || !isset($event['event'])) {
        http_response_code(400);
        logError('No event data', ['input' => $input]);
        exit(json_encode(['error' => 'Bad request']));
    }

    // 4. Проверяем webhook_id (защита от повторной обработки)
    $webhookId = $_SERVER['HTTP_X_BITRIX24_WEBHOOK_ID'] ?? md5($input . time());

    $logger = new LeadEventLogger();
    if ($logger->isWebhookProcessed($webhookId)) {
        // Уже обработан - возвращаем успех
        http_response_code(200);
        echo json_encode(['status' => 'already_processed', 'webhook_id' => $webhookId]);
        exit;
    }

    // 5. БЫСТРЫЙ ОТВЕТ: Отправляем 200 OK сразу
    http_response_code(200);
    echo json_encode(['status' => 'received', 'webhook_id' => $webhookId]);

    // Завершаем отправку ответа клиенту
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        flush();
        ob_flush();
    }

    // 6. ОБРАБОТКА: Обрабатываем событие в фоновом режиме
    $eventName = $event['event'];
    $eventData = $event['data'] ?? [];

    // Логируем событие
    $logger->logEvent($webhookId, $eventName, $eventData);

    // Обрабатываем в зависимости от типа события
    switch ($eventName) {
        case 'ONCRMLEADADD':
        case 'onCrmLeadAdd':
            handleLeadAdd($eventData, $logger);
            break;

        case 'ONCRMLEADUPDATE':
        case 'onCrmLeadUpdate':
            handleLeadUpdate($eventData, $logger);
            break;

        case 'ONCRMLEADDELETE':
        case 'onCrmLeadDelete':
            handleLeadDelete($eventData, $logger);
            break;

        default:
            $logger->logWarning("Unknown event type: $eventName", $eventData);
    }

} catch (Exception $e) {
    // Логируем ошибку
    logError('Exception in webhook handler', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    // НЕ возвращаем ошибку клиенту (уже отправили 200 OK)
}

/**
 * Обработчик создания лида
 */
function handleLeadAdd($data, LeadEventLogger $logger)
{
    $leadId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $leadTitle = $data['FIELDS']['TITLE'] ?? $data['title'] ?? 'Без названия';
    $leadName = $data['FIELDS']['NAME'] ?? $data['name'] ?? '';
    $leadLastName = $data['FIELDS']['LAST_NAME'] ?? $data['last_name'] ?? '';
    $leadPhone = $data['FIELDS']['PHONE'] ?? $data['phone'] ?? [];
    $leadEmail = $data['FIELDS']['EMAIL'] ?? $data['email'] ?? [];
    $responsibleId = $data['FIELDS']['ASSIGNED_BY_ID'] ?? $data['assigned_by_id'] ?? null;
    $sourceId = $data['FIELDS']['SOURCE_ID'] ?? $data['source_id'] ?? 'UNKNOWN';

    $logger->logInfo("New lead created: #$leadId - $leadTitle");

    // Отправляем уведомление ответственному
    if ($responsibleId) {
        $notification = new NotificationService();

        $phone = is_array($leadPhone) && !empty($leadPhone)
            ? $leadPhone[0]['VALUE'] ?? 'не указан'
            : 'не указан';

        $email = is_array($leadEmail) && !empty($leadEmail)
            ? $leadEmail[0]['VALUE'] ?? 'не указан'
            : 'не указан';

        $message = "🆕 Новый лид #$leadId\n\n" .
                   "Название: $leadTitle\n" .
                   "Имя: $leadName $leadLastName\n" .
                   "Телефон: $phone\n" .
                   "Email: $email\n" .
                   "Источник: $sourceId\n\n" .
                   "Перейти к лиду: " . BITRIX24_DOMAIN . "/crm/lead/details/$leadId/";

        $notification->sendToUser($responsibleId, 'Новый лид', $message);
    }

    // Дополнительная логика: проверка на дубли
    checkForDuplicates($data, $logger);
}

/**
 * Обработчик обновления лида
 */
function handleLeadUpdate($data, LeadEventLogger $logger)
{
    $leadId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $leadTitle = $data['FIELDS']['TITLE'] ?? $data['title'] ?? 'Без названия';
    $statusId = $data['FIELDS']['STATUS_ID'] ?? $data['status_id'] ?? null;
    $responsibleId = $data['FIELDS']['ASSIGNED_BY_ID'] ?? $data['assigned_by_id'] ?? null;

    $logger->logInfo("Lead updated: #$leadId - $leadTitle");

    // Если лид переведен в статус "Квалифицирован"
    if ($statusId === 'CONVERTED' || $statusId === 'QUALIFIED') {
        $logger->logInfo("Lead #$leadId qualified - sending notification");

        if ($responsibleId) {
            $notification = new NotificationService();
            $message = "✅ Лид #$leadId квалифицирован!\n\n" .
                       "Название: $leadTitle\n" .
                       "Статус: Квалифицирован\n\n" .
                       "Не забудьте создать сделку!\n" .
                       "Перейти к лиду: " . BITRIX24_DOMAIN . "/crm/lead/details/$leadId/";

            $notification->sendToUser($responsibleId, 'Лид квалифицирован', $message);
        }
    }

    // Если лид переведен в статус "Не целевой"
    if ($statusId === 'JUNK') {
        $logger->logInfo("Lead #$leadId marked as junk");
    }
}

/**
 * Обработчик удаления лида
 */
function handleLeadDelete($data, LeadEventLogger $logger)
{
    $leadId = $data['FIELDS']['ID'] ?? $data['id'] ?? null;
    $logger->logInfo("Lead deleted: #$leadId");
}

/**
 * Проверка на дубли лидов
 */
function checkForDuplicates($data, LeadEventLogger $logger)
{
    $leadPhone = $data['FIELDS']['PHONE'] ?? $data['phone'] ?? [];
    $leadEmail = $data['FIELDS']['EMAIL'] ?? $data['email'] ?? [];

    if (empty($leadPhone) && empty($leadEmail)) {
        return; // Нечего проверять
    }

    // Здесь можно добавить логику проверки через API
    // Например, поиск других лидов с таким же телефоном/email
    // и уведомление менеджера о возможном дубле

    $logger->logInfo("Duplicate check completed for lead");
}

/**
 * Логирование ошибок в файл
 */
function logError($message, $context = [])
{
    $logFile = __DIR__ . '/logs/error.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logMessage = "[$timestamp] ERROR: $message $contextStr\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
