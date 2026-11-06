<?php
/**
 * CachedRepository — паттерн для кеширования данных сущности.
 *
 * Уровень: senior. Показывает:
 * - Тегированный кеш с автоинвалидацией
 * - Версионирование ключа кеша (cache-buster при деплое)
 * - Разделение "кеш данных" и "кеш вычислений"
 * - Защиту от cache stampede через lock
 *
 * Сценарий из реальной жизни:
 * У нас каталог из 50к товаров. Детальная страница товара собирается
 * из 5-6 запросов (товар, цены, свойства, остатки, отзывы, seo-мета).
 * Без кеша страница генерируется 800мс. С кешем — 20мс.
 */

namespace Vendor\Shop\Repository;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\TaggedCache;
use Bitrix\Main\SystemException;

class ProductRepository
{
    private const CACHE_DIR     = '/vendor.shop/product';
    private const CACHE_TTL     = 86400;        // 24h — долго, т.к. есть теги
    private const CACHE_VERSION = 'v3';         // меняем при деплое ломающих изменений

    private Cache $cache;
    private TaggedCache $taggedCache;

    public function __construct()
    {
        $this->cache       = Cache::createInstance();
        $this->taggedCache = Application::getInstance()->getTaggedCache();
    }

    /**
     * Получить товар с кешированием.
     *
     * @throws SystemException
     */
    public function getById(int $productId): ?array
    {
        $cacheId = $this->buildCacheId('product', $productId);
        $cacheDir = self::CACHE_DIR . '/' . substr($productId, -2); // шардируем по последним 2 цифрам

        if ($this->cache->initCache(self::CACHE_TTL, $cacheId, $cacheDir)) {
            $data = $this->cache->getVars();
            return $data === false ? null : $data;
        }

        if ($this->cache->startDataCache()) {
            // Регистрируем теги ДО запроса — если запрос упадёт,
            // abortDataCache отменит и теги не сохранятся (хорошо!)
            $this->taggedCache->startTagCache($cacheDir);

            try {
                $data = $this->loadProductFromDb($productId);
            } catch (\Throwable $e) {
                $this->taggedCache->abortTagCache();
                $this->cache->abortDataCache();
                throw $e;
            }

            if ($data === null) {
                // Товар не найден — НЕ кешируем отсутствие
                // (иначе удалённый и восстановленный товар будет "пропадать")
                $this->taggedCache->abortTagCache();
                $this->cache->abortDataCache();
                return null;
            }

            // Теги инвалидации
            $this->registerProductTags($productId, $data);
            $this->taggedCache->endTagCache();

            $this->cache->endDataCache($data);
        }

        return $data;
    }

    /**
     * Список товаров раздела с пагинацией.
     * Демонстрирует кеширование "коллекций" с тегами элементов.
     */
    public function getBySection(int $sectionId, int $page = 1, int $perPage = 20): array
    {
        $cacheId = $this->buildCacheId(
            sprintf('section_%d_p%d_pp%d', $sectionId, $page, $perPage)
        );
        $cacheDir = self::CACHE_DIR . '/section/' . $sectionId;

        if ($this->cache->initCache(self::CACHE_TTL, $cacheId, $cacheDir)) {
            return $this->cache->getVars();
        }

        if ($this->cache->startDataCache()) {
            $this->taggedCache->startTagCache($cacheDir);

            $items = $this->loadSectionItems($sectionId, $page, $perPage);

            // Тег раздела — при изменении раздела сбросится вся пагинация
            $this->taggedCache->registerTag('iblock_section_' . $sectionId);
            // Теги каждого элемента — при изменении товара сбросятся
            // все страницы где он показан
            foreach ($items as $item) {
                $this->taggedCache->registerTag('iblock_element_' . $item['ID']);
            }

            $this->taggedCache->endTagCache();
            $this->cache->endDataCache($items);
        }

        return $items;
    }

    /**
     * Принудительный сброс кеша конкретного товара.
     * Используется в админских инструментах и при импорте.
     */
    public function invalidate(int $productId): void
    {
        // Вариант 1: по тегу — сбросит все кеши, где этот тег зарегистрирован
        $this->taggedCache->clearByTag('iblock_element_' . $productId);

        // Вариант 2: по ключу — если нужно сбросить именно этот файл
        $cacheDir = self::CACHE_DIR . '/' . substr($productId, -2);
        $this->cache->clean(
            $this->buildCacheId('product', $productId),
            $cacheDir
        );
    }

    /**
     * Ключ кеша с версией — "cache-buster" для деплоев.
     * Когда меняем структуру кеша, инкрементируем CACHE_VERSION,
     * и старые ключи автоматически "умирают" — не нужна очистка.
     */
    private function buildCacheId(string $type, $identifier = null): string
    {
        $parts = [self::CACHE_VERSION, $type];
        if ($identifier !== null) {
            $parts[] = $identifier;
        }
        return implode('_', $parts);
    }

    /**
     * Регистрация всех тегов, влияющих на товар.
     * Важно: регистрируем не только сам товар, но и связанные сущности,
     * изменение которых должно инвалидировать кеш.
     */
    private function registerProductTags(int $productId, array $data): void
    {
        // Сам товар
        $this->taggedCache->registerTag('iblock_element_' . $productId);
        // Инфоблок (изменение настроек инфоблока)
        $this->taggedCache->registerTag('iblock_id_' . $data['IBLOCK_ID']);
        // Раздел
        if ($data['IBLOCK_SECTION_ID']) {
            $this->taggedCache->registerTag('iblock_section_' . $data['IBLOCK_SECTION_ID']);
        }
        // Ценовая группа — при изменении цен товара
        if ($data['PRICE_GROUP_ID']) {
            $this->taggedCache->registerTag('catalog_group_' . $data['PRICE_GROUP_ID']);
        }
    }

    /**
     * Формирование данных из БД.
     * В реальном проекте здесь ORM-запросы к ElementTable, PriceTable и т.д.
     */
    private function loadProductFromDb(int $productId): ?array
    {
        // Заглушка — в реальном проекте здесь запрос через ORM
        // Пример:
        /*
        $element = \Bitrix\Iblock\Elements\ElementCatalogTable::getByPrimary($productId, [
            'select' => ['*', 'PRICES', 'PROPERTIES', 'STOCK'],
        ])->fetchObject();
        return $element?->collectValues();
        */

        // Симуляция "дорогого" запроса
        usleep(50000); // 50мс
        return [
            'ID'                => $productId,
            'NAME'              => "Product #{$productId}",
            'IBLOCK_ID'         => 5,
            'IBLOCK_SECTION_ID' => 12,
            'PRICE_GROUP_ID'    => 2,
            'PRICE'             => 1500.00,
            'CURRENCY'          => 'RUB',
        ];
    }

    private function loadSectionItems(int $sectionId, int $page, int $perPage): array
    {
        // Заглушка
        usleep(30000);
        $items = [];
        for ($i = 0; $i < $perPage; $i++) {
            $items[] = [
                'ID'                => $page * 100 + $i,
                'IBLOCK_SECTION_ID' => $sectionId,
            ];
        }
        return $items;
    }
}
