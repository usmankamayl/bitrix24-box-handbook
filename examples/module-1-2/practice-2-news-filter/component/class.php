<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * study:news.filtered — список с серверным фильтром.
 *
 * Три источника условий, которые нельзя смешивать молча:
 *   1. $arParams (IBLOCK_ID и т.п.) — настройка разработчика;
 *   2. FILTER_NAME — внешний фильтр из кода страницы ($GLOBALS['arrFilter']),
 *      так работает связка «умный фильтр → список»;
 *   3. GET-параметры (q, section) — выбор посетителя.
 *
 * Каждый из них влияет на результат, значит каждый должен попасть либо в ключ
 * кеша, либо отключить кеш. Иначе посетитель А получит выборку посетителя Б.
 */
class StudyNewsFilteredComponent extends CBitrixComponent
{
    private const ALLOWED_SORT_FIELDS = ['ID', 'NAME', 'SORT', 'ACTIVE_FROM'];

    /** Длина поисковой строки: ограничение и на ввод, и на сервере. */
    private const SEARCH_MAX_LEN = 64;

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['NEWS_COUNT'] = (int)($arParams['NEWS_COUNT'] ?? 10);
        if ($arParams['NEWS_COUNT'] <= 0 || $arParams['NEWS_COUNT'] > 100) {
            $arParams['NEWS_COUNT'] = 10;
        }

        $sortField = (string)($arParams['SORT_FIELD'] ?? 'ACTIVE_FROM');
        $arParams['SORT_FIELD'] = in_array($sortField, self::ALLOWED_SORT_FIELDS, true)
            ? $sortField
            : 'ACTIVE_FROM';

        $sortOrder = strtoupper((string)($arParams['SORT_ORDER'] ?? 'DESC'));
        $arParams['SORT_ORDER'] = in_array($sortOrder, ['ASC', 'DESC'], true) ? $sortOrder : 'DESC';

