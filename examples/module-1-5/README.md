# Примеры к подразделу 1.5 — жизненный цикл HTTP-запроса

| Каталог | Что показывает | Куда копировать на коробке |
|---|---|---|
| `ajax-endpoint/` | эталон служебной точки входа: JSON, авторизация, CSRF, коды ответов, `FinalActions()` | `/local/ajax/deal-status.php` |
| `antipattern-header-ajax/` | тот же сценарий через `header.php`, без прав и CSRF — с разбором каждого дефекта | `/local/ajax/deal-status-bad.php` |
| `request-trace/` | инструмент трассировки: печатает фазы запроса (`BX_STATE`, время, память) и показывает, что HTML меняется только в `OnEndBufferContent` | `local/php_interface/*`, `local/trace-page.php` |
| `practice-1-trace/` | решение задачи 1: что снять, как читать цифры, два эксперимента | — (использует `request-trace/`) |
| `practice-2-deal-stage/` | решение задачи 2: POST-эндпоинт смены стадии с идемпотентностью на `UNIQUE` + журнал ответов | `/local/ajax/deal-stage.php`, `local/php_interface/classes/DealStage/*`, `init.php`, `install.sql` |
| `practice-3-deal-page/` | решение задачи 3: страница с 404, свойствами и безопасным `backurl` | `/deal.php` |
| `review/` | Code Review эталонов: `before/`, `after/`, `DIFF.md`, `CODE-REVIEW.md`; `round2/`, `round3/` — последующие проходы | — |
| `control-task/` | контрольное задание: `broken.php` с пятью дефектами, `fixed.php` — эталон, `README.md` — разбор | `/local/ajax/control-broken.php`, `/local/ajax/control-fixed.php` |

Все примеры — для **коробки** (Box) с модулем `crm`. На облаке их не запустить:
нет файлового доступа и `init.php`.

Общее правило для всех трёх: ничего не правим в `/bitrix/`, всё кладём в `/local/`.
