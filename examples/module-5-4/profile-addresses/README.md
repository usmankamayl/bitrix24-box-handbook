# Кейс 3: Профили покупателя — сохранённые адреса и автозаполнение

## Что решает

«Покупатель сохранил адрес "Дом" → при следующем заказе выбрал его, и форма автозаполнилась». Это `b_sale_user_props` — не путать с `b_sale_order_props_value` (значения конкретного заказа).

## Модель данных

```
b_sale_user_props        — профили (USER_ID, PERSON_TYPE_ID, NAME='Дом')
b_sale_user_props_value  — значения свойств (USER_PROPS_ID, ORDER_PROPS_ID, VALUE)
b_sale_order_props       — сами свойства (CODE=CITY/ADDRESS/ZIP, PERSON_TYPE_ID)
```

- У каждого профиля свой `PERSON_TYPE_ID` — физлицо и юрлицо имеют разные наборы полей.
- `ORDER_PROPS_ID` → `b_sale_order_props.ID` — ссылка на свойство, а не его код. Код свойства через JOIN на `b_sale_order_props`.
- `PERSON_TYPE_ID` — не смешивай: профиль юрлица нельзя применить к заказу физлица (разные поля).

## Ключевые приёмы

1. **Разделение `UserProps` и `OrderProps`.** Профиль — шаблон, заказ — конкретные значения. Копируем из профиля → в `PropertyValueCollection` заказа → `save()`.

2. **Проверка владельца на каждый метод.** `UserPropsTable::getRowById` + `USER_ID !== $userId` → 403. Иначе можно читать/редактировать чужие адреса.

3. **`getPropsForPersonType` — фильтр по типу.** Записывать в профиль физлица свойство "ИНН" (только у юрлица) — нельзя. `array_intersect_key` по CODE.

4. **`IS_REQUIRED` — валидация.** Не хардкодить список — читаем из `b_sale_order_props`.

5. **`applyToOrder` — без `save()`.** Сервис модифицирует объект заказа в памяти, но `save()` вызывает вызывающий код — он может ещё менять корзину/доставку в одной транзакции.

6. **`getAsFormData` — для JS.** Возвращает плоский объект `{city, address, zip}` — фронт подставляет в форму без дополнительной обработки.

7. **`array_intersect_key` против лишних полей.** Клиент мог передать `is_admin=true` в props — фильтруем по валидным кодам свойств.

8. **`deleteByUserPropsId` — очистка значений.** Перед перезаписью удаляем старые `user_props_value`, иначе дубли.

## Установка

1. `lib/UserProfileService.php` → `/local/php_interface/include/acme/lib/`.
2. `profile_ajax.php` → `/local/ajax/profile.php`.
3. В админке: Магазин → Настройки → Свойства заказа — убедиться, что у PersonType есть `CITY`, `ADDRESS`, `ZIP`, `PHONE`, `EMAIL` с `ACTIVE=Y`.

## Тестирование

- `list` → только свои профили.
- `save` с чужим `profile_id` → 403.
- `apply` на заказ другого PersonType → ошибка или пустой результат (фильтр по `person_type_id` в `getPropsForPersonType`).
- Сохранить профиль → при следующем заказе `apply` подставил адрес → `Order::save()` закрепил значения.
