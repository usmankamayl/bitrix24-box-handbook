# Задача 2. Свой модуль с ORM-сущностью и обработчик `OnPageStart`

Два механизма, которые должен уметь любой Bitrix-разработчик:

1. **своя сущность D7** — таблица, `DataManager`-класс, установка модуля и миграция схемы;
2. **свой код в прологе** — автозагрузка классов и подписка на событие ядра без единой правки в `/bitrix/`.

Трекер визитов здесь — повод потрогать `OnPageStart`, контекст запроса, cookie и D7-ответ. Заказы — повод потрогать ORM и структуру модуля.

## Что делает код

При заходе на любую страницу сайта:

1. `init.php` подключает автозагрузку классов `MyCompany\Analytics` и подписывает `VisitorTracker::track()` на событие `main:OnPageStart`.
2. `VisitorTracker::track()` смотрит cookie `mycompany_visited`. Если она есть — выходит, ничего не делая.
3. Если cookie нет:
   - ставит `mycompany_visited=Y` на 30 дней (`HttpOnly`, `Secure` при HTTPS);
   - дописывает строку в лог `/local/logs/visitors/YYYY-MM-DD.log` в формате JSONL: Unix-время, IP, User-Agent, Referer, URI, UTM-метки;
   - пишет запись в журнал событий Bitrix (`CEventLog`) — признак «первый визит».

Отдельно, по адресу `/orders-demo.php`, доступна страница модуля `mycompany.orders`:

- список заказов с логином пользователя через `LEFT JOIN` к `b_user`;
- создание тестового заказа и удаление — только POST, только с `sessid`, только для администратора.

## Структура

```
task-2/
├── README.md                      этот файл
├── orders-demo.php                страница демо → копируется в корень сайта
└── local/
    ├── modules/
    │   └── mycompany.orders/
    │       ├── install/
    │       │   ├── index.php      установщик (CModule): InstallDB/UnInstallDB
    │       │   ├── version.php    VERSION / VERSION_DATE
    │       │   ├── lang/ru/index.php  тексты установщика
    │       │   └── db/
    │       │       ├── install.sql    CREATE TABLE mycompany_orders
    │       │       └── uninstall.sql  DROP TABLE
    │       └── lib/
    │           └── OrderTable.php     ORM-сущность Mycompany\Orders\OrderTable
    ├── php_interface/
    │   ├── init.php                       автозагрузка + подписка на OnPageStart
    │   └── classes/Analytics/
    │       └── VisitorTracker.php         трекер первого визита
    └── logs/
        └── .htaccess                      запрет HTTP-доступа к логам (Apache)
```

Пути в `local/` повторяют реальные: каталог `local` копируется в корень сайта целиком.

## Установка

1. Скопировать содержимое `task-2/local/` в `/local/` сайта (не перезаписывая существующий `init.php` — см. ниже), а `orders-demo.php` — в корень сайта.
2. Если `/local/php_interface/init.php` уже существует, **дописать** в него содержимое нашего `init.php`, а не заменять файл.
3. Открыть админку: **Настройки → Модули** (`/bitrix/admin/module_admin.php?lang=ru`), найти «Mycompany: заказы» и нажать «Установить».

`DoInstall()` создаёт таблицу `mycompany_orders` через `$DB->RunSQLBatch()` и регистрирует модуль в `b_module`. Без установки `Loader::includeModule('mycompany.orders')` вернёт `false`, и страница демо честно об этом скажет.

## Что делает каждый файл

### `local/modules/mycompany.orders/lib/OrderTable.php`

Сущность D7. `getTableName()` возвращает **полное** имя таблицы (`mycompany_orders`) — ядро префикс не дописывает (для сравнения: `UserTable::getTableName()` возвращает `b_user`).

`getMap()` описывается fluent-API:

- `IntegerField('ID')` + `configurePrimary()` + `configureAutocomplete()` — автоинкремент и первичный ключ;
- `IntegerField('USER_ID')` + `configureRequired()` — обязательное поле;
- `DatetimeField('DATE_CREATE')` + `configureDefaultValue(static fn() => new DateTime())` — значение по умолчанию вычисляется при вставке;
- `FloatField('PRICE')`, `StringField('STATUS')->configureSize(50)`;
- `Reference('USER', UserTable::class, Join::on('this.USER_ID', 'ref.ID'))` + `configureJoinType(TYPE_LEFT)` — связь с пользователем, по которой потом читается `USER.LOGIN`.

Про ключ значения по умолчанию: в массивной форме параметр называется `default_value`. `'default'` не существует и молча игнорируется — типичная ошибка при копировании примеров.

### `local/modules/mycompany.orders/install/index.php`

Установщик модуля. Класс `mycompany_orders extends CModule` — legacy, и это единственное место, где он уместен: у `install/index.php` модуля нет D7-замены.

