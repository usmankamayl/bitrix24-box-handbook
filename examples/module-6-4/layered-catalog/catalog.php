<?php
/**
 * Кейс 6.4.1 — Слоистая архитектура каталога
 *
 * Задача: каталог 500К+ товаров, сложные фильтры по свойствам.
 * Показываем: разделение на слои + вынесение поиска в Elasticsearch.
 *
 * Структура:
 *   CatalogService      — бизнес-логика (какие товары показать)
 *   ProductRepository   — ORM-доступ к товарам (Bitrix)
 *   SearchAdapter       — поиск/фильтрация (ES или MySQL fallback)
 *   CatalogComponent    — только сбор arResult + шаблон
 */

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Application;

Loader::includeModule('iblock');

// ============================================================================
// 1. DTO — стабильный контракт для фронта, независимый от ORM
// ============================================================================

final class ProductDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $price,
        public readonly string $currency,
        public readonly ?string $picture,
        public readonly array $props, // ['BRAND' => 'Nike', 'COLOR' => 'red']
    ) {}

    public static function fromElement(array $row, array $props = []): self
    {
        return new self(
            id: (int)$row['ID'],
            name: $row['NAME'],
            price: (float)($row['PRICE'] ?? 0),
            currency: $row['CURRENCY'] ?? 'RUB',
            picture: $row['PREVIEW_PICTURE'] ?? null,
            props: $props,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'picture' => $this->picture,
            'props' => $this->props,
        ];
    }
}

// ============================================================================
// 2. Repository — единственное место, где знаем про ORM/SQL
// ============================================================================

final class ProductRepository
{
    private int $iblockId;

    public function __construct(int $iblockId)
    {
        $this->iblockId = $iblockId;
    }

    /**
     * Получить товары по списку ID — для отображения после поиска в ES.
     * Не делает фильтрацию — только fetch по ID.
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $result = ElementTable::getList([
            'select' => ['ID', 'NAME', 'PREVIEW_PICTURE'],
            'filter' => [
                'IBLOCK_ID' => $this->iblockId,
                '=@ID' => $ids,
            ],
        ]);

        $items = [];
        while ($row = $result->fetch()) {
            $items[$row['ID']] = ProductDTO::fromElement($row);
        }

        // Сохраняем порядок из $ids (ES вернул отсортированные ID)
        $sorted = [];
        foreach ($ids as $id) {
            if (isset($items[$id])) {
                $sorted[] = $items[$id];
            }
        }
        return $sorted;
    }

    /**
     * Полный поиск по БД — fallback, если ES недоступен.
     */
    public function searchInDb(array $filter, int $limit = 20, int $offset = 0): array
    {
        $result = ElementTable::getList([
            'select' => ['ID', 'NAME', 'PREVIEW_PICTURE'],
            'filter' => array_merge(['IBLOCK_ID' => $this->iblockId], $filter),
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $items = [];
        while ($row = $result->fetch()) {
            $items[] = ProductDTO::fromElement($row);
        }
        return $items;
    }
}

// ============================================================================
// 3. SearchAdapter — абстракция поиска. ES или fallback на БД.
// ============================================================================

interface SearchAdapterInterface
{
    /**
     * Возвращает ID товаров по фильтру — отсортированные, с пагинацией.
     * Не возвращает сами товары — только ID.
     */
    public function search(array $filter, int $limit, int $offset): array;

    /**
     * Доступен ли поисковый бэкенд?
     */
    public function isAvailable(): bool;
}

final class ElasticSearchAdapter implements SearchAdapterInterface
{
    private string $esUrl;
    private string $index;

    public function __construct()
    {
        $this->esUrl = \Bitrix\Main\Config\Option::get('acme_catalog', 'es_url', 'http://localhost:9200');
        $this->index = 'products';
    }

    public function isAvailable(): bool
    {
        // Circuit breaker — если ES лежал, не долбим его каждым запросом
        $failures = (int)\Bitrix\Main\Config\Option::get('acme_catalog', 'es_failures', 0);
        $windowStart = (int)\Bitrix\Main\Config\Option::get('acme_catalog', 'es_window_start', 0);

        if (time() - $windowStart > 60) {
            return true; // окно истекло — пробуем снова
        }
        return $failures < 3;
    }

    public function search(array $filter, int $limit, int $offset): array
    {
        // Строим ES-запрос из фильтра
        $esQuery = $this->buildEsQuery($filter);

        $ch = curl_init("{$this->esUrl}/{$this->index}/_search");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($esQuery),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            $this->recordFailure();
            throw new \RuntimeException('ES unavailable: ' . $errno);
        }
        $this->recordSuccess();

        $data = json_decode($response, true);
        $hits = $data['hits']['hits'] ?? [];

        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = (int)$hit['_source']['product_id'];
        }
        return $ids;
    }

