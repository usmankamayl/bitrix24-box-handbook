# Практика 4. `Result` → HTTP: статусы, коды, отсутствие утечек

**Задача.** Отдать наружу тот же домен, что в практике 2, но через JSON-эндпоинт:
`422` на провал валидации, `404` на чужую заявку, `409` на конфликт состояния,
`200` на успех, `500` без внутренностей — и ни одного текста исключения в теле.

## Что делает код

`endpoint.php` (`/local/api/request-close.php`) — семь шагов, в этом порядке:

| Шаг | Проверка | Ответ при отказе |
|---|---|---|
| 1 | метод `POST` | `405` + `Allow: POST` |
| 2 | `$USER->IsAuthorized()` | `401 NOT_AUTHORIZED` |
| 3 | `X-Bitrix-Sessid` через `hash_equals` | `403 CSRF_FAILED` |
| 4 | тело — JSON (`Json::decode`) | `400 INVALID_JSON` / `EMPTY_BODY` |
| 5 | типы `id` (int или цифровая строка) и `comment` (string) | `400 REQUEST_ID_INVALID` / `COMMENT_INVALID` |
| 6 | `OrderStatusService::closeRequest()` | `422/404/409/422` по коду ошибки |
| 7 | ответ | `200` + `data` |

`ResultResponder` — единственное место, где код ошибки становится HTTP-статусом:

```
REQUEST_ID_INVALID        -> 400
REQUEST_NOT_FOUND         -> 404      ACCESS_DENIED        -> 404
ALREADY_CLOSED            -> 409      STATUS_TRANSITION_DENIED -> 409
COMMENT_REQUIRED          -> 422      COMMENT_TOO_LONG     -> 422
STORE_FAILED              -> 500
неизвестный код           -> 422
```

Если в результате несколько ошибок — побеждает самый «тяжёлый» статус
(`500 > 409 > 404 > 400 > 422`). Один запрос — один статус.

`sendUnexpected(\Throwable)`:

- генерирует `error_id` (16 hex-символов),
- пишет в лог `[error_id] Класс: сообщение` + trace,
- отдаёт клиенту `500` с `{"code":"INTERNAL","message":"…Код: <error_id>"}`.

Текста исключения, имени таблицы и пути в теле нет.

## Структура и куда копировать

```
install.sql            ->  выполнить, если таблица ещё не создана (та же, что в практике 2)
OrderTable.php         ->  /local/php_interface/classes/Requests/OrderTable.php
OrderStatusService.php ->  /local/php_interface/classes/Requests/OrderStatusService.php
ResultResponder.php    ->  /local/php_interface/classes/Api/ResultResponder.php
endpoint.php           ->  /local/api/request-close.php
sessid.php             ->  /local/tools/sessid.php   (только для разработки)
```

`OrderTable.php` и `OrderStatusService.php` — копия из практики 2. Папка должна
запускаться автономно; в проекте домен один.

## Как проверить

Нужны cookie авторизованного администратора и sessid:

```bash
BASE='http://b24.local'
COOKIE='-b /tmp/b24.txt'          # после входа в админку

# 1. sessid для заголовка
SESSID=$(curl -s $COOKIE "$BASE/local/tools/sessid.php")
```

Дальше — матрица сценариев (ID заявки возьмите свой; `id=1` должен
принадлежать текущему пользователю, `id=999999` не существует):

```bash
# 405: GET вместо POST
curl -i -s $COOKIE "$BASE/local/api/request-close.php"

# 401: без cookie
curl -i -s -X POST "$BASE/local/api/request-close.php" \
     -H 'Content-Type: application/json' -d '{"id":1,"comment":"ok"}'

# 403: нет заголовка sessid
curl -i -s $COOKIE -X POST "$BASE/local/api/request-close.php" \
     -H 'Content-Type: application/json' -d '{"id":1,"comment":"ok"}'

# 400: битый JSON
curl -i -s $COOKIE -X POST "$BASE/local/api/request-close.php" \
     -H "X-Bitrix-Sessid: $SESSID" -H 'Content-Type: application/json' \
     -d '{"id":1,'

# 400: id массивом
curl -i -s $COOKIE -X POST "$BASE/local/api/request-close.php" \
     -H "X-Bitrix-Sessid: $SESSID" -H 'Content-Type: application/json' \
     -d '{"id":[1],"comment":"ok"}'

# 422: пустой комментарий
curl -i -s $COOKIE -X POST "$BASE/local/api/request-close.php" \
     -H "X-Bitrix-Sessid: $SESSID" -H 'Content-Type: application/json' \
     -d '{"id":1,"comment":"   "}'

# 404: несуществующий ID (и так же — чужая заявка)
curl -i -s $COOKIE -X POST "$BASE/local/api/request-close.php" \
     -H "X-Bitrix-Sessid: $SESSID" -H 'Content-Type: application/json' \
     -d '{"id":999999,"comment":"ok"}'

# 409: повторное закрытие той же заявки
curl -i -s $COOKIE -X POST "$BASE/local/api/request-close.php" \
     -H "X-Bitrix-Sessid: $SESSID" -H 'Content-Type: application/json' \
     -d '{"id":1,"comment":"ok"}'

# 200: закрытие заявки в статусе new/in_work
```

