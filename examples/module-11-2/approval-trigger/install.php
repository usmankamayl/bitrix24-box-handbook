<?php
/**
 * Регистрация триггера "Решение банка по заявке"
 *
 * Запускается один раз. После этого в роботах сделки появится
 * триггер "Решение банка" — пользователь ставит его на стадию,
 * и сделка ждёт, пока наше приложение вызовет bizproc.event.send.
 */

require_once __DIR__ . '/crest.php';

$result = CRest::call('bizproc.activity.add', [
    'CODE'          => 'BANK_DECISION',
    'HANDLER'       => 'https://your-domain.com/bitrix-app/wait.php',
    'AUTH_USER_ID'  => 1,
    'NAME'          => ['ru' => 'Решение банка по заявке'],
    'DESCRIPTION'   => ['ru' => 'Ждёт ответа от банковского API'],
    'TYPE'          => 'TRIGGER',
    'PROPERTIES'    => [],
    'RETURN_PROPERTIES' => [
        'DECISION' => [
            'Name' => ['ru' => 'Решение'],
            'Type' => 'select',
            'Options' => [
                'approved' => 'Одобрено',
                'rejected' => 'Отклонено',
                'pending'  => 'На рассмотрении',
            ],
        ],
        'APPROVED_SUM' => [
            'Name' => ['ru' => 'Одобренная сумма'],
            'Type' => 'double',
        ],
        'COMMENT' => [
            'Name' => ['ru' => 'Комментарий банка'],
            'Type' => 'string',
        ],
    ],
    'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL'],
]);

echo '<pre>'; print_r($result); echo '</pre>';
