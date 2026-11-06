<?php
/**
 * Задача 3: проверка модульного префикса.
 *
 * Показывает главное различие: класс из lib/ находится БЕЗ includeModule
 * (файл подключён по пути), но модуль при этом не инициализирован.
 *
 * Разместить в корне сайта как /module-check.php.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

$moduleId = 'mycompany.tools';
$className = 'MyCompany\\Tools\\StringHelper';

$existsBefore = class_exists($className);
$installed = ModuleManager::isModuleInstalled($moduleId);
$included = Loader::includeModule($moduleId);
$includedAgain = Loader::includeModule($moduleId);
$legacyExists = class_exists('CMyCompanyToolsString');

?>
<h1>Модуль <?= htmlspecialcharsbx($moduleId) ?></h1>
<pre>
class_exists('<?= htmlspecialcharsbx($className) ?>') до includeModule : <?= $existsBefore ? 'true' : 'false' ?>

ModuleManager::isModuleInstalled()                        : <?= $installed ? 'true' : 'false' ?>

Loader::includeModule()                                   : <?= $included ? 'true' : 'false' ?>

Loader::includeModule() повторно                            : <?= $includedAgain ? 'true' : 'false' ?>

MYCOMPANY_TOOLS_INCLUDED (константа из include.php)       : <?= defined('MYCOMPANY_TOOLS_INCLUDED') ? 'true' : 'false' ?>

class_exists('CMyCompanyToolsString') (карта)             : <?= $legacyExists ? 'true' : 'false' ?>
</pre>

<?php if ($included): ?>
    <p>Модуль установлен и подключён. Результат работы класса:</p>
    <pre>
generateCode('Учебный модуль 1.6') = <?= htmlspecialcharsbx(\MyCompany\Tools\StringHelper::generateCode('Учебный модуль 1.6')) ?>

CMyCompanyToolsString::upper('ok') = <?= htmlspecialcharsbx(\CMyCompanyToolsString::upper('ok')) ?>
</pre>
<?php elseif ($existsBefore): ?>
    <p>
        Класс найден, модуль — нет. Именно поэтому «класс загрузился» нельзя
        считать признаком работоспособности: <code>lib/</code> разрешается по
        пути, а <code>include.php</code> (константы, обработчики, карта
        legacy-классов) не выполнялся.
    </p>
<?php else: ?>
    <p>
        Не найден даже класс: проверьте, что каталог
        <code>/local/modules/mycompany.tools/</code> скопирован целиком, а файл
        класса называется <code>lib/tools/stringhelper.php</code> — в нижнем
        регистре.
    </p>
<?php endif; ?>

<h2>Как установить модуль</h2>
<ol>
    <li>Скопировать <code>local/modules/mycompany.tools/</code> в
        <code>/local/modules/</code> сайта.</li>
    <li>Открыть <em>Marketplace → Установленные решения</em> (или
        <code>/bitrix/admin/partner_modules.php</code>) — модуль появится в
        списке как «MyCompany: инструменты».</li>
    <li>Нажать «Установить». Это выполнит <code>DoInstall()</code> →
        <code>ModuleManager::registerModule()</code>.</li>
</ol>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
