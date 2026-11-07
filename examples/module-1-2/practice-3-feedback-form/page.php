<?php

/**
 * Страница с формой. /study-feedback/index.php
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Обратная связь');

$APPLICATION->IncludeComponent(
    'study:feedback',
    '.default',
    [
        'EMAIL_TO' => 'manager@example.com',
        'EVENT_NAME' => 'STUDY_FEEDBACK',
        'USE_CAPTCHA' => 'N',
        'MIN_FILL_TIME' => 3,
        'MAX_FILL_TIME' => 3600,
        'THROTTLE_SECONDS' => 30,
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
