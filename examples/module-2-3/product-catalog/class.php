<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\SectionTable;

/**
 * Компонент каталога товаров с фильтрацией
 */
class ProductCatalogComponent extends CBitrixComponent
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
        $arParams['ELEMENT_COUNT'] = (int)($arParams['ELEMENT_COUNT'] ?? 12);
        $arParams['FILTER_PRICE_FROM'] = $arParams['FILTER_PRICE_FROM'] ?? 'Y';
        $arParams['FILTER_PRICE_TO'] = $arParams['FILTER_PRICE_TO'] ?? 'Y';
        $arParams['FILTER_SECTION'] = $arParams['FILTER_SECTION'] ?? 'Y';
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);

        return $arParams;
    }

    /**
     * Получение фильтра из запроса
     */
    private function getFilterFromRequest()
    {
        $request = Application::getInstance()->getContext()->getRequest();

        $filter = ['=IBLOCK_ID' => $this->arParams['IBLOCK_ID'], '=ACTIVE' => 'Y'];

        // Фильтр по цене
        if ($this->arParams['FILTER_PRICE_FROM'] === 'Y') {
            $priceFrom = (int)$request->get('PRICE_FROM');
            if ($priceFrom > 0) {
                $filter['>=PRICE.VALUE'] = $priceFrom;
            }
        }

        if ($this->arParams['FILTER_PRICE_TO'] === 'Y') {
            $priceTo = (int)$request->get('PRICE_TO');
            if ($priceTo > 0) {
                $filter['<=PRICE.VALUE'] = $priceTo;
            }
        }

        // Фильтр по разделу
        if ($this->arParams['FILTER_SECTION'] === 'Y') {
            $sectionId = (int)$request->get('SECTION_ID');
            if ($sectionId > 0) {
                $filter['=IBLOCK_SECTION_ID'] = $sectionId;
            }
        }

        return $filter;
    }

    /**
     * Получение списка разделов для фильтра
     */
    private function getSections()
    {
        $sections = [];

        $rsSections = SectionTable::getList([
            'select' => ['ID', 'NAME', 'CODE'],
            'filter' => [
                '=IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                '=ACTIVE' => 'Y'
            ],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC']
        ]);

        while ($section = $rsSections->fetch()) {
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * Получение элементов инфоблока
     */
    private function getElements($filter)
    {
        $iblock = Iblock::wakeUp($this->arParams['IBLOCK_ID']);
        $entityDataClass = $iblock->getEntityDataClass();

        $elements = [];
        $rsElements = $entityDataClass::getList([
            'select' => [
                'ID',
                'NAME',
                'CODE',
                'PREVIEW_TEXT',
                'PREVIEW_PICTURE',
                'DETAIL_PAGE_URL' => 'IBLOCK.DETAIL_PAGE_URL',
                'PRICE_VALUE' => 'PRICE.VALUE',
                'ARTICLE_VALUE' => 'ARTICLE.VALUE'
            ],
            'filter' => $filter,
            'order' => ['SORT' => 'ASC', 'ID' => 'DESC'],
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
            }

            $elements[] = $element;
        }

        return $elements;
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

            $filter = $this->getFilterFromRequest();

            // Формируем уникальный ID кеша на основе фильтра
            $cacheId = md5(serialize($filter));
            $cacheDir = '/product_catalog/' . $this->arParams['IBLOCK_ID'];

            if ($this->startResultCache(false, $cacheId)) {
                $cache = Cache::createInstance();
                $taggedCache = Application::getInstance()->getTaggedCache();
                $taggedCache->startTagCache($cacheDir);

                // Получаем разделы для фильтра
                if ($this->arParams['FILTER_SECTION'] === 'Y') {
                    $this->arResult['SECTIONS'] = $this->getSections();
                }

                // Получаем элементы
                $this->arResult['ITEMS'] = $this->getElements($filter);
                $this->arResult['FILTER'] = $filter;

                // Регистрируем тег для автоматической очистки кеша
                $taggedCache->registerTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
                $taggedCache->endTagCache();

                $this->includeComponentTemplate();
            }

        } catch (\Exception $e) {
            ShowError($e->getMessage());
        }
    }
}
