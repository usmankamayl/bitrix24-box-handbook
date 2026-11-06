<?php
/**
 * install.php — регистрация чат-бота при установке приложения
 *
 * Этот файл вызывается Bitrix24 при установке приложения на портал.
 * Здесь мы регистрируем бота через imbot.register.
 */

// Подключаем CRest (класс для работы с REST API Bitrix24)
// В реальном приложении используется SDK или crest.php
require_once 'crest.php';

// URL обработчика событий бота
// Bitrix24 будет отправлять POST-запросы на этот URL при новых сообщениях
$handlerUrl = 'https://your-domain.com/module-10-2/chatbot-echo/handler.php';

// ─────────────────────────────────────────────
// ШАГ 1: Регистрируем чат-бота
// ─────────────────────────────────────────────
$botResult = CRest::call('imbot.register', [
    // Уникальный код бота — используется для идентификации
    'CODE' => 'echo_bot',

    // Тип: 'B' = бот, 'H' = виртуальный пользователь
    'TYPE' => 'B',

    // URL обработчика событий
    // Все события бота придут POST-запросом на этот URL
    'EVENT_HANDLER'        => $handlerUrl,
    'EVENT_MESSAGE_ADD'    => $handlerUrl,  // Новые сообщения
    'EVENT_WELCOME_MESSAGE'=> $handlerUrl,  // Бот добавлен в чат / начало диалога
    'EVENT_BOT_DELETE'     => $handlerUrl,  // Бота удалили

    // Свойства бота — отображаются в профиле
    'PROPERTIES' => [
        'NAME'            => 'Эхо-бот',          // Имя в чате
        'LAST_NAME'       => '',
        'COLOR'           => 'GREEN',            // Цвет: RED, GREEN, BLUE, ORANGE
        'EMAIL'           => 'echobot@example.com',
        'WORK_POSITION'   => 'Помощник',
        'PERSONAL_WWW'    => 'https://example.com',
        'PERSONAL_GENDER' => 'M',                // M или F — влияет на склонение
    ],

    // 'Y' = бот может работать в Открытых линиях (отвечать клиентам)
    'OPENLINE' => 'Y',
]);

// ─────────────────────────────────────────────
// ШАГ 2: Проверяем результат и сохраняем BOT_ID
// ─────────────────────────────────────────────
if (isset($botResult['error'])) {
    // Ошибка при регистрации
    file_put_contents('install_error.log', json_encode($botResult));
    echo 'Ошибка регистрации бота: ' . $botResult['error_description'];
    exit;
}

$botId = $botResult['result']; // Числовой ID зарегистрированного бота

// Сохраняем BOT_ID — он нужен при каждом вызове imbot.message.add
file_put_contents('bot_id.txt', $botId);

// ─────────────────────────────────────────────
// ШАГ 3: Регистрируем команду /help
// ─────────────────────────────────────────────
$commandResult = CRest::call('imbot.command.register', [
    'BOT_ID'  => $botId,
    'COMMAND' => '/help',

    // Y = команда видна всем пользователям
    'COMMON' => 'Y',

    // N = команда отображается в списке команд бота
    'HIDDEN' => 'N',

    // Обработчик команды — тот же handler.php
    'EVENT_COMMAND_ADD' => $handlerUrl,

    'PROPERTIES' => [
        'COMMAND' => '/help',
        'TITLE'   => 'Помощь по боту',
        'PARAMS'  => '',
    ],
]);

if (isset($commandResult['error'])) {
    file_put_contents('command_error.log', json_encode($commandResult));
}

echo 'Бот зарегистрирован. BOT_ID: ' . $botId;