    private function buildEsQuery(array $filter): array
    {
        // Преобразуем Bitrix-фильтр в ES-запрос
        $must = [];
        if (isset($filter['section_id'])) {
            $must[] = ['term' => ['section_id' => $filter['section_id']]];
        }
        if (isset($filter['price_min']) || isset($filter['price_max'])) {
            $range = [];
            if (isset($filter['price_min'])) $range['gte'] = $filter['price_min'];
            if (isset($filter['price_max'])) $range['lte'] = $filter['price_max'];
            $must[] = ['range' => ['price' => $range]];
        }
        if (isset($filter['props'])) {
            foreach ($filter['props'] as $code => $value) {
                $must[] = ['term' => ["props.{$code}.keyword" => $value]];
            }
        }

        return [
            'query' => ['bool' => ['must' => $must]],
            'size' => $limit,
            'from' => $offset,
            'sort' => [['_score' => 'desc'], ['product_id' => 'desc']],
        ];
    }

    private function recordFailure(): void
    {
        $windowStart = (int)\Bitrix\Main\Config\Option::get('acme_catalog', 'es_window_start', 0);
        if (time() - $windowStart > 60) {
            \Bitrix\Main\Config\Option::set('acme_catalog', 'es_window_start', time());
            \Bitrix\Main\Config\Option::set('acme_catalog', 'es_failures', 1);
        } else {
            \Bitrix\Main\Config\Option::set(
                'acme_catalog',
                'es_failures',
                (int)\Bitrix\Main\Config\Option::get('acme_catalog', 'es_failures', 0) + 1
            );
        }
    }

    private function recordSuccess(): void
    {
        \Bitrix\Main\Config\Option::set('acme_catalog', 'es_failures', 0);
    }
}

// ============================================================================
// 4. CatalogService — бизнес-логика: выбирает источник данных
// ============================================================================

final class CatalogService
{
    public function __construct(
        private ProductRepository $repo,
        private SearchAdapterInterface $search,
    ) {}

    public function getProducts(array $filter, int $limit = 20, int $offset = 0): array
    {
        // Стратегия: ES для поиска, БД для fetch по ID
        if ($this->search->isAvailable()) {
            try {
                $ids = $this->search->search($filter, $limit, $offset);
                // Детали товара — из Bitrix (цены, наличие — актуальные)
                return $this->repo->findByIds($ids);
            } catch (\RuntimeException $e) {
                // ES упал — fallback на БД
            }
        }

        // Fallback: прямой поиск в БД (медленнее, но работает)
        return $this->repo->searchInDb($this->toDbFilter($filter), $limit, $offset);
    }

    private function toDbFilter(array $filter): array
    {
        // Преобразуем высокоуровневый фильтр в формат D7
        $dbFilter = [];
        if (isset($filter['section_id'])) {
            $dbFilter['IBLOCK_SECTION_ID'] = $filter['section_id'];
        }
        // ... остальные поля
        return $dbFilter;
    }
}

// ============================================================================
// 5. Компонент — только сбор данных + шаблон
// ============================================================================

class CatalogComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        $service = new CatalogService(
            new ProductRepository($this->arParams['IBLOCK_ID']),
            new ElasticSearchAdapter(),
        );

        $filter = [
            'section_id' => (int)($this->arParams['SECTION_ID'] ?? 0),
            'price_min' => (float)($this->arParams['PRICE_MIN'] ?? 0),
            'price_max' => (float)($this->arParams['PRICE_MAX'] ?? 0),
            'props' => $this->arParams['FILTER_PROPS'] ?? [],
        ];

        $products = $service->getProducts($filter, 20, 0);

        // Кеш с тегами — инвалидация при изменении товаров
        $cacheId = 'catalog_' . md5(serialize($filter));
        if ($this->startResultCache(3600, $cacheId)) {
            $this->setResultCacheKeys(['PRODUCTS']);
            $this->arResult['PRODUCTS'] = array_map(fn($p) => $p->toArray(), $products);
            $this->includeComponentTemplate();
        }
    }
}
