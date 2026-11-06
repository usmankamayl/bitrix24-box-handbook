<?php
/**
 * Установочный скрипт: регистрирует робот bizproc.robot.add в портале.
 * Запускать один раз — при установке приложения или вручную в браузере.
 */

require_once __DIR__ . '/crest.php';

// Входящий webhook должен иметь права: bizproc, crm.
// HANDLER — публичный URL handler.php из этого примера, доступный из интернета.

$robotCode = 'route_to_category';

$result = CRest::call('bizproc.robot.add', [
    'CODE' => $robotCode,
    'HANDLER' => 'https://yourapp.example.com/category-router-robot/handler.php',
    'AUTH_USER_ID' => 1,
    'NAME' => 'Перенести сделку в другую воронку',
    'USE_SUBSCRIPTION' => 'Y',
    'PROPERTIES' => [
        'target_category_id' => [
            'Name' => 'ID целевой воронки (deal category)',
            'Type' => 'int',
            'Required' => 'Y',
            'Description' => 'ID воронки, в которую перевести сделку. Для воронки по умолчанию — 0.',
        ],
        'min_opportunity' => [
            'Name' => 'Минимальная сумма сделки',
            'Type' => 'double',
            'Required' => 'N',
            'Description' => 'Робот сработает, только если сумма сделки >= указанной. Пусто = без условия.',
        ],
        'target_stage_semantics' => [
            'Name' => 'Семантика целевой стадии',
            'Type' => 'string',
            'Required' => 'N',
            'Default' => 'P',
            'Description' => 'P = в работе, S = успешно, F = провалено. По умолчанию ищется стадия с семантикой P.',
        ],
    ],
    'USE_FILTER' => 'Y', // разрешает встроенные условия робота в настройках стадии
]);

var_dump($result);
