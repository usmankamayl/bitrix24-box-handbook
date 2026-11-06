<?php
/**
 * Обработчик webhook событий от Bitrix24
 *
 * Разместите этот файл на доступном из интернета сервере
 * и зарегистрируйте его URL в Bitrix24 как обработчик событий
 */

require_once __DIR__ . '/WebhookHandler.php';

// ===== КОНФИГУРАЦИЯ =====
$LOG_FILE = __DIR__ . '/webhook.log';

// ===== ИНИЦИАЛИЗАЦИЯ =====
$handler = new WebhookHandler($LOG_FILE);

// ===== РЕГИСТРАЦИЯ ОБРАБОТЧИКОВ СОБЫТИЙ =====

// Обработчик создания сделки
$handler->on('ONCRMDEALADD', function($data) {
    $dealId = $data['FIELDS']['ID'] ?? null;
    $title = $data['FIELDS']['TITLE'] ?? 'Без названия';
    $amount = $data['FIELDS']['OPPORTUNITY'] ?? 0;

    // Ваша логика обработки
    // Например, отправить уведомление, создать задачу, обновить внешнюю систему

    // Пример: отправить email уведомление
    if ($amount > 100000) {
        mail(
            'manager@example.com',
            'Новая крупная сделка',
            "Создана сделка \"{$title}\" на сумму {$amount} руб."
        );
    }

    return ['deal_id' => $dealId, 'processed' => true];
});

// Обработчик обновления сделки
$handler->on('ONCRMDEALUPDATE', function($data) {
    $dealId = $data['FIELDS']['ID'] ?? null;
    $stageId = $data['FIELDS']['STAGE_ID'] ?? null;

    // Логика при изменении стадии сделки
    if ($stageId === 'WON') {
        // Сделка успешно закрыта
        // Например, начислить бонусы менеджеру
    }

    return ['deal_id' => $dealId, 'stage' => $stageId];
});

// Обработчик удаления сделки
$handler->on('ONCRMDEALDELETE', function($data) {
    $dealId = $data['FIELDS']['ID'] ?? null;

    // Логика при удалении сделки
    // Например, архивировать данные во внешней системе

    return ['deal_id' => $dealId, 'archived' => true];
});

// Обработчик создания лида
$handler->on('ONCRMLEAD ADD', function($data) {
    $leadId = $data['FIELDS']['ID'] ?? null;
    $name = $data['FIELDS']['NAME'] ?? '';
    $lastName = $data['FIELDS']['LAST_NAME'] ?? '';
    $phone = $data['FIELDS']['PHONE'][0]['VALUE'] ?? null;

    // Автоматическая обработка нового лида
    // Например, отправить SMS или создать задачу менеджеру

    return ['lead_id' => $leadId, 'auto_processed' => true];
});

// Обработчик создания задачи
$handler->on('ONTASKADD', function($data) {
    $taskId = $data['FIELDS']['ID'] ?? null;
    $title = $data['FIELDS']['TITLE'] ?? '';
    $responsibleId = $data['FIELDS']['RESPONSIBLE_ID'] ?? null;

    // Логика при создании задачи
    // Например, дублировать в внешний таск-трекер

    return ['task_id' => $taskId, 'synced' => true];
});

// Обработчик обновления задачи
$handler->on('ONTASKUPDATE', function($data) {
    $taskId = $data['FIELDS']['ID'] ?? null;
    $status = $data['FIELDS']['STATUS'] ?? null;

    // Синхронизация статуса задачи
    if ($status == 5) {
        // Задача завершена
    }

    return ['task_id' => $taskId, 'status' => $status];
});

// Обработчик создания контакта
$handler->on('ONCRMCONTACTADD', function($data) {
    $contactId = $data['FIELDS']['ID'] ?? null;
    $name = $data['FIELDS']['NAME'] ?? '';
    $email = $data['FIELDS']['EMAIL'][0]['VALUE'] ?? null;

    // Например, добавить контакт в email-рассылку
    if ($email) {
        // addToMailingList($email, $name);
    }

    return ['contact_id' => $contactId, 'added_to_mailing' => true];
});

// Обработчик добавления пользователя
$handler->on('ONUSERADD', function($data) {
    $userId = $data['FIELDS']['ID'] ?? null;
    $email = $data['FIELDS']['EMAIL'] ?? null;
    $name = $data['FIELDS']['NAME'] ?? '';

    // Приветственное письмо новому сотруднику
    // sendWelcomeEmail($email, $name);

    return ['user_id' => $userId, 'welcomed' => true];
});

// ===== ОБРАБОТКА ЗАПРОСА =====
$result = $handler->handle();

// Возвращаем ответ Bitrix24
header('Content-Type: application/json');
echo json_encode($result);
