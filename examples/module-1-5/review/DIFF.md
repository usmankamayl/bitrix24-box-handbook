# Diff: до и после Code Review

Подраздел 1.5. Снимки кода: `review/before/` — версия до ревью, `review/after/` — после.


## `deal-status.php`

```diff
--- before/deal-status.php
+++ after/deal-status.php
@@ -26,6 +26,7 @@
 
 header('Content-Type: application/json; charset=UTF-8');
 header('X-Content-Type-Options: nosniff');
+header('Cache-Control: no-store');
 
 /**
  * Единственная точка выхода: заголовок, тело, эпилог.
@@ -36,7 +37,10 @@
     global $APPLICATION;
 
     CHTTP::SetStatus($status);
-    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
+    echo json_encode(
+        $payload,
+        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
+    );
 
     $APPLICATION->FinalActions();
 };
@@ -58,6 +62,32 @@
 if (!check_bitrix_sessid()) {
     $respond(['error' => 'invalid_sessid'], '403 Forbidden');
 }
+
+/*
+ * Ограничение частоты: 60 запросов в минуту на пользователя.
+ * Окно фиксированное (ключ содержит номер минуты), поэтому кеш не продлевается
+ * бесконечно повторными запросами. Проверка неатомарна: при идеальном
+ * параллелизме лимит можно слегка превысить — для анти-перебора этого хватает,
+ * для квот по деньгам нужен счётчик в БД.
+ */
+$window = (int)floor(time() / 60);
+$rateKey = 'rl_' . $USER->GetID() . '_' . $window;
+$rateCache = \Bitrix\Main\Data\Cache::createInstance();
+$hits = 0;
+
+if ($rateCache->initCache(120, $rateKey, '/local/deal-status')) {
+    $hits = (int)($rateCache->getVars()['hits'] ?? 0);
+}
+
+$hits++;
+
+if ($hits > 60) {
+    header('Retry-After: 60');
+    $respond(['error' => 'too_many_requests'], '429 Too Many Requests');
+}
+
+$rateCache->startDataCache(120, $rateKey, '/local/deal-status');
+$rateCache->endDataCache(['hits' => $hits]);
 
 $rawDealId = $request->getPost('deal_id');
 
@@ -99,8 +129,16 @@
         ],
     ], '200 OK');
 } catch (\Throwable $e) {
+    // В логе — контекст: без user и deal инцидент не воспроизвести.
     AddMessage2Log(
-        $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(),
+        sprintf(
+            'user=%d deal=%d %s @ %s:%d',
+            (int)$USER->GetID(),
+            $dealId,
+            $e->getMessage(),
+            $e->getFile(),
+            $e->getLine()
+        ),
         'deal-status'
     );
```


## `deal-stage.php`

```diff
--- before/deal-stage.php
+++ after/deal-stage.php
@@ -34,7 +34,10 @@
     global $APPLICATION;
 
     CHTTP::SetStatus($status);
-    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
+    echo json_encode(
+        $payload,
+        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
+    );
 
     $APPLICATION->FinalActions();
 };
@@ -60,13 +63,20 @@
 
 // Параметры приходят строками, но ?deal_id[]=1 — уже массив.
 // (int) на массиве даёт warning и 1, (string) — "Array". Проверяем тип.
-$rawDealId    = $request->getPost('deal_id');
-$rawStageId   = $request->getPost('stage_id');
-$rawRequestId = $request->getPost('request_id');
+$rawDealId      = $request->getPost('deal_id');
+$rawStageId     = $request->getPost('stage_id');
+$rawRequestId   = $request->getPost('request_id');
+$rawExpected    = $request->getPost('expected_stage');
 
 $dealId    = (is_string($rawDealId) && ctype_digit($rawDealId)) ? (int)$rawDealId : 0;
-$stageId   = is_string($rawStageId) ? trim($rawStageId) : '';
+
+// Стадии в CRM — верхний регистр. Клиент может прислать 'new' или ' new ':
+// нормализуем здесь, иначе сверка со справочником даст ложный unknown_stage.
+$stageId   = is_string($rawStageId) ? strtoupper(trim($rawStageId)) : '';
 $requestId = is_string($rawRequestId) ? trim($rawRequestId) : '';
+
+// Необязательный параметр оптимистичной блокировки.
+$expectedStage = is_string($rawExpected) ? strtoupper(trim($rawExpected)) : '';
 
 if ($dealId <= 0 || $stageId === '') {
     $respond(['error' => 'bad_request'], '400 Bad Request');
@@ -117,13 +127,28 @@
         $respond(['error' => 'not_found'], '404 Not Found');
     }
 
+    // Оптимистичная блокировка: клиент присылает стадию, которую видел.
+    // Если её уже сменили — 409 вместо «молча перезаписали чужое изменение».
+    if ($expectedStage !== '' && (string)$deal['STAGE_ID'] !== $expectedStage) {
+        $respond([
+            'error'  => 'stage_conflict',
+            'actual' => (string)$deal['STAGE_ID'],
+        ], '409 Conflict');
+    }
+
     // CCrmDeal::Update, а не DealTable::update: сохраняются события CRM,
     // история, роботы и бизнес-процессы. Прямой ORM-апдейт их пропускает.
     // [проверить] возвращаемое значение в своей версии.
     $updated = \CCrmDeal::Update($dealId, ['STAGE_ID' => $stageId]);
 
     if (!$updated) {
-        throw new \RuntimeException('CCrmDeal::Update returned false for deal ' . $dealId);
+        // false — это не сбой инфраструктуры, а отказ бизнес-правил или прав.
+        // Поэтому 422, а не 500: повтор запроса ничего не изменит.
+        AddMessage2Log(
+            sprintf('update failed user=%d deal=%d stage=%s', (int)$USER->GetID(), $dealId, $stageId),
+            'deal-stage'
+        );
+        $respond(['error' => 'update_rejected'], '422 Unprocessable Entity');
     }
 
     $payload = [
@@ -140,7 +165,15 @@
     $respond($payload, '200 OK');
 } catch (\Throwable $e) {
     AddMessage2Log(
-        $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(),
+        sprintf(
+            'user=%d deal=%d stage=%s %s @ %s:%d',
+            (int)$USER->GetID(),
+            $dealId,
+            $stageId,
+            $e->getMessage(),
+            $e->getFile(),
+            $e->getLine()
+        ),
         'deal-stage'
     );
```


