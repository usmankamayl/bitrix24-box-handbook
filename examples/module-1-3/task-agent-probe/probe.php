<?php

/**
 * Пробник механики агентов: что реально делает ядро и что показывает b_agent.
 *
 * Путь в проекте: /local/tools/practice-1.3-probe.php
 * Запуск:          php -f <DOCUMENT_ROOT>/local/tools/practice-1.3-probe.php
 *
 * Скрипт не запускает CheckAgents(): тот выполняет ВСЕ наступившие агенты
 * инсталляции, включая чужие. Вместо этого он регистрирует свои агенты,
 * читает их записи и снимает после себя.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);

$root = dirname((string)realpath(__DIR__), 2);

if (!is_file($root . '/bitrix/modules/main/include/prolog_before.php')) {
    fwrite(STDERR, "Не найден пролог ядра. Положите файл в <DOCUMENT_ROOT>/local/tools/.\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $root;
$DOCUMENT_ROOT = $root;

require $root . '/bitrix/modules/main/include/prolog_before.php';

require_once __DIR__ . '/ProbeAgents.php';

use MyCompany\Agents\ProbeAgents;

header('Content-Type: text/plain; charset=UTF-8');

const STATE_FIELDS = ['ID', 'NAME', 'MODULE_ID', 'ACTIVE', 'IS_PERIOD', 'AGENT_INTERVAL', 'LAST_EXEC', 'NEXT_EXEC'];

$removeProbeAgents = static function (): int {
    $removed = 0;

    $result = \CAgent::GetList(['ID' => 'ASC'], ['NAME' => '%' . ProbeAgents::PREFIX . '%']);

    while ($row = $result->Fetch()) {
        if (str_contains((string)$row['NAME'], ProbeAgents::PREFIX)) {
            \CAgent::Delete((int)$row['ID']);
            $removed++;
        }
    }

    return $removed;
};

$findAgent = static function (string $name): ?array {
    $result = \CAgent::GetList(['ID' => 'ASC'], ['NAME' => $name]);

    while ($row = $result->Fetch()) {
        if ($row['NAME'] === $name) {
            return $row;
        }
    }

    return null;
};

$printAgent = static function (?array $row): void {
    if ($row === null) {
        echo "  (агент не найден)\n";

        return;
    }

    foreach (STATE_FIELDS as $field) {
        printf("  %-15s %s\n", $field, (string)($row[$field] ?? ''));
    }
};

echo "Пробник агентов Bitrix\n";
echo "SM_VERSION: " . (defined('SM_VERSION') ? SM_VERSION : 'не определена') . "\n";
printf("Версия main: %s\n\n", \Bitrix\Main\ModuleManager::getVersion('main'));

// ---------------------------------------------------------------- 1. подпись

echo "1. Подпись CAgent::AddAgent()\n";

$method = new ReflectionMethod('CAgent', 'AddAgent');

printf("  статический: %s\n", $method->isStatic() ? 'да' : 'нет');
printf("  параметров:  %d\n", $method->getNumberOfParameters());

foreach ($method->getParameters() as $index => $parameter) {
    printf("  %2d. $%s%s\n",
        $index + 1,
        $parameter->getName(),
        $parameter->isOptional() ? ' = по умолчанию' : '');
}

echo "\n  Вывод: аргумента IS_CRON нет. Девятый параметр — user_id,\n"
    . "  десятый — existError (выдавать ли ошибку при дублировании).\n\n";

// ------------------------------------------------------- 2. наличие методов

echo "2. Какие методы CAgent есть в этой сборке\n";

foreach (['AddAgent', 'GetList', 'GetById', 'Update', 'Delete', 'RemoveAgent', 'RemoveModuleAgents', 'CheckAgents', 'ExecuteAgents'] as $name) {
    printf("  %-20s %s\n", $name, method_exists('CAgent', $name) ? 'есть' : 'нет');
}

echo "\n";

// ----------------------------------------------------------- 3. регистрация

echo "3. Регистрация и повторная регистрация\n";

$removed = $removeProbeAgents();
printf("  снято прошлых пробников: %d\n", $removed);

$name = '\\' . ProbeAgents::class . "::work('probe');";

$firstId = \CAgent::AddAgent($name, '', 'N', 3600, '', 'Y', '', 100, false, false);
printf("  AddAgent #1: %s\n", $firstId === false ? 'false' : 'ID ' . (int)$firstId);

$secondId = \CAgent::AddAgent($name, '', 'N', 3600, '', 'Y', '', 100, false, false);
printf("  AddAgent #2 (то же имя, existError=false): %s\n",
    $secondId === false ? 'false' : 'ID ' . (int)$secondId);

$rows = [];
$result = \CAgent::GetList(['ID' => 'ASC'], ['NAME' => $name]);

while ($row = $result->Fetch()) {
    if ($row['NAME'] === $name) {
        $rows[] = $row;
    }
}

printf("  строк с этим NAME: %d\n", count($rows));

if (count($rows) > 1) {
    echo "  Вывод: AddAgent() не защищает от дубля по имени. Идемпотентность\n"
        . "  регистрации — задача вызывающего кода (поиск перед добавлением).\n";
} else {
    echo "  Вывод: дубль не создан (поведение зависит от версии ядра —\n"
        . "  поэтому поиск перед AddAgent всё равно нужен).\n";
}

$agentRow = $findAgent($name);
echo "\n  Запись в b_agent:\n";
$printAgent($agentRow);

// ------------------------------------------------- 4. Update не двигает NEXT_EXEC

echo "\n4. Update(AGENT_INTERVAL) и NEXT_EXEC\n";

if ($agentRow !== null) {
    $id = (int)$agentRow['ID'];
    $nextBefore = (string)$agentRow['NEXT_EXEC'];
    $intervalBefore = (string)$agentRow['AGENT_INTERVAL'];

    \CAgent::Update($id, ['AGENT_INTERVAL' => 7200]);

    $after = $findAgent($name);

    printf("  до:    AGENT_INTERVAL=%s NEXT_EXEC=%s\n", $intervalBefore, $nextBefore);
    printf("  после: AGENT_INTERVAL=%s NEXT_EXEC=%s\n",
        (string)($after['AGENT_INTERVAL'] ?? ''), (string)($after['NEXT_EXEC'] ?? ''));

    if ($nextBefore === (string)($after['NEXT_EXEC'] ?? '')) {
        echo "  Вывод: изменение интервала не переносит уже записанный NEXT_EXEC —\n"
            . "  ближайший запуск надо задавать явно.\n";
    } else {
        echo "  Вывод: в этой сборке NEXT_EXEC пересчитался автоматически.\n"
            . "  Проверять на своей версии, а не полагаться на память.\n";
    }
}

// --------------------------------------------------------- 5. дефект __METHOD__

echo "\n5. Дефект «имя агента строит вспомогательный метод»\n";

printf("  nextNameGood() -> %s\n", ProbeAgents::nextNameGood());
printf("  nextNameBad()  -> %s\n", ProbeAgents::nextNameBad());

$bad = ProbeAgents::nextNameBad();
$badRepeat = ProbeAgents::nextNameBad();

printf("  повторный вызов nextNameBad() -> %s\n", $badRepeat);
printf("  строки совпадают: %s\n", $bad === $badRepeat ? 'да' : 'нет');

echo "  Вывод: агент, вернувший nextNameBad(), на следующем запуске снова\n"
    . "  вызовет метод, который отдаёт имя, и снова вернёт его же. Работа\n"
    . "  (work()) не выполняется никогда, LAST_EXEC обновляется, ошибок нет.\n"
    . "  Правильно — возвращать имя рабочей точки входа:\n"
    . "    return '\\\\' . __METHOD__ . \"();\";  // внутри самого work()\n\n";

// ----------------------------------------------- 6. прямой вызов != запуск

echo "6. Прямой вызов метода не обновляет запись агента\n";

if ($agentRow !== null) {
    $before = $findAgent($name);

    \call_user_func([ProbeAgents::class, 'work'], 'direct');

    $after = $findAgent($name);

    printf("  LAST_EXEC до:    %s\n", (string)($before['LAST_EXEC'] ?? ''));
    printf("  LAST_EXEC после: %s\n", (string)($after['LAST_EXEC'] ?? ''));

    if ((string)($before['LAST_EXEC'] ?? '') === (string)($after['LAST_EXEC'] ?? '')) {
        echo "  Вывод: прямой вызов выполнил код, но расписание агента не тронул:\n"
            . "  ни LAST_EXEC, ни NEXT_EXEC, ни возвращённая строка не применены.\n";
    }
}

// ---------------------------------------------------------------- 7. уборка

echo "\n7. Уборка\n";

$removed = $removeProbeAgents();
printf("  снято пробников: %d\n", $removed);
printf("  осталось: %d\n", $findAgent($name) === null ? 0 : 1);

echo "\nГотово. Все выводы проверяются на живой сборке — при смене версии ядра\n"
    . "перезапустите пробник, а не верьте тексту модуля.\n";
