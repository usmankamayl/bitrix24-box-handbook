<?php
/**
 * Задача 3: Обработчик исходящего Webhook (событие создания лида)
 *
 * Этот скрипт принимает POST-запросы от Bitrix24 при наступлении события
 * (например, создание лида) и обрабатывает полученные данные.
 *
 * Использование:
 * 1. Разверни этот скрипт на сервере с HTTPS
 * 2. В Bitrix24: Приложения → Вебхуки → Добавить исходящий вебхук
 * 3. Выбери событие (ONCRMLEADADD) и укажи URL этого скрипта
 * 4. При создании лида Bitrix24 отправит POST-запрос сюда
 */

// ============================================
// КОНФИГУРАЦИЯ
// ============================================

// Файл для логирования событий
define('LOG_FILE', __DIR__ . '/webhook_events.log');

// Папка для сохранения JSON с данными событий
define('EVENTS_DIR', __DIR__ . '/events/');

// Включить детальное логирование
define('DEBUG_MODE', true);

// ============================================
// ФУНКЦИИ
// ============================================

/**
 * Записать сообщение в лог
 */
function writeLog(string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = sprintf("[%s] [%s] %s\n", $timestamp, $level, $message);

    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);

    if (DEBUG_MODE) {
        echo $logMessage;
    }
}

/**
 * Сохранить данные события в JSON-файл
 */
function saveEventData(string $eventType, array $data): string
{
    // Создаем папку если не существует
    if (!file_exists(EVENTS_DIR)) {
        mkdir(EVENTS_DIR, 0755, true);
    }

    // Генерируем имя файла с timestamp
    $filename = sprintf(
        '%s_%s_%s.json',
        date('Y-m-d_H-i-s'),
        $eventType,
        substr(md5(json_encode($data)), 0, 8)
    );

    $filepath = EVENTS_DIR . $filename;

    // Сохраняем JSON с красивым форматированием
    file_put_contents(
        $filepath,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    return $filepath;
}

/**
 * Отправить уведомление администратору
 */
function sendNotification(string $subject, string $message): void
{
    // В реальном проекте отправляй на email, Telegram, Slack, и т.д.
    writeLog("Уведомление: $subject - $message", 'NOTIFICATION');

    // Пример отправки на email (раскомментируй если нужно):
    // mail('admin@example.com', $subject, $message);
}

/**
 * Обработать событие создания лида
 */
function handleLeadAdd(array $data): void
{
    $leadId = $data['FIELDS']['ID'] ?? 'unknown';
    $leadTitle = $data['FIELDS']['TITLE'] ?? 'N/A';
    $leadName = $data['FIELDS']['NAME'] ?? 'N/A';
    $leadPhone = $data['FIELDS']['PHONE'] ?? [];
    $leadEmail = $data['FIELDS']['EMAIL'] ?? [];

    writeLog("Создан новый лид #$leadId: $leadTitle", 'EVENT');

    // Извлекаем первый телефон и email
    $phone = is_array($leadPhone) && !empty($leadPhone) ? $leadPhone[0]['VALUE'] ?? 'N/A' : 'N/A';
    $email = is_array($leadEmail) && !empty($leadEmail) ? $leadEmail[0]['VALUE'] ?? 'N/A' : 'N/A';

    writeLog("Контакты: Имя=$leadName, Телефон=$phone, Email=$email");

    // Отправляем уведомление
    sendNotification(
        'Новый лид #' . $leadId,
        "Создан лид: $leadTitle\nИмя: $leadName\nТелефон: $phone\nEmail: $email"
    );

    // Здесь можно добавить свою логику:
    // - Отправить данные в CRM
    // - Создать задачу менеджеру
    // - Отправить SMS/Email клиенту
    // - Записать в базу данных
    // - Отправить в Telegram/Slack
}

/**
 * Обработать событие обновления лида
 */
function handleLeadUpdate(array $data): void
{
    $leadId = $data['FIELDS']['ID'] ?? 'unknown';
    $leadTitle = $data['FIELDS']['TITLE'] ?? 'N/A';

    writeLog("Обновлен лид #$leadId: $leadTitle", 'EVENT');

    // Ваша логика обработки обновления лида
}

/**
 * Обработать событие удаления лида
 */
function handleLeadDelete(array $data): void
{
    $leadId = $data['FIELDS']['ID'] ?? 'unknown';

    writeLog("Удален лид #$leadId", 'EVENT');

    // Ваша логика обработки удаления лида
}

/**
 * Обработать событие создания сделки
 */
function handleDealAdd(array $data): void
{
    $dealId = $data['FIELDS']['ID'] ?? 'unknown';
    $dealTitle = $data['FIELDS']['TITLE'] ?? 'N/A';
    $dealAmount = $data['FIELDS']['OPPORTUNITY'] ?? 0;

    writeLog("Создана новая сделка #$dealId: $dealTitle (Сумма: $dealAmount)", 'EVENT');

    sendNotification(
        'Новая сделка #' . $dealId,
        "Сделка: $dealTitle\nСумма: $dealAmount руб."
    );
}

/**
 * Получить данные из webhook
 */
function getWebhookData(): ?array
{
    // Получаем сырые данные POST
    $rawData = file_get_contents('php://input');

    if (empty($rawData)) {
        writeLog('Получен пустой POST-запрос', 'WARNING');
        return null;
    }

    // Логируем сырые данные в debug режиме
    if (DEBUG_MODE) {
        writeLog("Raw POST data: " . substr($rawData, 0, 500), 'DEBUG');
    }

    // Bitrix24 отправляет данные в формате application/x-www-form-urlencoded
    parse_str($rawData, $parsedData);

    if (empty($parsedData)) {
        // Пробуем декодировать как JSON (на случай если Bitrix24 изменит формат)
        $parsedData = json_decode($rawData, true);
    }

    return $parsedData;
}

/**
 * Маршрутизация событий
 */
function routeEvent(string $eventType, array $data): void
{
    switch ($eventType) {
        case 'ONCRMLEADADD':
            handleLeadAdd($data);
            break;

        case 'ONCRMLEADUPDATE':
            handleLeadUpdate($data);
            break;

        case 'ONCRMLEADDELETE':
            handleLeadDelete($data);
            break;

        case 'ONCRMDEALDD':
            handleDealAdd($data);
            break;

        default:
            writeLog("Неизвестное событие: $eventType", 'WARNING');
            writeLog("Данные: " . json_encode($data), 'DEBUG');
    }
}

/**
 * Валидация webhook от Bitrix24
 */
function validateBitrix24Webhook(array $data): bool
{
    // Проверяем наличие обязательных полей
    if (empty($data['event'])) {
        writeLog('Отсутствует поле "event"', 'ERROR');
        return false;
    }

    if (empty($data['data'])) {
        writeLog('Отсутствует поле "data"', 'ERROR');
        return false;
    }

    // Проверяем auth (для облачной версии)
    if (empty($data['auth']['domain'])) {
        writeLog('Отсутствует домен портала в auth', 'WARNING');
        // Не критично для коробочной версии
    }

    return true;
}

// ============================================
// ГЛАВНАЯ ЛОГИКА
// ============================================

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    writeLog('Получен запрос методом ' . $_SERVER['REQUEST_METHOD'] . ', ожидался POST', 'ERROR');
    die(json_encode(['error' => 'Method Not Allowed. Use POST.']));
}

