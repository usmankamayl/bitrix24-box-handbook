# Multi-tenant Market-приложение: установка, хранение токенов, batch REST-клиент

Market-приложение, которое одновременно обслуживает множество независимых
порталов Bitrix24 (SaaS-модель). В отличие от локального приложения (см.
`examples/module-9-4/`), здесь нельзя хранить токен в сессии или файле —
запросы от Bitrix24 (события, крон) приходят без участия пользователя, а
данные каждого портала должны быть строго изолированы друг от друга.

## Файлы

- `schema.sql` — таблица `app_installations`, одна строка = один портал
- `config.php` — константы приложения и подключение к БД
- `InstallationRepository.php` — CRUD для установок + рефреш токена с блокировкой строки (`FOR UPDATE`)
- `TokenProvider.php` — обёртка над репозиторием: отдаёт валидный токен, при необходимости форсирует рефреш
- `RestClient.php` — REST-клиент с retry/backoff на `QUERY_LIMIT_EXCEEDED` и batch-методом
- `Security.php` — проверка домена (`*.bitrix24.<tld>`) и `application_token` входящих событий
- `install.php` — точка входа при установке приложения (обмен `code` на токены, `BX24.installFinish()`)
- `event-handler.php` — обработчик `ONAPPUNINSTALL` (soft-delete + отложенная очистка)
- `purge-cron.php` — крон-воркер: физически удаляет данные порталов, у которых истёк `purge_after`

## Установка

1. Выполните `schema.sql` в своей БД.
2. В `config.php` укажите `APP_CLIENT_ID`/`APP_CLIENT_SECRET` (из dev.bitrix24.ru/marketplace/) и параметры подключения к БД.
3. Зарегистрируйте `install.php` как "Путь установки" в настройках приложения в Маркетплейсе.
4. Зарегистрируйте `event-handler.php` как обработчик события `ONAPPUNINSTALL` (через `event.bind` после первой установки, либо в настройках приложения).
5. Поставьте `purge-cron.php` в системный crontab (раз в сутки достаточно).

## Как это работает вместе

```
install.php          → обменивает code на токены, сохраняет через InstallationRepository
                        (ключ — member_id, не domain)

RestClient            → перед каждым вызовом берёт токен через TokenProvider
    ↓ getToken()
TokenProvider          → делегирует InstallationRepository::getValidAccessTokenWithLock()
    ↓ FOR UPDATE
InstallationRepository → если токен жив — отдаёт как есть;
                          если протух — рефрешит, пока держит блокировку строки
                          (защита от гонки при параллельных запросах)

event-handler.php     → ONAPPUNINSTALL: проверяет application_token, ставит status=uninstalled
                          + purge_after = NOW() + 14 дней (soft-delete)

purge-cron.php        → раз в сутки физически удаляет записи с истёкшим purge_after
```

## Ключевые моменты для собеседования

- **Ключ — `member_id`, не `domain`.** Домен портала может смениться (ребрендинг, переход с тестового на боевой), `member_id` — нет.
- **Блокировка строки при рефреше токена.** `refresh_token` одноразовый: два параллельных запроса с одним и тем же `refresh_token` — второй получит ошибку. `SELECT ... FOR UPDATE` в `InstallationRepository::getValidAccessTokenWithLock()` сериализует рефреш для одного `member_id`.
- **batch — один запрос с точки зрения rate limit**, даже если внутри 50 команд. При массовой синхронизации всегда предпочтительнее N отдельных `call()`.
- **Soft-delete вместо немедленного удаления в обработчике события.** Обработчик должен быстро ответить Bitrix24 (несколько секунд); тяжёлая очистка вынесена в `purge-cron.php`. Это же защищает от случая, когда пользователь удалил приложение по ошибке и ставит обратно через минуту.
- **`application_token`, а не просто `member_id`, защищает обработчик событий** — `member_id` не секретный, его легко подобрать/узнать, а `application_token` выдаётся один раз при установке и сверяется через `hash_equals()` (защита от timing-атак).
- **`BX24.installFinish()` обязателен** — без него экран установки зависает на загрузке у пользователя, даже если токены уже сохранены.

Подробный теоретический разбор — в `docs/module-13-1.md`.
