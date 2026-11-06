# Кейс 1: Слоистая архитектура каталога

## Что показывает

Каталог 500К+ товаров со сложными фильтрами — как разделить на слои и вынести поиск в Elasticsearch.

## Структура

```
CatalogComponent (только arResult + шаблон)
      ↓
CatalogService (бизнес-логика: выбирает ES или БД)
      ↓
ProductRepository (ORM: ElementTable::getList)
      ↓
ElasticSearchAdapter (поиск: ID → fetch details из БД)
```

## Senior-решения

### 1. **DTO вместо Entity**

`ProductDTO` — стабильный контракт для фронта. Можем менять `ElementTable` или добавить поля — фронт не ломается.

### 2. **ES для поиска, БД для деталей**

- ES возвращает **только ID** товаров (быстро, индексировано по всем свойствам).
- Bitrix загружает детали по этим ID — цены, наличие — **актуальные** (не из кеша ES).

### 3. **Fallback на БД при падении ES**

```php
if ($this->search->isAvailable()) {
    try {
        $ids = $this->search->search($filter, $limit, $offset);
        return $this->repo->findByIds($ids);
    } catch (\RuntimeException $e) {
        // ES упал — fallback
    }
}
return $this->repo->searchInDb($dbFilter, $limit, $offset);
```

### 4. **Circuit breaker для ES**

Если ES упал 3 раза за минуту — не долбим его, сразу fallback на БД.

### 5. **Порядок из ES сохраняется**

`findByIds` сортирует по исходному порядку `$ids` — чтобы выдача соответствовала ES-релевантности.

## Когда это нужно

- **< 50K товаров, простые фильтры** → Bitrix + индексы + кеш — достаточно.
- **500K+ товаров, фасетный поиск, текстовый поиск** → ES для поиска, Bitrix для данных.
- **ML-рекомендации** → отдельный сервис, Bitrix как источник событий.

## Как отвечать на собеседовании

> «Слои: Component только собирает `arResult`, Service выбирает источник (ES или БД), Repository — ORM, Adapter — абстракция поиска. ES возвращает только ID, детали — из Bitrix (актуальные цены). Fallback на БД при падении ES. Circuit breaker — не долбим мёртвый ES.»

## Что спросят

- «Почему DTO, а не Entity?» — Entity привязан к ORM, меняется при рефакторинге. DTO — стабильный контракт.
- «Почему ES для поиска, а не для всего?» — цены/наличие должны быть актуальными — берём из БД по ID.
- «Как индексировать товары в ES?» — `OnAfterIBlockElementUpdate` → outbox → worker пушит в ES.
