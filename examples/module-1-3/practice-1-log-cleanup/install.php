<?php

/**
 * Регистрация агента очистки журнала. Административная страница, вызывается
 * один раз: /local/tools/practice-1-log-cleanup/install.php
 *
 * Контракт точки входа: права -> CSRF -> метод запроса -> работа.
 * Страница не создаёт таблицу (это install.sql) и не выполняет очистку.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Запускать через браузер под администратором.\n");
    exit(1);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$classDir = is_file(__DIR__ . '/LogCleanupAgent.php')
    ? __DIR__
    : $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/Agents';

require_once $classDir . '/LogTable.php';
require_once $classDir . '/LogCleanupAgent.php';

use Bitrix\Main\Application;
use MyCompany\Agents\LogCleanupAgent;
use MyCompany\Agents\LogTable;

if (!is_object($GLOBALS['USER']) || !$GLOBALS['USER']->IsAdmin()) {
    /* Чужому не показываем ни форму, ни существование агента. */
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403';
    exit;
}

$messages = [];
$agentRow = null;
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isPost) {
    if (!check_bitrix_sessid()) {
        $messages[] = ['error', 'Сессия истекла: обновите страницу и повторите.'];
    } else {
        $tableExists = Application::getConnection()
            ->isTableExists(LogTable::getTableName());

        if (!$tableExists) {
            $messages[] = ['error', 'Нет таблицы ' . LogTable::getTableName()
                . '. Сначала выполните install.sql.'];
        } else {
            $messages[] = registerAgent();
        }
    }
}

$agentRow = findAgent();

header('Content-Type: text/html; charset=UTF-8');

$escape = static fn($value): string => htmlspecialcharsbx((string)$value);
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Регистрация агента очистки журнала</title></head>
<body style="font:14px/1.5 sans-serif;max-width:900px;margin:40px auto">

<h1>Агент очистки проектного журнала</h1>

<p>
    Строка агента: <code><?= $escape(LogCleanupAgent::AGENT_NAME) ?></code><br>
    Срок хранения: <?= (int)LogCleanupAgent::RETENTION_DAYS ?> дней,
    порция: <?= (int)LogCleanupAgent::BATCH_SIZE ?> строк,
    интервал: 3600 с, <code>IS_PERIOD = N</code>.
</p>

<?php foreach ($messages as [$type, $text]): ?>
    <p style="padding:10px;border:1px solid <?= $type === 'error' ? '#c00' : '#080' ?>">
        <?= $escape($text) ?>
    </p>
<?php endforeach; ?>

<?php if ($agentRow !== null): ?>
    <h2>Агент зарегистрирован</h2>
    <table border="1" cellpadding="6" cellspacing="0">
        <?php foreach (['ID', 'NAME', 'MODULE_ID', 'ACTIVE', 'IS_PERIOD', 'AGENT_INTERVAL', 'LAST_EXEC', 'NEXT_EXEC'] as $field): ?>
            <tr>
                <th align="left"><?= $escape($field) ?></th>
                <td><?= $escape($agentRow[$field] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p>
        Проверка выполнения: <code>php -f <?= $escape(__DIR__) ?>/check.php</code>.
        Первый плановый запуск — при следующей проверке агентов (хит или cron).
    </p>
<?php else: ?>
    <h2>Агент ещё не зарегистрирован</h2>
    <form method="post">
        <?= bitrix_sessid_post() ?>
        <button type="submit">Зарегистрировать агента</button>
    </form>
<?php endif; ?>

<h2>Что нужно сделать в проекте</h2>
<ol>
    <li>Выполнить <code>install.sql</code> в БД инсталляции.</li>
    <li>Положить <code>LogTable.php</code> и <code>LogCleanupAgent.php</code>
        в <code>/local/php_interface/classes/Agents/</code>.</li>
    <li>Зарегистрировать автозагрузку в <code>/local/php_interface/init.php</code>:
<pre>Loader::registerAutoLoadClasses(null, [
    \MyCompany\Agents\LogTable::class       => '/local/php_interface/classes/Agents/LogTable.php',
    \MyCompany\Agents\LogCleanupAgent::class => '/local/php_interface/classes/Agents/LogCleanupAgent.php',
]);</pre>
        Без этого агент отработает из cron с <code>Class not found</code>.
    </li>
</ol>

</body>
</html>
<?php

/**
 * Идемпотентная регистрация: сначала поиск, потом добавление.
 *
 * @return array{0: string, 1: string}
 */
function registerAgent(): array
{
    $name = LogCleanupAgent::AGENT_NAME;
    $existing = findAgent();

    if ($existing !== null) {
        return ['info', 'Агент уже зарегистрирован (ID ' . (int)$existing['ID'] . '). Дубликат не создаётся.'];
    }

    /*
     * $existError = false: при гонке двух одновременных установок второй вызов
     * не будет сыпать сообщением об уже существующем агенте. Саму гонку это не
     * закрывает — закрывает уникальность NAME в b_agent и то, что установка
     * выполняется один раз.
     */
    $agentId = \CAgent::AddAgent(
        $name,
        '',     // MODULE_ID: проектный класс, модуль не подключается
        'N',    // IS_PERIOD: следующий запуск от факта выполнения
        3600,   // интервал — час
        '',     // DATE_CHECK
        'Y',    // ACTIVE
        '',     // NEXT_EXEC: ближайшая проверка агентов
        100,    // SORT
        false,  // USER_ID
        false   // EXIST_ERROR
    );

    if ($agentId === false) {
        return ['error', 'CAgent::AddAgent() вернул false. Смотрите журнал событий.'];
    }

    return ['info', 'Агент добавлен, ID ' . (int)$agentId . '.'];
}

/**
 * Поиск агента по точному совпадению NAME.
 *
 * Фильтр legacy-API не гарантирует побайтового совпадения строки, поэтому
 * результат перепроверяется в PHP: иначе легко получить «нашёлся» чужой агент
 * с похожим именем и не зарегистрировать свой.
 *
 * @return array<string, mixed>|null
 */
function findAgent(): ?array
{
    $name = LogCleanupAgent::AGENT_NAME;

    $result = \CAgent::GetList(['ID' => 'ASC'], ['NAME' => $name]);

    while ($row = $result->Fetch()) {
        if ($row['NAME'] === $name) {
            return $row;
        }
    }

    return null;
}
