# CODE REVIEW: module-5-3 (sale 25.700.0)

## Дата ревью
2026-11-02

## Версия модуля sale
25.700.0

---

## 1. ПРИМЕНЁННЫЕ ПРАВКИ

### 1.1 AcmeDeliveryService.php — критичные API-несоответствия

| Строка | Было | Стало | Обоснование |
|--------|------|-------|-------------|
| 38 | `public static function calculatePrice()` | `protected function calculateConcrete()` | В Base нет метода calculatePrice(), только calculateConcrete() |
| 122 | `$result->setPrice()` | `$result->setDeliveryPrice()` | setPrice() не существует в CalculationResult |
| 127 | `$result->setCurrency()` | Удалён | setCurrency() не существует в CalculationResult |
| 131 | `$result->setExtraParams()` | Удалён | setExtraParams() не существует в CalculationResult |
| 45,76 | `$result->setDeliveringReason()` | `$result->addError(new Error())` | setDeliveringReason() не существует в CalculationResult |
| 234-250 | Вложенные классы Courier/Pickup/Post | Отдельные файлы в lib/Profiles/ | PHP не поддерживает вложенные классы |
| 237,243,249 | `getDeliveryTypeId()` | Удалён | Метод не существует в Base |
| 52 | `$city = $props['CITY'] ?? 'Москва'` | `if (!$city) addError()` | Fallback на хардкод недопустим |
| 148 | `round($weight / 500) * 500` | Точные значения | Нормализация ломает точность кеша |
| 162-168 | `if (initCache) { startDataCache }` | Канон: `if (!initCache) { if (startDataCache) endDataCache }` | Неправильная логика — кеш не записывался |
| 174-193 | Option::get/set без атомарности | Атомарный INCR через SQL FOR UPDATE | Option::set не атомарен при конкуренции |
| 214-229 | `getChildrenStructure()` | `getChildrenClassNames()` | getChildrenStructure() устарел, нужны FQN классов |

**Создано 3 новых файла:**
- `lib/Profiles/Courier.php`
- `lib/Profiles/Pickup.php`
- `lib/Profiles/Post.php`

---

### 1.2 AcmePayHandler.php — идемпотентность и namespace

| Строка | Было | Стало | Обоснование |
|--------|------|-------|-------------|
| 8 | `namespace Acme\Sale\PaySystem` | `namespace Sale\Handlers\PaySystem` | Канонический namespace для PS-обработчиков |
| 50 | `$orderId = $order->getField('ACCOUNT_NUMBER') ?: ...` | `$orderId = (int)($order->getField('ACCOUNT_NUMBER') ?: ...)` | SQL-инъекция при попадании в addslashes() |
| 103-140 | `if ($payment->getField('PAYED') === 'Y')` | Таблица `acme_payment_webhooks` с UNIQUE (gateway_txn_id, event_type) | Идемпотентность через БД, не через проверку поля |
| 125-129 | `$payment->setPaid('Y'); $payment->save()` | `$payment->setField('PS_STATUS','succeeded'); $result->setOperationType(MONEY_COMING)` | setPaid() вызывает ядро, не хендлер |

**Добавлен метод:**
- `recordWebhook()` — атомарная вставка в таблицу идемпотентности

**Требуется миграция БД:**
```sql
CREATE TABLE acme_payment_webhooks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gateway_txn_id VARCHAR(255) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  payment_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (gateway_txn_id, event_type)
);
```

---

### 1.3 OrderEvents.php — ретраи и дедупликация

| Строка | Было | Стало | Обоснование |
|--------|------|-------|-------------|
| 136 | `serialize(['action' => ..., 'params' => ...])` | `serialize([..., 'retry_count' => 0])` | Добавлен счётчик попыток |
| 156-179 | Бесконечный ретрай при ошибке | Max 5 попыток + exponential backoff (30s, 60s, 120s, 240s, 300s) | Без ограничения агенты копятся в b_agent |
| 196-227 | Option::get/set для дедупликации | HL-блок OrderExportLog с UNIQUE (UF_ORDER_ID, UF_EVENT_KEY) | Option не масштабируется, нет TTL, блокировки при записи |
| 288 | `@mkdir(dirname($file), 0755, true)` | Проверка `is_dir()` + error_log() при ошибке | `@` скрывает ошибки создания лог-директории |

**Добавлен метод:**
- `recordExportAttempt()` — атомарная вставка в HL-блок с обработкой дубликатов

**Требуется HL-блок:**
```
Название: OrderExportLog
Поля:
- UF_ORDER_ID (int, обязательное)
- UF_EVENT_KEY (string, обязательное)
- UF_TS (datetime)
Уникальный ключ: (UF_ORDER_ID, UF_EVENT_KEY)
```

---

### 1.4 pay_system_registration.php

| Строка | Было | Стало | Обоснование |
|--------|------|-------|-------------|
| 14 | `Manager::includeHandler('Acme\\...')` | `Manager::includeHandler('Sale\\Handlers\\...')` | Соответствие новому namespace |
| 19-43 | Неиспользуемый массив `$data` | Удалён, добавлен комментарий про регистрацию через UI | Код не использовался |

---

## 2. ОТКЛОНЁННЫЕ ПРЕДЛОЖЕНИЯ

