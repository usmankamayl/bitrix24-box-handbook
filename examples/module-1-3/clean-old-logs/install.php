<?php

declare(strict_types=1);

use MyCompany\Agents\CleanOldLogsAgent;

$documentRoot = dirname(__DIR__, 3);

require $documentRoot
    . '/bitrix/modules/main/include/prolog_before.php';

global $USER, $APPLICATION;

if (!$USER || !$USER->IsAdmin()) {
    http_response_code(403);
    exit('Доступ разрешён только администратору.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_bitrix_sessid()) {
        http_response_code(403);
        $error = 'Сессия истекла. Обновите страницу и повторите действие.';
    } else {
        try {
            if (!class_exists(CleanOldLogsAgent::class)) {
                throw new RuntimeException(
                    'Класс агента не загружается. '
                    . 'Проверьте регистрацию автозагрузки в init.php.'
                );
            }

            CleanOldLogsAgent::validateEnvironment();

            $name = CleanOldLogsAgent::AGENT_NAME;
            $existingAgent = null;

            $result = \CAgent::GetList(
                ['ID' => 'ASC'],
                [
                    'NAME' => $name,
                    'MODULE_ID' => '',
                ]
            );

            while ($agent = $result->Fetch()) {
                if ($agent['NAME'] === $name) {
                    $existingAgent = $agent;
                    break;
                }
            }

            if ($existingAgent !== null) {
                $message = sprintf(
                    'Агент уже зарегистрирован. ID: %d. Активность: %s.',
                    (int) $existingAgent['ID'],
                    $existingAgent['ACTIVE'] === 'Y' ? 'да' : 'нет'
                );
            } else {
                $agentId = \CAgent::AddAgent(
                    $name,
                    '',      // MODULE_ID
                    'N',     // Отсчёт интервала от фактического выполнения
                    86400,   // Раз в сутки
                    '',      // DATE_CHECK
                    'Y',     // ACTIVE
                    '',      // NEXT_EXEC: ближайшая проверка агентов
                    100      // SORT
                );

                if (!$agentId) {
                    $exception = $APPLICATION->GetException();

                    throw new RuntimeException(
                        $exception
                            ? $exception->GetString()
                            : 'CAgent::AddAgent() не зарегистрировал агент.'
                    );
                }

                $message = sprintf(
                    'Агент зарегистрирован. ID: %d. Интервал: 86400 секунд.',
                    (int) $agentId
                );
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Установка агента очистки логов</title>
</head>
<body>
<h1>Агент очистки старых логов</h1>

<?php if ($message !== ''): ?>
    <p><?= htmlspecialcharsbx($message) ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p role="alert">
        <strong>Ошибка:</strong>
        <?= htmlspecialcharsbx($error) ?>
    </p>
<?php endif; ?>

<p>
    Будет зарегистрирован агент удаления обычных файлов
    старше 30 дней из /upload/logs/.
    Подкаталоги не обрабатываются.
</p>

<form method="post">
    <?= bitrix_sessid_post() ?>

    <button type="submit">
        Зарегистрировать агент
    </button>
</form>

<p>
    <a href="/bitrix/admin/agent_list.php?lang=ru">
        Список агентов
    </a>
</p>
</body>
</html>