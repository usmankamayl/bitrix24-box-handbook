<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Context;
use Bitrix\Iblock\SectionTable;
use Bitrix\Iblock\PropertyTable;

/**
 * Класс компонента "Список с фильтром"
 *
 * Функционал:
 * - Фильтрация по разделам
 * - Фильтрация по свойствам
 * - Сохранение фильтра в сессии
 * - Ajax-обновление списка
 * - Постраничная навигация
 * - Умное кеширование
 */
class FilteredListComponent extends CBitrixComponent
{
    /** @var array Текущий фильтр */
    protected $filter = [];

    /** @var array Доступные разделы */
    protected $sections = [];

    /** @var array Свойства для фильтра */
    protected $filterProperties = [];

    /**
     * Проверка модулей
     */
    protected function checkModules(): bool
    {
        try {
            if (!Loader::includeModule('iblock')) {
                throw new LoaderException('Модуль инфоблоков не установлен');
            }
        } catch (LoaderException $e) {
            $this->abortResultCache();
            ShowError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Подготовка параметров
     */
    public function onPrepareComponentParams($arParams): array
    {
        $arParams["IBLOCK_ID"] = intval($arParams["IBLOCK_ID"] ?? 0);
        $arParams["SECTION_ID"] = intval($arParams["SECTION_ID"] ?? 0);
        $arParams["INCLUDE_SUBSECTIONS"] = ($arParams["INCLUDE_SUBSECTIONS"] ?? "Y") === "Y";

        $arParams["SORT_BY"] = trim($arParams["SORT_BY"] ?? "SORT");
        $arParams["SORT_ORDER"] = strtoupper(trim($arParams["SORT_ORDER"] ?? "ASC"));

        $arParams["FILTER_NAME"] = trim($arParams["FILTER_NAME"] ?? "arrFilter");
        $arParams["SHOW_SECTIONS_FILTER"] = ($arParams["SHOW_SECTIONS_FILTER"] ?? "Y") === "Y";
        $arParams["SHOW_PROPERTIES_FILTER"] = ($arParams["SHOW_PROPERTIES_FILTER"] ?? "Y") === "Y";
        $arParams["FILTER_PROPERTIES"] = trim($arParams["FILTER_PROPERTIES"] ?? "");
        $arParams["SAVE_FILTER_IN_SESSION"] = ($arParams["SAVE_FILTER_IN_SESSION"] ?? "Y") === "Y";

        $arParams["PAGE_ELEMENT_COUNT"] = intval($arParams["PAGE_ELEMENT_COUNT"] ?? 12);
        $arParams["SHOW_PAGINATION"] = ($arParams["SHOW_PAGINATION"] ?? "Y") === "Y";

        $arParams["USE_AJAX"] = ($arParams["USE_AJAX"] ?? "Y") === "Y";

        $arParams["CACHE_TIME"] = intval($arParams["CACHE_TIME"] ?? 3600);
        $arParams["CACHE_FILTER"] = ($arParams["CACHE_FILTER"] ?? "N") === "Y";
        $arParams["CACHE_GROUPS"] = ($arParams["CACHE_GROUPS"] ?? "Y") === "Y";

        return $arParams;
    }

    /**
     * Получение фильтра из запроса
     */
    protected function getFilterFromRequest(): array
    {
        $request = Context::getCurrent()->getRequest();
        $filter = [];

        // Фильтр по разделу
        if ($this->arParams["SHOW_SECTIONS_FILTER"]) {
            $sectionId = $request->get("section");
            if ($sectionId) {
                $filter["SECTION_ID"] = intval($sectionId);
            }
        }

        // Фильтр по свойствам
        if ($this->arParams["SHOW_PROPERTIES_FILTER"]) {
            $properties = $request->get("property");
            if (is_array($properties)) {
                foreach ($properties as $code => $value) {
                    if (!empty($value)) {
                        $filter["PROPERTY_" . $code] = $value;
                    }
                }
            }
        }

        return $filter;
    }

    /**
     * Получение фильтра из сессии
     */
    protected function getFilterFromSession(): array
    {
        $sessionKey = "FILTERED_LIST_" . $this->arParams["IBLOCK_ID"];
        return $_SESSION[$sessionKey] ?? [];
    }

    /**
     * Сохранение фильтра в сессию
     */
    protected function saveFilterToSession(array $filter): void
    {
        $sessionKey = "FILTERED_LIST_" . $this->arParams["IBLOCK_ID"];
        $_SESSION[$sessionKey] = $filter;
    }

    /**
     * Подготовка фильтра
     */
    protected function prepareFilter(): array
    {
        $filter = [
            "IBLOCK_ID" => $this->arParams["IBLOCK_ID"],
            "ACTIVE" => "Y",
        ];

        // Базовый фильтр по разделу
        if ($this->arParams["SECTION_ID"] > 0) {
            $filter["SECTION_ID"] = $this->arParams["SECTION_ID"];
            if ($this->arParams["INCLUDE_SUBSECTIONS"]) {
                $filter["INCLUDE_SUBSECTIONS"] = "Y";
            }
        }

        // Получаем фильтр из запроса
        $requestFilter = $this->getFilterFromRequest();

        // Если фильтр пустой и сохранение включено - берем из сессии
        if (empty($requestFilter) && $this->arParams["SAVE_FILTER_IN_SESSION"]) {
            $requestFilter = $this->getFilterFromSession();
        }

        // Сохраняем текущий фильтр
        $this->filter = $requestFilter;

        // Сохраняем в сессию
        if ($this->arParams["SAVE_FILTER_IN_SESSION"]) {
            $this->saveFilterToSession($requestFilter);
        }

        // Объединяем фильтры
        $filter = array_merge($filter, $requestFilter);

        // Внешний фильтр (если задан в глобальном массиве)
        global ${$this->arParams["FILTER_NAME"]};
        if (is_array(${$this->arParams["FILTER_NAME"]})) {
            $filter = array_merge($filter, ${$this->arParams["FILTER_NAME"]});
        }

        return $filter;
    }

    /**
     * Получение списка разделов для фильтра
     */
    protected function getSections(): array
    {
        $sections = [];

        $rsSections = SectionTable::getList([
            'select' => ['ID', 'NAME', 'CODE'],
            'filter' => [
                'IBLOCK_ID' => $this->arParams["IBLOCK_ID"],
                'ACTIVE' => 'Y',
            ],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC']
        ]);

        while ($section = $rsSections->fetch()) {
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * Получение свойств для фильтра
     */
    protected function getFilterProperties(): array
    {
        if (empty($this->arParams["FILTER_PROPERTIES"])) {
            return [];
        }

        $propertyCodes = array_map('trim', explode(',', $this->arParams["FILTER_PROPERTIES"]));
        $properties = [];

        foreach ($propertyCodes as $code) {
            $rsProperty = PropertyTable::getList([
                'select' => ['ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'USER_TYPE'],
                'filter' => [
                    'IBLOCK_ID' => $this->arParams["IBLOCK_ID"],
                    'CODE' => $code,
                    'ACTIVE' => 'Y',
                ]
            ]);

            if ($property = $rsProperty->fetch()) {
                // Получаем значения свойства для списочных типов
                if ($property['PROPERTY_TYPE'] === 'L') {
                    $property['VALUES'] = $this->getPropertyEnumValues($property['ID']);
                }

                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * Получение значений списочного свойства
     */
    protected function getPropertyEnumValues(int $propertyId): array
    {
        $values = [];

        $rsValues = \CIBlockPropertyEnum::GetList(
            ['SORT' => 'ASC'],
            ['PROPERTY_ID' => $propertyId]
        );

        while ($value = $rsValues->Fetch()) {
            $values[$value['ID']] = $value['VALUE'];
        }

        return $values;
    }

    /**
     * Получение элементов
     */
    protected function getElements(array $filter): array
    {
        $arSelect = [
            "ID",
            "NAME",
            "CODE",
            "DETAIL_PAGE_URL",
            "PREVIEW_TEXT",
            "PREVIEW_PICTURE",
            "PROPERTY_*"
        ];

        $arOrder = [
            $this->arParams["SORT_BY"] => $this->arParams["SORT_ORDER"]
        ];

        // Постраничная навигация
        $navParams = false;
        if ($this->arParams["SHOW_PAGINATION"]) {
            $navParams = [
                "nPageSize" => $this->arParams["PAGE_ELEMENT_COUNT"],
                "bShowAll" => false,
            ];
        }

        $rsElements = \CIBlockElement::GetList(
            $arOrder,
            $filter,
            false,
            $navParams,
            $arSelect
        );

        $items = [];

        while ($arElement = $rsElements->GetNext()) {
            // Обработка картинки
            if ($arElement["PREVIEW_PICTURE"]) {
                $arElement["PREVIEW_PICTURE"] = \CFile::GetFileArray($arElement["PREVIEW_PICTURE"]);
            }

            $items[] = $arElement;
        }

        // Навигация
        $navigation = null;
        if ($this->arParams["SHOW_PAGINATION"]) {
            $navigation = $rsElements->GetPageNavStringEx(
                $navComponentObject,
                "",
                ".default",
                false
            );
        }

        return [
            "ITEMS" => $items,
            "NAV_STRING" => $navigation,
        ];
    }

    /**
     * Формирование ключа кеширования
     */
    protected function getCacheId(): string
    {
        $cacheId = $this->arParams["IBLOCK_ID"];

        // Учитываем фильтр в кеше
        if ($this->arParams["CACHE_FILTER"]) {
            $cacheId .= "_" . md5(serialize($this->filter));
        }

        // Учитываем права доступа
        if ($this->arParams["CACHE_GROUPS"]) {
            global $USER;
            $cacheId .= "_" . md5(serialize($USER->GetUserGroupArray()));
        }

        // Учитываем страницу пагинации
        $request = Context::getCurrent()->getRequest();
        $page = intval($request->get("PAGEN_1") ?? 1);
        $cacheId .= "_page_" . $page;

        return $cacheId;
    }

    /**
     * Ajax ответ
     */
    protected function sendAjaxResponse(): void
    {
        global $APPLICATION;
        $APPLICATION->RestartBuffer();

        header('Content-Type: application/json');

        // Перехватываем вывод шаблона
        ob_start();
        $this->includeComponentTemplate();
        $html = ob_get_clean();

        $response = [
            "success" => true,
            "html" => $html,
            "filter" => $this->filter,
        ];

        echo json_encode($response);
        die();
    }

    /**
     * Основной метод
     */
    public function executeComponent()
    {
        // Проверка модулей
        if (!$this->checkModules()) {
            return;
        }

        // Проверка параметров
        if ($this->arParams["IBLOCK_ID"] <= 0) {
            ShowError("Не указан инфоблок");
            return;
        }

        // Подготавливаем фильтр
        $filter = $this->prepareFilter();

        // Формируем ключ кеша
        $cacheId = $this->getCacheId();

        // Запускаем кеширование
        if ($this->startResultCache(false, $cacheId)) {

            // Получаем элементы
            $result = $this->getElements($filter);
            $this->arResult["ITEMS"] = $result["ITEMS"];
            $this->arResult["NAV_STRING"] = $result["NAV_STRING"];

            // Получаем данные для фильтра
            if ($this->arParams["SHOW_SECTIONS_FILTER"]) {
                $this->arResult["SECTIONS"] = $this->getSections();
            }

            if ($this->arParams["SHOW_PROPERTIES_FILTER"]) {
                $this->arResult["FILTER_PROPERTIES"] = $this->getFilterProperties();
            }

            // Сохраняем текущий фильтр
            $this->arResult["CURRENT_FILTER"] = $this->filter;

            // Если элементов нет - прерываем кеширование
            if (empty($this->arResult["ITEMS"])) {
                $this->abortResultCache();
            }

            // Ajax ответ
            if ($this->arParams["USE_AJAX"] && Context::getCurrent()->getRequest()->get("ajax") === "Y") {
                $this->sendAjaxResponse();
            }

            $this->includeComponentTemplate();
        }

        // Для ajax всегда отправляем ответ (даже из кеша)
        if ($this->arParams["USE_AJAX"] && Context::getCurrent()->getRequest()->get("ajax") === "Y") {
            $this->sendAjaxResponse();
        }
    }
}
