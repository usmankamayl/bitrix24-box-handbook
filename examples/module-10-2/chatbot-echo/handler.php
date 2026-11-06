<?php
/**
 * handler.php — обработчик событий чат-бота
 *
 * Bitrix24 отправляет POST-запросы на этот URL при каждом событии бота.
 * Здесь мы обрабатываем: новые сообщения, команды, приветствия.
 */

// Подключаем CRest для отправки ответов через REST API
require_once 'crest.php';

// ─────────────────────────────────────────────
// Читаем входящий запрос от Bitrix24
// ─────────────────────────────────────────────
// Bitrix24 отправляет JSON в теле POST-запроса
$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true);

// Логируем все входящие запросы — помогает при отладке
file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . ' ' . $rawInput . "\n\n", FILE_APPEND);

// Если запрос пустой или невалидный — выходим
if (empty($input) || !isset($input['event'])) {
    http_response_code(400);
    exit('Invalid request');
}

// Извлекаем основные данные из события
$event    = $input['event']           ?? '';   // Тип события
$botId    = $input['data']['BOT_ID']  ?? 0;    // ID нашего бота
$dialogId = $input['data']['DIALOG_ID'] ?? ''; // ID диалога для ответа
$message  = $input['data']['MESSAGE'] ?? '';   // Текст сообщения пользователя

// ─────────────────────────────────────────────
// Обработка события: новое сообщение боту
// ─────────────────────────────────────────────
if ($event === 'ONBOTMESSAGEADD') {

    // ── Определяем, откуда пришло сообщение ──
    // CHAT_ENTITY_TYPE говорит об источнике сообщения:
    //   'LINES' — Открытая линия (сообщение от клиента)
    //   'IM'    — внутренний чат Bitrix24
    $entityType = $input['data']['CHAT_ENTITY_TYPE'] ?? 'IM';
    $userId     = $input['data']['FROM_USER_ID']     ?? 0;

    // ── Обработка команды /help ──
    if (trim($message) === '/help') {
        sendHelp($botId, $dialogId);
        respondOk();
        exit;
    }

    // ── Эхо-ответ ──
    // Отправляем пользователю то же сообщение обратно
    $echoMessage = 'Вы написали: ' . $message;

    // Если сообщение из открытой линии — добавляем пометку
    if ($entityType === 'LINES') {
        $echoMessage = '[Открытая линия] ' . $echoMessage;
    }

    // ── Отправляем ответ с кнопками ──
    CRest::call('imbot.message.add', [
        'BOT_ID'    => $botId,
        'DIALOG_ID' => $dialogId,
        'MESSAGE'   => $echoMessage,

        // Клавиатура с кнопками под сообщением
        'KEYBOARD'  => [
            'BUTTONS' => [
                // Кнопка 1: отправит команду /help при нажатии
                [
                    'TEXT'       => 'Помощь',
                    'BG_COLOR'   => '#29619b',  // Синий фон
                    'TEXT_COLOR' => '#ffffff',  // Белый текст
                    'COMMAND'    => '/help',    // Отправит /help боту
                ],
                // Кнопка 2: открывает ссылку
                [
                    'TEXT' => 'Открыть сайт',
                    'LINK' => 'https://example.com',
                ],
                // Кнопка 3: команда с параметром
                [
                    'TEXT'          => 'Позвоните мне',
                    'BG_COLOR'      => '#28a745', // Зеленый фон
                    'TEXT_COLOR'    => '#ffffff',
                    'COMMAND'       => '/callback',
                    'COMMAND_PARAMS' => 'call_request',
                ],
            ],
            // Скрыть клавиатуру после нажатия на кнопку
            'HIDE_BY_CLICK' => 'Y',
        ],
    ]);

    respondOk();
    exit;
}

// ─────────────────────────────────────────────
// Обработка события: команда боту
// ─────────────────────────────────────────────
if ($event === 'ONBOTCOMMANDADD') {

    $command       = $input['data']['COMMAND']        ?? ''; // "/help"
    $commandParams = $input['data']['COMMAND_PARAMS'] ?? ''; // Параметры команды
    $dialogId      = $input['data']['DIALOG_ID']       ?? '';

    // Разделяем команду от параметров (если есть)
    // COMMAND может прийти как "/help" или "/callback call_request"
    $commandParts = explode(' ', $command);
    $cmd          = $commandParts[0]; // "/help"

    switch ($cmd) {
        case '/help':
            sendHelp($botId, $dialogId);
            break;

        case '/callback':
            // Пользователь нажал "Позвоните мне"
            CRest::call('imbot.message.add', [
                'BOT_ID'    => $botId,
                'DIALOG_ID' => $dialogId,
                'MESSAGE'   => 'Оператор перезвонит вам в ближайшее время. Параметр: ' . $commandParams,
            ]);
            break;

        default:
            CRest::call('imbot.message.add', [
                'BOT_ID'    => $botId,
                'DIALOG_ID' => $dialogId,
                'MESSAGE'   => 'Неизвестная команда: ' . $cmd,
            ]);
            break;
    }

    respondOk();
    exit;
}

// ─────────────────────────────────────────────
// Обработка события: бот добавлен в чат / начало диалога
// ─────────────────────────────────────────────
if ($event === 'ONBOTWELCOME') {

    $dialogId = $input['data']['DIALOG_ID'] ?? '';
    $userId   = $input['data']['USER_ID']   ?? 0;

    // Приветственное сообщение при добавлении бота в чат
    CRest::call('imbot.message.add', [
        'BOT_ID'    => $botId,
        'DIALOG_ID' => $dialogId,
        'MESSAGE'   => 'Привет! Я Эхо-бот. Напиши что-нибудь, и я повторю. ' .
                       'Или используй команду /help для справки.',
        'KEYBOARD'  => [
            'BUTTONS' => [
                [
                    'TEXT'    => 'Справка',
                    'COMMAND' => '/help',
                ],
            ],
            'HIDE_BY_CLICK' => 'N', // Оставляем кнопку видимой
        ],
    ]);

    respondOk();
    exit;
}

// ─────────────────────────────────────────────
// Обработка события: бота удалили
// ─────────────────────────────────────────────
if ($event === 'ONBOTDELETE') {
    // Можно удалить сохранённые данные, логи, подписки
    file_put_contents('bot_deleted.log', date('Y-m-d H:i:s') . " Бот удалён из чата\n", FILE_APPEND);
    respondOk();
    exit;
}

// Неизвестное событие — просто отвечаем 200, чтобы Bitrix24 не повторял запрос
respondOk();
exit;


// ─────────────────────────────────────────────
// Вспомогательные функции
// ─────────────────────────────────────────────

/**
 * Отправляет справочное сообщение с доступными командами
 */
function sendHelp(int $botId, string $dialogId): void
{
    CRest::call('imbot.message.add', [
        'BOT_ID'    => $botId,
        'DIALOG_ID' => $dialogId,
        'MESSAGE'   => "Доступные команды:\n" .
                       "/help — эта справка\n" .
                       "Напиши любой текст — я повторю его.\n\n" .
                       "Это демо-бот для обучения REST API Bitrix24.",
        'KEYBOARD'  => [
            'BUTTONS' => [
                [
                    'TEXT'    => 'Написать оператору',
                    'COMMAND' => '/callback',
                    'COMMAND_PARAMS' => 'operator_request',
                ],
            ],
            'HIDE_BY_CLICK' => 'Y',
        ],
    ]);
}

/**
 * Отправляет HTTP 200 Bitrix24, чтобы он не повторял запрос
 */
function respondOk(): void
{
    http_response_code(200);
    echo 'OK';
}
