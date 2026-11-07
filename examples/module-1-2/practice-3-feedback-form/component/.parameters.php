<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentParameters = [
    'PARAMETERS' => [
        'EMAIL_TO' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_FEEDBACK_EMAIL_TO'),
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'EVENT_NAME' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_FEEDBACK_EVENT_NAME'),
            'TYPE' => 'STRING',
            'DEFAULT' => 'STUDY_FEEDBACK',
        ],
        'USE_CAPTCHA' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_FEEDBACK_USE_CAPTCHA'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'MIN_FILL_TIME' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_FEEDBACK_MIN_FILL_TIME'),
            'TYPE' => 'STRING',
            'DEFAULT' => '3',
        ],
        'MAX_FILL_TIME' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_FEEDBACK_MAX_FILL_TIME'),
            'TYPE' => 'STRING',
            'DEFAULT' => '3600',
        ],
        'THROTTLE_SECONDS' => [
            'PARENT' => 'BASE',
            'NAME' => Loc::getMessage('STUDY_FEEDBACK_THROTTLE'),
            'TYPE' => 'STRING',
            'DEFAULT' => '30',
        ],
    ],
];
