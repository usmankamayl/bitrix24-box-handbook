<?php
/**
 * Защита от Cache Stampede через mutex-паттерн.
 *
 * Проблема: кеш истёк, 200 параллельных запросов все бросились
 * пересчитывать "тяжёлые" данные → БД умирает.
 *
 * Решения senior-уровня:
 * 1. Mutex/lock — один вычисляет, остальные ждут/отдают stale
 * 2. Probabilistic early recomputation (XFetch)
 * 3. Stale-while-revalidate
 */

namespace Vendor\Shop\Cache;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;

class StampedeProtectedCache
{
    private const LOCK_DIR = '/locks';
    private const LOCK_TTL = 30;       // макс. время удержания lock
    private const STALE_TTL = 300;     // stale-кеш живёт ещё 5 мин после TTL

    private Cache $cache;
    private Cache $lockCache;

    public function __construct()
    {
        $this->cache     = Cache::createInstance();
        $this->lockCache = Cache::createInstance();
    }

    /**
     * Stale-while-revalidate паттерн.
     *
     * Храним кеш вместе с его "временем свежести". Когда TTL истёк,
     * но stale-TTL ещё нет — отдаём устаревшие данные, а ОДИН запрос
     * (получивший lock) пересчитывает в фоне.
     */
    public function remember(
        string $cacheId,
        string $cacheDir,
        int $ttl,
        callable $producer
    ) {
        // Пытаемся взять "свежие" данные
        if ($this->cache->initCache($ttl, $cacheId, $cacheDir)) {
            return $this->cache->getVars();
        }

        // TTL истёк. Пытаемся взять stale-данные (срок хранения больше)
        $staleKey = $cacheId . '_stale';
        $hasStale = $this->cache->initCache($ttl + self::STALE_TTL, $staleKey, $cacheDir);

        // Пробуем захватить lock — кто первый, тот и пересчитывает
        $lockId = 'lock_' . $cacheId;
        if ($this->acquireLock($lockId)) {
            try {
                $fresh = $producer();

                // Сохраняем свежие данные и их stale-копию
                $this->cache->startDataCache();
                $this->cache->endDataCache($fresh);

                $this->cache->startDataCache(0, $staleKey, $cacheDir);
                $this->cache->endDataCache($fresh);

                return $fresh;
            } finally {
                $this->releaseLock($lockId);
            }
        }

        // Lock занят — другой процесс пересчитывает.
        // Отдаём stale, если есть. Иначе ждём с таймаутом.
        if ($hasStale) {
            return $this->cache->getVars();
        }

        // Нет stale — ждём lock до 5 секунд, потом считаем сами
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            usleep(50000); // 50мс
            if ($this->cache->initCache($ttl, $cacheId, $cacheDir)) {
                return $this->cache->getVars();
            }
        }

        // Не дождались — считаем сами (последняя линия защиты)
        return $producer();
    }

    /**
     * Probabilistic early expiration (XFetch algorithm).
     *
     * До истечения TTL начинаем с растущей вероятностью пересчитывать
     * кеш — так он обновляется "плавно", а не всеми сразу.
     *
     * @param float $beta  коэффициент агрессивности (1.0 — стандарт)
     */
    public function rememberXFetch(
        string $cacheId,
        string $cacheDir,
        int $ttl,
        callable $producer,
        float $beta = 1.0
    ) {
        $entry = null;
        $fresh = false;

        if ($this->cache->initCache($ttl * 2, $cacheId, $cacheDir)) {
            $entry = $this->cache->getVars();
            // Запись вида: ['data' => ..., 'created' => ts, 'ttl' => int]
            $age = time() - $entry['created'];
            $remaining = $entry['ttl'] - $age;

            if ($remaining > 0) {
                // Формула XFetch: вероятность растёт при приближении к TTL
                $delta = -log(mt_rand() / mt_getrandmax()) / $beta;
                $fresh = ($remaining >= $delta * $entry['ttl']);
            }
        }

        if ($fresh && $entry) {
            return $entry['data'];
        }

        // Пересчитываем (внутри producer можно добавить lock)
        $data = $producer();

        if ($this->cache->startDataCache(0, $cacheId, $cacheDir)) {
            $this->cache->endDataCache([
                'data'    => $data,
                'created' => time(),
                'ttl'     => $ttl,
            ]);
        }

        return $data;
    }

    private function acquireLock(string $lockId): bool
    {
        // Memcached::add — атомарная операция, идеальна для lock
        // В файловом кеше — эмулируем через initCache
        if ($this->lockCache->initCache(self::LOCK_TTL, $lockId, self::LOCK_DIR)) {
            return false; // lock уже есть
        }
        $this->lockCache->startDataCache();
        $this->lockCache->endDataCache(true);
        return true;
    }

    private function releaseLock(string $lockId): void
    {
        $this->lockCache->clean($lockId, self::LOCK_DIR);
    }
}