## Правильные ответы

| Сценарий | Статус | Тело (сокращённо) |
|---|---|---|
| GET | `405` | `{"status":"error","errors":[{"code":"METHOD_NOT_ALLOWED",…}]}`, заголовок `Allow: POST` |
| без cookie | `401` | `NOT_AUTHORIZED` |
| без sessid | `403` | `CSRF_FAILED` |
| битый JSON | `400` | `INVALID_JSON` |
| `id` массивом | `400` | `REQUEST_ID_INVALID` |
| пустой комментарий | `422` | `COMMENT_REQUIRED` |
| чужой/несуществующий ID | `404` | `REQUEST_NOT_FOUND` |
| повторное закрытие | `409` | `ALREADY_CLOSED` |
| успех | `200` | `{"status":"ok","data":{"id":1,"status":"closed","closed_at":"…"}}` |

Во всех ответах: `Content-Type: application/json; charset=UTF-8`,
`X-Content-Type-Options: nosniff`, `Cache-Control: no-store`, ровно один JSON.

## Почему решение такое

- **Порядок проверок.** Метод раньше авторизации: `GET` на POST-only ресурс —
  это `405`, а не `401`. Тело разбирается последним: отвечать «неверный JSON»
  тому, кто не авторизован, — лишняя информация.
- **Код → статус, а не текст → статус.** Таблица в одном файле; сервис можно
  переиспользовать в форме и в CLI, не трогая статусы.
- **`404` для чужой заявки.** `403` подтвердил бы существование объекта; см.
  практику 2.
- **`error_id` вместо текста исключения.** Поддержка находит запись в логе по
  коду, клиент не видит ни SQL, ни путей, ни структуры классов.
- **Один `catch (\Throwable)` на всю точку входа.** Не вокруг каждого вызова:
  непредвиденное — это баг, а не ожидаемое состояние, и ловится оно один раз.
- **`exit` внутри `json()`.** Без него после ответа продолжается выполнение и
  может уйти второй JSON (дефект из контрольного задания 1.7).

## Альтернативы и когда они лучше

| Вариант | Плюс | Минус |
|---|---|---|
| `Engine\Controller` + `ErrorCollection` | ядро само формирует ответ и JSON | `200` на ошибку валидации, свой CSRF-фильтр; детали — модуль 5 |
| Один общий обработчик исключений в `init.php` | не нужно `try` в каждом эндпоинте | для HTML-страниц; JSON-формат всё равно придётся выбирать |
| `http_response_code()` без маппера (`422` на всё) | проще | клиент не может отличить «повтори» от «исправь поле» |

## Непокрытые случаи

- **Идемпотентность.** Повторный `POST` возвращает `409 ALREADY_CLOSED`.
  Для сети, где клиент не получил ответ, правильнее ключ идемпотентности
  (`X-Idempotency-Key`) — механика в практике 3.
- **Rate limit.** Не реализован: `401/403` до счётчика не доходят, а без лимита
  эндпоинт перебирается. Это модуль 7.
- **`OPTIONS`/CORS.** Для внешнего клиента нужен preflight; здесь нет, потому что
  эндпоинт для своего фронта.
- **Логирование успешных вызовов.** Нет: аудит изменений — отдельная тема.

## Что здесь важно понять, а не скопировать

1. HTTP-статус — это решение транспорта, а не домена.
2. Клиент должен уметь ветвиться по `code`, не читая `message`.
3. Ответ `500` без `error_id` бесполезен: инцидент невозможно связать с логом.
4. `Cache-Control: no-store` на данных заявки — не формальность: иначе ответ
   осядет в прокси и в истории браузера.

## Ограничения

- Демонстрационный эндпоинт без роутера: в проекте таких точек входа десятки, и
  их сводят в контроллеры (модуль 5) или в один front-controller.
- `sessid.php` — только для разработки; на проде удаляется.
- Нет тестов: проверка — `curl` по матрице выше.