- `DoInstall()` проверяет права администратора, создаёт таблицы, вызывает `ModuleManager::registerModule()`.
- `InstallDB()` запускает `install/db/install.sql` через `$DB->RunSQLBatch()`. Метод возвращает `false` при успехе и массив ошибок при провале — проверка именно на `!== false`, а не на истинность.
- `DoUninstall()` удаляет таблицу и снимает модуль с регистрации.

### `local/modules/mycompany.orders/install/db/install.sql`

`CREATE TABLE IF NOT EXISTS mycompany_orders` с `utf8mb4`, индексами по `USER_ID`, `STATUS`, `DATE_CREATE`.

### `local/php_interface/init.php`

Две вещи:

- `Loader::registerNamespace('MyCompany\Analytics', __DIR__ . '/classes/Analytics')` — автозагрузка собственного пространства имён без Composer;
- `EventManager::addEventHandler('main', 'OnPageStart', [VisitorTracker::class, 'track'])` — подписка на событие.

Обработчик передаётся массивом `[класс, метод]`, а не именем глобальной функции: нет риска `Cannot redeclare`, нет засорения глобального пространства. Именно из-за этого в наборе больше нет варианта «на функциях» — два обработчика на одно событие давали двойную запись в лог и двойную запись в журнал.

Автозагрузку классов модуля регистрировать вручную не нужно: `Loader::includeModule('mycompany.orders')` сам превращает имя модуля в namespace `Mycompany\Orders` и ставит его на каталог `lib/`. Это видно в `Loader::includeModule()` ядра.

### `local/php_interface/classes/Analytics/VisitorTracker.php`

Класс трекера, три приватных метода:

- `markAsVisited()` — ставит cookie. Домен **не** задаётся: `setDomain($_SERVER['HTTP_HOST'])` позволил бы запросу с подменённым заголовком `Host` привязать cookie к чужому домену. `HttpOnly` — да, `Secure` — при HTTPS.
- `logVisit()` — пишет JSONL-строку. Флаги `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE`: последний обязателен, потому что заголовки приходят в произвольной кодировке и без него `json_encode` вернёт `false`, а строка молча потеряется. Запись — `FILE_APPEND | LOCK_EX`, иначе параллельные первые визиты перемешают строки. Каждое поле обрезается до 512 символов.
- `writeJournalEntry()` — `CEventLog::Add()` с разными `AUDIT_TYPE_ID` для авторизованных и анонимных.

Время хранится как Unix timestamp: это единственное представление, не зависящее от таймзоны PHP и настроек сайта. Имя файла — дата в UTC (`gmdate`).

### `orders-demo.php`

Страница демо. Порядок проверок — важен:

1. `$USER->IsAdmin()` — иначе сообщение и выход. Права проверяются на сервере, скрытая кнопка защитой не является.
2. `Loader::includeModule('mycompany.orders')`.
3. Для POST-действий — `check_bitrix_sessid()`.
4. `id` из `$_POST` валидируется как строка через `filter_var(..., FILTER_VALIDATE_INT, ['min_range' => 1])`: `(int)'12abc'` дал бы `12`, а `(int)[]` — `1`.
5. Вывод — `htmlspecialcharsbx()`.
6. Список — `getList()` с `select`, связи `USER.LOGIN` и обязательным `limit` (50).

## Как проверить

### Трекер

1. Открыть любую страницу сайта в приватном окне.
2. DevTools → Application → Cookies: появилась `BITRIX_SM_mycompany_visited` (префикс `BITRIX_SM_` добавляет сам Bitrix, `HttpRequest::prepareCookie()` его снимает при чтении — поэтому `getCookie('mycompany_visited')` находит значение).
3. Проверить лог:

```bash
tail -n 3 local/logs/visitors/$(date -u +%F).log
jq -c . local/logs/visitors/$(date -u +%F).log   # каждая строка — валидный JSON
```

4. Обновить страницу — новых строк быть не должно.
5. Удалить cookie и обновить — появилась ещё одна строка.
6. Убедиться, что лог не отдаётся по HTTP:

```bash
curl -i https://site.local/local/logs/visitors/$(date -u +%F).log
# ожидается 403 (Apache + .htaccess)
```

7. Журнал событий: **Настройки → Журнал событий**, тип `MYCOMPANY_FIRST_VISIT_ANON` / `_AUTH`.

### Заказы

1. Открыть `/orders-demo.php` под администратором — таблица заказов.
2. «Создать тестовый заказ» → появляется запись с текущим пользователем и логином из `b_user`. Работает именно `LEFT JOIN`, отдельного запроса к пользователю нет.
3. «Удалить» → запись исчезает.
4. Открыть ту же страницу под обычным пользователем — сообщение о правах и никаких действий.
5. Отправить POST без `sessid`:

