<?php
/**
 * Кейс 6.2.1 — «Импорт из 1С убивает сайт»
 *
 * Проблема: ночная выгрузка 50К товаров — сайт лагает, фронт 500-ит.
 * Причины: события на каждой записи, реиндекс фасеты, сброс кеша,
 * тяжёлые обработчики OnBeforeIBlockElement*.
 *
 * Решение: "import mode" — глобальный флаг, который отключает
 * дорогие побочные эффекты на время импорта + batch-запись +
 * отложенная инвалидация.
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

// ============================================================================
// 1. Флаг режима импорта — главный переключатель
// ============================================================================
//
// Устанавливается импорт-скриптом/агентом, читается обработчиками событий.
// Senior-пойнт: не $_SESSION (сессия у агента своя), а Option или define.

final class ImportMode
{
    private const OPTION_NAME = 'acme_import_running';

    public static function enable(): void
    {
        \Bitrix\Main\Config\Option::set('acme_catalog', self::OPTION_NAME, time());
    }

    public static function disable(): void
    {
        \Bitrix\Main\Config\Option::delete('acme_catalog', ['name' => self::OPTION_NAME]);
    }

    public static function isActive(): bool
    {
        $ts = (int)\Bitrix\Main\Config\Option::get('acme_catalog', self::OPTION_NAME, 0);
        // Защита от зависшего флага: если старше 2 часов — считаем выключенным
        return $ts > 0 && (time() - $ts) < 7200;
    }
}

// ============================================================================
// 2. Обработчики событий — проверяют флаг и выходят
// ============================================================================
//
// Классическая боль: в OnAfterIBlockElementUpdate лежит генерация SEO-полей,
// ресайз картинок, экспорт в Яндекс.Маркет — всё это *на каждой записи*.

AddEventHandler('iblock', 'OnAfterIBlockElementUpdate', function(&$arFields) {
    if (ImportMode::isActive()) {
        return true; // пропускаем тяжёлую логику при импорте
    }
    // ... обычная логика: SEO, картинки, маркетинговые пиксели
    return true;
});

AddEventHandler('iblock', 'OnAfterIBlockElementAdd', function(&$arFields) {
    if (ImportMode::isActive()) {
        return true;
    }
    return true;
});

// ============================================================================
// 3. Сам импорт — batch, без CIBlockElement::Update на каждую запись
// ============================================================================
//
// Anti-pattern: CIBlockElement::Add/Update в цикле — каждый вызов
// дёргает ~10 событий + кеш + индекс. На 50К записей — часы.
//
// Senior-вариант: прямая запись через ElementTable + разовый реиндекс.

final class SafeImporter
{
    private const BATCH_SIZE = 500;
    private int $iblockId;

    public function __construct(int $iblockId)
    {
        $this->iblockId = $iblockId;
    }

    /**
     * Импорт списка товаров из 1С (массив ['xml_id'=>.., 'name'=>.., 'price'=>..])
     */
    public function import(array $items): array
    {
        ImportMode::enable();

        // Отключаем фасетный индекс на время импорта — иначе
        // CIBlockElement пересчитывает его на каждую запись.
        if (class_exists('\Bitrix\Iblock\PropertyIndex\Manager')) {
            \Bitrix\Iblock\PropertyIndex\Manager::markAsInvalid($this->iblockId);
        }

        $stats = ['created' => 0, 'updated' => 0, 'errors' => 0];
        $batches = array_chunk($items, self::BATCH_SIZE);

        try {
            foreach ($batches as $batch) {
                $stats = array_merge($stats, $this->importBatch($batch));
                // Такт: небольшая пауза между батчами — даём БД "подышать",
                // реплики догоняют, фронт не лагает.
                usleep(50_000); // 50ms
            }
        } finally {
            // Сработает даже при фатале в батче — критично, иначе
            // флаг import mode останется включённым навсегда.
            ImportMode::disable();
        }

        // Отложенная инвалидация — ОДИН раз в конце, не на каждый элемент.
        $this->invalidateCaches();

        // Построение фасетного индекса — отдельным агентом (не блокируем импорт).
        \CAgent::AddAgent(
            "\\CIBlock::ClearIblockTagCache({$this->iblockId}); \\Bitrix\\Iblock\\PropertyIndex\\Manager::updateElementIndex({$this->iblockId}, 0);",
            'iblock',
            'N',
            0
        );

        return $stats;
    }

    private function importBatch(array $batch): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'errors' => 0];

        // Upsert по XML_ID — сначала выясняем какие уже есть (1 запрос!)
        $xmlIds = array_column($batch, 'xml_id');
        $existing = ElementTable::getList([
            'select' => ['ID', 'XML_ID'],
            'filter' => [
                'IBLOCK_ID' => $this->iblockId,
                '=@XML_ID' => $xmlIds,
            ],
        ])->fetchAll();

        $existingMap = array_column($existing, 'ID', 'XML_ID');

        $conn = Application::getConnection();
        $conn->startTransaction();
        try {
            foreach ($batch as $item) {
                if (isset($existingMap[$item['xml_id']])) {
                    // Update — только изменённые поля, без событий
                    ElementTable::update($existingMap[$item['xml_id']], [
                        'NAME' => $item['name'],
                        'TIMESTAMP_X' => new \Bitrix\Main\Type\DateTime(),
                    ]);
                    $stats['updated']++;
                } else {
                    ElementTable::add([
                        'IBLOCK_ID' => $this->iblockId,
                        'XML_ID' => $item['xml_id'],
                        'NAME' => $item['name'],
                        'ACTIVE' => 'Y',
                    ]);
                    $stats['created']++;
                }
            }
            $conn->commitTransaction();
        } catch (\Throwable $e) {
            $conn->rollbackTransaction();
            $stats['errors'] += count($batch);
            \Bitrix\Main\Diag\Debug::writeToFile(
                ['error' => $e->getMessage(), 'batch_start' => $batch[0]['xml_id'] ?? '?'],
                '',
                '/logs/import_errors.log'
            );
        }

        return $stats;
    }

    private function invalidateCaches(): void
    {
        // Тегированная инвалидация — только затронутые страницы каталога
        $tagged = Application::getInstance()->getTaggedCache();
        $tagged->clearByTag('iblock_id_' . $this->iblockId);
        // НЕ $GLOBALS['CACHE_MANAGER']->ClearAll() — это сносит весь кеш сайта.
    }
}

// ============================================================================
// 4. Запуск — через CLI-агент, НЕ через веб (нет лимита 30с, нет нагрузки на nginx)
// ============================================================================
//
// /local/cron/import_1c.php:
//   #!/usr/bin/env php
//   <?php
//   $_SERVER['DOCUMENT_ROOT'] = '/var/www/site';
//   require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';
//   $importer = new SafeImporter(IBLOCK_CATALOG_ID);
//   $items = parseXml('/upload/1c/products.xml');
//   $stats = $importer->import($items);
//   echo json_encode($stats), PHP_EOL;
//
// crontab:
//   0 3 * * *  /usr/bin/php /local/cron/import_1c.php >> /logs/import.log 2>&1
