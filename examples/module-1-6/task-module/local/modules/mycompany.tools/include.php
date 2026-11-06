<?php
/**
 * include.php модуля mycompany.tools.
 *
 * Вызывается ядром при Loader::includeModule('mycompany.tools') — один раз за
 * запрос; повторный вызов модуля не перезапускает этот файл (модуль уже в
 * $loadedModules).
 *
 * Здесь место для всего, что должно произойти при подключении модуля:
 * константы, карта legacy-классов, регистрация обработчиков событий.
 * Классы из lib/ регистрировать не нужно — они находятся по модульному
 * префиксу (MyCompany\Tools\... → mycompany.tools/lib/...).
 */

use Bitrix\Main\Loader;

define('MYCOMPANY_TOOLS_INCLUDED', true);

// Legacy-класс без namespace: модульный префикс для него не работает,
// поэтому путь задаётся картой. Путь — относительно корня модуля.
Loader::registerAutoLoadClasses('mycompany.tools', [
    'CMyCompanyToolsString' => 'classes/general/string.php',
]);

return true;
