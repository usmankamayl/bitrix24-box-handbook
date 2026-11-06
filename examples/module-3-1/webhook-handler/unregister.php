<?php
/**
 * Скрипт отмены регистрации событий в Bitrix24
 * Используйте для удаления webhook обработчиков
 */

require_once __DIR__ . '/WebhookHandler.php';

// ===== КОНФИГУРАЦИЯ =====
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/ваш_webhook_код/';
$HANDLER_URL = 'https://ваш-домен.ru/handler.php';

// ===== ИНИЦИАЛИЗАЦИЯ =====
$eventManager = new EventManager($WEBHOOK_URL);

echo "=== Отмена регистрации событий ===\n\n";

// Получаем список зарегистрированных событий
$registeredEvents = $eventManager->getList();

if (!$registeredEvents) {
    echo "Не удалось получить список событий\n";
    exit;
}

$unregistered = 0;
$failed = 0;

foreach ($registeredEvents as $event) {
    // Удаляем только события для нашего обработчика
    if ($event['handler'] === $HANDLER_URL) {
        echo "Отмена регистрации: {$event['event']}... ";

        $result = $eventManager->unbind($event['event'], $HANDLER_URL);

        if ($result) {
            echo "✓\n";
            $unregistered++;
        } else {
            echo "✗\n";
            $failed++;
        }

        usleep(100000); // 0.1 сек
    }
}

echo "\n";
echo "=== Итоги ===\n";
echo "Отменено: $unregistered\n";
echo "Ошибок: $failed\n";