writeLog('===== Получен новый webhook от Bitrix24 =====');

// Получаем данные
$webhookData = getWebhookData();

if (!$webhookData) {
    http_response_code(400);
    writeLog('Не удалось распарсить данные webhook', 'ERROR');
    die(json_encode(['error' => 'Invalid webhook data']));
}

// Валидируем данные
if (!validateBitrix24Webhook($webhookData)) {
    http_response_code(400);
    writeLog('Webhook не прошел валидацию', 'ERROR');
    die(json_encode(['error' => 'Invalid Bitrix24 webhook structure']));
}

// Извлекаем событие
$eventType = $webhookData['event'];
$eventData = $webhookData['data'];
$authData = $webhookData['auth'] ?? [];

writeLog("Событие: $eventType");
writeLog("Портал: " . ($authData['domain'] ?? 'N/A'));

// Сохраняем полные данные в JSON
$savedFile = saveEventData($eventType, $webhookData);
writeLog("Данные сохранены в файл: $savedFile");

// Обрабатываем событие
try {
    routeEvent($eventType, $eventData);
    writeLog('Событие успешно обработано', 'SUCCESS');

    // Возвращаем успешный ответ Bitrix24
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Event processed successfully',
    ]);
} catch (Exception $e) {
    writeLog('Ошибка при обработке события: ' . $e->getMessage(), 'ERROR');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}

writeLog('===== Обработка завершена =====');
?>
