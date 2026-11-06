<?php
/**
 * Компонент "Избранные товары" — смешанный контент.
 *
 * Показывает:
 * - Статичный список товаров (кешируется)
 * - Динамическую кнопку "В избранное" с состоянием (is_favorite)
 * - Голосование composite за статичность
 */

namespace Vendor\Shop\Components;

use Bitrix\Main\Page\Frame;

class FavoriteProductsComponent extends \CBitrixComponent
{
    public function executeComponent()
    {
        // Голосуем за composite — компонент может быть статичным
        // за исключением frame-областей
        $this->setFrameMode(true);

        if ($this->startResultCache(
            $this->arParams['CACHE_TIME'] ?? 3600,
            [
                $this->arParams['SECTION_ID'],
                $_GET['PAGEN_1'] ?? 1,
                // НЕ добавляем ID пользователя — список товаров общий,
                // а "избранность" определяется динамически
            ]
        )) {
            $this->arResult['ITEMS'] = $this->getProducts();
            $this->includeComponentTemplate();
        }
    }

    private function getProducts(): array
    {
        // Список товаров — статика, общая для всех
        // НЕ включаем is_favorite — это решается динамически в шаблоне
        $result = \CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'ACTIVE'    => 'Y',
            ],
            false,
            ['nPageSize' => $this->arParams['PAGE_SIZE'] ?? 20],
            ['ID', 'NAME', 'PREVIEW_PICTURE', 'PRICE', 'DETAIL_PAGE_URL']
        );

        $items = [];
        while ($item = $result->GetNext()) {
            $items[] = $item;
        }
        return $items;
    }
}