```bash
curl -i -X POST https://site.local/orders-demo.php -d 'action=delete&id=1'
# ожидается «Сессия устарела» и никакого удаления
```

6. Проверить переполнение и мусор в `id`: `id=12abc`, `id[]=1`, `id=-5` — все три отклоняются.
7. Повторная установка модуля не должна падать: `CREATE TABLE IF NOT EXISTS` + запись в `b_module` уже существует.

## Что здесь важно понять, а не скопировать

- `getTableName()` — это полное имя таблицы. Ошибка в префиксе даёт «таблица существует, а ORM её не видит».
- `default_value`, а не `default`. Массивные параметры полей в ядре помечены deprecated — новый путь `configure*()`.
- `isSuccess()` проверяется у каждого `add/update/delete`. Иначе ошибка валидации выглядит как «сохранено».
- `Loader::includeModule()` — не «подключить файл», а «подключить установленный модуль». Нет установки — нет класса.
- Побочные эффекты по GET — дыра. Даже в демо: страницу обходит любой краулер.
- `check_bitrix_sessid()` после проверки прав, но до любого изменения данных.
- `setDomain($_SERVER['HTTP_HOST'])` — не «настройка cookie», а приём Host-заголовка от клиента.
- JSONL — не JSON-массив. Запятая в конце строки ломает файл для любого парсера.
- `FILE_APPEND` без `LOCK_EX` теряет строки при конкурентной записи.
- `OnPageStart` не выполняется на композитных страницах: пролог там не запускается.
- Событие срабатывает в начале пролога — на момент вызова `$USER` может быть ещё не авторизован.

## Ограничения и отличия от продакшена

- **Лог лежит в `DOCUMENT_ROOT`.** `.htaccess` закрывает его только на Apache. На nginx нужен `location ^~ /local/logs/ { deny all; }`. В реальном проекте лог выносят вне корня сайта: `$DOCUMENT_ROOT . '/../logs'`.
- **Персональные данные.** IP, User-Agent, Referer — это ПД. Для сбора нужны основания (политика, согласие), иначе трекер — нарушение 152-ФЗ. В учебном примере это не решается кодом.
- **Нет ротации по размеру** — только по дате. При большом трафике нужен logrotate или запись в БД.
- **Миграция не версионируется.** `install/db/install.sql` выполняется один раз при установке. Добавили поле в `getMap()` — таблица сама не изменится, нужен отдельный `ALTER` (в реальном модуле — runner миграций, см. модуль 14.1).
- **Признак авторизации в `OnPageStart` может быть ложным.** Порядок «событие → авторизация» зависит от версии ядра; проверяется по `prolog_before.php`. Если признак принципиален — переносите запись на `OnEpilog`.
- **Композит.** На композитной странице ни cookie, ни лог не появятся.
- **`Reference`** — API актуальных ядер (`Bitrix\Main\ORM\Fields\Relations\Reference` + `Join::on`). На очень старых сборках (до появления этого класса) связь описывается иначе; проверьте `class_exists()` у себя до копирования.
- **Однопользовательская демонстрация.** Демо-страница показывает механику, а не UI: без постраничной навигации, без фильтров, без редактирования.

## Что исправлено по сравнению с первой версией

| Было | Стало |
|---|---|
| `Loader::includeModule('mycompany.orders')` без модуля → fatal | полноценный модуль: `install/index.php`, `version.php`, `install/db/` |
| `example-usage.php` создавал, менял и удалял заказ по GET | `orders-demo.php`: POST + `sessid` + проверка `IsAdmin()` |
| `$orderId` использовался вне `isSuccess()` | любое действие проверяет `isSuccess()`, ID валидируется |
| `setDomain($_SERVER['HTTP_HOST'])` | домен не задаётся; `Secure` при HTTPS |
| лог в `DOCUMENT_ROOT` без защиты | `.htaccess` + явное ограничение про nginx |
| `json_encode(...) . ','` — невалидный JSON | JSONL: одна строка — один объект |
| `file_put_contents` без `LOCK_EX` | `FILE_APPEND \| LOCK_EX` |
| `json_encode` без флага для битого UTF-8 | `JSON_INVALID_UTF8_SUBSTITUTE` |
| `date()` в таймзоне PHP | Unix `ts` + имя файла по UTC |
| SQL-склейка в закомментированном примере | убрана |
| два обработчика `OnPageStart` | один, через класс |
| глобальные функции в `init.php` | класс + `[class, 'method']` |
| `utf8` (3 байта) | `utf8mb4` |
| вывод без экранирования | `htmlspecialcharsbx()` |
| `getList` без `limit` | `limit => 50` |
| `Entity\ReferenceField` (legacy) | `Reference` + `Join::on` |
| нет README | этот файл |
