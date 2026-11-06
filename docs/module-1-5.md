# Модуль 1.5: Жизненный цикл HTTP-запроса: пролог, эпилог, `header.php`/`footer.php`

## Теория

### Границы темы

Здесь разбирается путь запроса до первой строки твоего кода и после последней:
маршрутизация на веб-сервере, точки входа Bitrix, цепочка `prolog_*` /
`epilog_*`, шаблон сайта, порядок вывода и завершение запроса. Не разбираются:
компоненты (1.2), события как механизм (1.4), `Application`/`Context`/`Request`
подробно (1.7), кеширование (модуль 4).

> **О версии.** Механика ниже сверена по исходникам ядра Bitrix24-коробки
> (2024 год, `modules/main`): `include/prolog.php`, `include/prolog_before.php`,
> `include/prolog_after.php`, `include/epilog_before.php`,
> `include/epilog_after.php`, `classes/general/main.php`,
> `lib/application.php`, `lib/composite/engine.php`.
>
> Это важно: с 12.5.3 (BУС 2013) часть механики изменилась не «чуть-чуть», а
> принципиально — `buffer_content` больше нет, `CMain::EpilogActions()` пуст,
> `CEvent::CheckEvents()` из эпилога убран, `FinalActions()` теперь сам вызывает
> `exit()`. Отличия старых сборок по ходу текста помечены как **«было в 12–19»**.
>
> Проверка у себя — те же файлы плюс `grep`:
>
> ```bash
> # буферизация: в актуальном ядре включается безусловно, опции нет
> grep -n "buffer_content" bitrix/modules/main/classes/general/main.php
> # почтовые события: в старых сборках — в эпилоге, сейчас — background job
> grep -n "CheckEvents" bitrix/modules/main/classes/general/main.php
> ```
>
> Если строка нашлась — у тебя старая ветка, читай соответствующий абзац.

### 1. До PHP: у Bitrix нет front controller

Это первое, что ломает ожидания после Symfony/Laravel. В Bitrix **каждый URL —
реальный файл** в документ-руте: `/index.php`, `/news/index.php`,
`/catalog/detail.php`. Единого `index.php`, который разбирает `REQUEST_URI`,
нет.

Красивые URL (`/news/novosti-kompanii/`) делает `urlrewrite`:

1. Веб-сервер не нашёл файл → отдаёт запрос в `/bitrix/urlrewrite.php`
   (Apache — правила в `.htaccess`; nginx — `try_files`/`rewrite` в конфиге
   сайта, `.htaccess` он не читает).
2. `bitrix/urlrewrite.php` подключает `urlrewrite.php` из корня сайта, где
   лежит массив `$arUrlRewrite`. Правила создаёт админка «Обработка адресов»,
   часть добавляют компоненты при сохранении настроек.
3. Каждое правило: `CONDITION` (регэксп против `REQUEST_URI`), `PATH` (реальный
   файл), `ID` (компонент). Первое совпавшее выигрывает.
4. Ядро выставляет `$_SERVER['REAL_FILE_PATH']` и подключает `PATH`. Дальше
   компонент в SEF-режиме разбирает хвост URL сам
   (`CComponentEngine::ParseComponentPath()` → `SECTION_CODE` / `ELEMENT_CODE`).

Следствия, которые реально спрашивают:

- **Статус 404 не появляется сам.** Нет файла и нет правила — это либо 404
  веб-сервера (без шаблона сайта), либо страница из настройки «Страница 404».
  Корректный `404 Not Found` — ответственность контента, а не «ядра».
- **Порядок правил важнее их качества.** `#^/news/#` перехватит `/news/rss/`,
  если стоит выше правила для RSS.
- **`urlrewrite` не знает о правах.** Авторизация начинается позже, в прологе,
  — то есть уже после выбора файла.

### 2. Пять ветвей входа

| Ветка | Как выглядит | Шаблон сайта | Типичное применение |
|---|---|---|---|
| Публичная страница | `require header.php` … код … `require footer.php` | да | сайт, каталог, формы |
| Служебный скрипт | `require prolog_before.php` | нет | JSON/AJAX, вебхуки, cron-обёртки |
| D7 AJAX-контроллер | `BX.ajax.runComponentAction` → `/bitrix/services/main/ajax.php` | нет | действия компонентов |
| Админка | `/bitrix/admin/*.php` | свой, не публичный | настройки, гриды |
| CLI | `php -f script.php` | нет | агенты, миграции, импорт |

Разница между первой и второй — не «красиво/некрасиво», а **что именно
поднимается**:

```php
// 1) Публичная страница: пролог + шаблон + эпилог
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->SetTitle('Каталог');
// ... вывод ...
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
```

```php
// 2) Служебный скрипт: только ядро, без шаблона и без HTML
// Константы статистики — наследие модуля statistic; в актуальных коробках
// его нет, но строки безвредны и остались в тысячах проектов.
define('NO_KEEP_STATISTIC', true);   // не писать хит в статистику
define('STOP_STATISTICS', true);     // не подключать модуль statistic
define('NO_AGENT_STATISTIC', true);  // не считать агентов
define('BX_SKIP_SESSION_EXPAND', true); // не тянуть js-расширитель сессии (это работает)
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
```

```php
// 3) CLI: DOCUMENT_ROOT веб-сервером не выставлен — задаём сами
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true); // в CLI нет ни пользователя, ни прав
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
```

`NOT_CHECK_PERMISSIONS` в публичной ветке — почти всегда дыра: она отключает
проверки, которые делает ядро, и перекладывает их на тебя. Ставить её можно
только там, где ты сам проверяешь `check_bitrix_sessid()` и права
пользователя.

### 3. Цепочка пролога

`bitrix/header.php` в дистрибутиве — **одна строка**:

```php
<?require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog.php');?>
```

`prolog.php` в актуальном ядре — не «развилка D7/legacy», а четыре шага
(исходник целиком, комментарии мои):

```php
require_once(__DIR__ . "/../bx_root.php");      // BX_ROOT, BX_PERSONAL_ROOT, LANG

if (file_exists($_SERVER["DOCUMENT_ROOT"] . BX_PERSONAL_ROOT . "/html_pages/.enabled"))
{
    require_once(__DIR__ . "/../lib/composite/responder.php");
    Bitrix\Main\Composite\Responder::respond(); // композит: отдать HTML из кеша и выйти
}

require_once(__DIR__ . "/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/include/prolog_after.php");
```

