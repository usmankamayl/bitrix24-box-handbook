<?php

/**
 * Диагностика контекста запроса.
 *
 * Путь в проекте: /local/tools/context-probe.php
 *
 * Что показывает:
 *   - какие классы ядро положило в Application/Context/Request/Response;
 *   - какой набор методов реально есть в ТВОЕЙ версии ядра (вместо веры
 *     в документацию и в чужие статьи);
 *   - что Request — снимок: правки $_GET/$_POST после старта контекста в него
 *     не попадают;
 *   - откуда какой геттер берёт значение и что делает get();
 *   - как мапятся заголовки ($_SERVER['HTTP_*'] против CONTENT_TYPE);
 *   - читается ли php://input дважды;
 *   - структуру $_FILES через getFile()/getFileList().
 *
 * Почему всё через method_exists и try/catch: набор методов в D7 зависит от
 * версии ядра. Скрипт не должен падать с fatal на другой версии — он должен
 * напечатать, чего в этой версии нет.
 */

define('NO_KEEP_STATISTIC', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;

header('Content-Type: text/plain; charset=UTF-8');

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------

function probe_line(string $label, mixed $value, int $pad = 34): void
{
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif ($value === null) {
        $value = 'null';
    } elseif (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (is_object($value)) {
        $value = get_class($value);
    }

    echo str_pad($label, $pad), ': ', (string)$value, "\n";
}

function probe_section(string $title): void
{
    echo "\n=== ", $title, " ", str_repeat('=', max(0, 60 - mb_strlen($title))), "\n";
}

/**
 * Цепочка наследования: Bitrix\Main\HttpRequest < Bitrix\Main\Request.
 */
function probe_class(?object $object): string
{
    if (!is_object($object)) {
        return 'не объект (' . gettype($object) . ')';
    }

    $chain = [get_class($object)];
    $parent = get_parent_class($object);

    while ($parent !== false) {
        $chain[] = $parent;
        $parent = get_parent_class($parent);
    }

    return implode(' < ', $chain);
}

/**
 * Короткое представление значения для таблиц: null / array(N) / строка с
 * обрезкой. Нужно, чтобы вывод пробы читался глазами, а не распухал.
 */
function probe_short(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }
    if (is_array($value)) {
        return 'array(' . count($value) . ')';
    }
    if (is_string($value)) {
        return mb_strlen($value) > 16 ? mb_substr($value, 0, 13) . '...' : $value;
    }

    return var_export($value, true);
}

/**
 * Печатает, какие из перечисленных методов есть у объекта, а каких нет.
 * Именно это отвечает на вопрос «есть ли getHeader()/getCulture() в моей версии».
 */
function probe_methods(object|string $target, array $methods): void
{
    $has = [];
    $missing = [];

    foreach ($methods as $method) {
        if (method_exists($target, $method)) {
            $has[] = $method . '()';
        } else {
            $missing[] = $method . '()';
        }
    }

    echo '  есть   : ', $has === [] ? '—' : implode(', ', $has), "\n";
    echo '  нет    : ', $missing === [] ? '—' : implode(', ', $missing), "\n";
}

// ---------------------------------------------------------------------------
probe_section('1. Кто есть кто');
// ---------------------------------------------------------------------------

$app = Application::getInstance();
$context = Context::getCurrent();
$request = $context->getRequest();

probe_line('$APPLICATION (легаси)', probe_class($GLOBALS['APPLICATION'] ?? null));
probe_line('Application', probe_class($app));
probe_line('Context', probe_class($context));
probe_line('Request', probe_class($request));
probe_line('Server', probe_class($context->getServer()));

try {
    probe_line('Response', probe_class($context->getResponse()));
} catch (\Throwable $e) {
    probe_line('Response', 'исключение: ' . $e->getMessage());
}

try {
    probe_line('Session', probe_class($context->getSession()));
} catch (\Throwable $e) {
    probe_line('Session', 'исключение: ' . $e->getMessage());
}

try {
    probe_line('Environment', probe_class($context->getEnvironment()));
} catch (\Throwable $e) {
    probe_line('Environment', 'исключение: ' . $e->getMessage());
}

probe_line('Context::getCurrent() тот же?', Context::getCurrent() === $context);
probe_line('Application::getInstance() тот же?', Application::getInstance() === $app);
probe_line('CMain === Application?', ($GLOBALS['APPLICATION'] ?? null) === $app);

echo "\nВывод: CMain и Bitrix\\Main\\Application — разные объекты, живущие\n";
echo "одновременно. application — инфраструктура D7, CMain — страница и шаблон.\n";

// ---------------------------------------------------------------------------
probe_section('2. Что есть в этой версии ядра');
// ---------------------------------------------------------------------------

echo "\nBitrix\\Main\\HttpRequest:\n";
probe_methods($request, [
    'get', 'getQuery', 'getPost', 'getFile', 'getCookie',
    'getQueryList', 'getPostList', 'getFileList', 'getCookieList',
    'getHeader', 'getHeaders',
    'getRequestMethod', 'getRequestUri', 'getServer',
    'isPost', 'isGet', 'isAjaxRequest', 'isHttps', 'isDelete', 'isPut',
]);

echo "\nBitrix\\Main\\Context:\n";
probe_methods($context, [
    'getRequest', 'getResponse', 'getServer', 'getSession',
    'getLanguage', 'getSite', 'getEnvironment', 'getCulture',
]);

echo "\nBitrix\\Main\\Application:\n";
probe_methods($app, [
    'getContext', 'getConnection', 'getCache', 'getManagedCache',
    'getSession', 'getDocumentRoot', 'isCli', 'run', 'end', 'restart',
]);

try {
    $response = $context->getResponse();
    echo "\n", get_class($response), ":\n";
    probe_methods($response, [
        'getHeaders', 'setStatus', 'getStatus', 'setContent', 'getContent',
        'write', 'flush', 'send', 'redirect',
    ]);
} catch (\Throwable $e) {
    echo "\nResponse недоступен: ", $e->getMessage(), "\n";
}

// ---------------------------------------------------------------------------
probe_section('3. Request — снимок, а не живой $_GET');
// ---------------------------------------------------------------------------

$queryKey = 'probe_a';
$queryBefore = $request->getQuery($queryKey);

// Меняем суперглобальный массив уже ПОСЛЕ того, как контекст собран.
$_GET[$queryKey] = 'CHANGED_AFTER_CONTEXT';
$_GET['probe_injected'] = 'INJECTED';

probe_line('getQuery(' . $queryKey . ') до правки', $queryBefore);
probe_line('$_GET[' . $queryKey . '] после правки', $_GET[$queryKey] ?? null);
probe_line('getQuery(' . $queryKey . ') после правки', $request->getQuery($queryKey));
probe_line('getQuery(probe_injected)', $request->getQuery('probe_injected'), 40);

echo "\nВывод: если getQuery() вернул исходное значение и null на внедрённый ключ,\n";
echo "Request держит копию массивов на момент создания контекста. Значит код,\n";
echo "который правит \$_GET/\$_POST в init.php или в обработчике, до Request\n";
echo "не доходит — и наоборот, Request не видит поздних правок вообще.\n";

// ---------------------------------------------------------------------------
probe_section('4. Откуда какой геттер берёт значение');
// ---------------------------------------------------------------------------

$probeKeys = ['probe_a', 'probe_both', 'probe_only_post', 'probe_cookie'];

printf("%-16s | %-18s | %-18s | %-18s | %s\n",
    'ключ', 'getQuery()', 'getPost()', 'get()', '$_GET/$_POST');

foreach ($probeKeys as $key) {
    $query = $request->getQuery($key);
    $post = $request->getPost($key);
    $any = $request->get($key);

    $globals = 'G=' . probe_short($_GET[$key] ?? null) . ' P=' . probe_short($_POST[$key] ?? null);

    printf("%-16s | %-18s | %-18s | %-18s | %s\n",
        $key,
        probe_short($query),
        probe_short($post),
        probe_short($any),
        $globals
    );
}

// Вспомогательные функции probe_short()/probe_line() объявлены выше.

echo "\nКак читать таблицу:\n";
echo "  probe_both задан и в query, и в теле POST — колонка get() показывает,\n";
echo "  какой источник get() предпочитает В ЭТОЙ версии. Это деталь реализации:\n";
echo "  не полагайся на неё, вызывай getQuery()/getPost() явно.\n";

// ---------------------------------------------------------------------------
probe_section('5. Заголовки: $_SERVER против getHeader()');
// ---------------------------------------------------------------------------

$headers = ['User-Agent', 'Content-Type', 'Content-Length', 'X-Requested-With',
            'Authorization', 'X-Bitrix-Sessid', 'Accept-Language'];

printf("%-18s | %-14s | %-24s | %s\n", 'заголовок', 'getHeader()', 'ключ $_SERVER', 'значение $_SERVER');

foreach ($headers as $name) {
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $fromRequest = $request->getHeader($name);
    $raw = $_SERVER[$serverKey] ?? null;

    // Content-Type и Content-Length приходят БЕЗ префикса HTTP_ — классическая
    // ловушка: HTTP_CONTENT_TYPE в $_SERVER обычно пуст.
    $fallbackKey = strtoupper(str_replace('-', '_', $name));
    $fallback = $_SERVER[$fallbackKey] ?? null;

    printf("%-18s | %-14s | %-24s | %s\n",
        $name,
        probe_short($fromRequest),
        $serverKey . ($raw === null && $fallback !== null ? ' (пусто, есть ' . $fallbackKey . ')' : ''),
        probe_short($raw ?? $fallback)
    );
}

echo "\nВывод: Authorization при CGI/FastCGI часто срезается веб-сервером до PHP.\n";
echo "Если getHeader('Authorization') пуст — виноват не Битрикс: смотреть\n";
echo "CGIPassAuth / mod_rewrite (Apache) или fastcgi_param (nginx).\n";

// ---------------------------------------------------------------------------
probe_section('6. Метод, URI, признаки');
// ---------------------------------------------------------------------------

probe_line('REQUEST_METHOD ($_SERVER)', $_SERVER['REQUEST_METHOD'] ?? null);
probe_line('getRequestMethod()', $request->getRequestMethod());
probe_line('isPost()', $request->isPost());

if (method_exists($request, 'isAjaxRequest')) {
    probe_line('isAjaxRequest()', $request->isAjaxRequest());
}
if (method_exists($request, 'isHttps')) {
    probe_line('isHttps()', $request->isHttps());
}

probe_line('getRequestUri()', $request->getRequestUri());
probe_line('$_SERVER[REQUEST_URI]', $_SERVER['REQUEST_URI'] ?? null);
probe_line('getQueryString()', method_exists($request, 'getQueryString')
    ? $request->getQueryString()
    : 'метода нет');

// ---------------------------------------------------------------------------
probe_section('7. Тело запроса: php://input');
// ---------------------------------------------------------------------------

$body1 = (string)file_get_contents('php://input');
$body2 = (string)file_get_contents('php://input');

probe_line('длина тела (1-е чтение)', strlen($body1));
probe_line('длина тела (2-е чтение)', strlen($body2));
probe_line('оба чтения совпали', $body1 === $body2);
probe_line('ключей в $_POST', count($_POST));
probe_line('первые 120 байт тела', substr($body1, 0, 120) !== '' ? substr($body1, 0, 120) : '—', 34);

if ($body1 !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) {
    $decoded = json_decode($body1, true);
    probe_line('json_decode: is_array', is_array($decoded));
    probe_line('json_decode: error', json_last_error_msg());
    probe_line('$_POST при JSON-теле', count($_POST) . ' ключей (PHP его не разбирает)');
}

echo "\nВывод: php://input читается повторно (PHP >= 5.6 для не-multipart),\n";
echo "но при multipart/form-data второе чтение пустое. JSON в \$_POST не попадает\n";
echo "никогда — тело разбирается только вручную.\n";

// ---------------------------------------------------------------------------
probe_section('8. Файлы');
// ---------------------------------------------------------------------------

try {
    $fileList = method_exists($request, 'getFileList') ? $request->getFileList() : [];
    probe_line('getFileList() тип', is_object($fileList) ? get_class($fileList) : gettype($fileList));
    probe_line('ключей в $_FILES', count($_FILES));

    if ($fileList !== [] && is_object($fileList) && method_exists($fileList, 'getKeys')) {
        probe_line('поля файлов', implode(', ', $fileList->getKeys()));
    } elseif (is_array($fileList) && $fileList !== []) {
        probe_line('поля файлов', implode(', ', array_keys($fileList)));
    }
} catch (\Throwable $e) {
    probe_line('getFileList()', 'исключение: ' . $e->getMessage());
}

echo "\nВывод: файл без выбора даёт error = UPLOAD_ERR_NO_FILE (4), а не пустой\n";
echo "tmp_name. Проверять надо error, а не tmp_name.\n";

// ---------------------------------------------------------------------------
probe_section('9. Сессия, язык, сайт');
// ---------------------------------------------------------------------------

try {
    $session = $context->getSession();
    probe_line('Session класс', probe_class($session));
    probe_line('Session::get() есть', method_exists($session, 'get'));
} catch (\Throwable $e) {
    probe_line('Session', 'исключение: ' . $e->getMessage());
}

try {
    $language = $context->getLanguage();
    probe_line('Language класс', probe_class($language));
    if (method_exists($language, 'getId')) {
        probe_line('Language::getId()', $language->getId());
    }
} catch (\Throwable $e) {
    probe_line('Language', 'исключение: ' . $e->getMessage());
}

try {
    $site = $context->getSite();
    probe_line('Site класс', probe_class($site));
} catch (\Throwable $e) {
    probe_line('Site', 'исключение: ' . $e->getMessage());
}

echo "\nВывод: в CLI-скрипте (php -f) Context тоже есть, но HTTP-полей в нём нет:\n";
echo "REQUEST_METHOD пуст, getQuery() всегда null. Код, который безусловно\n";
echo "дёргает getHeader()/getServer() на верхнем уровне, в CLI падает.\n";

echo "\n=== конец пробы ===\n";
