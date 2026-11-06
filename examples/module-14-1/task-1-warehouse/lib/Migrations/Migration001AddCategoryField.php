<?php
/**
 * Миграция 001: Добавление поля CATEGORY
 *
 * Добавляет поле для категории товаров в таблицу
 */

namespace Acme\Warehouse\Migrations;

use Bitrix\Main\Application;

class Migration001AddCategoryField
{
    /**
     * Применить миграцию
     *
     * Добавляет поле CATEGORY в таблицу товаров
     */
    public function up()
    {
        $connection = Application::getConnection();

        // Проверяем, не существует ли уже поле
        $columns = $connection->getTableFields('acme_warehouse_products');

        if (!isset($columns['CATEGORY'])) {
            $connection->query("
                ALTER TABLE `acme_warehouse_products`
                ADD COLUMN `CATEGORY` VARCHAR(100) DEFAULT NULL COMMENT 'Категория товара' AFTER `SKU`
            ");

            // Добавляем индекс для поиска по категории
            $connection->query("
                ALTER TABLE `acme_warehouse_products`
                ADD KEY `IX_CATEGORY` (`CATEGORY`)
            ");

            AddMessage2Log(
                'Migration001: Добавлено поле CATEGORY и индекс',
                'acme.warehouse'
            );
        }
    }

    /**
     * Откатить миграцию
     *
     * Удаляет поле CATEGORY из таблицы товаров
     */
    public function down()
    {
        $connection = Application::getConnection();

        // Удаляем индекс
        $connection->query("
            ALTER TABLE `acme_warehouse_products`
            DROP INDEX `IX_CATEGORY`
        ");

        // Удаляем поле
        $connection->query("
            ALTER TABLE `acme_warehouse_products`
            DROP COLUMN `CATEGORY`
        ");

        AddMessage2Log(
            'Migration001: Удалено поле CATEGORY и индекс',
            'acme.warehouse'
        );
    }
}