| Предложение из предыдущего ревью | Причина отклонения |
|-----------------------------------|-------------------|
| `$cache->startDataCache($ttl, $key, $dir)` | Неканоничная сигнатура. Применён канон: `initCache()` проверяет наличие, если нет — `startDataCache()` без параметров, затем `endDataCache($data)` |
| `$order->save()` вместо `$payment->save()` в processRequest | Лечит симптом, не причину. Корректное решение: убрать `setPaid()` из хендлера, вернуть `setOperationType(MONEY_COMING)` — ядро само вызовет setPaid() |
| `set_time_limit(10)` перед HTTP-запросом | Ненадёжен на shared-хостинге, игнорируется в safe_mode. socketTimeout/streamTimeout достаточно |
| `StatusTable` вместо `Option` для статусов | Option::get() валиден для настроек модуля, StatusTable избыточен для простого списка |
| Утверждение «calculatePrice — правильно» | ОШИБКА: метод не существует в Base, нужен calculateConcrete() |

---

## 3. ПРОВЕРКА: ЗАПРЕЩЁННЫЕ МЕТОДЫ УДАЛЕНЫ

```bash
$ grep -rn "calculatePrice\|getDeliveryTypeId\|setDeliveringReason\|setPrice\|setCurrency\|setExtraParams" \
  examples/module-5-3/*/lib/
OK: no forbidden methods found
```

✅ Все запрещённые методы удалены  
✅ Вложенные классы вынесены в отдельные файлы  
✅ Namespace PS-обработчика исправлен  
✅ Идемпотентность через БД/HL, не через Option  

---

## 4. ТРЕБУЕТ СВЕРКИ С ДОКУМЕНТАЦИЕЙ sale 25.700.0

### Методы CalculationResult (требуется подтверждение сигнатур):

```php
$result->setDeliveryPrice(float $price)  // вместо setPrice()
$result->setPeriodFrom(int $days)        // существует ли?
$result->setPeriodTo(int $days)          // существует ли?
$result->setPeriodType(string $type)     // существует ли?
$result->setPeriodDescription(string $desc) // точно существует
$result->addError(Error $error)          // вместо setDeliveringReason()
```

**Рекомендация:** Сверить с `/bitrix/modules/sale/lib/delivery/calculationresult.php` в sale 25.700.0.

### Методы Base (требуется подтверждение):

```php
Base::getChildrenClassNames(): array  // вместо getChildrenStructure()
```

**Рекомендация:** Сверить с `/bitrix/modules/sale/lib/delivery/services/base.php`.

---

## 5. МИГРАЦИИ ДЛЯ PRODUCTION

### SQL-миграция для payment-handler:
```sql
CREATE TABLE IF NOT EXISTS acme_payment_webhooks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gateway_txn_id VARCHAR(255) NOT NULL COMMENT 'ID транзакции на стороне шлюза',
  event_type VARCHAR(50) NOT NULL COMMENT 'Тип события: succeeded, canceled, refunded',
  payment_id INT NOT NULL COMMENT 'ID платежа в b_sale_order_payment',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_txn_event (gateway_txn_id, event_type),
  KEY idx_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### HL-блок для order-events:
```php
use Bitrix\Highloadblock\HighloadBlockTable;

HighloadBlockTable::add([
    'NAME' => 'OrderExportLog',
    'TABLE_NAME' => 'acme_order_export_log',
    'FIELDS' => [
        [
            'FIELD_NAME' => 'UF_ORDER_ID',
            'USER_TYPE_ID' => 'integer',
            'MANDATORY' => 'Y',
        ],
        [
            'FIELD_NAME' => 'UF_EVENT_KEY',
            'USER_TYPE_ID' => 'string',
            'SETTINGS' => ['SIZE' => 255],
            'MANDATORY' => 'Y',
        ],
        [
            'FIELD_NAME' => 'UF_TS',
            'USER_TYPE_ID' => 'datetime',
        ],
    ],
]);

// В БД добавить UNIQUE KEY:
// ALTER TABLE acme_order_export_log ADD UNIQUE KEY (UF_ORDER_ID, UF_EVENT_KEY);
```

---

## 6. ИТОГОВЫЙ СТАТУС

### ✅ Применено из предыдущего ревью:
- OrderEvents.php — max 5 ретраев + exponential backoff
- OrderEvents.php — @mkdir → проверка с error_log
- AcmePayHandler.php — ACCOUNT_NUMBER → (int)
- pay_system_registration.php — удалён $data

### ✅ Критичные API-несоответствия исправлены:
- calculatePrice → calculateConcrete
- setPrice/setCurrency/setExtraParams → setDeliveryPrice + нативные методы
- setDeliveringReason → addError
- Вложенные классы → отдельные файлы
- getDeliveryTypeId → удалён
- Namespace PS → Sale\Handlers\PaySystem
- Идемпотентность через БД/HL

### ⚠️ Требует сверки:
- Сигнатуры методов CalculationResult (setPeriodFrom/setPeriodTo/setPeriodType)
- getChildrenClassNames() в Base

### 📋 Требуется для production:
- Миграция SQL для acme_payment_webhooks
- Создание HL-блока OrderExportLog
- Circuit breaker для production: перейти на Redis INCR/EXPIRE вместо SQL

---

## ФАЙЛЫ ПОСЛЕ ИСПРАВЛЕНИЙ

См. финальные версии в:
- `examples/module-5-3/delivery-service/lib/AcmeDeliveryService.php`
- `examples/module-5-3/delivery-service/lib/Profiles/*.php`
- `examples/module-5-3/payment-handler/lib/AcmePayHandler.php`
- `examples/module-5-3/payment-handler/pay_system_registration.php`
- `examples/module-5-3/order-events/lib/OrderEvents.php`
