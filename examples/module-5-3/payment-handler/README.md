# Кейс 1: Кастомный платёжный обработчик (AcmePay)

## Что решает

Когда встроенные ЮKassa/Robokassa не подходят (свой шлюз, маркетплейс с эквайрингом, оплата внешнего API), нужен свой PS handler. Типичные ошибки новичков:

- изменяют поля заказа в `initiatePay` — race с `Order::save()`;
- `processRequest` без проверки подписи — любой может "оплатить" чужой заказ;
- `processRequest` без идемпотентности — шлюз ретраит webhook → двойная оплата и двойное письмо;
- `getPaymentIdFromRequest` возвращает `ORDER_ID` — при двух платежах на один заказ `sale_ps_result.php` цепляет не ту платежу.

## Структура

```
payment-handler/
├── lib/
│   └── AcmePayHandler.php       # класс хендлера (initiatePay / processRequest / refund)
├── pay_system_registration.php  # регистрация в админке + Manager::includeHandler
└── README.md
```

## Ключевые приёмы (на что обратить внимание на собеседовании)

1. **`initiatePay` — только создание сессии у шлюза и возврат url.** Никакого изменения `$order` / `$payment` кроме `PS_INVOICE_ID` (свой внутренний ID у шлюза — нужен для рефандов). Заказ в этот момент ещё в `save()`.

2. **`processRequest` — идемпотентность + подпись в начале.** Сначала `isSignatureValid`, потом `if PAYED === 'Y' → return`. Шлюз ретраит webhook → второй вызов не делает повторных `setPaid` и не шлёт письмо повторно.

3. **`getPaymentIdFromRequest` возвращает `PAYMENT_ID`, не `ORDER_ID`.** Тело webhook'а содержит `payment_id` — по нему Bitrix найдёт нужную `Payment` в коллекции заказа.

4. **`setPaid('Y')` + `payment->save()`, а не `Order::setField('STATUS_ID','P')`.** `setPaid` поднимает `OnSaleOrderPaid`, который и меняет статус. Ручная правка `STATUS_ID` обходит события оплаты — не сработают чеки 54-ФЗ и автоматические сценарии.

5. **Частичный возврат через `refund($payment, $refundableSum)`.** Отдельный метод — не путать с "отменой заказа". Возврат не меняет `PAYED`, он списывает часть суммы через шлюз.

6. **Подпись — `hash_hmac` + `hash_equals`.** `hash_equals` защищает от timing-attack (сравнение за фиксированное время). Подпись считается по **сырому телу** (`getInput()`), а не по распарсенному массиву — порядок ключей JSON ломает HMAC.

7. **`setExtraParams(['REDIRECT_URL' => ...])`** — стандартный способ отдать шаблону «куда редиректить». Шаблон `template.php` ПС читает `$this->params['REDIRECT_URL']` и делает `<meta http-equiv="refresh">` или JS-redirect.

8. **`getServiceUrl($payment, 'ps_result')`** — собирает URL, на который шлюз постучится после оплаты. Не хардкодить `/bitrix/tools/sale_ps_result.php` вручную.

## Регистрация в админке

1. Скопировать `lib/AcmePayHandler.php` в `/local/php_interface/include/sale_payment/acme/handler/AcmePayHandler.php`.
2. В админке → Магазин → Настройки → Платёжные системы → Добавить.
3. Поле «Обработчик» указать `Sale\Handlers\PaySystem\AcmePayHandler` — `Manager::includeHandler` найдёт файл автоматически.
4. Заполнить `ACME_API_KEY`, `ACME_SECRET`, success/fail URLs.

**ВАЖНО:** Перед использованием выполнить SQL-миграцию:

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

## Тестирование

- Локальный webhook можно эмулировать через `curl`:

```bash
curl -X POST https://site.local/bitrix/tools/sale_ps_result.php \
  -H "Content-Type: application/json" \
  -H "X-Acme-Signature: $(echo -n '{"payment_id":123,"status":"succeeded"}' | openssl dgst -sha256 -hmac 'secret')" \
  -d '{"payment_id":123,"status":"succeeded"}'
```

- Для sandbox-тестов использовать `requestbin` или `ngrok` — чтобы шлюз видел реальный callback.