        $filterName = (string)($arParams['FILTER_NAME'] ?? 'arrFilter');
        $arParams['FILTER_NAME'] = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $filterName)
            ? $filterName
            : 'arrFilter';

        $arParams['SHOW_SEARCH'] = ($arParams['SHOW_SEARCH'] ?? 'Y') === 'Y';
        $arParams['SHOW_SECTION_FILTER'] = ($arParams['SHOW_SECTION_FILTER'] ?? 'Y') === 'Y';

        $cacheType = (string)($arParams['CACHE_TYPE'] ?? 'A');
        $arParams['CACHE_TYPE'] = in_array($cacheType, ['A', 'Y', 'N'], true) ? $cacheType : 'A';
        $arParams['CACHE_TIME'] = max(0, (int)($arParams['CACHE_TIME'] ?? 3600));
        $arParams['CACHE_FILTER'] = ($arParams['CACHE_FILTER'] ?? 'Y') === 'Y';
        $arParams['CACHE_GROUPS'] = ($arParams['CACHE_GROUPS'] ?? 'Y') === 'Y';

        return $arParams;
    }

    public function executeComponent(): void
    {
        if ($this->arParams['IBLOCK_ID'] <= 0) {
            ShowError(Loc::getMessage('STUDY_NEWS_FILTERED_ERR_NO_IBLOCK'));
            return;
        }

        if (!Loader::includeModule('iblock')) {
            ShowError(Loc::getMessage('STUDY_NEWS_FILTERED_ERR_NO_MODULE'));
            return;
        }

        CPageOption::SetOptionString('main', 'nav_page_in_session', 'N');

        $userFilter = $this->getUserFilter();
        $filter = $this->mergeFilters($userFilter);

        // Классическая развилка стандартных компонентов:
        // если фильтр есть, а CACHE_FILTER выключен — кешировать нельзя вообще,
        // потому что фильтр не попадёт в ключ и «протечёт» в чужой кеш.
        if (!$this->arParams['CACHE_FILTER'] && $this->hasUserOrExternalFilter($filter)) {
            $this->arParams['CACHE_TIME'] = 0;
        }

        $navStartParams = [
            'nPageSize' => $this->arParams['NEWS_COUNT'],
            'bShowAll' => false,
        ];
        $navData = CDBResult::GetNavParams($navStartParams);

        $additionalCacheId = [
            $this->arParams['CACHE_GROUPS'] ? $this->getGroupsKey() : 'no-groups',
            $navData,
            $this->arParams['CACHE_FILTER'] ? $filter : 'filter-not-in-cache',
        ];

        if ($this->startResultCache(false, $additionalCacheId)) {
            try {
                $this->loadItems($filter, $navStartParams);
            } catch (\Throwable $exception) {
                $this->abortResultCache();
                AddMessage2Log(
                    get_class($exception) . ': ' . $exception->getMessage(),
                    'study.news.filtered'
                );
                ShowError(Loc::getMessage('STUDY_NEWS_FILTERED_ERR_LOAD'));
                return;
            }

            $this->arResult['FILTER'] = $userFilter;
            $this->arResult['SECTIONS'] = $this->arParams['SHOW_SECTION_FILTER']
                ? $this->getSections()
                : [];
            $this->arResult['RESET_URL'] = $this->getResetUrl();
            $this->arResult['FORM_URL'] = $this->getFormUrl();

            $this->registerCacheTag();
            $this->setResultCacheKeys(['ITEMS_COUNT']);
            $this->includeComponentTemplate();
        }
    }

    /**
     * Пользовательский фильтр из GET. Каждое значение проверяется по типу,
     * затем по формату, затем по длине/диапазону. В массив фильтра попадают
     * только нормализованные значения.
     *
     * @return array{SEARCH?: string, SECTION_ID?: int}
     */
    private function getUserFilter(): array
    {
        $request = Context::getCurrent()->getRequest();
        $filter = [];

        if ($this->arParams['SHOW_SEARCH']) {
            $q = $request->getQuery('q');
            if (is_string($q)) {
                $q = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $q) ?? '');
                if ($q !== '') {
                    $filter['SEARCH'] = mb_substr($q, 0, self::SEARCH_MAX_LEN);
                }
            }
            // Массив вместо строки (?q[]=1) молча игнорируется: (string) на массиве
            // даёт «Array» и warning, а в фильтр попадёт мусор.
        }

        if ($this->arParams['SHOW_SECTION_FILTER']) {
            $section = $request->getQuery('section');
            if (is_string($section) && ctype_digit($section)) {
                $sectionId = (int)$section;
                if ($sectionId > 0) {
                    $filter['SECTION_ID'] = $sectionId;
                }
            }
        }

        return $filter;
    }

    /**
     * Итоговый фильтр для CIBlockElement::GetList.
     *
     * @param array<string,mixed> $userFilter
     * @return array<string,mixed>
     */
    private function mergeFilters(array $userFilter): array
    {
        $filter = [
            'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION' => 'R',
            'ACTIVE_DATE' => 'Y',
        ];

        // Внешний фильтр: $GLOBALS['arrFilter'] = ['PROPERTY_PRICE' => ...].
        // Имя проверено в onPrepareComponentParams, значение — только массив.
        $filterName = $this->arParams['FILTER_NAME'];
        if (isset($GLOBALS[$filterName]) && is_array($GLOBALS[$filterName])) {
            $filter = array_merge($filter, $GLOBALS[$filterName]);
        }

        if (isset($userFilter['SEARCH'])) {
            // % — штатный модификатор «подстрока» в фильтре инфоблоков.
            $filter['%NAME'] = $userFilter['SEARCH'];
        }

        if (isset($userFilter['SECTION_ID'])) {
            $filter['SECTION_ID'] = $userFilter['SECTION_ID'];
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        }

        return $filter;
    }

    /**
     * @param array<string,mixed> $filter
     */
    private function hasUserOrExternalFilter(array $filter): bool
    {
        $baseKeys = [
            'IBLOCK_ID', 'ACTIVE', 'CHECK_PERMISSIONS', 'MIN_PERMISSION', 'ACTIVE_DATE',
        ];

        foreach (array_keys($filter) as $key) {
            if (!in_array($key, $baseKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int,int>
     */
    private function getGroupsKey(): array
    {
        global $USER;

        $groups = array_map('intval', $USER->GetUserGroupArray());
        sort($groups);

        return $groups;
    }

    /**
     * @param array<string,mixed> $filter
     * @param array<string,mixed> $navStartParams
     */
    private function loadItems(array $filter, array $navStartParams): void
    {
        $arOrder = [$this->arParams['SORT_FIELD'] => $this->arParams['SORT_ORDER']];
        if (!array_key_exists('ID', $arOrder)) {
            $arOrder['ID'] = 'DESC';
        }

        $arSelect = [
            'ID',
            'IBLOCK_ID',
            'NAME',
            'ACTIVE_FROM',
            'DETAIL_PAGE_URL',
            'PREVIEW_TEXT',
            'PREVIEW_TEXT_TYPE',
            'PREVIEW_PICTURE',
        ];

        $rsElement = CIBlockElement::GetList($arOrder, $filter, false, $navStartParams, $arSelect);

        $items = [];
        while ($row = $rsElement->Fetch()) {
            $items[] = [
                'ID' => (int)$row['ID'],
                'NAME' => (string)$row['NAME'],
                'DETAIL_PAGE_URL' => (string)$row['DETAIL_PAGE_URL'],
                'PREVIEW_TEXT' => (string)$row['PREVIEW_TEXT'],
                'ACTIVE_FROM' => (string)$row['ACTIVE_FROM'],
                'DISPLAY_ACTIVE_FROM' => $row['ACTIVE_FROM'] !== ''
                    ? FormatDate('d.m.Y', MakeTimeStamp($row['ACTIVE_FROM']))
                    : '',
            ];
        }

        $this->arResult['ITEMS'] = $items;
        $this->arResult['ITEMS_COUNT'] = count($items);
        $this->arResult['NAV_STRING'] = $rsElement->GetPageNavStringEx(
            $navComponentObject,
            '',
            '.default',
            false,
            $this
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getSections(): array
    {
        $sections = [];

        $rsSection = SectionTable::getList([
            'select' => ['ID', 'NAME', 'DEPTH_LEVEL'],
            'filter' => ['=IBLOCK_ID' => $this->arParams['IBLOCK_ID'], '=ACTIVE' => 'Y'],
            'order' => ['LEFT_MARGIN' => 'ASC'],
        ]);

        while ($section = $rsSection->fetch()) {
            $sections[] = [
                'ID' => (int)$section['ID'],
                'NAME' => (string)$section['NAME'],
                'DEPTH_LEVEL' => (int)$section['DEPTH_LEVEL'],
            ];
        }

        return $sections;
    }

    private function getFormUrl(): string
    {
        global $APPLICATION;

        // PAGEN_1 снимаем: после смены фильтра логично начать с первой страницы.
        return (string)$APPLICATION->GetCurPageParam('', ['PAGEN_1']);
    }

    private function getResetUrl(): string
    {
        global $APPLICATION;

        return (string)$APPLICATION->GetCurPageParam('', ['PAGEN_1', 'q', 'section']);
    }

    private function registerCacheTag(): void
    {
        if (!defined('BX_COMP_MANAGED_CACHE')) {
            return;
        }

        global $CACHE_MANAGER;
        $CACHE_MANAGER->RegisterTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
    }
}
