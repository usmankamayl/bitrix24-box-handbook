<?php
/**
 * Вспомогательные функции проверки подлинности и прав для входящих
 * событийных запросов. Два независимых уровня защиты:
 *
 * 1. verifyApplicationToken() — подтверждает, что запрос пришёл от
 *    Bitrix24, а не был подделан третьей стороной, знающей URL.
 * 2. userHasRole() — подтверждает, что пользователь, от имени которого
 *    выполняется привилегированное действие, действительно состоит
 *    в нужной группе/роли (бизнес-уровень доступа, а не просто
 *    "запрос подлинный").
 *
 * Это разные вопросы: подлинность запроса не означает, что у
 * конкретного пользователя есть права на конкретное действие.
 */

require_once __DIR__ . '/crest.php';

function verifyApplicationToken(string $received, string $expected): bool
{
    // hash_equals — защита от timing-атаки при сравнении секретов
    return hash_equals($expected, $received);
}

/**
 * Проверяет, входит ли пользователь в одну из разрешённых групп доступа.
 * $allowedGroupCodes — например ['G2', 'G5'] (ID групп на портале).
 */
function userHasRole(int $userId, array $allowedGroupCodes): bool
{
    $access = CRest::call('user.access', ['filter' => ['USER_ID' => $userId]]);

    if (!empty($access['error']) || empty($access['result'])) {
        return false;
    }

    // user.access возвращает список кодов доступа пользователя,
    // включая 'G2', 'G5' и т.п. — коды групп, в которые он входит
    foreach ($access['result'] as $accessCode) {
        if (in_array($accessCode, $allowedGroupCodes, true)) {
            return true;
        }
    }

    return false;
}

function logSecurityEvent(string $message): void
{
    file_put_contents(
        __DIR__ . '/security.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND
    );
}
