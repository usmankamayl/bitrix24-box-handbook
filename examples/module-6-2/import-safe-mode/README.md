# Кейс 1: «Импорт из 1С убивает сайт»

## Что показывает

Реальная ситуация: ночная выгрузка 50К товаров из 1С кладёт сайт. Классический кейс на senior-собеседовании.

## Причины боли (что искать)

| Симптом | Причина | Как найти |
|---------|---------|-----------|
| 500-е на фронте во время импорта | Обработчики `OnAfterIBlockElementUpdate` делают тяжёлую работу | SqlTracker + лог по времени |
| Импорт длится 4+ часа | `CIBlockElement::Add/Update` в цикле — каждый вызов ~10 событий | Профилирование |
| Фасетный поиск отдаёт stale | `PropertyIndex\Manager` пересчитывается на каждую запись | `b_iblock_property_index` |
| Кеш сайта пуст после импорта | `ClearAll()` после каждого элемента | `cache.log` |

## Ключевые решения

### 1. `ImportMode` — глобальный флаг

```php
final class ImportMode {
    public static function isActive(): bool {
        $ts = (int)Option::get('acme_catalog', 'acme_import_running', 0);
        return $ts > 0 && (time() - $ts) < 7200; // защита от зависшего флага
    }
}
```

- `Option`, не `$_SESSION` — у агента сессия своя.
- TTL 2 часа — если скрипт упал, флаг не останется навсегда.
- Все обработчики проверяют `ImportMode::isActive()` и возвращаются — пропускают SEO/картинки/пиксели.

### 2. Batch-запись без `CIBlockElement`

```php
// Anti-pattern:
foreach ($items as $item) {
    $el = new CIBlockElement();
    $el->Add($fields); // ~10 событий + кеш + фасета на КАЖДУЮ запись
}

// Senior:
$batches = array_chunk($items, 500);
foreach ($batches as $batch) {
    ElementTable::add([...]); // прямая запись, без событий
    usleep(50_000);           // пауза между батчами — даём фронту дышать
}
```

### 3. Отложенная инвалидация — ОДИН раз в конце

```php
// НЕ после каждого элемента:
$GLOBALS['CACHE_MANAGER']->ClearAll();

// А в конце импорта:
$tagged = Application::getInstance()->getTaggedCache();
$tagged->clearByTag('iblock_id_' . $this->iblockId);
```

### 4. `try/finally` для флага

```php
try {
    foreach ($batches as $batch) { /* ... */ }
} finally {
    ImportMode::disable(); // сработает даже при фатале
}
```

Если скрипт упрётся в memory_limit и не выключит флаг — сайт навсегда останется в "import mode".

### 5. Фасетный индекс — отдельным агентом

```php
\CAgent::AddAgent(
    "\\CIBlock::ClearIblockTagCache({$this->iblockId});
     \\Bitrix\\Iblock\\PropertyIndex\\Manager::updateElementIndex({$this->iblockId}, 0);",
    'iblock', 'N', 0
);
```

Не блокируем импорт на 30+ минут индексации.

## Senior-ответ на собеседовании

> «Сначала диагностика — SqlTracker на время импорта. Если события — ввожу `ImportMode` флаг, обработчики пропускают. Если полная выгрузка — переключаю на дельту по `TIMESTAMP_X`. Запись — batch по 500 через `ElementTable`, не `CIBlockElement`. Фасета и кеш — отложенная инвалидация. Запуск — через CLI-агент, не веб. Проверяю метрику: импорт < 1ч, фронт < 500мс во время импорта.»

## Что спросят

- «Почему `Option`, а не `$_SESSION`?» — у агента сессия своя, фронт и агент не разделяют `$_SESSION`.
- «Зачем TTL на флаг?» — если скрипт упадёт, флаг не останется — иначе сайт "забывает" обновляться.
- «Почему `try/finally`, а не `try/catch`?» — `finally` сработает и на фатале (memory_limit), `catch` — нет.
- «Что если импорт длится 8 часов, а 50К — это половина каталога?» — параллельные воркеры по `xml_id` диапазонам, либо импорт в отдельную таблицу + `RENAME TABLE` (swap).
