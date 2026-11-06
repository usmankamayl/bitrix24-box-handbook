<?php
// Скрипт установки виджета
// Регистрирует вкладку в карточке сделки через Placement API

require_once 'crest.php';  // Твоя библиотека для работы с REST API

// Регистрируем виджет
$result = CRest::call('placement.bind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
    'HANDLER' => 'https://your-domain.com/module-9-2/deal-tab-widget/widget.php',
    'TITLE' => 'Информация о сделке',
    'DESCRIPTION' => 'Детальная информация и связанные данные',
    'OPTIONS' => [
        'height' => 600
    ]
]);

if (isset($result['result'])) {
    echo "Виджет успешно зарегистрирован!\n";
    echo "Теперь он появится в карточке каждой сделки как вкладка 'Информация о сделке'\n";
} else {
    echo "Ошибка регистрации: " . json_encode($result);
}
