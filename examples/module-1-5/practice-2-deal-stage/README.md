# Задача 2. Смена стадии сделки — эталонное решение

`/local/ajax/deal-stage.php`: POST-эндпоинт смены стадии сделки с проверкой прав,
кодами ответов и **настоящей** идемпотентностью (таблица + `UNIQUE`), а не кешем.

## Файлы и куда копировать

```
local/ajax/deal-stage.php                              → /local/ajax/deal-stage.php
local/php_interface/init.php                           → /local/php_interface/init.php
local/php_interface/classes/DealStage/Journal.php      → /local/php_interface/classes/DealStage/Journal.php
local/php_interface/classes/DealStage/JournalTable.php → /local/php_interface/classes/DealStage/JournalTable.php
install.sql                                            → один раз применить к БД портала
```

`install.sql` создаёт `mycompany_deal_stage_request` — журнал ключей. Если правишь
DDL под себя, `UNIQUE(USER_ID, REQUEST_ID)` трогать нельзя: он и есть механизм.

## Как проверить

`<cookie>` и `<sessid>` — из браузера (`<?=bitrix_sessid()?>` на любой странице
под тем же пользователем).

```bash
# 405 — GET
curl -i "http://box.local/local/ajax/deal-stage.php"

# 401 — без cookie
curl -i -X POST "http://box.local/local/ajax/deal-stage.php" \
  -d "deal_id=1&stage_id=WON&request_id=req-00000001"

# 403 — авторизован, без sessid
curl -i -X POST "http://box.local/local/ajax/deal-stage.php" \
  -b "<cookie>" -d "deal_id=1&stage_id=WON&request_id=req-00000001"

# 400 — неизвестная стадия
curl -i -X POST "http://box.local/local/ajax/deal-stage.php" \
  -b "<cookie>" -d "sessid=<sessid>&deal_id=1&stage_id=NOPE&request_id=req-00000001"

# 404 — чужая или несуществующая сделка
curl -i -X POST "http://box.local/local/ajax/deal-stage.php" \
  -b "<cookie>" -d "sessid=<sessid>&deal_id=999999&stage_id=WON&request_id=req-00000002"

# 200
curl -i -X POST "http://box.local/local/ajax/deal-stage.php" \
  -b "<cookie>" -d "sessid=<sessid>&deal_id=1&stage_id=WON&request_id=req-00000003"

# 200 повторно с тем же request_id: тело байт в байт, стадия не меняется
curl -i -X POST "http://box.local/local/ajax/deal-stage.php" \
  -b "<cookie>" -d "sessid=<sessid>&deal_id=1&stage_id=WON&request_id=req-00000003"
```

**Доказательство идемпотентности глазами:** после первого `200` смени стадию
вручную в карточке сделки и повтори запрос с тем же `request_id`. Вернётся
сохранённый ответ, а стадия останется той, что ты поставил руками. Кеш так не
умеет: он бы отдал то же тело, но параллельный запрос всё равно прошёл бы
дважды.

```bash
# гонка: два одинаковых запроса одновременно
curl -s -X POST ... -d "...&request_id=req-race-0001" &
curl -s -X POST ... -d "...&request_id=req-race-0001" &
wait
# один выполняет действие, второй получает 200-повтор или 409 request_in_progress,
# но два действия не выполняются никогда
```

## Почему решение такое

- **Метод проверяется первым.** GET на write-эндпоинт — ошибка клиента или
  CSRF-проба; отвечаем `405` + `Allow: POST`, не раскрывая ничего.
- **`401` до `403`.** Сначала «кто ты», потом «докажи, что запрос твой».
  Наоборот — утечка: неавторизованный видит, что эндпоинт живой и требует sessid.
- **Идемпотентность атомарна.** `Journal::claim()` делает `INSERT` в таблицу с
  `UNIQUE(USER_ID, REQUEST_ID)`; проигравший получает `DuplicateEntryException`,
  а не второй шанс. Схема «прочитать, потом вставить» неверна принципиально:
  между двумя шагами второй запрос успевает прочитать «нет».
