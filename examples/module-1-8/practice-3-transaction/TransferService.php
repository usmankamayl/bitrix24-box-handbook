<?php

/**
 * Перенос остатка между складами: списание, зачисление, журнал — одной
 * транзакцией.
 *
 * Путь в проекте: /local/php_interface/classes/Stock/TransferService.php
 *
 * Главная мысль: у сервиса два контракта, и они не смешиваются.
 *   снаружи  — Result: вызывающий получает коды и данные, как в практике 2;
 *   внутри   — исключения: любая ошибка между startTransaction() и
 *              commitTransaction() обязана прервать выполнение, иначе коммит
 *              запишет половину операции.
 *
 * Третьего не дано: `return $result` из середины try оставит транзакцию
 * открытой, а соединение закроет её неявным откатом — иногда. «Иногда» в
 * биллинге остатков недопустимо.
 */

namespace MyCompany\Stock;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;

final class TransferService
{
    private Connection $connection;

    /**
     * Необязательный хук после списания — только чтобы показать откат при
     * инфраструктурном сбое (check.php). В рабочем коде на его месте была бы
     * отправка события, запись в очередь или вызов внешнего API.
     *
     * @var (\Closure():void)|null
     */
    private ?\Closure $afterDebit;

    public function __construct(?Connection $connection = null, ?\Closure $afterDebit = null)
    {
        $this->connection = $connection ?? Application::getConnection();
        $this->afterDebit = $afterDebit;
    }

    public function transfer(
        int $productId,
        string $warehouseFrom,
        string $warehouseTo,
        int $quantity,
        string $operationKey
    ): Result {
        $result = new Result();

        // 1. Дешёвые проверки до БД и до транзакции.
        if ($productId <= 0) {
            return $result->addError(new Error('Не указан товар.', 'PRODUCT_ID_INVALID'));
        }

        if ($quantity <= 0) {
            return $result->addError(new Error('Количество должно быть положительным.', 'QUANTITY_INVALID'));
        }

        if ($warehouseFrom === '' || $warehouseTo === '') {
            return $result->addError(new Error('Не указан склад.', 'WAREHOUSE_REQUIRED'));
        }

        if ($warehouseFrom === $warehouseTo) {
            return $result->addError(new Error('Склады отправления и назначения совпадают.', 'WAREHOUSE_SAME'));
        }

        if ($operationKey === '' || mb_strlen($operationKey) > 64) {
            return $result->addError(new Error('Некорректный ключ операции.', 'OPERATION_KEY_INVALID', [
                'max' => 64,
            ]));
        }

        // 2. Идемпотентность: повтор того же ключа — не ошибка, а «уже сделано».
        $existing = StockLogTable::getList([
            'select' => ['ID', 'QUANTITY'],
            'filter' => ['=OP_KEY' => $operationKey],
            'limit'  => 1,
        ])->fetch();

        if ($existing !== false) {
            return $result->setData([
                'duplicate' => true,
                'log_id'    => (int)$existing['ID'],
                'quantity'  => (int)$existing['QUANTITY'],
            ]);
        }

        // 3. Транзакция.
        $this->connection->startTransaction();

        try {
            $origin = $this->fetchStock($productId, $warehouseFrom, 'STOCK_NOT_FOUND');

            if ((int)$origin['QUANTITY'] < $quantity) {
                throw new TransferFailed(
                    (new Result())->addError(new Error(
                        'Недостаточно товара на складе отправления.',
                        'INSUFFICIENT_STOCK',
                        ['available' => (int)$origin['QUANTITY'], 'requested' => $quantity]
                    ))
                );
            }

            $originLeft = (int)$origin['QUANTITY'] - $quantity;
            $this->writeQuantity((int)$origin['ID'], $originLeft);
            $this->verifyQuantity((int)$origin['ID'], $originLeft, 'STOCK_WRITE_FAILED');

            if ($this->afterDebit !== null) {
                ($this->afterDebit)();
            }

            // Целевой склад мог быть удалён/не создан: это доменный отказ,
            // но он случается уже ПОСЛЕ списания, поэтому откатывать нужно всё.
            $target = $this->fetchStock($productId, $warehouseTo, 'TRANSFER_TARGET_NOT_FOUND');

            $targetNew = (int)$target['QUANTITY'] + $quantity;
            $this->writeQuantity((int)$target['ID'], $targetNew);
            $this->verifyQuantity((int)$target['ID'], $targetNew, 'STOCK_WRITE_FAILED');

            $this->writeLog($productId, $warehouseFrom, $warehouseTo, $quantity, $operationKey);

            $this->connection->commitTransaction();
        } catch (TransferFailed $domainFailure) {
            // Доменный отказ: откат и снова Result — снаружи исключений нет.
            $this->connection->rollbackTransaction();

            return $domainFailure->getResult();
        } catch (\Throwable $infrastructureFailure) {
            // Всё остальное (SQL, TypeError, отказ внешнего сервиса) — наверх.
            // Ловим Throwable, а не Exception: TypeError в PHP 8 не Exception.
            $this->connection->rollbackTransaction();

            throw $infrastructureFailure;
        }

        return $result->setData([
            'duplicate'    => false,
            'quantity'     => $quantity,
            'from_balance' => $originLeft,
            'to_balance'   => $targetNew,
        ]);
    }

