# Round 3 — сверка с исходниками ядра

Повод: материалы 1.5 были написаны по снимку дистрибутива `SM_VERSION` 12.5.3
(BУС 2013). С тех пор механика пролога/эпилога изменилась, и часть утверждений
стала неверной — не «неточной», а такой, что по ней нельзя работать.

Сверено по исходникам Bitrix24-коробки (2024):

```
bitrix/modules/main/include/prolog.php
bitrix/modules/main/include/prolog_before.php
bitrix/modules/main/include/prolog_after.php
bitrix/modules/main/include/epilog_before.php
bitrix/modules/main/include/epilog_after.php
bitrix/modules/main/classes/general/main.php
bitrix/modules/main/lib/application.php
bitrix/modules/main/lib/composite/engine.php
bitrix/modules/main/lib/composite/page.php
bitrix/modules/main/lib/composite/responder.php
bitrix/modules/main/lib/db/mysqlcommonconnection.php
```

## Что было неверно и что теперь

| # | Было | Стало (по исходнику) | Где правилось |
|---|---|---|---|
| 1 | `prolog.php` — развилка по `/bitrix/d7.php` с `HttpApplication::turnOnCompatibleMode()` / `initialize()` / `start()` | `prolog.php` = `bx_root.php` + `Composite\Responder::respond()` (если есть `html_pages/.enabled`) + `prolog_before.php` + `prolog_after.php`. Файла `d7.php` в ядре нет; инициализация D7 — в `modules/main/include.php` | `docs/module-1-5.md` §3 |
| 2 | Буферизация включается опцией `main` → `buffer_content` (дефолт `Y`) | `CMain::PrologActions()` включает `ob_start([$APPLICATION,'EndBufferContent'])` **безусловно** и регистрирует shutdown-хендлер сброса буферов. Опции `buffer_content` в актуальном ядре нет | §3, §8, §13, задача 1 |
| 3 | `FinalActions()` не останавливает скрипт, нужен `exit` после неё | `CMain::FinalActions()` → `Application::end()` → `terminate()` → `exit($status)`. `exit` после неё — мёртвый код | §9, §11, задача 2, `ajax-endpoint`, `control-task/fixed.php`, `practice-2` |
| 4 | Эпилог: `CMain::EpilogActions()` шлёт почту (`CEvent::CheckEvents()`) и чистит файлы | `EpilogActions()` пуст и `@deprecated`; почта — background job `check_events` в `terminate()`, после отправки ответа | §9, edge case 3, интервью Q5 |
| 5 | `BX_STATE` имеет состояние `EV` для событий | В актуальном ядре состояний только `PB`, `PA`, `WA`, `EB`, `EA`. `OnEpilog` и `OnAfterEpilog` выполняются в `EA` | §12, `request-trace`, `practice-1` |
| 6 | `ERROR_404` запрещает запись HTML-кеша | Старого HTML-кеша нет; композит решает по `CHTTP::GetLastStatus()` (`Page::isCacheable()`), кеширует только `200`/пусто | §10, §edge 7, задача 3, `practice-3` |
| 7 | `NO_KEEP_STATISTIC` / `STOP_STATISTICS` экономят работу на каждом AJAX | Модуля `statistic` в актуальных коробках нет; константы безвредны и ничего не экономят. Реально работают `BX_SKIP_SESSION_EXPAND` и `PUBLIC_AJAX_MODE` | §5 |
| 8 | `die()` «теряет» письма, `CEvent::CheckEvents()` не выполняется | Ответ отдаётся (буферы сбрасывает shutdown-хендлер), но эпилог не выполняется: нет `OnAfterEpilog`, `session_write_close()`, background jobs, `ManagedCache::finalize()` | edge 3, интервью Q5, `antipattern/README.md`, `control-task/README.md` |
| 9 | `ShowHead()` целиком отложен | `ShowHead()` сам печатает `<meta http-equiv="Content-Type">`, отложены `ShowMeta`/`ShowCSS`/`ShowHeadStrings`/`ShowHeadScripts` | §8 |

## Что изменено в практике

**`practice-2-deal-stage/` — идемпотентность переделана с кеша на таблицу.**

Было: `Data\Cache` с ключом `user + request_id`, в тексте честно написано «кеш не
атомарен, в проде — UNIQUE». Для эталонного решения этого мало: требование
задачи («повтор не выполняет действие второй раз») кеш не выполняет.

Стало:

```
install.sql                                          таблица mycompany_deal_stage_request
local/php_interface/classes/DealStage/JournalTable.php   ORM-сущность
local/php_interface/classes/DealStage/Journal.php        claim / complete / release
local/php_interface/init.php                             регистрация namespace
local/ajax/deal-stage.php                                эндпоинт
```

- захват ключа — `INSERT` в таблицу с `UNIQUE(USER_ID, REQUEST_ID)`;
  проигравший получает `DuplicateEntryException` (MySQL 1062 маппится в него в
  `MysqlCommonConnection`);
- ответ сохраняется **до** отправки, повтор получает его байт в байт;
- «зависший» захват (`HTTP_CODE = 0` старше 120 с) переиспользуется, иначе ключ
  блокировал бы действие навсегда;
- ошибки до записи (`404`, `409`, `422`, `500`) освобождают ключ.

**`practice-1-trace/` — эксперимент 2 заменён.** «Выключить буферизацию
настройкой» больше невозможно; вместо этого — `SetTitle()` + `RestartBuffer()`:
видно, что отложенные колбэки живут в `buffer_content_type` и умирают вместе с
буфером.

**Все точки выхода** приведены к `FinalActions()` без `exit`.

## Что осталось как исторический снимок

`review/before/`, `review/after/`, `review/round2/after/` — снимки предыдущих
проходов, в них сохранены прежние формулировки про `exit` и `CEvent::CheckEvents`.
Актуальные эталоны — в `../ajax-endpoint/`, `../practice-2-deal-stage/`,
`../control-task/fixed.php`.
