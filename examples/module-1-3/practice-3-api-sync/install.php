<?php

/**
 * Регистрация агента синхронизации и настройка адреса API.
 * /local/tools/practice-3-api-sync/install.php
 *
 * Контракт: права -> CSRF -> метод -> валидация -> запись.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Запускать через браузер под администратором.\n");
    exit(1);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$classDir = is_file(__DIR__ . '/ApiSyncAgent.php')
    ? __DIR__
    : $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/Agents';

require_once $classDir . '/SyncStateTable.php';
require_once $classDir . '/SyncItemTable.php';
require_once $classDir . '/ApiSyncAgent.php';

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use MyCompany\Agents\ApiSyncAgent;
use MyCompany\Agents\SyncItemTable;
use MyCompany\Agents\SyncStateTable;

if (!is_object($GLOBALS['USER']) || !$GLOBALS['USER']->IsAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403';
    exit;
}

$scheme = \CMain::IsHTTPS() ? 'https' : 'http';
$selfDir = rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/');
$demoUrl = $scheme . '://' . (string)$_SERVER['HTTP_HOST'] . $selfDir . '/demo-api.php';

$currentUrl = (string)Option::get(ApiSyncAgent::OPTION_MODULE, ApiSyncAgent::OPTION_URL, '');
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_bitrix_sessid()) {
        $messages[] = ['error', 'Сессия истекла: обновите страницу и повторите.'];
    } else {
        $hasError = false;
        $url = trim((string)($_POST['api_url'] ?? ''));

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $messages[] = ['error', 'Адрес API не является URL.'];
            $hasError = true;
        } elseif (!preg_match('~^https?://~i', $url)) {
            $messages[] = ['error', 'Разрешены только схемы http и https.'];
            $hasError = true;
        } else {
            Option::set(ApiSyncAgent::OPTION_MODULE, ApiSyncAgent::OPTION_URL, $url);
            $currentUrl = $url;
            $messages[] = ['info', 'Адрес API сохранён.'];
        }

        $token = trim((string)($_POST['api_token'] ?? ''));

        /*
         * Токен пишется только если он введён: пустое поле не должно затирать
         * ранее сохранённый секрет.
         */
        if ($token !== '' && !$hasError) {
            Option::set(ApiSyncAgent::OPTION_MODULE, ApiSyncAgent::OPTION_TOKEN, $token);
            $messages[] = ['info', 'Токен сохранён (в выводе не показывается).'];
        }

        if (!Application::getConnection()->isTableExists(SyncItemTable::getTableName())) {
            $messages[] = ['error', 'Нет таблиц синхронизации. Сначала выполните install.sql.'];
        } elseif (!$hasError) {
            $messages[] = registerAgent();
        }
    }
}

$agentRow = findAgent();
$stateRow = Application::getConnection()->isTableExists(SyncStateTable::getTableName())
    ? SyncStateTable::getById(ApiSyncAgent::PROVIDER)->fetch()
    : false;

header('Content-Type: text/html; charset=UTF-8');

$escape = static fn($value): string => htmlspecialcharsbx((string)$value);
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Агент синхронизации с внешним API</title></head>
<body style="font:14px/1.5 sans-serif;max-width:900px;margin:40px auto">

<h1>Агент синхронизации с внешним API</h1>

<p>
    Строка агента: <code><?= $escape(ApiSyncAgent::AGENT_NAME) ?></code><br>
    Страниц за запуск: <?= (int)ApiSyncAgent::MAX_PAGES_PER_RUN ?>,
    размер страницы: <?= (int)ApiSyncAgent::PAGE_SIZE ?>,
    интервал: 3600 с, <code>IS_PERIOD = N</code>.
</p>

<?php foreach ($messages as [$type, $text]): ?>
    <p style="padding:10px;border:1px solid <?= $type === 'error' ? '#c00' : '#080' ?>">
        <?= $escape($text) ?>
    </p>
<?php endforeach; ?>

<h2>Настройки</h2>

<p>
    Адрес API: <code><?= $escape($currentUrl === '' ? '(не задан)' : $currentUrl) ?></code><br>
    Токен: <?= Option::get(ApiSyncAgent::OPTION_MODULE, ApiSyncAgent::OPTION_TOKEN, '') === ''
        ? '(не задан)'
        : 'задан' ?><br>
    Демонстрационный провайдер: <code><?= $escape($demoUrl) ?></code>
</p>

<form method="post">
    <?= bitrix_sessid_post() ?>
    <p>
        <label>Адрес API<br>
            <input type="url" name="api_url" size="80"
                   value="<?= $escape($currentUrl === '' ? $demoUrl : $currentUrl) ?>" required>
        </label>
    </p>
    <p>
        <label>Токен (необязательно, не выводится)<br>
            <input type="password" name="api_token" size="80" autocomplete="off">
        </label>
    </p>
    <button type="submit">Сохранить и зарегистрировать агента</button>
</form>

<h2>Состояние</h2>

<?php if ($agentRow !== null): ?>
    <table border="1" cellpadding="6" cellspacing="0">
        <?php foreach (['ID', 'NAME', 'ACTIVE', 'IS_PERIOD', 'AGENT_INTERVAL', 'LAST_EXEC', 'NEXT_EXEC'] as $field): ?>
            <tr>
                <th align="left"><?= $escape($field) ?></th>
                <td><?= $escape($agentRow[$field] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p>Агент ещё не зарегистрирован — отправьте форму.</p>
<?php endif; ?>

<?php if (is_array($stateRow)): ?>
    <h3>Контрольная точка</h3>
    <table border="1" cellpadding="6" cellspacing="0">
        <?php foreach (['CODE', 'CURSOR', 'PAGES_DONE', 'ITEMS_DONE', 'UPDATED_AT'] as $field): ?>
            <tr>
                <th align="left"><?= $escape($field) ?></th>
                <td><?= $escape($stateRow[$field] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<p>
    Прогон сценариев: <code><?= $escape($selfDir) ?>/check.php</code> (под
    администратором, в браузере).
</p>

</body>
</html>
<?php

/**
 * @return array{0: string, 1: string}
 */
function registerAgent(): array
{
    if (findAgent() !== null) {
        return ['info', 'Агент уже зарегистрирован.'];
    }

    $agentId = \CAgent::AddAgent(
        ApiSyncAgent::AGENT_NAME,
        '',
        'N',
        3600,
        '',
        'Y',
        '',
        100,
        false,
        false
    );

    if ($agentId === false) {
        return ['error', 'CAgent::AddAgent() вернул false. Смотрите журнал событий.'];
    }

    return ['info', 'Агент добавлен, ID ' . (int)$agentId . '.'];
}

/**
 * @return array<string, mixed>|null
 */
function findAgent(): ?array
{
    $name = ApiSyncAgent::AGENT_NAME;
    $result = \CAgent::GetList(['ID' => 'ASC'], ['NAME' => $name]);

    while ($row = $result->Fetch()) {
        if ($row['NAME'] === $name) {
            return $row;
        }
    }

    return null;
}
