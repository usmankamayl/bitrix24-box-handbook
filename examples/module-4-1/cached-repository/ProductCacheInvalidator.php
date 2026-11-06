<?php
/**
 * Обработчик событий для автоинвалидации кеша репозитория.
 *
 * Регистрируем на события изменения товаров:
 * - OnAfterIBlockElementUpdate
 * - OnAfterIBlockElementDelete
 * - OnPriceUpdate (кастомное)
 */

namespace Vendor\Shop\EventHandlers;

use Bitrix\Main\Application;
use Vendor\Shop\Repository\ProductRepository;

class ProductCacheInvalidator
{
    /**
     * После обновления элемента инфоблока.
     * Bitrix сам сбрасывает iblock_element_* тег, но если у тебя
     * кеш без тегов или кастомный — сбрасывай вручную.
     */
    public static function onAfterIBlockElementUpdate(array &$fields): void
    {
        if (empty($fields['ID']) || empty($fields['RESULT'])) {
            return;
        }
        self::invalidateProduct((int)$fields['ID']);
    }

    public static function onAfterIBlockElementDelete(array $fields): void
    {
        if (empty($fields['ID'])) {
            return;
        }
        self::invalidateProduct((int)$fields['ID']);
    }

    /**
     * При изменении цены — сбрасываем связанные теги.
     */
    public static function onAfterPriceUpdate(array $fields): void
    {
        if (!empty($fields['PRODUCT_ID'])) {
            Application::getInstance()
                ->getTaggedCache()
                ->clearByTag('iblock_element_' . $fields['PRODUCT_ID']);
        }
    }

    /**
     * При изменении раздела — сбрасываем кеш списков этого раздела.
     */
    public static function onAfterIBlockSectionUpdate(array &$fields): void
    {
        if (!empty($fields['ID'])) {
            Application::getInstance()
                ->getTaggedCache()
                ->clearByTag('iblock_section_' . $fields['ID']);
        }
    }

    private static function invalidateProduct(int $productId): void
    {
        // Тег сбрасывает и сам Bitrix, но если используешь
        // кастомные ключи без тегов — сбрасывай явно
        (new ProductRepository())->invalidate($productId);
    }
}

// Регистрация в init.php или include.php модуля:
/*
use Bitrix\Main\EventManager;

EventManager::getInstance()->addEventHandler(
    'iblock',
    'OnAfterIBlockElementUpdate',
    ['Vendor\\Shop\\EventHandlers\\ProductCacheInvalidator', 'onAfterIBlockElementUpdate']
);
EventManager::getInstance()->addEventHandler(
    'iblock',
    'OnAfterIBlockElementDelete',
    ['Vendor\\Shop\\EventHandlers\\ProductCacheInvalidator', 'onAfterIBlockElementDelete']
);
EventManager::getInstance()->addEventHandler(
    'catalog',
    'OnPriceUpdate',
    ['Vendor\\Shop\\EventHandlers\\ProductCacheInvalidator', 'onAfterPriceUpdate']
);
*/