    /**
     * @return array{ID:string|int, QUANTITY:string|int}
     */
    private function fetchStock(int $productId, string $warehouse, string $errorCode): array
    {
        $row = StockTable::getList([
            'select' => ['ID', 'QUANTITY'],
            'filter' => [
                '=PRODUCT_ID'     => $productId,
                '=WAREHOUSE_CODE' => $warehouse,
            ],
            'limit' => 1,
        ])->fetch();

        if ($row === false) {
            throw new TransferFailed(
                (new Result())->addError(new Error(
                    'Склад ' . $warehouse . ' не найден для товара ' . $productId . '.',
                    $errorCode,
                    ['warehouse' => $warehouse, 'product_id' => $productId]
                ))
            );
        }

        return $row;
    }

    private function writeQuantity(int $stockId, int $quantity): void
    {
        $update = StockTable::update($stockId, ['QUANTITY' => $quantity]);

        if (!$update->isSuccess()) {
            throw new TransferFailed((new Result())->addErrors($update->getErrors()));
        }
    }

    /**
     * Read-back: isSuccess() у update() не доказывает, что значение записалось —
     * строка могла исчезнуть или измениться. Проверяем факт внутри транзакции:
     * если не сошлось, откат бесплатен, данных ещё никто не увидел.
     */
    private function verifyQuantity(int $stockId, int $expected, string $errorCode): void
    {
        $row = StockTable::getById($stockId)->fetch();

        if ($row === false || (int)$row['QUANTITY'] !== $expected) {
            throw new TransferFailed(
                (new Result())->addError(new Error(
                    'Запись остатка не подтвердилась чтением.',
                    $errorCode,
                    ['stock_id' => $stockId, 'expected' => $expected]
                ))
            );
        }
    }

    private function writeLog(
        int $productId,
        string $warehouseFrom,
        string $warehouseTo,
        int $quantity,
        string $operationKey
    ): void {
        try {
            $add = StockLogTable::add([
                'PRODUCT_ID'     => $productId,
                'WAREHOUSE_FROM' => $warehouseFrom,
                'WAREHOUSE_TO'   => $warehouseTo,
                'QUANTITY'       => $quantity,
                'OP_KEY'         => $operationKey,
                'CREATED_AT'     => new DateTime(),
            ]);
        } catch (\Throwable $exception) {
            // Уникальный индекс по OP_KEY: гонка двух запросов с одним ключом.
            // Разные версии ядра отдают это либо исключением, либо Result —
            // обрабатываем оба варианта одинаково.
            throw new TransferFailed(
                (new Result())->addError(new Error(
                    'Операция с таким ключом уже выполняется.',
                    'OPERATION_IN_PROGRESS',
                    ['operation_key' => $operationKey]
                )),
                $exception
            );
        }

        if (!$add->isSuccess()) {
            throw new TransferFailed(
                (new Result())->addError(new Error(
                    'Не удалось записать операцию в журнал.',
                    'OPERATION_LOG_FAILED',
                    ['reason' => implode('; ', $add->getErrorMessages())]
                ))
            );
        }
    }
}
