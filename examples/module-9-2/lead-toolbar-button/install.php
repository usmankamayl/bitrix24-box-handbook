<?php
// Регистрация кнопки в тулбаре лида
require_once 'crest.php';

$result = CRest::call('placement.bind', [
    'PLACEMENT' => 'CRM_LEAD_DETAIL_TOOLBAR',
    'HANDLER' => 'https://your-domain.com/module-9-2/lead-toolbar-button/widget.php',
    'TITLE' => 'Отправить в сервис',
    'OPTIONS' => [
        'type' => 'slider',   // откроется как боковая панель
        'width' => 500
    ]
]);

echo isset($result['result'])
    ? "Кнопка зарегистрирована в тулбаре лида\n"
    : "Ошибка: " . json_encode($result);
