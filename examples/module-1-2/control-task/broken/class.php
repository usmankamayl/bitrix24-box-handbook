<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * КОНТРОЛЬНОЕ ЗАДАНИЕ: в этом компоненте пять дефектов.
 * Найденное сформулируйте как «ошибка → причина → последствие → исправление».
 * Эталон — в папке ../fixed/.
 */
class StudyNewsSidebarBrokenComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['COUNT'] = (int)($arParams['COUNT'] ?? 5);
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);

        return $arParams;
    }

    public function executeComponent(): void
    {
        global $CACHE_MANAGER;

        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            ShowError('Модуль инфоблоков не установлен');
            return;
        }

        $page = (int)($_GET['PAGEN_1'] ?? 1);

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
            ['nPageSize' => $this->arParams['COUNT']],
            ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_TEXT', 'ACTIVE_FROM']
        );

        $items = [];
        while ($row = $rsElement->GetNext(false, false)) {
            $items[] = $row;
        }

        $this->arResult['ITEMS'] = $items;
        $this->arResult['ITEMS_COUNT'] = count($items);
        $this->arResult['NAV_STRING'] = $rsElement->GetPageNavStringEx(
            $navComponentObject,
            '',
            '.default',
            false
        );
        $this->arResult['PAGE'] = $page;

        if ($this->startResultCache()) {
            if (empty($this->arResult['ITEMS'])) {
                $this->abortResultCache();
            }

            if (defined('BX_COMP_MANAGED_CACHE')) {
                $CACHE_MANAGER->RegisterTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
            }

            $this->includeComponentTemplate();
        }

        $this->setResultCacheKeys(['ITEMS_COUNT']);
    }
}