## `deal.php`

```diff
--- before/deal.php
+++ after/deal.php
@@ -64,22 +64,39 @@
 // ?id[]=1 приходит массивом. В PHP 8 ctype_digit(массив) — TypeError,
 // а (string) на массиве — warning «Array to string conversion».
 // Поэтому проверяем тип до приведения.
-$dealId = (is_string($rawId) && $rawId !== '' && ctype_digit($rawId)) ? (int)$rawId : 0;
+// Длина ограничена: ID в Bitrix — int(11), больше 10 цифр быть не может,
+// а (int) на 20-значном числе молча насыщается до PHP_INT_MAX.
+$dealId = (
+    is_string($rawId)
+    && $rawId !== ''
+    && strlen($rawId) <= 10
+    && ctype_digit($rawId)
+) ? (int)$rawId : 0;
 
 $deal = null;
+$crmMissing = false;
 
-if ($dealId > 0 && \Bitrix\Main\Loader::includeModule('crm')) {
-    $deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();
+if ($dealId > 0) {
+    if (!\Bitrix\Main\Loader::includeModule('crm')) {
+        // Публичная страница: 404 скрыл бы поломку конфигурации.
+        // Отсутствующий модуль — это 500, а не «сделки нет».
+        $crmMissing = true;
+    } else {
+        $deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();
 
-    // [проверить] сигнатуру CheckReadPermission под свою версию CRM.
-    if ($deal && !\CCrmDeal::CheckReadPermission($dealId)) {
-        $deal = null;
+        // [проверить] сигнатуру CheckReadPermission под свою версию CRM.
+        if ($deal && !\CCrmDeal::CheckReadPermission($dealId)) {
+            $deal = null;
+        }
     }
 }
 
-// 404 помечаем ДО шапки: ERROR_404 запрещает запись HTML-кеша,
+// Код помечаем ДО шапки: ERROR_404 запрещает запись HTML-кеша,
 // CHTTP::SetStatus выставляет код. Шаблон при этом отдаётся.
-if ($deal === null) {
+// 500 не кешируем вообще — статус выставляем, ERROR_404 не трогаем.
+if ($crmMissing) {
+    CHTTP::SetStatus('500 Internal Server Error');
+} elseif ($deal === null) {
     define('ERROR_404', 'Y');
     CHTTP::SetStatus('404 Not Found');
 }
@@ -98,7 +115,14 @@
     LocalRedirect($backUrl);
 }
 
-if ($deal === null) {
+if ($crmMissing) {
+    $APPLICATION->SetTitle('Ошибка конфигурации');
+    $APPLICATION->SetPageProperty('robots', 'noindex, nofollow');
+    ?>
+    <h1>Страница временно недоступна</h1>
+    <p>Модуль CRM не установлен. Обратитесь к администратору портала.</p>
+    <?php
+} elseif ($deal === null) {
     $APPLICATION->SetTitle('Сделка не найдена');
     $APPLICATION->SetPageProperty('robots', 'noindex, nofollow');
     ?>
```
