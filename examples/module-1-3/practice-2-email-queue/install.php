<?php

/**
 * Регистрация агента разбора очереди. Административная страница.
 * /local/tools/practice-2-email-queue/install.php
 *
 * Контракт: права -> CSRF -> метод -> работа. Ничего не отправляет.
 */

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Запускать через браузер под администратором.\n");
    exit(1);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$classDir = is_file(__DIR__ . '/EmailQueueAgent.php')
    ? __DIR__
    : $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/Agents';

require_once $classDir . '/EmailQueueTable.php';
require_once $classDir . '/EmailQueueAgent.php';

use Bitrix\Main\Application;
use MyCompany\Agents\EmailQueueAgent;
use MyCompany\Agents\EmailQueueTable;

if (!is_object($GLOBALS['USER']) || !$GLOBALS['USER']->IsAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '403';
    exit;
}

$messages = [];
$agentRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_bitrix_sessid()) {
        $messages[] = ['error', 'Сессия истекла: обновите страницу и повторите.'];
    } elseif (!Application::getConnection()->isTableExists(EmailQueueTable::getTableName())) {
        $messages[] = ['error', 'Нет таблицы ' . EmailQueueTable::getTableName()
            . '. Сначала выполните install.sql.'];
    } else {
        $messages[] = registerAgent();
    }
}

$agentRow = findAgent();
$templateExists = mailTemplateExists();

header('Content-Type: text/html; charset=UTF-8');

$escape = static fn($value): string => htmlspecialcharsbx((string)$value);
?>
<!doctype html>
<html lang="ru">
<head><meta charset="utf-8"><title>Агент разбора очереди писем</title></head>
<body style="font:14px/1.5 sans-serif;max-width:900px;margin:40px auto">

<h1>Агент разбора очереди писем</h1>

<p>
    Строка агента: <code><?= $escape(EmailQueueAgent::AGENT_NAME) ?></code><br>
    Порция: <?= (int)EmailQueueAgent::BATCH_SIZE ?> писем, попыток:
    <?= (int)EmailQueueAgent::MAX_ATTEMPTS ?>, интервал: 60 с,
    <code>IS_PERIOD = N</code>.
</p>

<?php foreach ($messages as [$type, $text]): ?>
    <p style="padding:10px;border:1px solid <?= $type === 'error' ? '#c00' : '#080' ?>">
        <?= $escape($text) ?>
    </p>
<?php endforeach; ?>

<?php if ($templateExists): ?>
    <p style="padding:10px;border:1px solid #080">
        Почтовый шаблон <code><?= $escape(EmailQueueAgent::EVENT_NAME) ?></code> найден.
    </p>
<?php else: ?>
    <p style="padding:10px;border:1px solid #c60">
        Почтовый шаблон <code><?= $escape(EmailQueueAgent::EVENT_NAME) ?></code> не найден —
        отправка будет возвращать неуспешный результат, и письма уйдут в
        <code>error</code> после <?= (int)EmailQueueAgent::MAX_ATTEMPTS ?> попыток. Создайте тип события и шаблон: Настройки → Настройки продукта →
        Почтовые события → Типы событий, затем Почтовые шаблоны. Поля шаблона:
        тема <code>#SUBJECT#</code>, тело <code>#BODY#</code>, получатель
        <code>#EMAIL#</code>.
    </p>
<?php endif; ?>

<?php if ($agentRow !== null): ?>
    <h2>Агент зарегистрирован</h2>
    <table border="1" cellpadding="6" cellspacing="0">
        <?php foreach (['ID', 'NAME', 'ACTIVE', 'IS_PERIOD', 'AGENT_INTERVAL', 'LAST_EXEC', 'NEXT_EXEC'] as $field): ?>
            <tr>
                <th align="left"><?= $escape($field) ?></th>
                <td><?= $escape($agentRow[$field] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p>Прогон: <code>php -f <?= $escape(__DIR__) ?>/check.php</code></p>
<?php else: ?>
    <h2>Агент ещё не зарегистрирован</h2>
    <form method="post">
        <?= bitrix_sessid_post() ?>
        <button type="submit">Зарегистрировать агента</button>
    </form>
<?php endif; ?>

<h2>Порядок работы</h2>
<ol>
    <li><?= $escape('install.sql') ?> в БД инсталляции.</li>
    <li><code>EmailQueueTable.php</code>, <code>EmailQueueAgent.php</code> →
        <code>/local/php_interface/classes/Agents/</code>.</li>
    <li>Автозагрузка в <code>/local/php_interface/init.php</code>
        (<code>Loader::registerAutoLoadClasses</code> или PSR-4 из
        <code>/local/composer.json</code>).</li>
    <li>Почтовый шаблон типа <code><?= $escape(EmailQueueAgent::EVENT_NAME) ?></code>.</li>
</ol>

</body>
</html>
<?php

/**
 * @return array{0: string, 1: string}
 */
function registerAgent(): array
{
    $existing = findAgent();

    if ($existing !== null) {
        return ['info', 'Агент уже зарегистрирован (ID ' . (int)$existing['ID'] . ').'];
    }

    $agentId = \CAgent::AddAgent(
        EmailQueueAgent::AGENT_NAME,
        '',
        'N',
        60,     // очередь должна разбираться часто: минута от факта выполнения
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
    $name = EmailQueueAgent::AGENT_NAME;
    $result = \CAgent::GetList(['ID' => 'ASC'], ['NAME' => $name]);

    while ($row = $result->Fetch()) {
        if ($row['NAME'] === $name) {
            return $row;
        }
    }

    return null;
}

/**
 * Есть ли шаблон письма для события агента.
 */
function mailTemplateExists(): bool
{
    $result = \CEventMessage::GetList(
        $by = 'id',
        $order = 'asc',
        ['EVENT_NAME' => EmailQueueAgent::EVENT_NAME]
    );

    return is_array($result->Fetch());
}
