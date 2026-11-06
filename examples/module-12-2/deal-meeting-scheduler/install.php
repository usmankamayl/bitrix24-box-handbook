<?php
/**
 * Подписка на событие onCrmDealAdd: при создании сделки автоматически
 * планируем «ознакомительную встречу» в календаре ответственного через
 * 1 рабочий день после создания сделки.
 *
 * Запускать ОДИН РАЗ при установке приложения.
 */

require_once __DIR__ . '/crest.php';

$handlerUrl = 'https://your-domain.com/bitrix-app/handler.php';

$result = CRest::call('event.bind', [
    'event'        => 'onCrmDealAdd',
    'handler'      => $handlerUrl,
    'auth_type'    => 0, // 0 — webhook/application auth (проверяем application_token в обработчике)
]);

echo '<pre>';
print_r($result);
echo '</pre>';

// Проверка: список подписок
// print_r(CRest::call('event.get'));
