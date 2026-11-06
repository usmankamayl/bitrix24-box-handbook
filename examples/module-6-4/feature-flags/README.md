# Кейс 3: Feature Flags — безопасный rollout новой фичи

## Что показывает

Новый checkout — выкатываем постепенно: 10% → 50% → 100%. Без деплоя, с мгновенным отключением.

## Архитектурные решения

### 1. **Детерминированное хеширование — не `rand()`**

```php
$hash = crc32($userId . ':' . $feature);
$bucket = $hash % 100;
return $bucket < $percent;
```

- **Проблема `rand()`:** при обновлении страницы пользователь видит то старый, то новый UI — сломанный UX.
- **Решение:** `crc32(USER_ID + feature)` — всегда одно число для одного пользователя. Пользователь 123 для `checkout_v2` всегда в одной группе.

### 2. **`Option`, не код — выключение без деплоя**

```php
Option::get('acme_features', 'checkout_v2', 'N') === 'Y'
```

- Фичу можно выключить из админки за 1 секунду — не ждём деплоя.
- Rollout меняется через `Option::set('acme_features', 'checkout_v2_rollout', 50)`.

### 3. **Три уровня включения**

```
Глобальный флаг: isEnabled('checkout_v2')?
    ↓ да
Rollout %: isInRollout(userId, feature, 25)?
    ↓ да
Beta-группа: isInGroup(userId, feature, ['beta_testers'])?
    → оба пути дают включение
```

### 4. **Метрика exposure + conversion**

```sql
-- Кто увидел фичу
INSERT INTO acme_feature_exposure (USER_ID, FEATURE, EXPOSED_AT) VALUES (?, ?, NOW());

-- Конверсия: из увидевших — сколько оформили заказ
SELECT COUNT(DISTINCT USER_ID) FROM acme_feature_exposure fe
JOIN b_sale_order o ON o.USER_ID = fe.USER_ID
WHERE fe.FEATURE = 'checkout_v2' AND o.DATE_INSERT > fe.EXPOSED_AT;
```

- Без метрики feature flag — бесполезен: не знаем, лучше или хуже.

### 5. **Подмена компонента, не ветвление внутри**

```php
if (GradualRollout::isEnabledForUser($userId, 'checkout_v2')) {
    return $this->renderV2(); // отдельный компонент/сервис
}
return $this->renderV1(); // старый, стабильный
```

- **Не:** `if ($flag) { /* 100 строк нового кода */ } else { /* старый */ }` — смешиваем версии.
- **Да:** два отдельных метода/компонента — легко удалить старый после 100% rollout.

## Как отвечать на собеседовании

> «Feature flag — `Option`, выключается без деплоя. Rollout по проценту — детерминированное хеширование `crc32(USER_ID + feature)`, чтобы не мигать между версиями. Beta-группы для раннего доступа. Метрика exposure + conversion — без неё не знаем, лучше или хуже. Подменяем компонент целиком, не ветвимся внутри.»

## Что спросят

- «Почему `crc32`, а не `rand()`?» — `rand()` меняется при каждом запросе — пользователь видит то старый, то новый UI. `crc32` — детерминирован.
- «Как выключить фичу при инциденте?» — `Option::set('acme_features', 'checkout_v2', 'N')` — мгновенно, без деплоя.
- «Что если фича работает только для API, не для веб?» — тот же флаг, но проверяем в API-контроллере, не в компоненте.
- «Как удалить флаг после 100% rollout?» — `if (isEnabledForUser)` → `renderV2()` безусловно, удаляем `renderV1` и флаг.