- **Ответ фиксируется до отправки.** `Journal::complete()` сохраняет тело до
  `$respond()`. Повтор получает те же байты и тот же `200`, даже если состояние
  сделки уже изменилось.
- **Зависший захват переиспользуется.** Если процесс умер между `INSERT` и
  `complete()`, строка останется с `HTTP_CODE = 0`. Через 120 секунд `claim()`
  переиспользует её — иначе ключ заблокировал бы действие навсегда.
- **Идемпотентность не отменяет `expected_stage`.** Первый запрос проверяет
  ожидаемую стадию (`409` при расхождении), повтор — уже нет: он не выполняет
  действие, а возвращает записанный ответ.
- **`400` на неизвестную стадию, а не молчаливая запись.** Стадии — справочник;
  запись `STAGE_ID = 'WON '` (с пробелом) ломает воронку и отчёты.
- **`404` вместо `403`** для чужой сделки: `403` подтверждает существование
  объекта и позволяет перебором узнать, какие ID есть в системе.
- **`CCrmDeal::Update`, а не `DealTable::update`.** Прямой ORM-апдейт не
  запускает события CRM, роботов и бизнес-процессы: сделка «переедет» по
  стадии, но автоматизация не сработает. ORM уместен в миграциях и импорте.
- **`FinalActions()` и только она.** Она собирает буфер, отдаёт ответ и вызывает
  `Application::end()` → `terminate()`, где стоит `exit()`. Отдельный `exit`
  после неё — мёртвый код, а `die()` вместо неё обрывает эпилог: не выполнятся
  `OnAfterEpilog`, `session_write_close()`, background jobs (`check_events`),
  `ManagedCache::finalize()`.

## Альтернативы и когда они лучше

| Вариант | Когда лучше | Цена |
|---|---|---|
| `CCrmDeal::Update()` (выбрано) | обычная работа с CRM | медленнее ORM, тянет события |
| `\Bitrix\Crm\DealTable::update()` | миграции, импорт, массовые правки | не запускает роботов, БП, историю |
| `\Bitrix\Crm\Service\Container::getInstance()->getFactory(...)` | смарт-процессы и универсальный код | сложнее, нужен ID типа |
| REST-метод `crm.deal.update` | внешняя интеграция, другой сервер | сетевой хоп, лимиты, вебхук-права |

## Edge cases

**Покрыто:**

- неверный метод, нет авторизации, нет sessid;
- массивы вместо строк (`deal_id[]=1`): `is_string` + `filter_var` вместо `(int)`;
- переполнение (`deal_id=99999999999999999999`) → `400`, а не `PHP_INT_MAX`;
- неизвестная стадия;
- несуществующая или чужая сделка;
- повтор с тем же `request_id` → сохранённый ответ;
- параллельные запросы с тем же `request_id` → один выполняет, второй получает
  `200`-повтор или `409 request_in_progress`;
- `expected_stage` не совпал → `409` с фактической стадией;
- исключение → `500` без деталей, ключ освобождён.

**Не покрыто (осознанно):**

- **Ограничение частоты** — нет. В проде — счётчик по пользователю/IP (пример
  есть в `../ajax-endpoint/`).
- **Проверка `Origin`/`Referer`** — нет; `sessid` закрывает CSRF.
- **Уборка журнала** — старые строки копятся. Агент/крон раз в сутки:
  `DELETE FROM mycompany_deal_stage_request WHERE CREATED_AT < NOW() - INTERVAL 30 DAY`.
- **Аудит переходов** — своего журнала нет; историю сделки пишет
  `CCrmDeal::Update()`, для внешних вызовов нужен отдельный.

## Что здесь важно понять, а не скопировать

- Идемпотентность — это **ключ запроса и уникальный индекс**, а не «повторить и
  проверить». Кеш не атомарен и не переживает очистку.
- `404` вместо `403` — не стилистика, а защита от перебора.
- `FinalActions()` завершает запрос сама (`Application::terminate()` → `exit`).
  Проверить: `grep -n "exit(\$status)" bitrix/modules/main/lib/application.php`.
- `CCrmDeal::Update` vs ORM — выбор между «работает автоматизация» и «быстро и
  без побочных эффектов».
