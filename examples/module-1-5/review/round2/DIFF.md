# Round 2 — исправления по ревью

Источник «до» — `review/after/` (версия после первого ревью). «После» — текущие файлы.


## `deal-status.php`

```diff
--- before/deal-status.php
+++ after/deal-status.php
@@ -4,14 +4,22 @@
  * Копировать в /local/ajax/deal-status.php на коробке.
  *
- * Что демонстрирует:
- *  - преамбула констант (не пишем статистику, не тянем js-расширитель сессии);
- *  - prolog_before.php вместо header.php — нет шаблона, нет HTML;
- *  - порядок проверок: метод → авторизация → CSRF → валидация;
- *  - коды ответов через CHTTP::SetStatus;
- *  - FinalActions() вместо die();
- *  - session_write_close() после чтения пользователя;
- *  - ошибки — в лог, клиенту — только код.
+ * Контракт:
+ *   POST deal_id, sessid
+ *   200 — сделка доступна, в теле только ID/TITLE/STAGE_ID
+ *   400 — нет deal_id, массив, не число, 0, отрицательное, переполнение
+ *   401 — не авторизован
+ *   403 — нет sessid (CSRF)
+ *   404 — сделки нет или нет прав на чтение (ответы неразличимы)
+ *   405 — не POST
+ *   429 — превышен лимит запросов, есть Retry-After
+ *   500 — внутренняя ошибка (детали только в логе)
+ *
+ * Инфраструктурные ошибки (fatal в прологе, падение PHP-FPM) — вне контракта:
+ * их обрабатывает веб-сервер, и JSON-формат для них не гарантируется.
  */
 declare(strict_types=1);
+
+use Bitrix\Main\Application;
+use Bitrix\Main\Loader;
 
 define('NO_KEEP_STATISTIC', true);
@@ -22,6 +30,14 @@
 require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
 
-use Bitrix\Main\Application;
-use Bitrix\Main\Loader;
+global $APPLICATION, $USER;
+
+/*
+ * Пролог мог успеть что-то вывести (BOM, warning при display_errors=On,
+ * echo в init.php). Сбрасываем буфер ДО любых заголовков и тела, иначе
+ * JSON получит текстовый префикс и клиент не сможет его разобрать.
+ * Если вывод уже ушёл в сокет, RestartBuffer не спасёт — поэтому ничего
+ * не печатаем до этой строки.
+ */
+$APPLICATION->RestartBuffer();
 
 header('Content-Type: application/json; charset=UTF-8');
@@ -29,11 +45,19 @@
 header('Cache-Control: no-store');
 
+$responded = false;
+
 /**
- * Единственная точка выхода: заголовок, тело, эпилог.
- * FinalActions() отправляет ответ и завершает приложение —
- * в отличие от die() он не пропускает эпилог (почтовые события, закрытие БД).
- */
-$respond = static function (array $payload, string $status): void {
-    global $APPLICATION;
+ * Единственная точка выхода.
+ *
+ * FinalActions() отправляет ответ и запускает эпилог, но сам по себе не
+ * гарантирует остановку скрипта — в ядре Bitrix после него всегда стоит die()
+ * (см. CMain::sendJsonAnswer). Поэтому exit обязателен: иначе после 401/403/404
+ * выполнение продолжится и в тело уйдёт второй JSON.
+ */
+$respond = static function (array $payload, string $status) use ($APPLICATION, &$responded): void {
+    if ($responded) {
+        exit;
+    }
+    $responded = true;
 
     CHTTP::SetStatus($status);
@@ -43,5 +67,7 @@
     );
 
+    // Эпилог обязателен: почтовые события, закрытие БД, фоновая работа.
     $APPLICATION->FinalActions();
+    exit;
 };
 
@@ -53,5 +79,42 @@
 }
 
-global $USER;
+/*
+ * Ограничение частоты — до проверки авторизации.
+ *
+ * Иначе 401 и 403 не лимитируются: перебор sessid и DoS на сессионные запросы
+ * остаются бесплатными. Для авторизованного пользователя ключ — его ID,
+ * для анонимного — IP (иначе все анонимы делят один счётчик).
+ *
+ * ManagedCache::read()/get()/set() выбран потому, что Data\Cache после
+ * успешного initCache() не даёт перезаписать значение: startDataCache()
+ * вернёт false, endDataCache() ничего не запишет, и счётчик застрянет на 1.
+ *
+ * Проверка неатомарна (read-modify-write): при идеальном параллелизме лимит
+ * можно слегка превысить. Для анти-перебора этого достаточно; для денежных
+ * квот нужен атомарный INCR в Redis или счётчик в БД.
+ */
+$limit = 60;
+$identity = $USER->IsAuthorized()
+    ? 'u' . (int)$USER->GetID()
+    : 'ip' . md5((string)$request->getRemoteAddress());
+
+$window = (int)floor(time() / 60);
+$rateKey = 'rl_' . $identity . '_' . $window;
+
+$managedCache = Application::getInstance()->getManagedCache();
+$hits = 0;
+
+if ($managedCache->read(120, $rateKey)) {
+    $hits = (int)$managedCache->get($rateKey);
+}
+
+$hits++;
+
+if ($hits > $limit) {
+    header('Retry-After: ' . (60 - (time() % 60)));
+    $respond(['error' => 'too_many_requests'], '429 Too Many Requests');
+}
+
+$managedCache->set($rateKey, $hits);
 
 if (!$USER->IsAuthorized()) {
@@ -64,42 +127,29 @@
 }
 
+$rawDealId = $request->getPost('deal_id');
+
+// filter_var отсекает массивы, мусор и переполнение (ctype_digit пропускает
+// длинную строку цифр, а (int) насыщает её до PHP_INT_MAX).
+$dealId = is_string($rawDealId)
+    ? filter_var($rawDealId, FILTER_VALIDATE_INT, [
+        'options' => ['min_range' => 1, 'max_range' => 2147483647],
+    ])
+    : false;
+
+if ($dealId === false) {
+    $respond(['error' => 'bad_request'], '400 Bad Request');
+}
+
 /*
- * Ограничение частоты: 60 запросов в минуту на пользователя.
- * Окно фиксированное (ключ содержит номер минуты), поэтому кеш не продлевается
- * бесконечно повторными запросами. Проверка неатомарна: при идеальном
- * параллелизме лимит можно слегка превысить — для анти-перебора этого хватает,
- * для квот по деньгам нужен счётчик в БД.
- */
-$window = (int)floor(time() / 60);
-$rateKey = 'rl_' . $USER->GetID() . '_' . $window;
-$rateCache = \Bitrix\Main\Data\Cache::createInstance();
-$hits = 0;
-
-if ($rateCache->initCache(120, $rateKey, '/local/deal-status')) {
-    $hits = (int)($rateCache->getVars()['hits'] ?? 0);
-}
-
-$hits++;
-
-if ($hits > 60) {
-    header('Retry-After: 60');
-    $respond(['error' => 'too_many_requests'], '429 Too Many Requests');
-}
-
-$rateCache->startDataCache(120, $rateKey, '/local/deal-status');
-$rateCache->endDataCache(['hits' => $hits]);
-
-$rawDealId = $request->getPost('deal_id');
-
-// deal_id[]=1 даёт массив: (int) на массиве — warning и 1 вместо ID.
-$dealId = (is_string($rawDealId) && ctype_digit($rawDealId)) ? (int)$rawDealId : 0;
-
-if ($dealId <= 0) {
-    $respond(['error' => 'bad_request'], '400 Bad Request');
-}
-
-// Сессия больше не нужна. Пока она открыта, её файл заблокирован,
-// и второй запрос этого же пользователя ждёт завершения первого.
-session_write_close();
+ * Закрываем сессию после чтения пользователя и проверки sessid.
+ * Для файловых сессий это снимает блокировку файла: пока она открыта, второй
+ * AJAX этого же пользователя ждёт завершения первого.
+ *
+ * Штатный D7-способ — Application::getInstance()->getSession()->save().
+ * Для файловых сессий он эквивалентен session_write_close(). При сессиях
+ * в БД/Redis, а также в режимах readonly/virtual поведение зависит от
+ * настроек — проверяйте под свою конфигурацию.
+ */
+Application::getInstance()->getSession()->save();
 
 try {
@@ -108,5 +158,10 @@
     }
 
-    $deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();
+    // Только нужные поля: getById() тянет всю строку, включая текстовые поля.
+    $deal = \Bitrix\Crm\DealTable::getList([
+        'select' => ['ID', 'TITLE', 'STAGE_ID'],
+        'filter' => ['=ID' => $dealId],
+        'limit'  => 1,
+    ])->fetch();
 
     // Чужой и несуществующий объект отвечают одинаково — 404.
@@ -116,6 +171,11 @@
     }
 
-    // [проверить] сигнатуру под свою версию CRM: в разных сборках
-    // CheckReadPermission принимает ещё и $userPermissions.
+    /*
+     * Проверка прав. Сигнатура стабильна в CRM 20.x–24.x:
+     *   CCrmDeal::CheckReadPermission($id, $userPermissions = null, $categoryId = null)
+     * Второй и третий аргументы опциональны; при вызове без них берётся
+     * текущий пользователь. Если в вашей сборке сигнатура иная — сверьтесь
+     * с crm_deal.php, но логика «нет прав → 404» не меняется.
+     */
     if (!\CCrmDeal::CheckReadPermission($dealId)) {
         $respond(['error' => 'not_found'], '404 Not Found');
@@ -135,5 +195,5 @@
             'user=%d deal=%d %s @ %s:%d',
             (int)$USER->GetID(),
-            $dealId,
+            (int)$dealId,
             $e->getMessage(),
             $e->getFile(),
```


