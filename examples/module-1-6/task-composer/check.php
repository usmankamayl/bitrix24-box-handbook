<?php
/**
 * Задача 4: проверка Composer PSR-4.
 *
 * Разместить в корне сайта как /composer-check.php.
 * Требует: /local/composer.json, выполненный `composer install` в /local/,
 * подключение autoload.php в init.php (файл init.php в этой папке).
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Bitrix\Main\Loader;

$autoloadPath = Loader::getDocumentRoot() . '/local/vendor/autoload.php';
$composerLoader = null;
$loaders = spl_autoload_functions() ?: [];

foreach ($loaders as $loader) {
    if (is_array($loader) && is_object($loader[0]) && $loader[0] instanceof \Composer\Autoload\ClassLoader) {
        $composerLoader = $loader[0];
        break;
    }
}

$class = 'MyCompany\\Pricing\\Calculator';
$exists = class_exists($class);
$file = $exists ? (new \ReflectionClass($class))->getFileName() : null;

?>
<h1>Composer PSR-4</h1>
<pre>
vendor/autoload.php существует                 : <?= is_file($autoloadPath) ? 'true' : 'false' ?>

Composer\Autoload\ClassLoader в стеке          : <?= $composerLoader ? 'true' : 'false' ?>
загрузчиков в стеке                            : <?= count($loaders) ?>

<?= htmlspecialcharsbx($class) ?> : <?= $exists ? 'найден' : 'НЕ найден' ?>

файл класса                                    : <?= htmlspecialcharsbx((string)$file) ?>

</pre>

<?php if (!$composerLoader): ?>
    <p>
        Composer не подключён. Проверьте: <code>/local/vendor/autoload.php</code>
        создаётся командой <code>composer install</code> в каталоге
        <code>/local/</code>, а <code>init.php</code> должен его подключать.
        Без этого класс <code>MyCompany\Pricing\Calculator</code> не найдётся —
        Loader про namespace <code>MyCompany\</code> ничего не знает.
    </p>
<?php else: ?>
    <p>
        Загрузчик Composer стоит в стеке после <code>Loader::autoLoad()</code>.
        Для классов <code>Bitrix\*</code> это значит, что ядро всегда выигрывает;
        для <code>MyCompany\*</code> Loader просто ничего не находит и управление
        переходит к Composer.
    </p>
    <pre>
Calculator::withVat(1000)        = <?= \MyCompany\Pricing\Calculator::withVat(1000) ?>

Calculator::vatAmount(1000)      = <?= \MyCompany\Pricing\Calculator::vatAmount(1000) ?>

Calculator::withVat(1000, 0.05)  = <?= \MyCompany\Pricing\Calculator::withVat(1000, 0.05) ?>
</pre>
    <h2>Эксперимент: почему `-o` опасен в разработке</h2>
    <ol>
        <li>Выполните в <code>/local/</code>: <code>composer dump-autoload -o</code>.</li>
        <li>Создайте <code>php_interface/classes/Pricing/Formatter.php</code> с
            классом <code>MyCompany\Pricing\Formatter</code>.</li>
        <li>Обратитесь к нему на этой странице — получите
            <code>Class not found</code>: карта классов заморожена.</li>
        <li>Повторите <code>composer dump-autoload</code> (без <code>-o</code>) —
            класс находится.</li>
    </ol>
    <?php
    $putUrl = '/local/vendor/autoload.php';
    ?>
    <h2>Проверка закрытости служебных файлов</h2>
    <pre>
curl -I https://<?= htmlspecialcharsbx((string)($_SERVER['HTTP_HOST'] ?? 'site.local')) ?>/local/composer.json
curl -I https://<?= htmlspecialcharsbx((string)($_SERVER['HTTP_HOST'] ?? 'site.local')) ?><?= $putUrl ?>

ожидаемо: 403 или 404, НЕ 200
</pre>
<?php endif; ?>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
