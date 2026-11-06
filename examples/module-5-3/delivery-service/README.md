# Кейс 2: Кастомная служба доставки с расчётом по API

## Что решает

«Рассчитай доставку по API СДЭК/DPD/своего сервиса» — типичная senior-задача. Ошибки:

- дёргают API прямо в `calculatePrice` без кеша → страница заказа висит 5+ сек при каждом изменении поля;
- не проверяют таймаут/ошибку API → `Order::save()` падает, пользователь видит WSOD;
- возвращают `float` вместо `CalculationResult` → не отображаются сроки/описание;
- не используют restrictions → служба показывается где не надо (вся Россия для курьерки).

## Структура

```
delivery-service/
├── lib/
│   └── AcmeDeliveryService.php    # Base + кеш + circuit breaker + профили
├── delivery_registration.php      # регистрация через Manager
└── README.md
```

## Ключевые приёмы

1. **Кеш по (city + weight + price).** Одинаковые параметры — одинаковый ответ API. `round()` нормализует вес/цену чтобы разные пользователи с близкими корзинами шарили кеш. TTL 30 мин — тарифы не меняются чаще.

2. **Circuit breaker через `Option`.** 3 подряд ошибки → 5 минут "не ходим в API", возвращаем `CalculationResult` с `deliveringReason`. Страница заказа продолжает работать — пользователь просто не видит эту службу.

3. **Таймаут 5 сек на HTTP.** Без этого `calculatePrice` может висеть 30s и `Order::save()` отваливается по max_execution_time.

4. **`CalculationResult`, а не float.** Иначе не отобразятся `period`/`periodDescription`/`extraParams` — а менеджеры ждут "2-3 дня" и список ПВЗ.

5. **`getChildrenStructure()` — профили.** Курьер/ПВЗ/почта — это **три записи в `b_sale_delivery_srv` с PARENT_ID**, а не "одна служба с параметром". У каждой свои restrictions и настройки.

6. **`Restrictions` — до расчёта.** `ByLocation`, `ByPaySystem`, `ByWeight`, `ByDimensions` применяются в `Manager::getList` — `calculatePrice` вообще не вызовется, если служба не подходит. Не дублируйте эту логику в хендлере.

7. **`setDeliveringReason`** — текст, который увидит пользователь в списке служб ("недоступно", "временно не считаем") — не пустой результат.

8. **Свойства заказа через `getPropertyCollection()`.** Не `CSaleOrderPropsValue::GetList` — объектная модель уже подгружена, вторая выборка лишняя.

## Установка

1. `lib/AcmeDeliveryService.php` → `/local/php_interface/include/sale_delivery/acme/AcmeDeliveryService.php`.
2. Админка → Магазин → Настройки → Службы доставки → Добавить → класс `Acme\Sale\Delivery\AcmeDeliveryService`.
3. Настроить `Restrictions` (местоположение, тип плательщика, способы оплаты).
4. Если нужны профили — убедиться, что `getChildrenStructure()` отдаёт массив.

## Тестирование

- Отключить API → убедиться, что страница заказа открывается и служба показывает "недоступно" (не белый экран).
- 3 подряд запроса → circuit breaker, 4-й не ходит в API.
- Разные города → разные записи в `/upload/tmp/acme_delivery/`.
- Одинаковые корзины разных пользователей → шарят кеш.