## `deal-stage.php`

```diff
--- before/deal-stage.php
+++ after/deal-stage.php
@@ -12,7 +12,13 @@
  *   404 — сделки нет или нет прав на изменение
  *   405 — не POST
+ *   409 — конфликт ожидаемой и фактической стадии
+ *   422 — Update отказал по бизнес-правилам
  *   500 — внутренняя ошибка (детали только в логе)
  */
 declare(strict_types=1);
+
+use Bitrix\Main\Application;
+use Bitrix\Main\Data\Cache;
+use Bitrix\Main\Loader;
 
 define('NO_KEEP_STATISTIC', true);
@@ -23,7 +29,8 @@
 require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
 
-use Bitrix\Main\Application;
-use Bitrix\Main\Data\Cache;
-use Bitrix\Main\Loader;
+global $APPLICATION, $USER;
+
+// Сбрасываем возможный вывод пролога/init.php до заголовков и тела.
+$APPLICATION->RestartBuffer();
 
 header('Content-Type: application/json; charset=UTF-8');
@@ -31,6 +38,16 @@
 header('Cache-Control: no-store');
 
-$respond = static function (array $payload, string $status): void {
-    global $APPLICATION;
+$responded = false;
+
+/**
+ * Единственная точка выхода.
+ * FinalActions() запускает эпилог, но не гарантирует остановку скрипта:
+ * в ядре Bitrix после него всегда стоит die(). Поэтому exit обязателен.
+ */
+$respond = static function (array $payload, string $status) use ($APPLICATION, &$responded): void {
+    if ($responded) {
+        exit;
+    }
+    $responded = true;
 
     CHTTP::SetStatus($status);
@@ -41,4 +58,5 @@
 
     $APPLICATION->FinalActions();
+    exit;
 };
 
@@ -49,6 +67,4 @@
     $respond(['error' => 'method_not_allowed'], '405 Method Not Allowed');
 }
-
-global $USER;
 
 if (!$USER->IsAuthorized()) {
@@ -62,12 +78,14 @@
 }
 
-// Параметры приходят строками, но ?deal_id[]=1 — уже массив.
-// (int) на массиве даёт warning и 1, (string) — "Array". Проверяем тип.
-$rawDealId      = $request->getPost('deal_id');
-$rawStageId     = $request->getPost('stage_id');
-$rawRequestId   = $request->getPost('request_id');
-$rawExpected    = $request->getPost('expected_stage');
-
-$dealId    = (is_string($rawDealId) && ctype_digit($rawDealId)) ? (int)$rawDealId : 0;
+$rawDealId    = $request->getPost('deal_id');
+$rawStageId   = $request->getPost('stage_id');
+$rawRequestId = $request->getPost('request_id');
+$rawExpected  = $request->getPost('expected_stage');
+
+$dealId = is_string($rawDealId)
+    ? filter_var($rawDealId, FILTER_VALIDATE_INT, [
+        'options' => ['min_range' => 1, 'max_range' => 2147483647],
+    ])
+    : false;
 
 // Стадии в CRM — верхний регистр. Клиент может прислать 'new' или ' new ':
@@ -79,5 +97,5 @@
 $expectedStage = is_string($rawExpected) ? strtoupper(trim($rawExpected)) : '';
 
-if ($dealId <= 0 || $stageId === '') {
+if ($dealId === false || $stageId === '') {
     $respond(['error' => 'bad_request'], '400 Bad Request');
 }
@@ -89,5 +107,5 @@
 
 // Сессия больше не нужна: снимаем блокировку файла сессии.
-session_write_close();
+Application::getInstance()->getSession()->save();
 
 try {
@@ -104,4 +122,8 @@
      * вставка строки и есть «захват» запроса, второй INSERT падает по
      * уникальному индексу, и мы возвращаем сохранённый ответ.
+     *
+     * Здесь initCache() и startDataCache() используются правильно:
+     * при попадании в кеш выполнение уходит в $respond() и завершается,
+     * поэтому startDataCache() вызывается только при промахе.
      */
     $cache = Cache::createInstance();
@@ -113,5 +135,4 @@
     }
 
-    // [проверить] ключ справочника стадий в своей версии CRM.
     $stages = \CCrmStatus::GetStatusList('DEAL_STAGE');
 
@@ -120,8 +141,12 @@
     }
 
-    $deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();
+    // Только нужные поля вместо getById().
+    $deal = \Bitrix\Crm\DealTable::getList([
+        'select' => ['ID', 'STAGE_ID'],
+        'filter' => ['=ID' => $dealId],
+        'limit'  => 1,
+    ])->fetch();
 
     // Чужая и несуществующая сделка отвечают одинаково — 404.
-    // [проверить] сигнатуру CheckUpdatePermission под свою версию.
     if (!$deal || !\CCrmDeal::CheckUpdatePermission($dealId)) {
         $respond(['error' => 'not_found'], '404 Not Found');
@@ -139,5 +164,4 @@
     // CCrmDeal::Update, а не DealTable::update: сохраняются события CRM,
     // история, роботы и бизнес-процессы. Прямой ORM-апдейт их пропускает.
-    // [проверить] возвращаемое значение в своей версии.
     $updated = \CCrmDeal::Update($dealId, ['STAGE_ID' => $stageId]);
 
@@ -169,5 +193,5 @@
             'user=%d deal=%d stage=%s %s @ %s:%d',
             (int)$USER->GetID(),
-            $dealId,
+            (int)$dealId,
             $stageId,
             $e->getMessage(),
```
