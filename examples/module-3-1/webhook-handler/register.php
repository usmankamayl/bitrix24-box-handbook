<?php
/**
 * Скрипт регистрации событий в Bitrix24
 * Запустите этот файл один раз для настройки webhook обработчиков
 */

require_once __DIR__ . '/WebhookHandler.php';

// ===== КОНФИГУРАЦИЯ =====
$WEBHOOK_URL = 'https://ваш-портал.bitrix24.ru/rest/1/ваш_webhook_код/';
$HANDLER_URL = 'https://ваш-домен.ru/handler.php'; // URL вашего обработчика

// ===== ИНИЦИАЛИЗАЦИЯ =====
$eventManager = new EventManager($WEBHOOK_URL);

echo "=== Регистрация событий в Bitrix24 ===\n\n";

// События для регистрации
$events = [
    // CRM - Сделки
    'ONCRMDEALADD' => 'Создание сделки',
    'ONCRMDEALUPDATE' => 'Обновление сделки',
    'ONCRMDEALDELETE' => 'Удаление сделки',

    // CRM - Лиды
    'ONCRMLЕADADD' => 'Создание лида',
    'ONCRMLEADUPDATE' => 'Обновление лида',
    'ONCRMLEADDELETE' => 'Удаление лида',

    // CRM - Контакты
    'ONCRMCONTACTADD' => 'Создание контакта',
    'ONCRMCONTACTUPDATE' => 'Обновление контакта',
    'ONCRMCONTACTDELETE' => 'Удаление контакта',

    // CRM - Компании
    'ONCRMCOMPANYADD' => 'Создание компании',
    'ONCRMCOMPANYUPDATE' => 'Обновление компании',
    'ONCRMCOMPANYDELETE' => 'Удаление компании',

    // Задачи
    'ONTASKADD' => 'Создание задачи',
    'ONTASKUPDATE' => 'Обновление задачи',
    'ONTASKDELETE' => 'Удаление задачи',

    // Пользователи
    'ONUSERADD' => 'Добавление пользователя',
    'ONUSERUPDATE' => 'Обновление пользователя',
    'ONUSERDELETE' => 'Удаление пользователя'
];

// Регистрируем события
$registered = 0;
$failed = 0;

foreach ($events as $event => $description) {
    echo "Регистрация: $event ($description)... ";

    $result = $eventManager->bind($event, $HANDLER_URL);

    if ($result) {
        echo "✓\n";
        $registered++;
    } else {
        echo "✗\n";
        $failed++;
    }

    // Небольшая пауза между запросами
    usleep(100000); // 0.1 сек
}

echo "\n";
echo "=== Итоги регистрации ===\n";
echo "Успешно зарегистрировано: $registered\n";
echo "Ошибок: $failed\n";
echo "\n";

// Показываем список зарегистрированных событий
echo "=== Список зарегистрированных событий ===\n";
$registeredEvents = $eventManager->getList();

if ($registeredEvents) {
    foreach ($registeredEvents as $event) {
        echo "- {$event['event']} → {$event['handler']}\n";
    }
} else {
    echo "Не удалось получить список событий\n";
}

echo "\n";
echo "=== Настройка завершена ===\n";
echo "URL обработчика: $HANDLER_URL\n";
echo "Теперь Bitrix24 будет отправлять уведомления на этот URL\n";
