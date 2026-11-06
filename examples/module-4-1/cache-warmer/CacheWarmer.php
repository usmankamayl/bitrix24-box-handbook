<?php
/**
 * CacheWarmer — проактивный прогрев кеша.
 *
 * Senior-контекст: после деплоя/очистки кеша первая волна пользователей
 * не должна ждать пересчёта "тяжёлых" данных. Мы прогреваем кеш сами
 * через CLI-скрипт или агент.
 *
 * Проблемы реального прогрева:
 * 1. Нельзя делать HTTP-запросы к себе (loopback) — долго, может упасть.
 *    Лучше вызывать producer-функцию напрямую.
 * 2. Нельзя прогревать ВСЁ — только "горячие" ключи (топ-1000 товаров,
 *    главная, популярные разделы).
 * 3. Прогрев должен быть идемпотентным и безопасным при параллельном запуске.
 * 4. Мониторим прогресс — прогрев 50к товаров может занять 20 минут.
 *
 * Запуск:
 *   php cache-warmer.php --type=products --limit=1000
 *   или через cron-агент в Bitrix.
 */

namespace Vendor\Shop\Cache;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

class CacheWarmer
{
    private const BATCH_SIZE   = 50;     // обрабатываем пачками
    private const LOCK_ID      = 'cache_warmer_lock';
    private const PROGRESS_ID  = 'cache_warmer_progress';
    private const PROGRESS_DIR = '/warmer';

    /** @var callable[] */
    private array $producers = [];

    /** @var callable[] */
    private array $idProviders = [];

    public function __construct()
    {
        Loader::includeModule('iblock');
    }

    /**
     * Регистрируем тип прогрева:
     * - $idProvider возвращает список ID для прогрева
     * - $producer вычисляет данные для одного ID
     */
    public function register(
        string $type,
        callable $idProvider,
        callable $producer
    ): self {
        $this->idProviders[$type] = $idProvider;
        $this->producers[$type]   = $producer;
        return $this;
    }

    /**
     * Прогреть указанный тип данных.
     *
     * @return array{warmed: int, skipped: int, errors: int, duration: float}
     */
    public function warm(string $type, int $limit = 0, int $offset = 0): array
    {
        if (!$this->acquireLock()) {
            echo "Warmer already running, skip\n";
            return ['warmed' => 0, 'skipped' => 0, 'errors' => 0, 'duration' => 0];
        }

        $started = microtime(true);
        $stats   = ['warmed' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $ids = ($this->idProviders[$type])($limit, $offset);
            $total = count($ids);
            echo "Warming {$type}: {$total} items\n";

            foreach (array_chunk($ids, self::BATCH_SIZE) as $batchNum => $batch) {
                foreach ($batch as $id) {
                    try {
                        $this->warmItem($type, $id);
                        $stats['warmed']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->logError($type, $id, $e);
                    }
                }

                // Прогресс для мониторинга
                $processed = ($batchNum + 1) * self::BATCH_SIZE;
                $this->saveProgress($type, $processed, $total, $stats);
                echo sprintf(
                    "  Progress: %d/%d (%.1f%%) | warmed=%d errors=%d\n",
                    min($processed, $total),
                    $total,
                    min($processed, $total) / $total * 100,
                    $stats['warmed'],
                    $stats['errors']
                );

                // Небольшая пауза, чтобы не душить БД
                usleep(50000); // 50мс
            }
        } finally {
            $this->releaseLock();
            $this->saveProgress($type, 0, 0, $stats, finished: true);
        }

        $stats['duration'] = round(microtime(true) - $started, 2);
        echo "Done in {$stats['duration']}s\n";

        return $stats;
    }

    /**
     * Прогреть один элемент.
     */
    private function warmItem(string $type, $id): void
    {
        $producer = $this->producers[$type];

        // Producer сам пишет в кеш — мы просто вызываем его
        $producer($id);
    }

    /**
     * Блокировка параллельного запуска.
     */
    private function acquireLock(): bool
    {
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if ($cache->initCache(3600, self::LOCK_ID, self::PROGRESS_DIR)) {
            return false;
        }
        $cache->startDataCache();
        $cache->endDataCache(time());
        return true;
    }

    private function releaseLock(): void
    {
        \Bitrix\Main\Data\Cache::createInstance()
            ->clean(self::LOCK_ID, self::PROGRESS_DIR);
    }

    /**
     * Сохраняем прогресс — чтобы мониторинг мог показать "прогрев на 45%".
     */
    private function saveProgress(
        string $type,
        int $processed,
        int $total,
        array $stats,
        bool $finished = false
    ): void {
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $cache->startDataCache();
        $cache->endDataCache([
            'type'      => $type,
            'processed' => $processed,
            'total'     => $total,
            'stats'     => $stats,
            'finished'  => $finished,
            'updated'   => time(),
        ]);
    }

    /**
     * Получить текущий прогресс (для админки/мониторинга).
     */
    public static function getProgress(): ?array
    {
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if ($cache->initCache(3600, self::PROGRESS_ID, self::PROGRESS_DIR)) {
            return $cache->getVars();
        }
        return null;
    }

    private function logError(string $type, $id, \Throwable $e): void
    {
        // В реальном проекте — в syslog/Sentry
        $msg = sprintf(
            "[CacheWarmer] type=%s id=%s error=%s\n",
            $type, $id, $e->getMessage()
        );
        @file_put_contents(
            $_SERVER['DOCUMENT_ROOT'] . '/logs/cache_warmer.log',
            $msg,
            FILE_APPEND
        );
    }
}


// ============================================================
// Фабрика провайдеров ID — что прогревать
// ============================================================

class WarmerIdProviders
{
    /**
     * Топ-N товаров по просмотрам (или продажам).
     * В реальном проекте — из таблицы статистики или по SORT.
     */
    public static function topProducts(int $limit, int $offset): array
    {
        $limit  = $limit ?: 1000;
        $result = \CIBlockElement::GetList(
            ['SHOW_COUNTER' => 'DESC'],   // по просмотрам
            ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => $limit, 'nOffset' => $offset],
            ['ID']
        );

        $ids = [];
        while ($row = $result->Fetch()) {
            $ids[] = (int)$row['ID'];
        }
        return $ids;
    }

    /**
     * Все разделы каталога.
     */
    public static function allSections(int $limit, int $offset): array
    {
        $result = \CIBlockSection::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y'],
            false,
            ['ID'],
            ['nTopCount' => $limit ?: 500, 'nOffset' => $offset]
        );

        $ids = [];
        while ($row = $result->Fetch()) {
            $ids[] = (int)$row['ID'];
        }
        return $ids;
    }

    /**
     * Главная + популярные страницы (из статистики или хардкод).
     */
    public static function popularPages(int $limit, int $offset): array
    {
        // В реальном проекте — из таблицы статистики посещений
        return ['/', '/catalog/', '/about/', '/delivery/', '/contacts/'];
    }
}
