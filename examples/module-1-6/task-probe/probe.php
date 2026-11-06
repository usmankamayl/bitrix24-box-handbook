<?php
/**
 * Задача 2: диагностика автозагрузки.
 *
 * Одна страница, которая показывает фактическое состояние, а не ожидаемое.
 * Разместить в корне сайта как /autoload-probe.php и открыть в браузере
 * (желательно под администратором — часть данных иначе скрыта).
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

/** Краткое имя загрузчика для вывода. */
function probeLoaderName($loader): string
{
    if (is_string($loader)) {
        return $loader . '()';
    }
    if ($loader instanceof \Closure) {
        $r = new \ReflectionFunction($loader);

        return 'Closure ' . ($r->getFileName() ?: '?') . ':' . $r->getStartLine();
    }
    if (is_array($loader)) {
        [$class, $method] = $loader;
        $class = is_object($class) ? get_class($class) : $class;

        return $class . '::' . $method . '()';
    }
    if (is_object($loader)) {
        return get_class($loader);
    }

    return gettype($loader);
}

/** Проверка: файловую систему интересует регистр или нет. */
function probeFsCaseInsensitive(string $dir, string $camel, string $lower): ?bool
{
    $camelPath = $dir . '/' . $camel;
    $lowerPath = $dir . '/' . $lower;

    if (!is_dir($dir) || !file_exists($camelPath)) {
        return null;
    }

    // Если файла в нижнем регистре нет, но file_exists его находит —
    // ФС регистронезависима.
    return ($camel !== $lower) && file_exists($lowerPath);
}

$documentRoot = Loader::getDocumentRoot();
$classesDir = $documentRoot . '/local/php_interface/classes';

?>
<h1>Диагностика автозагрузки</h1>

<h2>1. Пути</h2>
<pre>
Loader::getDocumentRoot()   = <?= htmlspecialcharsbx($documentRoot) ?>

$_SERVER['DOCUMENT_ROOT']   = <?= htmlspecialcharsbx((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?>

Loader::getLocal('php_interface/init.php') = <?= htmlspecialcharsbx((string)Loader::getLocal('php_interface/init.php')) ?>
PHP <?= PHP_VERSION ?> (<?= PHP_OS_FAMILY ?>)
</pre>
<p>
    Оба пути должны совпадать. Если нет — где-то используется относительный
    путь или симлинк, и `require` из шаблона может уехать не туда.
</p>

<h2>2. Стек автозагрузчиков (порядок вызова)</h2>
<pre>
<?php foreach (spl_autoload_functions() ?: [] as $i => $loader): ?>
<?= $i + 1 ?>. <?= htmlspecialcharsbx(probeLoaderName($loader)) ?>

<?php endforeach; ?>
</pre>
<p>
    `Bitrix\Main\Loader::autoLoad()` регистрируется ядром при старте пролога.
    `Composer\Autoload\ClassLoader` появится ниже только после подключения
    `/local/vendor/autoload.php` в init.php. Кто выше — тот и объявит класс
    первым.
</p>

<h2>3. Резолвинг классов</h2>
<pre>
<?php
$candidates = [
    'Bitrix\\Main\\Application'      => 'ядро, карта main',
    'Bitrix\\Iblock\\ElementTable'   => 'ядро, модульный префикс',
    'CUser'                          => 'legacy, класс объявлен ядром',
    'MyCompany\\Helpers\\StringHelper' => 'задача 1, карта из init.php',
    'MyCompany\\Tools\\StringHelper' => 'задача 3, модульный префикс',
    'MyCompany\\Pricing\\Calculator' => 'задача 4, Composer PSR-4',
    'MyCompany\\Nope\\Missing'       => 'не существует',
];

foreach ($candidates as $class => $comment) {
    $registeredBefore = class_exists($class, false); // только уже объявленные
    $resolved = class_exists($class);                // с автозагрузкой
    printf(
        "%-40s %-38s declared=%s resolved=%s\n",
        $class,
        $comment,
        $registeredBefore ? 'yes' : 'no',
        $resolved ? 'yes' : 'no'
    );
}
?>
</pre>
<p>
    `class_exists($class, false)` не запускает загрузчики. Разница между
    колонками — это и есть работа автозагрузки: файл был подключён в момент
    проверки.
</p>

<h2>4. Цена промаха</h2>
<pre>
<?php
$iterations = 1000;
$probe = static fn(int $i): string => 'MyCompany\\Missing\\Class' . $i;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    class_exists($probe($i));            // каждый вызов — полный проход по стеку
}
$withAutoload = (microtime(true) - $start) * 1000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    class_exists($probe($i), false);     // только таблица объявленных классов
}
$withoutAutoload = (microtime(true) - $start) * 1000;

printf("%d промахов с автозагрузкой: %.2f ms\n", $iterations, $withAutoload);
printf("%d проверок без автозагрузки: %.2f ms\n", $iterations, $withoutAutoload);
printf("отношение: x%.1f\n", $withoutAutoload > 0 ? $withAutoload / $withoutAutoload : 0);
?>
</pre>
<p>
    На файловой системе с холодным кешем разница доходит до десятков раз. В
    цикле на 1000 элементов это уже секунды — типичная причина «страница
    грузится 4 секунды без видимых причин».
</p>

<h2>5. Регистр файловой системы</h2>
<pre>
<?php
$fsCase = probeFsCaseInsensitive($classesDir, 'Helpers/StringHelper.php', 'helpers/stringhelper.php');
?>
Каталог: <?= htmlspecialcharsbx($classesDir) ?>

ФС регистронезависима: <?= $fsCase === null ? 'не определено (нет файлов задачи 1)' : ($fsCase ? 'ДА — ловушка регистра не проявится локально' : 'НЕТ — как на Linux-проде') ?>

</pre>
<p>
    Если здесь «ДА», любой `registerNamespace` с CamelCase-файлами у вас
    работает, а на боевом сервере даст `Class not found`. Проверять надо не
    локально.
</p>

<h2>6. Модуль из задачи 3</h2>
<pre>
isModuleInstalled('mycompany.tools') = <?= ModuleManager::isModuleInstalled('mycompany.tools') ? 'true' : 'false' ?>

Loader::includeModule('mycompany.tools') вернул = <?= Loader::includeModule('mycompany.tools') ? 'true' : 'false' ?>

</pre>
<p>
    Класс `MyCompany\Tools\StringHelper` находится по модульному префиксу даже
    при `false` — файл подключается по пути. Но модуль не инициализирован:
    его `include.php` не выполнен, обработчики не навешены.
</p>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