Ветка с `/bitrix/d7.php` и `HttpApplication::turnOnCompatibleMode()` /
`setInputParameters()` / `initialize()` / `start()` — **было в 12–19**: она жила
как раз в `prolog.php`, и файла `d7.php` в актуальном ядре нет. Сейчас инициализация
ядра D7 (Application, `.settings.php`, соединение с БД, кеш, сессия, `$USER`,
`init.php`, авторизация) находится внутри `modules/main/include.php`, который
подключает `prolog_before.php`.

Полная цепочка:

```
header.php → prolog.php
├── bx_root.php                       ← BX_ROOT, BX_PERSONAL_ROOT, язык
├── Composite\Responder::respond()    ← если включён композит: кеш → ответ → выход
├── prolog_before.php
│   ├── START_EXEC_PROLOG_BEFORE_1, BX_STATE = 'PB'
│   ├── unset($_GET['BX_STATE']) …    ← состояние нельзя подсунуть снаружи
│   ├── modules/main/include.php
│   │   ├── автолоадер, Application::initialize(), .settings.php, БД, кеш
│   │   ├── php_interface/init.php (local → bitrix, в этом порядке)
│   │   ├── сессия, авторизация, $USER = new CUser
│   │   └── OnBeforeProlog, OnPageStart
│   └── CMain::PrologActions()        ← ob_start(EndBufferContent), BX_BUFFER_USED,
│                                       session expander, OnProlog
└── prolog_after.php
    ├── BX_STATE = 'PA', header('Content-type: text/html; charset=…')
    ├── проверка «сайт закрыт» (site_closed.php)
    └── include SITE_TEMPLATE_PATH . '/header.php'
        └── BX_STATE = 'WA', Asset::startTarget('PAGE'),
            $APPLICATION->RestartWorkarea(true)
```

Что из этого надо уметь объяснить:

1. **`$APPLICATION` (`CMain`) и `$USER` (`CUser`) создаются в прологе**, до
   твоего кода. К первой строке страницы это готовые объекты — и с уже
   выполненной авторизацией.
2. **`init.php` выполняется в прологе, до шаблона и до `OnProlog`.** Это
   единственное место, где можно вмешаться «для всего сайта», но платить за него
   будут все запросы, включая AJAX и `bitrix/tools`. Порядок относительно
   создания `$USER` и авторизации по сборкам различается, поэтому в `init.php`
   нельзя полагаться на «пользователь уже известен»: права проверяют позже
   (компонент, `OnEndBufferContent`, точка входа).
3. **`CMain::PrologActions()`** делает ровно четыре вещи (проверено по
   исходнику): включает буферизацию вывода **безусловно** (если `BX_BUFFER_USED`
   ещё не определена), регистрирует `register_shutdown_function()` на сброс
   буферов, инициализирует js-расширитель сессии, вызывает `OnProlog`.
   Никакой опции «буферизация Y/N» в актуальном ядре нет — **было в 12–19**
   (`COption::GetOptionString("main", "buffer_content", "Y")`).
4. **`OnBeforeProlog` и `OnProlog` — разные события и разные места.**
   `OnBeforeProlog` (и `OnPageStart`) стреляет внутри `include.php`, до
   `PrologActions()`; `OnProlog` — уже из `PrologActions()`. Порядок относительно
   `init.php` и создания `$USER` зависит от сборки, поэтому в `init.php` не
   полагайся на «пользователь уже известен». Проверяется своим trace-инструментом:
   если строки `OnBeforeProlog` в таблице нет — обработчик не успел
   зарегистрироваться до выстрела.
5. **Шаблон сайта подключается в `prolog_after.php`**, до тела страницы.
   `RestartWorkarea(true)` после шапки ставит закладку: всё, что напечатано
   дальше, — «work area», который композитный сайт умеет пересобирать отдельно.


### 4. Что уже доступно на первой строке твоей страницы

```php
global $APPLICATION, $USER, $DB;
$APPLICATION->GetCurPage();        // '/news/index.php'
$APPLICATION->GetCurDir();         // '/news/'
$APPLICATION->GetCurUri();         // '/news/?tag=php'
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$request->getQuery('tag');         // GET через D7
$request->getPost('name');         // POST
```

Плюс определены `SITE_ID`, `LANGUAGE_ID`, `SITE_DIR`, `SITE_TEMPLATE_ID`;
`init.php` уже выполнен; классы модулей доступны по namespace, если модуль
подключён (`Loader::includeModule()` — для остальных). То есть **любой
`require` в первой строке страницы — это уже «внутри» приложения**.

### 5. Стоимость пролога и почему это архитектурный факт

Пролог выполняется на **каждый** HTTP-запрос: конфиг, БД, сессия, авторизация,
автозагрузка, `init.php`, события. Отсюда правила:

- Тяжёлое не место в `init.php`: он грузится на всех запросах, включая AJAX,
  `bitrix/tools` и страницы ошибок. Тяжёлое — в агенты, очереди, кеш,
  отложенные задания.
- Точка входа без шаблона экономит рендер и вывод, но **не** экономит пролог.
  Экономия пролога — отдельный слой: кеш nginx, композитный сайт, endpoint вне
  Bitrix.
- `NO_KEEP_STATISTIC` / `STOP_STATISTICS` / `NO_AGENT_STATISTIC` — наследие
  модуля `statistic`. В актуальных коробках (и в Bitrix24) этого модуля нет,
  константы безвредны, но ничего не экономят; в БУС со установленным
  `statistic` они по-прежнему отключают запись хита.
- Реальная экономия на служебных точках — `BX_SKIP_SESSION_EXPAND` и
  `PUBLIC_AJAX_MODE`: первый отключает js-расширитель сессии, второй — ещё и
  отладочный вывод эпилога (`debug_info.php`, SQL/кеш-статистику).
- Композитный сайт отдаёт HTML **до пролога**: `prolog.php` проверяет файл
  `BX_PERSONAL_ROOT/html_pages/.enabled` и вызывает
  `\Bitrix\Main\Composite\Responder::respond()`, который может отдать кеш и
  завершить запрос. То есть `init.php` и события при попадании в кеш не
  выполняются вообще.


### 6. Где живёт твой код

| Путь | Что | Переживает обновление |
|---|---|---|
| `/bitrix/` | ядро, модули, штатные шаблоны | нет — вендор, править нельзя |
| `/local/` | твой код: `php_interface/init.php`, `templates/`, `components/`, `modules/`, `ajax/`, `cron/` | да |
| `/bitrix/php_interface/init.php` | legacy-место bootstrap | да, но не рекомендуется: смешивается с вендором |

