<?php
/**
 * Регистрация пользовательского робота/активити "Отправить Telegram-сообщение"
 *
 * Запускать ОДИН РАЗ при установке приложения.
 * После выполнения активити появится в конструкторе роботов и БП
 * для сделок, лидов, контактов и компаний.
 */

require_once __DIR__ . '/crest.php';

$handlerUrl = 'https://your-domain.com/bitrix-app/handler.php';
$authUserId = 1; // ID пользователя, от имени которого работают REST-вызовы активити

$result = CRest::call('bizproc.activity.add', [
    'CODE'          => 'TG_SEND_MESSAGE',
    'HANDLER'       => $handlerUrl,
    'AUTH_USER_ID'  => $authUserId,
    'NAME'          => 'Telegram: отправить сообщение',
    'DESCRIPTION'   => 'Отправляет сообщение в Telegram-чат через бот API',
    'TYPE'          => 'ROBOT', // Робот — доступен и в роботах, и в БП

    // Входные параметры — настраиваются в конструкторе
    'PROPERTIES' => [
        'CHAT_ID' => [
            'Name'        => ['ru' => 'ID чата'],
            'Description' => ['ru' => 'Telegram chat_id (напр. -1001234567890 или @channel)'],
            'Type'        => 'string',
            'Required'    => 'Y',
        ],
        'TEXT' => [
            'Name'        => ['ru' => 'Текст сообщения'],
            'Description' => ['ru' => 'Поддерживаются поля документа через конструктор'],
            'Type'        => 'text',
            'Required'    => 'Y',
        ],
        'PARSE_MODE' => [
            'Name'        => ['ru' => 'Формат'],
            'Type'        => 'select',
            'Required'    => 'N',
            'Options'     => [
                'MarkdownV2' => 'Markdown',
                'HTML'       => 'HTML',
                ''           => 'Без форматирования',
            ],
            'Default'     => 'HTML',
        ],
    ],

    // Выходные параметры — доступны следующим действиям БП
    'RETURN_PROPERTIES' => [
        'MESSAGE_ID' => [
            'Name' => ['ru' => 'ID сообщения'],
            'Type' => 'int',
        ],
        'SENT_AT' => [
            'Name' => ['ru' => 'Время отправки'],
            'Type' => 'datetime',
        ],
        'STATUS' => [
            'Name' => ['ru' => 'Статус'],
            'Type' => 'string',
        ],
    ],

    // Привязка к типу документа и конструкторам
    'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL'],
    'FILTER' => [
        'INCLUDE' => [
            ['crm', 'CCrmDocumentDeal'],
            ['crm', 'CCrmDocumentLead'],
            ['crm', 'CCrmDocumentContact'],
            ['crm', 'CCrmDocumentCompany'],
        ],
    ],
]);

echo '<pre>';
print_r($result);
echo '</pre>';

// Проверка: список активити
// print_r(CRest::call('bizproc.activity.list'));
