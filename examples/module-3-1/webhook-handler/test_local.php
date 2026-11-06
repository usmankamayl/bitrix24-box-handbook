<?php
/**
 * Локальное тестирование webhook обработчика
 * Симулирует отправку событий от Bitrix24
 */

require_once __DIR__ . '/WebhookHandler.php';

$LOG_FILE = __DIR__ . '/webhook_test.log';

// Инициализация обработчика
$handler = new WebhookHandler($LOG_FILE);

// Регистрируем тестовые обработчики
$handler->on('ONCRMDEALADD', function($data) {
    echo "✓ Обработчик ONCRMDEALADD вызван\n";
    echo "  Deal ID: {$data['FIELDS']['ID']}\n";
    echo "  Название: {$data['FIELDS']['TITLE']}\n";
    echo "  Сумма: {$data['FIELDS']['OPPORTUNITY']}\n";
    return ['processed' => true];
});

$handler->on('ONTASKADD', function($data) {
    echo "✓ Обработчик ONTASKADD вызван\n";
    echo "  Task ID: {$data['FIELDS']['ID']}\n";
    echo "  Название: {$data['FIELDS']['TITLE']}\n";
    return ['processed' => true];
});

echo "=== Тестирование webhook обработчика ===\n\n";

// ===== ТЕСТ 1: Создание сделки =====
echo "=== ТЕСТ 1: Создание сделки ===\n";

$_POST = [
    'event' => 'ONCRMDEALADD',
    'data' => [
        'FIELDS' => [
            'ID' => 123,
            'TITLE' => 'Тестовая сделка',
            'OPPORTUNITY' => 150000,
            'CURRENCY_ID' => 'RUB',
            'STAGE_ID' => 'NEW'
        ]
    ]
];

$result = $handler->handle();
echo "Результат: " . json_encode($result) . "\n\n";

// ===== ТЕСТ 2: Создание задачи =====
echo "=== ТЕСТ 2: Создание задачи ===\n";

$_POST = [
    'event' => 'ONTASKADD',
    'data' => [
        'FIELDS' => [
            'ID' => 456,
            'TITLE' => 'Тестовая задача',
            'RESPONSIBLE_ID' => 1,
            'STATUS' => 2
        ]
    ]
];

$result = $handler->handle();
echo "Результат: " . json_encode($result) . "\n\n";

// ===== ТЕСТ 3: Несуществующее событие =====
echo "=== ТЕСТ 3: Несуществующее событие ===\n";

$_POST = [
    'event' => 'UNKNOWN_EVENT',
    'data' => []
];

$result = $handler->handle();
echo "Результат: " . json_encode($result) . "\n\n";

// ===== ТЕСТ 4: Событие без данных =====
echo "=== ТЕСТ 4: Отсутствие поля event ===\n";

$_POST = [
    'data' => ['test' => 'data']
];

$result = $handler->handle();
echo "Результат: " . json_encode($result) . "\n\n";

// ===== Показываем лог =====
echo "=== Последние записи в логе ===\n";

$logEntries = $handler->getLastLogEntries(10);
foreach ($logEntries as $entry) {
    echo $entry;
}

echo "\n=== Тестирование завершено ===\n";
echo "Лог файл: $LOG_FILE\n";