Файлы в `/local/` лежат в документ-руте, то есть **доступны по HTTP**. Поэтому
свои include-файлы начинай с защиты:

```php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
```

Без неё `init.php` можно дёрнуть напрямую и выполнить часть кода в неожиданном
контексте: без пролога, без `$USER`, с пустым `DOCUMENT_ROOT`.

### 7. Два разных `header.php`

Их постоянно путают, а на собеседовании это различают сразу:

| Файл | Что это | Кто подключает |
|---|---|---|
| `/bitrix/header.php` | точка входа пролога, **не шаблон** | ты, в начале страницы |
| `local/templates/<id>/header.php` | HTML-шапка шаблона: `<!DOCTYPE>`, `ShowHead()`, меню | ядро, из `prolog_after.php` |
| `/bitrix/footer.php` | точка входа эпилога, **не шаблон** | ты, в конце страницы |
| `local/templates/<id>/footer.php` | HTML-подвал шаблона | ядро, из эпилога |

Шаблон ищется сначала в `local/templates/<id>/`, затем в
`bitrix/templates/<id>/`; `$APPLICATION->GetTemplatePath('header.php')` вернёт
фактический путь, `SITE_TEMPLATE_PATH` — каталог.

Контракт шапки шаблона:

```php
// local/templates/my/header.php
?><!DOCTYPE html>
<html lang="<?=LANGUAGE_ID?>">
<head>
    <?php $APPLICATION->ShowHead(); // meta, css, js — всё отложенно ?>
    <title><?php $APPLICATION->ShowTitle(); ?></title>
</head>
<body>
<?php $APPLICATION->ShowPanel(); // админ-панель ?>
```

`ShowHead()` выводит `<meta http-equiv="Content-Type">`, `robots`, `keywords`,
`description`, CSS и JS. `ShowPanel()` — плавающая админ-панель: внутри она
сама проверяет права, но лишний вызов на публичке — это лишняя работа.

### 8. Отложенный вывод: почему `SetTitle()` после шапки всё-таки работает

Это главный механизм темы, и он объясняет половину «магии» Bitrix.

`ShowTitle()`, `ShowMeta()`, `ShowProperty()`, `ShowCSS()`, `ShowHeadScripts()`
**не печатают** — они регистрируют колбэк. Важное уточнение: `ShowHead()` сам по
себе **печатает** `<meta http-equiv="Content-Type">` сразу, а остальное отдаёт
отложенным методам (исходник `CMain::ShowHead()`):

```php
public function ShowHead($bXhtmlStyle = true)
{
    echo '<meta http-equiv="Content-Type" content="text/html; charset=' . LANG_CHARSET . '"' …;
    $this->ShowMeta("robots", false, $bXhtmlStyle);
    $this->ShowMeta("keywords", false, $bXhtmlStyle);
    $this->ShowMeta("description", false, $bXhtmlStyle);
    $this->ShowLink("canonical", null, $bXhtmlStyle);
    $this->ShowCSS(true, $bXhtmlStyle);
    $this->ShowHeadStrings();
    $this->ShowHeadScripts();
}
```

Механизм отложенности (`CMain`, сокращённо, но по исходнику):

```php
public function ShowTitle($property_name = "title", $strip_tags = true)
{
    $this->AddBufferContent([&$this, "GetTitle"], $property_name, $strip_tags);
}

public function AddBufferContent($callback)   // аргументы — func_get_arg()
{
    if (!defined("BX_BUFFER_USED") || BX_BUFFER_USED !== true)
    {
        echo call_user_func_array($callback, $args);   // немедленный вывод
        return;
    }
    $this->buffer_content[] = ob_get_contents();       // уже напечатанный кусок
    $this->buffer_content[] = "";
    $this->buffer_content_type[] = ["F" => $callback, "P" => $args];
    $this->buffer_man = true;
    ob_end_clean();                                    // вывод «схлопнут» в массив
    $this->buffer_man = false;
    $this->buffered = true;
    ob_start();                                        // новый буфер — уже без колбэка
}
```

Колбэки вычисляются в `EndBufferContent()` (её вызывает `EndBufferContentMan()`
из эпилога), то есть **в конце запроса**, когда все `SetTitle()` /
`SetPageProperty()` уже выполнены. Отсюда:

- `SetTitle()` в теле страницы, после `require header.php`, попадает в `<title>`
  и в `H1` — потому что `<title>` собирается на сбросе буфера.
- `SetPageProperty('title', ...)` **сильнее** `SetTitle()`: `GetTitle()` сначала
  берёт свойство страницы и только потом `sDocTitle`.
- `SetPageProperty('description', ...)` тоже успевает — `ShowMeta()` отложен.
- `AddChainItem()` работает по той же причине: крошки печатает шаблон в
  `footer.php`, а данные копятся в `$APPLICATION`.

**Ломается это одним способом: если `BX_BUFFER_USED` не определена** — то есть
скрипт не проходил через `prolog_before.php` (`CMain::PrologActions()`). Тогда
`AddBufferContent()` печатает сразу, и всё, что поставлено после шапки, уже не
влияет. Опция «Буферизация вывода» (`buffer_content`) существовала в main
12–19; в актуальном ядре её нет, и выключить буфер настройкой нельзя. Практический
вывод: «на одном сайте работает, на другом нет» — это про самописные точки
входа без пролога, а не про настройку.


Второе следствие того же механизма: **`header()` работает почти везде**, даже
после вывода шапки, потому что байты физически уходят в SAPI только на сбросе
буфера. Проверять всё равно надо `headers_sent()` — объясняет это буфер, а не
«Bitrix отправляет заголовки позже».

### 9. Эпилог: порядок и где реально можно вмешаться

`bitrix/footer.php` → `epilog.php` → `epilog_before.php` + `epilog_after.php`
(в актуальном ядре; в `footer.php` дополнительно стоит проверка
`B_PROLOG_INCLUDED === true`).

`epilog_before.php` короткий: `BX_STATE = 'EB'`, иконка «редактировать страницу»
для админа, `Asset::startTarget('TEMPLATE')` и `include SITE_TEMPLATE_PATH .
'/footer.php'`.

`epilog_after.php` (сокращённо, но по исходнику):

```php
$GLOBALS["BX_STATE"] = "EA";
foreach (GetModuleEvents("main", "OnEpilog", true) as $arEvent)
    ExecuteModuleEventEx($arEvent);              // буфер ещё открыт

$buffer = $APPLICATION->EndBufferContentMan();   // сборка отложенных колбэков
                                                 // → OnBeforeEndBufferContent
                                                 // → OnEndBufferContent(&$content)
                                                 // → композит
CMain::FinalActions($buffer);                    // ← это конец запроса
```

