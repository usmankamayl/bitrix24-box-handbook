# Задача 3: Настройка Composite

## Что в файле

`settings.php` — production-ready конфигурация Composite:

- **Redis-хранилище** — не файлы (медленно на NFS и при тысячах файлов)
- **TTL 120с** — баланс актуальности и нагрузки
- **`auto_update`** — при изменении элемента кеш пересобирается сам
- **`exclude_mask`** — личный кабинет, корзина, авторизация не кешируются
- **`exclude_params`** — UTM-метки не создают тысячи копий кеша
- **`exclude_cookies`** — авторизованные пользователи всегда динамика

## Senior-инсайты

1. **`'db' => 2` для redis composite** — отдельная база, не смешиваем с другими кешами. При `flushdb` не сломаем managed cache.

2. **`exclude_cookies`** — авторизованный пользователь = персонализация → не кешируем. Иначе админ увидит кеш гостя.

3. **`auto_update_ttl`** — при серии изменений (импорт) кеш не генерируется 100 раз, а один раз через 60с после последнего.

4. **Debug-ловушки**:
   - `X-Bitrix-Composite: dynamic` на всех страницах → ищи компонент с `setFrameMode(false)` или warning в PHP
   - Пустой `/bitrix/composite_cache/` → composite не срабатывает, проверь exclude
   - BOM в начале файла → убивает буферизацию

## Отладка

```bash
# Проверить заголовок
curl -I https://site.com/

# Сбросить кеш
rm -rf /bitrix/composite_cache/en/*

# CLI
php -r "require 'prolog_before.php'; print_r(\Bitrix\Main\Composite\Engine::getStatus());"
```

## Вопросы на собеседовании

- "Как исключить страницу из composite?" → exclude_mask
- "Почему UTM-метки ломают composite?" → каждая метка = свой кеш
- "Что делать, если авторизованный пользователь видит кеш гостя?" → exclude_cookies с BITRIX_SM_*
