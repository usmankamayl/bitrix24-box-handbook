<?php
/**
 * Сервис кеширования данных API
 *
 * Обеспечивает кеширование ответов от внешних API
 * для снижения нагрузки и ускорения работы
 */

namespace Acme\Integration\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;

class CacheService
{
    /** @var bool Включено ли кеширование */
    protected $enabled;

    /** @var int TTL по умолчанию (секунды) */
    protected $defaultTtl;

    /** @var string Тип кеша */
    protected $cacheType;

    /** @var string Директория кеша */
    protected $cacheDir = 'acme.integration';

    public function __construct()
    {
        $this->enabled = Option::get('acme.integration', 'cache_enabled', 'Y') === 'Y';
        $this->defaultTtl = (int)Option::get('acme.integration', 'cache_ttl', 3600);
        $this->cacheType = Option::get('acme.integration', 'cache_type', 'files');
    }

    /**
     * Получить значение из кеша
     *
     * @param string $key Ключ кеша
     * @return mixed|null
     */
    public function get(string $key)
    {
        if (!$this->enabled) {
            return null;
        }

        $cache = Cache::createInstance();

        if ($cache->initCache($this->defaultTtl, $key, $this->cacheDir)) {
            return $cache->getVars();
        }

        return null;
    }

    /**
     * Сохранить значение в кеш
     *
     * @param string $key Ключ кеша
     * @param mixed $value Значение
     * @param int|null $ttl Время жизни (секунды)
     */
    public function set(string $key, $value, ?int $ttl = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $ttl = $ttl ?? $this->defaultTtl;

        $cache = Cache::createInstance();

        if ($cache->startDataCache($ttl, $key, $this->cacheDir)) {
            $cache->endDataCache($value);
        }
    }

    /**
     * Удалить значение из кеша
     *
     * @param string $key Ключ кеша
     */
    public function delete(string $key): void
    {
        $cache = Cache::createInstance();
        $cache->clean($key, $this->cacheDir);
    }

    /**
     * Очистить весь кеш модуля
     */
    public function clear(): void
    {
        $cache = Cache::createInstance();
        $cache->cleanDir($this->cacheDir);
    }

    /**
     * Получить или вычислить значение (кеш с callback)
     *
     * Если значение есть в кеше - вернет его.
     * Если нет - выполнит callback, сохранит результат и вернет.
     *
     * @param string $key Ключ кеша
     * @param int $ttl Время жизни
     * @param callable $callback Функция для получения данных
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback)
    {
        // Проверяем кеш
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        // Вычисляем значение
        $value = $callback();

        // Сохраняем в кеш
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Получить или вычислить значение навсегда (до явного удаления)
     *
     * @param string $key Ключ кеша
     * @param callable $callback Функция для получения данных
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback)
    {
        return $this->remember($key, 31536000, $callback); // 1 год
    }

    /**
     * Инвалидировать кеш по тегу/префиксу
     *
     * Удаляет все ключи кеша, начинающиеся с заданного префикса
     *
     * @param string $prefix Префикс ключей
     */
    public function invalidateByPrefix(string $prefix): void
    {
        $cache = Cache::createInstance();

        // Для file-based кеша можно очистить поддиректорию
        $cache->cleanDir($this->cacheDir . '/' . $prefix);
    }

    /**
     * Получить статистику кеша
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'type' => $this->cacheType,
            'default_ttl' => $this->defaultTtl,
            'directory' => $this->cacheDir,
        ];
    }
}
