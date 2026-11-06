<?php

/**
 * probe.php — разведка проекта без изменений.
 *
 * Целевой путь в проекте: /local/tools/probe.php
 *
 * Задача 1 модуля 1.1: за один проход понять, что за проект, какие модули
 * стоят, где фактически выполняется инициализация и что трогали в /bitrix/.
 *
 * Правила этой страницы:
 *   - только администратор;
 *   - ничего не пишет и не меняет (read-only);
 *   - не выводит пароли, логины и ключи;
 *   - вывод экранирован htmlspecialcharsbx();
 *   - после работы файл удаляется.
 *
 * Внимание: страница не должна оставаться на боевом сайте. Даже под защитой
 * прав она раскрывает структуру проекта.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\ModuleManager;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

/** @var CMain $APPLICATION */
/** @var CUser $USER */

$APPLICATION->SetTitle('Разведка проекта');

if (!$USER->IsAdmin()) {
    // Никаких подробностей: посторонний не должен узнать, что страница есть.
    LocalRedirect('/');
}

$documentRoot = Application::getDocumentRoot();
$rows = [];

/**
 * Строка таблицы.
 */
function probe_row(string $group, string $key, string $value): void
{
    global $rows;
    $rows[] = [$group, $key, $value];
}

function probe_list_dir(string $dir, bool $dirsOnly = true): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $items = scandir($dir);
    if ($items === false) {
        return [];
    }

    $result = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if ($dirsOnly && !is_dir($dir . '/' . $item)) {
            continue;
        }
        $result[] = $item;
    }

    return $result;
}

// ---------------------------------------------------------------------------
// 1. Ядро и окружение
// ---------------------------------------------------------------------------

probe_row('Ядро', 'SM_VERSION', defined('SM_VERSION') ? SM_VERSION : 'не определена');
probe_row('Ядро', 'BX_ROOT', defined('BX_ROOT') ? BX_ROOT : 'не определена');
probe_row('Ядро', 'DOCUMENT_ROOT', $documentRoot);
probe_row('Ядро', 'PHP', PHP_VERSION . ' (' . PHP_SAPI . ')');
probe_row('Ядро', 'Версия main (ModuleManager)',
    (string)ModuleManager::getVersion('main'));

// ---------------------------------------------------------------------------
// 2. Инициализация: какой init.php фактически участвует
// ---------------------------------------------------------------------------

$localInit = $documentRoot . '/local/php_interface/init.php';
$coreInit = $documentRoot . '/bitrix/php_interface/init.php';

$initState = 'нет ни одного';
if (is_file($localInit) && is_file($coreInit)) {
    $initState = '/local/php_interface/init.php (перекрывает /bitrix/php_interface/init.php)';
} elseif (is_file($localInit)) {
    $initState = '/local/php_interface/init.php';
} elseif (is_file($coreInit)) {
    $initState = '/bitrix/php_interface/init.php';
}

probe_row('Инициализация', 'Работающий init.php', $initState);
probe_row('Инициализация', '/local/php_interface/init.php',
    is_file($localInit) ? 'есть, ' . filesize($localInit) . ' байт' : 'нет');
probe_row('Инициализация', '/bitrix/php_interface/init.php',
    is_file($coreInit) ? 'есть, ' . filesize($coreInit) . ' байт' : 'нет');

// ---------------------------------------------------------------------------
// 3. Конфигурация: только безопасные ключи
// ---------------------------------------------------------------------------

$settingsFile = $documentRoot . '/bitrix/.settings.php';
probe_row('Конфигурация', '.settings.php',
    is_file($settingsFile) ? 'есть, изменён ' . date('d.m.Y H:i', (int)filemtime($settingsFile)) : 'нет');
probe_row('Конфигурация', '.settings_extra.php',
    is_file($documentRoot . '/bitrix/.settings_extra.php') ? 'есть' : 'нет');

$cache = Configuration::getValue('cache');
if (is_array($cache)) {
    $cacheType = (string)($cache['type'] ?? 'не задан');
    // 'A' — автоопределение: файлы, если не настроен memcache/redis/apcu.
    $cacheHuman = [
        'A' => 'авто (файлы, если нет memcache/redis/apcu)',
        'file' => 'файлы',
        'memcache' => 'memcache',
        'redis' => 'redis',
        'apcu' => 'apcu',
    ][$cacheType] ?? $cacheType;
    probe_row('Конфигурация', 'Кеш (тип)', $cacheHuman);
} else {
    probe_row('Конфигурация', 'Кеш (тип)', 'секции cache нет — работает значение по умолчанию');
}

$exceptionHandling = Configuration::getValue('exception_handling');
if (is_array($exceptionHandling)) {
    probe_row('Конфигурация', 'Ошибки: debug',
        !empty($exceptionHandling['debug']) ? 'включён' : 'выключен');
    probe_row('Конфигурация', 'Ошибки: лог',
        (string)($exceptionHandling['log']['file'] ?? 'не задан'));
} else {
    probe_row('Конфигурация', 'Ошибки', 'секции exception_handling нет');
}

