<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

/**
 * Эталон контрольного задания.
 *
 * Отличия от ../broken/class.php — по одному на каждый дефект:
 *  1. выборка выполняется ВНУТРИ блока кеша, а не до него;
 *  2. пустой список кешируется, abortResultCache() остаётся только для ошибок;
 *  3. номер страницы входит в дополнительный ключ кеша;
 *  4. setResultCacheKeys() вызывается ДО includeComponentTemplate();
 *  5. экранирование перенесено в шаблон (см. template.php).
 */
class StudyNewsSidebarComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);

        $arParams['COUNT'] = (int)($arParams['COUNT'] ?? 5);
        if ($arParams['COUNT'] <= 0 || $arParams['COUNT'] > 50) {
            $arParams['COUNT'] = 5;
        }

        $cacheType = (string)($arParams['CACHE_TYPE'] ?? 'A');
        $arParams['CACHE_TYPE'] = in_array($cacheType, ['A', 'Y', 'N'], true) ? $cacheType : 'A';
        $arParams['CACHE_TIME'] = max(0, (int)($arParams['CACHE_TIME'] ?? 3600));

        return $arParams;
    }

    public function executeComponent(): void
    {
        global $CACHE_MANAGER;

        if ($this->arParams['IBLOCK_ID'] <= 0) {
            ShowError('Не указан инфоблок');
            return;
        }

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль инфоблоков не установлен');
            return;
        }

        CPageOption::SetOptionString('main', 'nav_page_in_session', 'N');

        $navStartParams = [
            'nPageSize' => $this->arParams['COUNT'],
            'bShowAll' => false,
        ];

        // Номер страницы не входит в $arParams и не входит в ключ кеша
        // автоматически — значит его надо добавить руками.
        $navData = CDBResult::GetNavParams($navStartParams);

        if (!$this->startResultCache(false, [$navData])) {
            return;
        }

        try {
            $this->loadItems($navStartParams);
        } catch (\Throwable $exception) {
            $this->abortResultCache();
            AddMessage2Log(get_class($exception) . ': ' . $exception->getMessage(), 'study.news.sidebar');
            ShowError('Не удалось загрузить новости');
            return;
        }

        if (defined('BX_COMP_MANAGED_CACHE')) {
            $CACHE_MANAGER->RegisterTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
        }

        // До includeComponentTemplate(): ядро урезает $arResult до этих ключей
        // сразу после вывода шаблона и только потом пишет его в кеш.
        $this->setResultCacheKeys(['ITEMS_COUNT']);

        $this->includeComponentTemplate();
    }

    /**
     * @param array<string,mixed> $navStartParams
     */
    private function loadItems(array $navStartParams): void
    {
        $rsElement = CIBlockElement::GetList(
            ['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'],
            [
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'ACTIVE' => 'Y',
                'ACTIVE_DATE' => 'Y',
                'CHECK_PERMISSIONS' => 'Y',
                'MIN_PERMISSION' => 'R',
            ],
            false,
            $navStartParams,
            ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_TEXT', 'PREVIEW_TEXT_TYPE', 'ACTIVE_FROM']
        );

        $items = [];
        while ($row = $rsElement->Fetch()) {
            $items[] = [
                'ID' => (int)$row['ID'],
                'NAME' => (string)$row['NAME'],
                'DETAIL_PAGE_URL' => (string)$row['DETAIL_PAGE_URL'],
                'PREVIEW_TEXT' => (string)$row['PREVIEW_TEXT'],
                'PREVIEW_TEXT_TYPE' => (string)$row['PREVIEW_TEXT_TYPE'],
            ];
        }

        $this->arResult['ITEMS'] = $items;
        $this->arResult['ITEMS_COUNT'] = count($items);
        $this->arResult['NAV_STRING'] = $rsElement->GetPageNavStringEx(
            $navComponentObject,
            '',
            '.default',
            false
        );
    }
}
