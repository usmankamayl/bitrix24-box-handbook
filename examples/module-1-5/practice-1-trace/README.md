# Задача 1. Трассировка запроса и цифры — решение

## Задание

Снять фазы запроса на живой коробке, доказать, что `OnAfterEpilog` не влияет на
HTML, и показать, что отложенные свойства (`ShowTitle`, `ShowMeta`) живут в
буфере и умирают вместе с ним.

## Файлы

Готовый инструмент — в `../../request-trace/`:

```
local/php_interface/init.php    → /local/php_interface/init.php
local/php_interface/trace.php   → /local/php_interface/trace.php
local/trace-page.php            → /trace-page.php
```

Отдельного кода эта задача не требует: решение — инструмент плюс два
эксперимента ниже.

## Как запустить

1. Скопировать три файла, открыть `/trace-page.php?bx_trace=Y` под администратором.
2. Внизу страницы — таблица фаз: `BX_STATE`, время от старта, дельта, память.
3. Открыть `/upload/bx_trace_last.log` — там `OnAfterEpilog`.

## Что должно получиться

| Фаза | `BX_STATE` | Комментарий |
|---|---|---|
| `init.php` | `PB` | пролог идёт, `$USER` уже создан, авторизация выполнена |
| `OnBeforeProlog` | `PB` | строки может не быть — значит `init.php` не успел зарегистрировать обработчик до выстрела события |
| `OnProlog` | `PB` | вызывается внутри `CMain::PrologActions()` из `prolog_before.php` |
| `OnEpilog` | `EA` | `epilog_after.php` выставил `EA` до вызова события |
| `OnBeforeEndBufferContent` | `EA` | перед сборкой отложенных колбэков |
| `OnEndBufferContent` | `EA` | здесь правится HTML |
| `OnAfterEpilog` | `EA` | только файл лога, в HTML не попадёт |

Шкала состояний целиком:

```
PB  prolog_before.php        (include.php: init.php, $USER, авторизация, OnBeforeProlog)
PA  prolog_after.php         (заголовок Content-type, шаблон сайта)
WA  после шапки шаблона      (RestartWorkarea(true) — начинается work area)
EB  epilog_before.php        (footer.php шаблона)
EA  epilog_after.php         (OnEpilog, сборка буфера, FinalActions)
```

Состояния `EV` в актуальном ядре нет: `OnAfterEpilog` выполняется, когда
`BX_STATE` всё ещё `EA` (проверяется тем же инструментом — это и есть ответ).

Ориентиры по числам (демо-коробка, страница через шаблон): пролог — единицы
миллисекунд на пустой странице и десятки на странице с компонентами; доля
пролога в общем времени 30–70 % в зависимости от веса контента. Абсолютные
цифры у тебя будут свои — важны **дельты между фазами**, а не сами значения.

## Эксперимент 1: `OnAfterEpilog` не меняет HTML

Добавить в обработчик `OnAfterEpilog` строку:

```php
AddEventHandler('main', 'OnAfterEpilog', static function (): void {
    echo '<!-- AFTER_EPILOG_MARK -->';
});
```

Открыть страницу и поискать в исходнике `AFTER_EPILOG_MARK`. Его **нет**: ответ
уже ушёл (`$response->send()` внутри `Application::end()`), а `OnAfterEpilog`
выполняется в `Application::terminate()` — уже после отправки. Событие при этом
сработало — видно по файлу лога.

Вывод: править HTML можно только в `OnEndBufferContent` (или раньше, в
`OnEpilog`, пока буфер не сброшен), но не в `OnAfterEpilog`.

## Эксперимент 2: отложенные свойства живут в буфере

`ShowTitle()`/`ShowMeta()` не печатают — они кладут колбэк в
`$APPLICATION->buffer_content_type`. `RestartBuffer()` этот массив чистит, вместе
с ним пропадает и заголовок.

`/restart-probe.php`:

```php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

$APPLICATION->SetTitle('Заголовок до RestartBuffer');
$APPLICATION->RestartBuffer();          // старый буфер и колбэки уничтожены
?><!DOCTYPE html>
<html><head><title><?php $APPLICATION->ShowTitle(); ?></title></head>
<body><p>ok</p></body></html>
```

```bash
curl -s 'http://box.local/restart-probe.php' | grep -o '<title>.*</title>'
# <title></title>   — SetTitle() был, а колбэк уже потерян
```

| Сценарий | `<title>` |
|---|---|
| обычная страница: `SetTitle()` после `header.php` | содержит заголовок |
| `RestartBuffer()` после `SetTitle()` | пустой |

Про «выключить буферизацию настройкой» забудь: опция `buffer_content`
существовала в main 12–19, в актуальном ядре её нет, `CMain::PrologActions()`
включает буфер безусловно.

```bash
# доказательство для отчёта: строк нет
grep -n '"buffer_content"' bitrix/modules/main/classes/general/main.php
```

## Edge cases

- **Композит включён** — страница может отдаваться из кеша до пролога: в таблице
  будет только `OnEndBufferContent` или вообще ничего. Это не ошибка
  инструмента, а другой путь запроса (`Composite\Responder::respond()`).
- **Кеш компонентов** — второй запрос покажет меньше времени в теле страницы,
  но пролог останется тем же: он не кешируется.
- **`OnBeforeProlog` отсутствует** — значит `init.php` выполняется позже, чем
  выстреливает событие, в этой сборке.
- **Не админ** — таблица не появится: права проверяются внутри
  `OnEndBufferContent`.
- **Композит + `OnAfterEpilog`** — при отдаче из кеша `terminate()` тоже
  выполняется, но `BX_TRACE` пуст: `init.php` не запускался.

## Что здесь важно понять, а не скопировать

- Порядок фаз и то, что `BX_STATE` — реальные состояния из prolog/epilog-файлов,
  а не метки «на глаз».
- Отложенный вывод: `<head>` собирается в конце, поэтому `SetTitle()` после
  шапки работает — ровно до первого `RestartBuffer()`.
- `OnAfterEpilog` — про побочные эффекты, не про контент.