`CMain::FinalActions($output)` (исходник):

```php
public static function FinalActions($output = "")
{
    if ($output === "")
        $output = $APPLICATION->EndBufferContentMan();

    $response = Main\Context::getCurrent()->getResponse();
    $response->setContent($output);

    Main\Application::getInstance()->end();      // НЕ возвращает управление
}
```

`Application::end()` → `$response->send()` → `Application::terminate()`:

| Шаг | Что происходит | Влияет на ответ |
|---|---|---|
| `OnEpilog` | событие, буфер открыт | да: `echo` попадёт в тело |
| `EndBufferContentMan()` | `OnBeforeEndBufferContent`, колбэки `ShowHead`/`ShowTitle`, `OnEndBufferContent(&$content)` | **да, единственное правильное место для правки HTML** |
| `$response->send()` | заголовки + тело уходят клиенту | — |
| `CMain::RunFinalActionsInternal()` | `OnAfterEpilog`, отправка счётчиков livefeed | **нет**: ответ уже отдан |
| `session_write_close()` | сессия сохраняется и блокировка снимается | — |
| `runBackgroundJobs()` | background jobs, в т.ч. `check_events` / `check_agents` | — |
| `ManagedCache::finalize()`, `$pool->disconnect()` | кеш и БД | — |
| `exit($status)` | **приложение завершается** | — |

Четыре вывода:

1. **`OnEndBufferContent` получает `array(&$content)`** — по ссылке. Это
   штатная точка изменения готового HTML: сжатие, вставка счётчиков, замена
   ссылок. Правка в `OnEpilog` работает только потому, что буфер ещё не сброшен,
   и легко ломается при `RestartBuffer()`.
2. **`OnAfterEpilog` — не место для правки контента.** Ответ уже отдан; здесь
   только побочные эффекты: логи, метрики, очистка, постановка задач.
3. **`FinalActions()` завершает запрос сам** — внутри `Application::end()` →
   `terminate()` стоит `exit($status)`. Ставить после него `exit` бессмысленно,
   а считать, что «FinalActions не останавливает скрипт» — ошибка (это было
   верно для очень старых сборок, где эпилог заканчивался `die()` в другом
   месте).
4. **Почта больше не уходит «в эпилоге».** `CEvent::CheckEvents()` из
   `CMain::EpilogActions()` убран (**было в 12–19**), сам `EpilogActions()` в
   актуальном ядре пуст и помечен `@deprecated`. Отправка событий — background
   job `check_events`, который выполняется в `terminate()` **после** отправки
   ответа (или по крону, если настроен `cron`). Отсюда: `die()` не «теряет»
   письма навсегда, но и не запускает job — они уйдут при следующем запросе
   или агентом.


`RestartBuffer()` — отдельный механизм: чистит все буферы, вызывает
`OnBeforeRestartBuffer`, поднимает новый буфер с `EndBufferContent`. Нужен,
когда внутри уже начатого вывода надо отдать чистый ответ (JSON, файл,
редирект). После него всё, что было напечатано (включая шапку), исчезает.

### 10. 404, редиректы, ошибки

```php
// страница 404: до подключения шапки
CHTTP::SetStatus('404 Not Found');        // заголовок + запоминание статуса
define('ERROR_404', 'Y');                 // историческая пометка, ядром не читается
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->SetTitle('Страница не найдена');
$APPLICATION->SetPageProperty('robots', 'noindex, nofollow');
```

- `CHTTP::SetStatus('404 Not Found')` — это `header($_SERVER['SERVER_PROTOCOL'] .
  ' 404 Not Found')` плюс запоминание статуса (`CHTTP::GetLastStatus()`).
  Из-за буферизации вызывать его можно и после `header.php`, но правильнее до:
  страница не должна успеть «подумать, что она нормальная».
- `ERROR_404` статус **не** ставит. В актуальном ядре его никто и не проверяет:
  старый HTML-кеш удалён, а композитный сайт решает сам — `Page::isCacheable()`
  смотрит на `CHTTP::GetLastStatus()` и кеширует только `200`/пустой статус.
  Константу можно оставить (её читают отдельные модули и шаблоны), но
  полагаться на неё как на защиту от кеширования нельзя. Проверка:
  `grep -rn "ERROR_404" bitrix/modules/main/lib`.
- `LocalRedirect($url, $skip_security_check = false, $status = "302 Found")`
  ставит `Location`, вызывает `OnBeforeLocalRedirect` (композит успевает
  пометить редирект) и **завершает запрос** — код после него не выполняется.
  **Никогда** не передавай туда пользовательский URL без проверки: `LocalRedirect($_GET['backurl'])` — это open redirect. Разрешай
  только относительные пути и отбрасывай `//`, `\`, `%0d%0a`.
- Ошибка до вывода: исключение в прологе или в `init.php` даёт белую страницу
  или 500 от веб-сервера. Смотри `bitrix/modules/error.log` и лог PHP; свой
  обработчик ставится в `init.php` (`set_exception_handler`).
- **BOM и пробелы до `<?php`** в подключаемых файлах — вывод раньше заголовков:
  ломаются редиректы, JSON и `Content-Type`. Самая частая причина «заголовки
  уже отправлены» в самописных точках входа.

### 11. Служебная точка входа production-уровня

```php
<?php
// /local/ajax/deal-status.php
declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', true);
define('BX_SKIP_SESSION_EXPAND', true);   // не тянуть js-расширитель сессии

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;

global $APPLICATION;

// Пролог мог оставить вывод (BOM, warning): сбрасываем буфер до заголовков.
$APPLICATION->RestartBuffer();

header('Content-Type: application/json; charset=UTF-8');

$responded = false;

/*
 * Единственная точка выхода.
 * FinalActions() собирает буфер (EndBufferContentMan), отдаёт ответ и
 * завершает приложение: внутри Application::end() -> terminate() стоит
 * exit(). Ничего после неё не выполняется — поэтому второй ответ физически
 * невозможен, а отдельный exit не нужен.
 */