// ---------------------------------------------------------------------------
// 4. Модули: свои и установленные
// ---------------------------------------------------------------------------

$installed = ModuleManager::getInstalledModules();
$installedCount = count($installed);
probe_row('Модули', 'Установлено всего', (string)$installedCount);

$coreModules = [];
foreach ($installed as $moduleId => $info) {
    $coreModules[] = $moduleId . ' ' . (string)($info['VERSION'] ?? '?');
}
sort($coreModules);
probe_row('Модули', 'Установленные', implode(', ', $coreModules));

$localModules = probe_list_dir($documentRoot . '/local/modules');
$localModulesReport = [];
foreach ($localModules as $moduleId) {
    $versionFile = $documentRoot . '/local/modules/' . $moduleId . '/install/version.php';
    $version = 'нет install/version.php';
    if (is_file($versionFile)) {
        $arModuleVersion = [];
        include $versionFile;
        $version = (string)($arModuleVersion['VERSION'] ?? '?');
    }
    $state = ModuleManager::isModuleInstalled($moduleId) ? 'установлен' : 'не установлен';
    $localModulesReport[] = $moduleId . ' (' . $version . ', ' . $state . ')';
}
probe_row('Модули', 'Свои в /local/modules', $localModulesReport ? implode('; ', $localModulesReport) : 'нет');

// ---------------------------------------------------------------------------
// 5. Что переопределено в /local/
// ---------------------------------------------------------------------------

$localComponents = [];
foreach (probe_list_dir($documentRoot . '/local/components') as $vendor) {
    foreach (probe_list_dir($documentRoot . '/local/components/' . $vendor) as $component) {
        $localComponents[] = $vendor . '/' . $component;
    }
}
probe_row('Переопределения', 'Свои компоненты',
    $localComponents ? implode(', ', $localComponents) : 'нет');

$templates = probe_list_dir($documentRoot . '/local/templates');
probe_row('Переопределения', 'Свои шаблоны сайта',
    $templates ? implode(', ', $templates) : 'нет');

probe_row('Переопределения', 'urlrewrite в корне',
    is_file($documentRoot . '/urlrewrite.php') ? 'есть' : 'нет');

// ---------------------------------------------------------------------------
// 6. Правки в /bitrix/ — эвристика по mtime
// ---------------------------------------------------------------------------

/**
 * Файлы, изменённые за последние N дней, в каталогах, которые обновление
 * перезаписывает. Честная оговорка: свежая дата бывает и после обновления
 * ядра, поэтому это сигнал «посмотреть внимательнее», а не приговор.
 */
$scanRoots = [
    '/bitrix/php_interface',
    '/bitrix/components/bitrix',
    '/bitrix/templates',
];
$since = time() - 90 * 86400;
$suspicious = [];

foreach ($scanRoots as $relativeRoot) {
    $absoluteRoot = $documentRoot . $relativeRoot;
    if (!is_dir($absoluteRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        if ($file->getMTime() < $since) {
            continue;
        }
        $suspicious[] = str_replace($documentRoot, '', $file->getPathname());
        if (count($suspicious) >= 30) {
            break 2;
        }
    }
}

probe_row('Правки ядра', 'Файлы в /bitrix/ моложе 90 дней',
    $suspicious ? implode(', ', $suspicious) . ' (проверить по дистрибутиву)' : 'не найдены');

// ---------------------------------------------------------------------------
// 7. Обработчики ключевых событий
// ---------------------------------------------------------------------------

$events = [
    ['main', 'OnPageStart'],
    ['iblock', 'OnBeforeIBlockElementAdd'],
    ['main', 'OnEndBufferContent'],
];

foreach ($events as [$module, $event]) {
    // GetModuleEvents читает и D7-, и legacy-регистрации из b_module_to_module.
    $handlers = GetModuleEvents($module, $event, true);
    $formatted = [];
    if (is_array($handlers)) {
        foreach ($handlers as $handler) {
            $toClass = (string)($handler['TO_CLASS'] ?? '');
            $toMethod = (string)($handler['TO_METHOD'] ?? '');
            $fromModule = (string)($handler['FROM_MODULE_ID'] ?? '');
            $formatted[] = $fromModule . ': ' . $toClass . '::' . $toMethod;
        }
    }
    probe_row('События', $module . ':' . $event,
        $formatted ? implode('; ', $formatted) : 'нет обработчиков');
}

// ---------------------------------------------------------------------------
// Вывод
// ---------------------------------------------------------------------------

?>
<h2>Разведка проекта: <?= htmlspecialcharsbx($documentRoot) ?></h2>

<p class="notetext">
    Страница только читает. Пароли и ключи не выводятся. После работы удалите файл.
</p>

<table class="data-table" style="width: 100%;">
    <thead>
    <tr>
        <th style="width: 15%;">Группа</th>
        <th style="width: 25%;">Параметр</th>
        <th>Значение</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as [$group, $key, $value]): ?>
        <tr>
            <td><?= htmlspecialcharsbx($group) ?></td>
            <td><?= htmlspecialcharsbx($key) ?></td>
            <td style="word-break: break-all;"><?= htmlspecialcharsbx($value) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';