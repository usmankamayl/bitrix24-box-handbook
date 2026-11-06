<?php

namespace BitrixCourse\PriceImport;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\StoreProductTable;
use Bitrix\Catalog\StoreTable;
use Bitrix\Catalog\GroupTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\DB\SqlQueryException;

/**
 * Ночной импорт цен и остатков из CSV.
 *
 * Требования, из-за которых всё не сводится к простому while(fgetcsv()):
 *  - файл может быть большим (сотни тысяч строк) -> читаем построчно, не грузим в память;
 *  - импорт не должен пересекаться с предыдущим незавершённым запуском -> лок через Option;
 *  - частичный сбой одной строки не должен ронять весь импорт -> батч с откатом
 *    на построчную обработку при ошибке батча;
 *  - нужна отчётность: сколько обработано/обновлено/ошибок.
 *
 * Формат CSV (с заголовком):
 * PRODUCT_XML_ID;PRICE_TYPE_CODE;PRICE;CURRENCY;QUANTITY;STORE_XML_ID
 */
class PriceStockImporter
{
    private const BATCH_SIZE = 500;
    private const LOCK_OPTION = 'course_price_import_lock_until';
    private const LOCK_TTL = 3600; // на случай, если процесс умер без снятия лока

    private ImportLogger $logger;
    /** @var array<string,int> кэш XML_ID товара -> PRODUCT_ID */
    private array $productCache = [];
    /** @var array<string,int> кэш кода типа цены -> CATALOG_GROUP_ID */
    private array $priceTypeCache = [];
    /** @var array<string,int> кэш XML_ID склада -> STORE_ID */
    private array $storeCache = [];

    public function __construct(ImportLogger $logger)
    {
        $this->logger = $logger;
    }

    public function run(string $csvPath): array
    {
        if (!Loader::includeModule('catalog') || !Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модули catalog/iblock не подключены');
        }

        if (!is_file($csvPath)) {
            throw new \InvalidArgumentException("Файл не найден: {$csvPath}");
        }

        if (!$this->acquireLock()) {
            $this->logger->warning('Импорт уже выполняется другим процессом — выход');
            return $this->logger->getStats();
        }

        try {
            $this->preloadPriceTypes();
            $this->processFile($csvPath);
        } finally {
            $this->releaseLock();
            $this->logger->finish();
        }

        return $this->logger->getStats();
    }

    /**
     * Простой distributed lock поверх таблицы опций модуля.
     * В высоконагруженном проекте — Redis SETNX, здесь — Option для наглядности принципа.
     */
    private function acquireLock(): bool
    {
        $existing = (int)Option::get('bitrix_course', self::LOCK_OPTION, 0);
        if ($existing > time()) {
            return false;
        }
        Option::set('bitrix_course', self::LOCK_OPTION, (string)(time() + self::LOCK_TTL));
        return true;
    }

    private function releaseLock(): void
    {
        Option::set('bitrix_course', self::LOCK_OPTION, '0');
    }

    private function preloadPriceTypes(): void
    {
        $iterator = GroupTable::getList(['select' => ['ID', 'NAME_LANG' => 'NAME']]);
        // Код типа цены маппим по латинской транслитерации NAME в простом виде,
        // в реальном проекте лучше завести XML_ID у b_catalog_group.
        while ($row = $iterator->fetch()) {
            $this->priceTypeCache[mb_strtoupper($row['NAME'])] = (int)$row['ID'];
        }
    }

    private function processFile(string $csvPath): void
    {
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось открыть файл: {$csvPath}");
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('Пустой CSV-файл');
        }
        $header = array_map('trim', $header);

        $batch = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNumber++;
            if (count($row) !== count($header)) {
                $this->logger->errorRow($lineNumber, 'Число колонок не совпадает с заголовком');
                continue;
            }

            $batch[] = ['line' => $lineNumber] + array_combine($header, $row);

            if (count($batch) >= self::BATCH_SIZE) {
                $this->processBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->processBatch($batch);
        }