$respond = static function (array $payload, string $status = '200 OK') use ($APPLICATION, &$responded): void {
    if ($responded) {
        return;
    }
    $responded = true;

    CHTTP::SetStatus($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $APPLICATION->FinalActions();   // ответ + эпилог + exit внутри ядра
};

global $USER;

if (!$USER->IsAuthorized()) {
    $respond(['error' => 'unauthorized'], '401 Unauthorized');
}

if (!check_bitrix_sessid()) {       // CSRF: sessid в POST/GET
    $respond(['error' => 'invalid sessid'], '403 Forbidden');
}

$request = Application::getInstance()->getContext()->getRequest();
$dealId = (int)$request->getPost('deal_id');

if ($dealId <= 0) {
    $respond(['error' => 'bad deal_id'], '400 Bad Request');
}

try {
    // ... бизнес-логика ...
    $respond(['deal' => $dealId, 'status' => 'ok']);
} catch (\Throwable $e) {
    AddMessage2Log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'deal-status');
    $respond(['error' => 'internal'], '500 Internal Server Error');
}
```

Что здесь принципиально:

- **Порядок:** сначала авторизация, потом CSRF, потом валидация, потом работа.
  Обратный порядок — возможность для неавторизованного перебора.
- **`FinalActions()` вместо `die()`.** `FinalActions()` собирает буфер,
  отправляет ответ и вызывает `Application::end()` → `terminate()`, где стоит
  `exit()`: событий `OnEpilog`/`OnAfterEpilog`, `session_write_close()`,
  background jobs (`check_events`, `check_agents`), `ManagedCache::finalize()`
  и закрытие соединений не пропустит никто. `die()` же обрывает обработку на
  месте: PHP всё равно сбросит ob-буферы (в `PrologActions` зарегистрирован
  shutdown-хендлер), поэтому клиент ответ получит, — но эпилог Bitrix не
  выполнится, и побочные эффекты запроса отложатся до следующего запроса.
- **Никаких деталей исключения в ответ.** Текст ошибки — в лог, клиенту — код.
- **`header()` до любого вывода.** Если файл начинается с BOM или пустой
  строки, JSON поедет с HTML-заголовком.

### 12. Стоимость, сессии, наблюдаемость

- **Файловые сессии блокируют параллельные запросы одного пользователя.**
  Пока запрос не завершён, сессионный файл заблокирован; второй AJAX-запрос
  того же пользователя ждёт. Для «долгих» служебных точек — закрытие сессии
  сразу после чтения нужных данных: штатный D7-способ
  `Application::getInstance()->getSession()->save()`; для файловых сессий он
  эквивалентен `session_write_close()`. При сессиях в БД/Redis и в режимах
  `readonly`/`virtual` поведение зависит от настроек — проверяйте под свою
  конфигурацию.
- **`init.php` — на каждом запросе.** Один `Loader::includeModule()` в
  `init.php` стоит дорого на всей публичке; подключай модуль там, где он нужен.
- **`BX_STATE`** (`$GLOBALS['BX_STATE']`) показывает фазу запроса:
  `PB` — prolog before, `PA` — prolog after, `WA` — work area (тело страницы),
  `EB`/`EA` — epilog before/after. Состояния `EV` в актуальном ядре **нет**:
  `OnAfterEpilog` выполняется, когда `BX_STATE` всё ещё `EA`, — это видно в
  trace-инструменте. Состояние выставляется в prolog/epilog-файлах, а не в
  `CMain`, и из входящего запроса его вычищают (`unset($_GET['BX_STATE'])`).
- **Метки времени** `START_EXEC_PROLOG_BEFORE_1`, `START_EXEC_PROLOG_BEFORE_2`,
  `START_EXEC_PROLOG_AFTER_1`, `START_EXEC_PROLOG_AFTER_2`,
  `START_EXEC_EPILOG_BEFORE_1`, `START_EXEC_EPILOG_AFTER_1`, `START_EXEC_TIME` —
  встроенный профайлер этапов; из них считается время генерации страницы.
- **Композит и кеш nginx** отдают ответ до пролога; тогда ни `init.php`, ни
  твои события не выполняются. Любая логика «на каждый запрос» (счётчики,
  ограничение частоты) обязана это учитывать.
- **Сессию можно закрыть раньше**, и это единственный способ разблокировать
  параллельные AJAX: `Application::getInstance()->getSession()->save()`.
  В актуальном ядре то же самое делает `terminate()` в конце запроса — но
  «в конце» и есть проблема для долгой операции, поэтому вызывай явно.

### 13. Чек-лист «где я нахожусь»

- Вижу HTML без шапки → подключён только `prolog_before.php`.
- Вижу `<!DOCTYPE>` без `<title>` → шаблон не вызвал `ShowHead()`/`ShowTitle()`.
- `SetTitle()` не работает → страница не шла через `prolog_before.php` в этой
  сессии/ветке, либо `ShowTitle()` вызван до `SetTitle()` и был тут же собран.
- `OnAfterEpilog` не меняет HTML → так и должно быть, ответ уже отдан.
- Редирект «headers already sent» → BOM/пробел до `<?php` или вывод до `header()`.
- AJAX-запросы пользователя идут по очереди → файловая сессия держит lock.
- Тело ответа есть, а эпилог «не сработал» → где-то `die()`/`exit` вместо
  `FinalActions()`.

## Вопросы для собеседования

Пометка **[спрашивают]** — вопрос реально встречается на вакансиях Bitrix24 Box
Developer; остальные — типовые для PHP/Bitrix-собеседований и проверяют то же
понимание.

### Junior

1. **Чем `header.php` отличается от `local/templates/<id>/header.php`?**
   Первый — точка входа пролога в `/bitrix/`, её подключает разработчик в начале
   страницы; второй — HTML-шапка шаблона, её подключает ядро из
   `prolog_after.php`. Ошибка «положить логику в шаблон» растёт отсюда.

2. **Зачем `prolog_before.php`, если есть `header.php`?** `header.php`
   подключает ещё и шаблон сайта: рендер шапки, меню, CSS/JS. Для JSON-ответа,
   вебхука или cron это лишняя работа и лишний HTML в теле ответа.
   **[спрашивают]**

3. **Почему `SetTitle()` работает, если вызван после `header.php`?**
   Потому что вывод буферизован (`CMain::PrologActions()` включает буфер
   безусловно), а `ShowTitle()` не печатает сразу, а регистрирует колбэк через
   `AddBufferContent()`. Заголовок подставляется при сборке буфера в эпилоге.
   Ломается это, только если `BX_BUFFER_USED` не определена — то есть скрипт не
   проходил через пролог.

### Middle

4. **Где можно изменить готовый HTML перед отправкой и где уже нельзя?**
   Только в `OnEndBufferContent` — единственное место, где содержимое ещё
   доступно и правится по ссылке. В `OnAfterEpilog` ответ уже отдан: события
   выполняются, но `echo` оттуда до клиента не дойдёт. **[спрашивают]**

5. **Что произойдёт, если в AJAX-обработчике вызвать `die()` вместо
   `FinalActions()`?** Ответ уйдёт (ob-буферы сбрасываются shutdown-хендлером
   `PrologActions`), но эпилог Bitrix не выполнится: ни `OnEpilog`, ни
   `OnAfterEpilog`, ни `session_write_close()`, ни background jobs
   (`check_events`, `check_agents`), ни `ManagedCache::finalize()`. Побочные
   эффекты отложатся до следующего запроса или крона. **[спрашивают]**

6. **Почему `?id=12abc` опасно пускать через `(int)`?** Приведение молча даёт
   `12` и превращает мусор в валидный ID: запрос уходит к реальной записи.
   Валидировать надо строку (`ctype_digit`), а не результат приведения.

7. **Какой код ответа на чужую сделку и почему не `403`?** `404`. `403`
   подтверждает существование объекта и превращает эндпоинт в оракул для
   перебора ID. **[спрашивают]**

8. **Зачем `session_write_close()` сразу после чтения пользователя?**
   Файловая сессия держит блокировку до конца запроса; без закрытия
   параллельные AJAX одного пользователя выполняются последовательно.

### Senior

9. **Спроектируйте защиту от двойной отправки формы. Почему проверка «есть ли
   уже такая запись» не работает?** Проверка и запись не атомарны: между ними
   второй запрос успевает прочитать «нет» и тоже записать. Нужен захват на
   уровне хранилища — `INSERT` в таблицу с `UNIQUE(request_id)`, где конфликт
   уникального индекса и есть признак дубля; либо Redis `SET NX` с TTL.
   Кеш `initCache/endDataCache` для этого не годится — он не атомарен.
   **[спрашивают]**

10. **Пользователь нажал «назад» после выхода из аккаунта и увидел данные
    сделки. Что вы сделали не так и как исправить?** Не выставлен
    `Cache-Control: no-store` на ответе с персональными данными. Плюс проверить
    `fastcgi_cache`/CDN: ключ без cookie отдаёт чужой ответ. Плюс `Vary: Cookie`
    при кешировании на прокси.

11. **`CCrmDeal::Update()` вернул `false`. Какой код ответа и почему не
    `500`?** `422` (или `409`, если причина — состояние ресурса). `false`
    означает отказ бизнес-правил или прав, повтор не поможет; `500` заставляет
    клиента ретраить и шумит в мониторинге как авария.

12. **Что происходит с запросом при включённом композите для анонимного
    пользователя?** HTML отдаётся из кеша до выполнения `init.php` и событий;
    логика «на каждый запрос» (счётчики, лимиты, метрики) не выполняется.
    Учитывать это или выносить такую логику за кешируемый слой. **[спрашивают]**

13. **Как найти источник «headers already sent», если файл неизвестен?**
    `headers_sent($file, $line)` до первого `header()` даёт файл и строку
    первого вывода. Частые причины: BOM, пустая строка до `<?php`, `?>` с
    переводом строки в конце include, `display_errors = On` с notice.

14. **Где в актуальном ядре отправляется почта и что будет с ней при `die()`?**
    Не в эпилоге: `CEvent::CheckEvents()` из `CMain::EpilogActions()` убран.
    Отправка — background job `check_events`, который выполняет
    `Application::terminate()` после `$response->send()`. `die()` не запускает
    `terminate()`, поэтому job не выполнится; события останутся в `b_event` до
    следующего запроса или крона. Ответ при этом уйдёт — ob-буферы сбрасывает
    shutdown-хендлер.

15. **Чем `CMain::FinalActions()` отличается от `exit` и почему `exit` после неё
    не нужен?** `FinalActions()` собирает буфер через `EndBufferContentMan()`,
    кладёт результат в `Response`, вызывает `Application::end()` → `terminate()`,
    а там стоит `exit($status)`. То есть она и отдаёт ответ, и завершает процесс,
    попутно выполнив `OnAfterEpilog`, `session_write_close()`, background jobs,
    `ManagedCache::finalize()` и закрытие соединений. **[спрашивают]**

16. **Почему на 404 композитный сайт не отдаёт закешированную страницу?**
    `Page::isCacheable()` проверяет `CHTTP::GetLastStatus()` и разрешает кеш
    только для `200`/пустого статуса. Значит статус должен быть выставлен в
    запросе-генераторе; константа `ERROR_404` тут ни при чём — её в актуальном
    ядре не читают.

17. **Как доказать, что `SetTitle()` после `header.php` — не магия, а буфер?**
    Вызвать `SetTitle()`, затем `RestartBuffer()` и вручную `ShowTitle()`:
    заголовок окажется пустым, потому что `RestartBuffer()` очищает
    `buffer_content_type` вместе с колбэком `GetTitle`.

## Edge cases

### 1. Входные данные: пустые, массивы, «почти числа»

```php
$rawId  = $_GET['id'] ?? '';
$dealId = (is_string($rawId) && $rawId !== '' && ctype_digit($rawId)) ? (int)$rawId : 0;
```

| Вход | `(int)` | `ctype_digit()` | Что должно быть |
|---|---|---|---|
| `id=12` | 12 | true | 12 |
| `id=` | 0 | false | 404 |
| `id=0` | 0 | true | 404 (нет такого объекта) |
| `id=abc` | 0 | false | 404 |
| `id=12abc` | 12 | false | 404 — `(int)` молча превращает мусор в валидный ID |
| `id[]=12` | warning, `1` | **TypeError** в PHP 8 | 404 — проверять `is_string` до приведения |
| `id= 12` | 12 | false | 404 или `trim()` осознанно |
| `id=1e3` | 1000 | false | 404 |
| `id=-1` | -1 | false | 404 |
| `id=99999999999999999999` | `PHP_INT_MAX` | true | 404 — `ctype_digit` пропускает, `(int)` насыщает; нужен лимит длины |

Правило: сначала тип, потом формат, потом диапазон. `(int)` — не валидация,
а приведение; `ctype_digit` — не защита от переполнения.

### 2. Вывод раньше заголовков

Источники: BOM в начале подключаемого файла, пустая строка до `<?php`, `?>`
плюс перевод строки в конце include, `echo`/`var_dump` в `init.php`, notice при
`display_errors = On`.

- При включённой буферизации вывод попадает в буфер, и `header()` продолжает
  работать — дефект маскируется и всплывает, как только буфер сбросили
  (`RestartBuffer()`, `ob_flush()`, вывод больше буфера при отключённом
  сжатии).
- В JSON-эндпоинте BOM перед `{` ломает `json_decode` на клиенте, хотя сервер
  отвечает `200`.
- Диагностика: `headers_sent($file, $line)` — покажет файл и строку первого
  вывода.

### 3. `die()` вместо `FinalActions()`

`die()`/`exit()` не запускают эпилог Битрикса (это не shutdown-функция), поэтому
не выполняются:

- `OnEpilog`, `OnAfterEpilog`;
- `session_write_close()` — сессия сохранится PHP-ом, но блокировка снимется
  только при завершении процесса, а не сразу после логики;
- background jobs (`check_events`, `check_agents`) — почтовые события и агенты
  не запускаются в этом запросе, уходят следующим запросом или по крону;
- `ManagedCache::finalize()` — счётчики managed-кеша не сбрасываются на диск;
- закрытие соединений с БД и «master only» переключения.

Сам ответ при этом уйдёт: `CMain::PrologActions()` регистрирует
`register_shutdown_function()`, который сбрасывает ob-буферы. То есть симптом
не «пустая страница», а «всё работает, но побочные эффекты с задержкой» — именно
поэтому дефект живёт в проде годами.

### 4. Ошибки в прологе и `init.php`

Исключение в `init.php` или в прологе даёт белую страницу или `500` от
веб-сервера: шаблон ещё не подключён, страница ошибки сайта не участвует.

- Смотреть `bitrix/modules/error.log` и лог PHP (`error_log`).
- Свой обработчик — в `init.php`: `set_exception_handler()` для исключений и
  `register_shutdown_function()` + `error_get_last()` для fatal (`E_ERROR`,
  `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`).
- В служебной точке входа имеет смысл отдавать JSON даже при fatal: иначе клиент
  получит HTML-страницу сервера вместо ожидаемого формата.

### 5. Повторные запросы и гонки

- **Двойная отправка** (F5, двойной клик) — два одинаковых POST. Лечится ключом
  идемпотентности, не «проверкой перед записью»: проверка и запись не атомарны.
- **Retry после таймаута** — запрос мог успеть выполниться; повтор без ключа
  делает действие дважды.
- **Lost update**: два пользователя меняют стадию — побеждает последний. Если
  это важно, передавайте ожидаемую стадию (`expected_stage`) и отвечайте `409`.
- **Кеш как хранилище идемпотентности не атомарен**: два параллельных запроса
  могут оба не найти ключ. Настоящая гарантия — `UNIQUE(request_id)` в таблице.
- **Файловая сессия** держит блокировку до конца запроса: параллельные AJAX
  одного пользователя выполняются по очереди, а не параллельно.

### 6. `RestartBuffer()` на уже начатом выводе

- Чистит все буферы, вызывает `OnBeforeRestartBuffer`, поднимает новый буфер.
- Всё напечатанное до него — включая шапку шаблона — исчезает. Типичный дефект:
  компонент печатает HTML, потом вызывает `RestartBuffer()`, и клиент получает
  пустой ответ без ошибки.
- `ShowHead()`/`ShowTitle()`, вызванные до `RestartBuffer()`, уже потеряны: их
  отложенные колбэки были в старом буфере.

### 7. 404 и редиректы при кеше и композите

- Композитный сайт не кеширует ответы с кодом не `200`: `Page::isCacheable()`
  проверяет `CHTTP::GetLastStatus()`. Поэтому 404 не «оседает» в кеше при
  условии, что статус выставлен (`CHTTP::SetStatus`), а не только
  `ERROR_404` — эту константу в актуальном ядре никто не читает.
- Композитный сайт не кеширует авторизованных; анонимным может отдать HTML до
  пролога — тогда `init.php` и события не выполняются.
- `fastcgi_cache` в nginx без cookie в ключе отдаёт чужой `302`/`404` другому
  пользователю. Ключ должен включать cookie сессии, либо кеш только для
  анонимных.
- `LocalRedirect()` после вывода: при буферизации работает (заголовки ещё не
  ушли), при уже сброшенном буфере — «headers already sent» и отсутствие
  редиректа.
- `backurl`, указывающий на ту же страницу, даёт цикл редиректов.

### 8. Частичные отказы инфраструктуры

- **Отправка почты** — background job `check_events`, выполняется в
  `terminate()` после отдачи ответа и в режиме master-соединения
  (`useMasterOnly(true)`). Медленный SMTP держит php-fpm-воркер уже после того,
  как пользователь получил страницу: смотреть `request_terminate_timeout`.
- **Хранилище сессий недоступно** (Redis/БД) — падает пролог, то есть весь сайт,
  а не отдельная страница.
- **Кеш недоступен** — обычно деградация в БД, но проверить поведение
  `\Bitrix\Main\Data\Cache` в своей конфигурации.
- **`CMain::ForkActions()`** в актуальном ядре `@deprecated` и просто вызывает
  `Application::addBackgroundJob()`; фоновая работа выполняется в
  `terminate()` после отправки ответа. При `die()` она не выполнится вовсе.

### 9. Что не выполняется при отдаче из кеша

При композите (`Responder::respond()` в `prolog.php`) или внешнем кеше
(nginx `fastcgi_cache`, CDN) не выполняются `init.php`, события и обработчики
буфера. Любая логика «на каждый запрос» — счётчики просмотров, ограничение
частоты, сбор метрик — должна либо жить вне кешируемого слоя, либо учитывать,
что часть запросов её не вызовет.

### 10. Симптом → причина → где смотреть

| Симптом | Причина | Где смотреть |
|---|---|---|
| «headers already sent» | BOM/пробел/`echo` до `header()` | `headers_sent($f, $l)` |
| JSON не парсится на клиенте | шаблон в ответе или BOM | `curl -i`, первые байты |
| `SetTitle`/`SetPageProperty` не работают | страница не шла через `prolog_before.php`, `BX_BUFFER_USED` не определена | точка входа, `headers_sent()` |
| Письма задерживаются после действия | `die()` до эпилога: background job `check_events` не запущен | код точки выхода |
| 404 отдаётся как 200 | нет `CHTTP::SetStatus` | `curl -I` |
| 404 закешировался | кеш на прокси/CDN без учёта статуса | конфиг nginx/CDN, ключ кеша |
| AJAX пользователя «тормозят» | блокировка файловой сессии | `getSession()->save()` |
| Тело ответа есть, а фоновые задачи «не идут» | `exit`/`die` вместо `FinalActions()` | код точки выхода |
| После `RestartBuffer` пустой ответ | буферы очищены вместе с выводом | порядок вызовов |

## Практические задачи

Решения всех трёх задач — в `examples/module-1-5/practice-1-trace/`,
`practice-2-deal-stage/`, `practice-3-deal-page/`: рабочий код, разбор
альтернатив, покрытые и непокрытые edge cases.

Code Review этих решений (этап 5) — `examples/module-1-5/review/`: снимки кода
до и после (`before/`, `after/`), точные правки (`DIFF.md`) и разбор каждого
дефекта по схеме «ошибка → причина → последствие → исправление»
(`CODE-REVIEW.md`). Дефекты там реальные, не учебные: кеширование персональных
данных, отсутствие rate limit, ложные коды ответов, неатомарная
идемпотентность. `review/round2/` и `review/round3/` — последующие проходы;
в round3 записано, что именно в 1.5 было неверно по сравнению с актуальным
ядром и почему идемпотентность в задаче 2 переведена на `UNIQUE`-таблицу.

Все три задачи — для **коробки** с модулем `crm`. Если в твоём демо модуля нет,
скажи: заменим сущность на инфоблок, механика проверок та же.

### Задача 1. Трассировка запроса и цифры (30–40 мин)

Цель — увидеть фазы своими глазами и получить числа, а не поверить на слово.

1. Подключи инструмент из `examples/module-1-5/request-trace/` (или напиши свой)
   и сними на `/trace-page.php?bx_trace=Y`: время до `OnProlog`, до `OnEpilog`,
   до `OnEndBufferContent`; долю пролога в общем времени; память на каждой фазе.
2. Докажи, что `OnAfterEpilog` не может изменить HTML: добавь в него `echo 'X'`
   и покажи, что в ответе этого `X` нет.
3. Покажи, что отложенные свойства живут в буфере: после `header.php` сделай
   `$APPLICATION->SetTitle('A')`, затем `$APPLICATION->RestartBuffer()` и вручную
   `ShowTitle()`. Заголовок окажется **пустым**: `RestartBuffer()` чистит
   `buffer_content_type`, вместе с ним пропадает колбэк `GetTitle`.

   ```php
   require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
   $APPLICATION->SetTitle('Заголовок до RestartBuffer');
   $APPLICATION->RestartBuffer();          // старый буфер и колбэки уничтожены
   ?><!DOCTYPE html><html><head>
   <title><?php $APPLICATION->ShowTitle(); ?></title>
   </head><body><p>ok</p></body></html>
   ```

   ```bash
   curl -s 'http://box.local/restart-probe.php' | grep -o '<title>.*</title>'
   # <title></title>
   ```

   Про «выключить буферизацию настройкой» (`buffer_content = N`) забудь: опция
   была в main 12–19, в актуальном ядре её нет, и `CMain::PrologActions()`
   включает буфер безусловно. Проверка:
   `grep -n '"buffer_content"' bitrix/modules/main/classes/general/main.php` —
   пусто.

**Сдать:** таблицу фаз с числами, строку из п. 2 и вывод `curl` из п. 3,
плюс `grep` по `buffer_content`, доказывающий, что опции нет.

### Задача 2. Служебный endpoint с записью (60–90 мин)

`/local/ajax/deal-stage.php` — смена стадии сделки.

Понадобится таблица-журнал ключей: DDL, ORM-сущность и `init.php` — в
`examples/module-1-5/practice-2-deal-stage/` (`install.sql`,
`local/php_interface/classes/DealStage/*`).

Требования:

- только `POST`, иначе `405` + `Allow: POST`;
- неавторизован — `401`, нет/неверный `sessid` — `403`;
- `deal_id` и `stage_id` валидируются: стадия должна существовать в справочнике
  стадий сделок, ID — положительное целое;
- чужая или несуществующая сделка — `404` (не `403`);
- запись через D7-ORM или `CCrmDeal::Update()` [проверить под свою версию],
  права на изменение проверяются явно;
- идемпотентность: повторный запрос с тем же `request_id` не выполняет действие
  второй раз и возвращает тот же ответ. Реализация — таблица-журнал с
  `UNIQUE(USER_ID, REQUEST_ID)`, а не кеш: кеш не атомарен;
- ошибка — `500`, детали только в лог (`AddMessage2Log` или логгер D7);
- выход только через `FinalActions()`: она сама завершает приложение
  (`Application::end()` → `terminate()` → `exit`), поэтому `exit`/`die` рядом
  не нужны, а голый `die()` без неё обрывает эпилог этого запроса;
- закрытие сессии после чтения пользователя: штатный D7-способ
  `Application::getInstance()->getSession()->save()`; для файловых сессий он
  эквивалентен `session_write_close()` (при сессиях в БД/Redis и в режимах
  `readonly`/`virtual` проверяйте под свою конфигурацию).

**Сдать:** файл + вывод `curl -i` для сценариев `405`, `401`, `403`, `404`, `200`
и повторного `200` с тем же `request_id`.

### Задача 3. Страница: свойства, 404, безопасный редирект (45–60 мин)

`/deal.php` — публичная страница сделки через шаблон сайта.

- `?id=` не число или объект не найден → `404`: `CHTTP::SetStatus('404 Not Found')`
  **до** подключения шапки (композит не кеширует не-200), шаблон при этом
  отдаётся, заголовок «Сделка не найдена», `robots` = `noindex, nofollow`;
  `define('ERROR_404', 'Y')` можно добавить для совместимости с модулями, но
  защитой от кеширования она не является;
- для найденной сделки — `SetPageProperty('description', ...)`, `AddChainItem`,
  заголовок с номером сделки;
- `?backurl=` — редирект только на относительный путь из белого списка
  префиксов; `//evil.com`, `\evil.com`, `%0d%0a` и внешние URL отбрасываются;
- `curl -I` должен показывать правильные коды, `curl` — правильные `<title>` и
  `<meta name="robots">`.

**Сдать:** файл + `curl -I` для `200` и `404` + попытку редиректа на
`//evil.com` с показом, что уводит не туда.

### Контрольное задание

Фрагмент кода с пятью дефектами — `examples/module-1-5/control-task/broken.php`,
эталонное решение и разбор — там же, `fixed.php` и `README.md`. Задание:
назвать все пять, объяснить последствие каждого и условия, при которых дефект
проявляется.

### Критерии приёмки

- Все три задачи работают на твоей коробке, вывод команд приложен.
- В коде нет `die()` в точках выхода, нет `NOT_CHECK_PERMISSIONS` в публичных
  ветках, нет вывода до `header()`.
- Ты объясняешь, почему в задаче 3 выбран `404`, а не `403`, и почему `sessid`
  проверяется после авторизации, а не до.

