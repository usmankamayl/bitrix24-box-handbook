<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Iblock\Iblock;

/**
 * Компонент вывода связанных товаров
 */
class RelatedProductsComponent extends CBitrixComponent
{
    /**
     * Проверка подключения необходимых модулей
     */
    private function checkModules()
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не установлен');
        }
    }

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['ELEMENT_ID'] = (int)($arParams['ELEMENT_ID'] ?? 0);
        $arParams['PROPERTY_CODE'] = trim($arParams['PROPERTY_CODE'] ?? 'RELATED_PRODUCTS');
        $arParams['ELEMENT_COUNT'] = (int)($arParams['ELEMENT_COUNT'] ?? 4);
        $arParams['SHOW_PRICE'] = $arParams['SHOW_PRICE'] ?? 'Y';
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);

        return $arParams;
    }

    /**
     * Получение ID связанных товаров из свойства
     */
    private function getRelatedIds()
    {
        $iblock = Iblock::wakeUp($this->arParams['IBLOCK_ID']);
        $entityDataClass = $iblock->getEntityDataClass();

        // Получаем текущий элемент со свойством связанных товаров
        $element = $entityDataClass::getList([
            'select' => [
                'ID',
                'RELATED_IDS' => $this->arParams['PROPERTY_CODE'] . '.VALUE'
            ],
            'filter' => ['=ID' => $this->arParams['ELEMENT_ID']]
        ])->fetch();

        if (!$element || empty($element['RELATED_IDS'])) {
            return [];
        }

        // Если свойство одиночное - приводим к массиву
        $relatedIds = is_array($element['RELATED_IDS'])
            ? $element['RELATED_IDS']
            : [$element['RELATED_IDS']];

        return array_filter($relatedIds);
    }

    /**
     * Получение данных связанных товаров
     */
    private function getRelatedProducts($relatedIds)
    {
        if (empty($relatedIds)) {
            return [];
        }

        $iblock = Iblock::wakeUp($this->arParams['IBLOCK_ID']);
        $entityDataClass = $iblock->getEntityDataClass();

        $select = [
            'ID',
            'NAME',
            'CODE',
            'PREVIEW_TEXT',
            'PREVIEW_PICTURE',
            'DETAIL_PAGE_URL' => 'IBLOCK.DETAIL_PAGE_URL'
        ];

        // Добавляем цену в выборку если нужно
        if ($this->arParams['SHOW_PRICE'] === 'Y') {
            $select['PRICE_VALUE'] = 'PRICE.VALUE';
        }

        $products = [];
        $rsElements = $entityDataClass::getList([
            'select' => $select,
            'filter' => [
                '@ID' => $relatedIds,
                '=ACTIVE' => 'Y'
            ],
            'limit' => $this->arParams['ELEMENT_COUNT']
        ]);

        while ($element = $rsElements->fetch()) {
            // Формируем URL детальной страницы
            if ($element['DETAIL_PAGE_URL']) {
                $element['DETAIL_PAGE_URL'] = str_replace(
                    ['#ELEMENT_ID#', '#ELEMENT_CODE#'],
                    [$element['ID'], $element['CODE']],
                    $element['DETAIL_PAGE_URL']
                );
            }

            // Обрабатываем картинку
            if ($element['PREVIEW_PICTURE']) {
                $element['PREVIEW_PICTURE_SRC'] = \CFile::GetPath($element['PREVIEW_PICTURE']);

                // Получаем информацию о файле (размеры, alt)
                $fileInfo = \CFile::GetFileArray($element['PREVIEW_PICTURE']);
                if ($fileInfo) {
                    $element['PREVIEW_PICTURE_INFO'] = [
                        'WIDTH' => $fileInfo['WIDTH'],
                        'HEIGHT' => $fileInfo['HEIGHT'],
                        'FILE_SIZE' => $fileInfo['FILE_SIZE']
                    ];
                }
            }

            $products[] = $element;
        }

        return $products;
    }

    /**
     * Получение похожих товаров (если связанные не указаны)
     */
    private function getSimilarProducts()
    {
        $iblock = Iblock::wakeUp($this->arParams['IBLOCK_ID']);
        $entityDataClass = $iblock->getEntityDataClass();

        // Получаем раздел текущего товара
        $currentElement = $entityDataClass::getList([
            'select' => ['ID', 'IBLOCK_SECTION_ID'],
            'filter' => ['=ID' => $this->arParams['ELEMENT_ID']]
        ])->fetch();

        if (!$currentElement || !$currentElement['IBLOCK_SECTION_ID']) {
            return [];
        }

        $select = [
            'ID',
            'NAME',
            'CODE',
            'PREVIEW_TEXT',
            'PREVIEW_PICTURE',
            'DETAIL_PAGE_URL' => 'IBLOCK.DETAIL_PAGE_URL'
        ];

        if ($this->arParams['SHOW_PRICE'] === 'Y') {
            $select['PRICE_VALUE'] = 'PRICE.VALUE';
        }

        // Получаем товары из того же раздела, кроме текущего
        $products = [];
        $rsElements = $entityDataClass::getList([
            'select' => $select,
            'filter' => [
                '=IBLOCK_SECTION_ID' => $currentElement['IBLOCK_SECTION_ID'],
                '!=ID' => $this->arParams['ELEMENT_ID'],
                '=ACTIVE' => 'Y'
            ],
            'order' => ['RAND' => 'ASC'],
            'limit' => $this->arParams['ELEMENT_COUNT']
        ]);

        while ($element = $rsElements->fetch()) {
            if ($element['DETAIL_PAGE_URL']) {
                $element['DETAIL_PAGE_URL'] = str_replace(
                    ['#ELEMENT_ID#', '#ELEMENT_CODE#'],
                    [$element['ID'], $element['CODE']],
                    $element['DETAIL_PAGE_URL']
                );
            }

            if ($element['PREVIEW_PICTURE']) {
                $element['PREVIEW_PICTURE_SRC'] = \CFile::GetPath($element['PREVIEW_PICTURE']);
            }

            $products[] = $element;
        }

        return $products;
    }

    /**
     * Выполнение компонента
     */
    public function executeComponent()
    {
        try {
            $this->checkModules();

            if ($this->arParams['IBLOCK_ID'] <= 0) {
                throw new \Exception('Не указан ID инфоблока');
            }

            if ($this->arParams['ELEMENT_ID'] <= 0) {
                throw new \Exception('Не указан ID элемента');
            }

            // Формируем уникальный ID кеша
            $cacheId = md5(serialize([
                $this->arParams['IBLOCK_ID'],
                $this->arParams['ELEMENT_ID'],
                $this->arParams['PROPERTY_CODE'],
                $this->arParams['ELEMENT_COUNT']
            ]));

            $cacheDir = '/related_products/' . $this->arParams['IBLOCK_ID'];

            if ($this->startResultCache(false, $cacheId)) {
                $taggedCache = Application::getInstance()->getTaggedCache();
                $taggedCache->startTagCache($cacheDir);

                // Получаем ID связанных товаров
                $relatedIds = $this->getRelatedIds();

                if (!empty($relatedIds)) {
                    // Если есть связанные товары - выводим их
                    $this->arResult['ITEMS'] = $this->getRelatedProducts($relatedIds);
                    $this->arResult['TYPE'] = 'RELATED';
                } else {
                    // Если нет - выводим похожие из того же раздела
                    $this->arResult['ITEMS'] = $this->getSimilarProducts();
                    $this->arResult['TYPE'] = 'SIMILAR';
                }

                // Регистрируем теги для автоматической очистки кеша
                $taggedCache->registerTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
                $taggedCache->registerTag('iblock_element_' . $this->arParams['ELEMENT_ID']);
                $taggedCache->endTagCache();

                $this->includeComponentTemplate();
            }

        } catch (\Exception $e) {
            ShowError($e->getMessage());
        }
    }
}