        fclose($handle);
    }

    /**
     * Пытаемся провести батч одной транзакцией (быстро).
     * Если транзакция падает (напр. deadlock, constraint violation на
     * непредвиденных данных) — откатываем и обрабатываем построчно,
     * чтобы одна плохая строка не убила весь батч из 500 товаров.
     */
    private function processBatch(array $rows): void
    {
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            foreach ($rows as $row) {
                $this->processRow($row);
            }
            $connection->commitTransaction();
        } catch (\Throwable $e) {
            $connection->rollbackTransaction();
            $this->logger->warning(
                'Батч откачен (' . $e->getMessage() . '), переходим на построчную обработку'
            );
            $this->processRowsIndividually($rows);
        }
    }

    private function processRowsIndividually(array $rows): void
    {
        $connection = Application::getConnection();

        foreach ($rows as $row) {
            $connection->startTransaction();
            try {
                $this->processRow($row);
                $connection->commitTransaction();
            } catch (\Throwable $e) {
                $connection->rollbackTransaction();
                $this->logger->errorRow((int)$row['line'], $e->getMessage());
            }
        }
    }

    private function processRow(array $row): void
    {
        $this->logger->incProcessed();

        $productId = $this->resolveProductId($row['PRODUCT_XML_ID'] ?? '');
        if ($productId === null) {
            throw new \RuntimeException("Товар с XML_ID '{$row['PRODUCT_XML_ID']}' не найден");
        }

        $priceTypeId = $this->resolvePriceTypeId($row['PRICE_TYPE_CODE'] ?? '');
        if ($priceTypeId === null) {
            throw new \RuntimeException("Тип цены '{$row['PRICE_TYPE_CODE']}' не найден");
        }

        $price = isset($row['PRICE']) ? (float)str_replace(',', '.', $row['PRICE']) : null;
        if ($price === null || $price < 0) {
            throw new \RuntimeException('Некорректная цена');
        }

        $this->upsertPrice($productId, $priceTypeId, $price, $row['CURRENCY'] ?: 'RUB');

        if (isset($row['STORE_XML_ID']) && $row['STORE_XML_ID'] !== '') {
            $storeId = $this->resolveStoreId($row['STORE_XML_ID']);
            if ($storeId === null) {
                throw new \RuntimeException("Склад с XML_ID '{$row['STORE_XML_ID']}' не найден");
            }
            $this->upsertStock($productId, $storeId, (float)($row['QUANTITY'] ?? 0));
        }

        $this->logger->incUpdated();
    }

    private function resolveProductId(string $xmlId): ?int
    {
        if ($xmlId === '') {
            return null;
        }
        if (isset($this->productCache[$xmlId])) {
            return $this->productCache[$xmlId];
        }

        $row = ElementTable::getRow([
            'select' => ['ID'],
            'filter' => ['XML_ID' => $xmlId],
        ]);

        $id = $row ? (int)$row['ID'] : null;
        $this->productCache[$xmlId] = $id;
        return $id;
    }

    private function resolvePriceTypeId(string $code): ?int
    {
        $code = mb_strtoupper(trim($code));
        return $this->priceTypeCache[$code] ?? null;
    }

    private function resolveStoreId(string $xmlId): ?int
    {
        if (isset($this->storeCache[$xmlId])) {
            return $this->storeCache[$xmlId];
        }

        $row = StoreTable::getRow([
            'select' => ['ID'],
            'filter' => ['XML_ID' => $xmlId, 'ACTIVE' => 'Y'],
        ]);

        $id = $row ? (int)$row['ID'] : null;
        $this->storeCache[$xmlId] = $id;
        return $id;
    }

    /**
     * Идемпотентный upsert цены: обновляем, если запись для
     * (PRODUCT_ID, CATALOG_GROUP_ID, QUANTITY_FROM=null) уже есть, иначе создаём.
     */
    private function upsertPrice(int $productId, int $priceTypeId, float $price, string $currency): void
    {
        $existing = PriceTable::getRow([
            'select' => ['ID'],
            'filter' => [
                'PRODUCT_ID'        => $productId,
                'CATALOG_GROUP_ID'  => $priceTypeId,
                'QUANTITY_FROM'     => false,
                'QUANTITY_TO'       => false,
            ],
        ]);

        $fields = [
            'PRODUCT_ID'       => $productId,
            'CATALOG_GROUP_ID' => $priceTypeId,
            'PRICE'            => $price,
            'CURRENCY'         => $currency,
        ];

        if ($existing) {
            $result = PriceTable::update($existing['ID'], $fields);
        } else {
            $result = PriceTable::add($fields);
        }

        if (!$result->isSuccess()) {
            throw new \RuntimeException(
                'Ошибка записи цены: ' . implode('; ', $result->getErrorMessages())
            );
        }
    }

    private function upsertStock(int $productId, int $storeId, float $amount): void
    {
        $existing = StoreProductTable::getRow([
            'select' => ['ID'],
            'filter' => ['PRODUCT_ID' => $productId, 'STORE_ID' => $storeId],
        ]);

        $fields = ['PRODUCT_ID' => $productId, 'STORE_ID' => $storeId, 'AMOUNT' => $amount];

        if ($existing) {
            $result = StoreProductTable::update($existing['ID'], ['AMOUNT' => $amount]);
        } else {
            $result = StoreProductTable::add($fields);
        }

        if (!$result->isSuccess()) {
            throw new \RuntimeException(
                'Ошибка записи остатка: ' . implode('; ', $result->getErrorMessages())
            );
        }
    }
}
