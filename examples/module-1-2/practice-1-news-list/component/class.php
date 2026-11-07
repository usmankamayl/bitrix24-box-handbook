<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Iblock\Component\Tools;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * study:news.list — эталонный простой компонент для курса.
 *
 * Что здесь важно (и чего нет в «учебных» компонентах из интернета):
 *  1. $additionalCacheId считается ДО startResultCache и содержит всё, что влияет
 *     на результат, но не входит в $arParams: группы пользователя и номер страницы.
 *  2. После startResultCache(false) работа не прекращается: пост-кешевая часть
 *     (SetTitle) должна выполняться и на попадании в кеш.
 *  3. $arResult урезается до setResultCacheKeys() сразу после includeComponentTemplate(),
 *     поэтому всё, что нужно после шаблона, перечислено явно.
 *  4. Ошибка получения данных прерывает кеш; пустой список — нормальный результат
 *     и кешируется.
 */
class StudyNewsListComponent extends CBitrixComponent
{
    /** Поля, по которым разрешено сортировать. Всё остальное игнорируется. */
    private const ALLOWED_SORT_FIELDS = ['ID', 'NAME', 'SORT', 'ACTIVE_FROM', 'TIMESTAMP_X'];

    /** Верхняя граница NEWS_COUNT: страховка от nPageSize=100000. */
    private const MAX_NEWS_COUNT = 100;

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['SECTION_ID'] = (int)($arParams['SECTION_ID'] ?? 0);

        $arParams['NEWS_COUNT'] = (int)($arParams['NEWS_COUNT'] ?? 10);
        if ($arParams['NEWS_COUNT'] <= 0 || $arParams['NEWS_COUNT'] > self::MAX_NEWS_COUNT) {
            $arParams['NEWS_COUNT'] = 10;
        }

        $sortBy1 = (string)($arParams['SORT_BY1'] ?? 'ACTIVE_FROM');
        $arParams['SORT_BY1'] = in_array($sortBy1, self::ALLOWED_SORT_FIELDS, true)
            ? $sortBy1
            : 'ACTIVE_FROM';

        $order1 = strtoupper((string)($arParams['SORT_ORDER1'] ?? 'DESC'));
        $arParams['SORT_ORDER1'] = in_array($order1, ['ASC', 'DESC'], true) ? $order1 : 'DESC';

        // Вторая сортировка нужна для стабильного порядка при равных значениях:
        // без неё пагинация может показать один элемент дважды и потерять другой.
        $sortBy2 = (string)($arParams['SORT_BY2'] ?? 'ID');
        $arParams['SORT_BY2'] = in_array($sortBy2, self::ALLOWED_SORT_FIELDS, true)
            ? $sortBy2
            : 'ID';
        if ($arParams['SORT_BY2'] === $arParams['SORT_BY1']) {
            $arParams['SORT_BY2'] = $arParams['SORT_BY1'] === 'ID' ? 'SORT' : 'ID';
        }

        $order2 = strtoupper((string)($arParams['SORT_ORDER2'] ?? 'DESC'));
        $arParams['SORT_ORDER2'] = in_array($order2, ['ASC', 'DESC'], true) ? $order2 : 'DESC';

        $arParams['INCLUDE_SUBSECTIONS'] = ($arParams['INCLUDE_SUBSECTIONS'] ?? 'Y') !== 'N';
        $arParams['CHECK_DATES'] = ($arParams['CHECK_DATES'] ?? 'Y') !== 'N';
        $arParams['SET_TITLE'] = ($arParams['SET_TITLE'] ?? 'Y') !== 'N';

        $arParams['PREVIEW_TRUNCATE_LEN'] = max(0, (int)($arParams['PREVIEW_TRUNCATE_LEN'] ?? 200));
        $arParams['ACTIVE_DATE_FORMAT'] = trim((string)($arParams['ACTIVE_DATE_FORMAT'] ?? ''));
        if ($arParams['ACTIVE_DATE_FORMAT'] === '') {
            $arParams['ACTIVE_DATE_FORMAT'] = 'd.m.Y';
        }

        $arParams['DISPLAY_PAGER'] = ($arParams['DISPLAY_PAGER'] ?? 'Y') === 'Y';
        $arParams['PAGER_TEMPLATE'] = trim((string)($arParams['PAGER_TEMPLATE'] ?? '')) ?: '.default';

        $cacheType = (string)($arParams['CACHE_TYPE'] ?? 'A');
        $arParams['CACHE_TYPE'] = in_array($cacheType, ['A', 'Y', 'N'], true) ? $cacheType : 'A';
        $arParams['CACHE_TIME'] = max(0, (int)($arParams['CACHE_TIME'] ?? 3600));
        $arParams['CACHE_GROUPS'] = ($arParams['CACHE_GROUPS'] ?? 'Y') === 'Y';

        return $arParams;
    }

    public function executeComponent(): void
    {
        if ($this->arParams['IBLOCK_ID'] <= 0) {
            ShowError(Loc::getMessage('STUDY_NEWS_LIST_ERR_NO_IBLOCK'));
            return;
        }

        if (!Loader::includeModule('iblock')) {
            ShowError(Loc::getMessage('STUDY_NEWS_LIST_ERR_NO_MODULE'));
            return;
        }

        // Номер страницы не должен запоминаться в сессии: иначе две вкладки
        // с разными PAGEN_1 начинают влиять друг на друга.
        CPageOption::SetOptionString('main', 'nav_page_in_session', 'N');

        [$navStartParams, $navData] = $this->buildNavigation();

        // Всё, что влияет на HTML, но не лежит в $arParams.
        // $arParams целиком попадает в ключ кеша автоматически (см. getCacheID()).
        $additionalCacheId = [
            $this->arParams['CACHE_GROUPS'] ? $this->getGroupsKey() : 'no-groups',
            $navData,
        ];

        if ($this->startResultCache(false, $additionalCacheId)) {
            try {
                if (!$this->loadIblock()) {
                    $this->abortResultCache();
                    Tools::process404(
                        Loc::getMessage('STUDY_NEWS_LIST_ERR_NOT_FOUND'),
                        true,
                        true,
                        false
                    );
                    return;
                }
                $this->loadItems($navStartParams);
            } catch (\Throwable $exception) {
                // Кешировать ошибку нельзя: иначе она проживёт весь CACHE_TIME.
                $this->abortResultCache();
                AddMessage2Log(
                    get_class($exception) . ': ' . $exception->getMessage(),
                    'study.news.list'
                );
                ShowError(Loc::getMessage('STUDY_NEWS_LIST_ERR_LOAD'));
                return;
            }

            $this->registerCacheTag();

            $this->setResultCacheKeys([
                'IBLOCK_ID',
                'IBLOCK_NAME',
                'ITEMS_COUNT',
                'NAV_STRING',
            ]);

            $this->includeComponentTemplate();
        }

        // На попадании в кеш $arResult восстановлен только по списку выше —
        // поэтому здесь используется только IBLOCK_NAME, а не ITEMS.
        $this->setPageTitle();
    }

    /**
     * Пагинация и её вклад в ключ кеша.
     *
     * @return array{0: array<string,mixed>|array{nTopCount:int}, 1: array<string,int>}
     */
    private function buildNavigation(): array
    {
        if (!$this->arParams['DISPLAY_PAGER']) {
            // Ограничение количества: первые N, без навигации.
            return [['nTopCount' => $this->arParams['NEWS_COUNT']], ['page' => 1]];
        }

        $navStartParams = [
            'nPageSize' => $this->arParams['NEWS_COUNT'],
            'bShowAll' => false,
        ];

        // GetNavParams читает PAGEN_1 из запроса и возвращает нормализованные
        // значения — именно их нужно положить в ключ кеша, иначе первая страница
        // будет отдаваться всем остальным.
        $navData = CDBResult::GetNavParams($navStartParams);

        return [$navStartParams, $navData];
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

    private function loadIblock(): bool
    {
        $rsIblock = CIBlock::GetList(
            ['SORT' => 'ASC'],
            ['ID' => $this->arParams['IBLOCK_ID'], 'ACTIVE' => 'Y']
        );

        $arIblock = $rsIblock->Fetch();
        if (!$arIblock) {
            return false;
        }

        $this->arResult['IBLOCK_ID'] = (int)$arIblock['ID'];
        $this->arResult['IBLOCK_NAME'] = (string)$arIblock['NAME'];

        return true;
    }

    /**
     * @param array<string,mixed> $navStartParams
     */
    private function loadItems(array $navStartParams): void
    {
        $arOrder = [
            $this->arParams['SORT_BY1'] => $this->arParams['SORT_ORDER1'],
            $this->arParams['SORT_BY2'] => $this->arParams['SORT_ORDER2'],
        ];

        $arFilter = [
            'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
            'ACTIVE' => 'Y',
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION' => 'R',
        ];

        if ($this->arParams['CHECK_DATES']) {
            $arFilter['ACTIVE_DATE'] = 'Y';
        }

        if ($this->arParams['SECTION_ID'] > 0) {
            $arFilter['SECTION_ID'] = $this->arParams['SECTION_ID'];
            if ($this->arParams['INCLUDE_SUBSECTIONS']) {
                $arFilter['INCLUDE_SUBSECTIONS'] = 'Y';
            }
        }

        $arSelect = [
            'ID',
            'IBLOCK_ID',
            'NAME',
            'ACTIVE_FROM',
            'TIMESTAMP_X',
            'DETAIL_PAGE_URL',
            'PREVIEW_TEXT',
            'PREVIEW_TEXT_TYPE',
            'PREVIEW_PICTURE',
        ];

        $rsElement = CIBlockElement::GetList($arOrder, $arFilter, false, $navStartParams, $arSelect);

        $items = [];
        while ($row = $rsElement->Fetch()) {
            $items[] = $this->prepareItem($row);
        }

        // Навигация строится по тому же результату: NavNum у него уже выставлен.
        $this->arResult['NAV_STRING'] = $this->arParams['DISPLAY_PAGER']
            ? $rsElement->GetPageNavStringEx(
                $navComponentObject,
                $this->arResult['IBLOCK_NAME'],
                $this->arParams['PAGER_TEMPLATE'],
                false,
                $this
            )
            : '';

        $this->arResult['ITEMS'] = $items;
        $this->arResult['ITEMS_COUNT'] = count($items);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function prepareItem(array $row): array
    {
        $previewText = (string)$row['PREVIEW_TEXT'];

        $plainText = $row['PREVIEW_TEXT_TYPE'] === 'html'
            ? html_entity_decode(
                strip_tags($previewText),
                ENT_QUOTES | ENT_HTML5,
                defined('SITE_CHARSET') ? SITE_CHARSET : 'UTF-8'
            )
            : $previewText;

        $plainText = trim(preg_replace('/\s+/u', ' ', $plainText) ?? $plainText);

        $len = $this->arParams['PREVIEW_TRUNCATE_LEN'];
        if ($len > 0 && mb_strlen($plainText) > $len) {
            $plainText = mb_substr($plainText, 0, $len) . '…';
        }

        $item = [
            'ID' => (int)$row['ID'],
            'NAME' => (string)$row['NAME'],
            'DETAIL_PAGE_URL' => (string)$row['DETAIL_PAGE_URL'],
            'PREVIEW_TEXT' => $previewText,
            'PREVIEW_TEXT_PLAIN' => $plainText,
            'ACTIVE_FROM' => (string)$row['ACTIVE_FROM'],
            'TIMESTAMP_X' => (string)$row['TIMESTAMP_X'],
            'DISPLAY_ACTIVE_FROM' => '',
            'PREVIEW_PICTURE' => false,
        ];

        if ($item['ACTIVE_FROM'] !== '') {
            $item['DISPLAY_ACTIVE_FROM'] = FormatDate(
                $this->arParams['ACTIVE_DATE_FORMAT'],
                MakeTimeStamp($item['ACTIVE_FROM'])
            );
        } elseif ($item['TIMESTAMP_X'] !== '') {
            $item['DISPLAY_ACTIVE_FROM'] = FormatDate(
                $this->arParams['ACTIVE_DATE_FORMAT'],
                MakeTimeStamp($item['TIMESTAMP_X'])
            );
        }

        // Штатный помощник: превращает ID файла в массив, подставляет ALT/TITLE
        // из SEO-свойств элемента и безопасный SRC.
        Tools::getFieldImageData(
            $item,
            ['PREVIEW_PICTURE'],
            Tools::IPROPERTY_ENTITY_ELEMENT
        );

        return $item;
    }

    private function registerCacheTag(): void
    {
        if (!defined('BX_COMP_MANAGED_CACHE')) {
            return;
        }

        global $CACHE_MANAGER;

        // CIBlockResult::Fetch() регистрирует этот тег сам, но только когда
        // прочитана хотя бы одна строка. Для пустого списка и для данных,
        // полученных не через CIBlockElement (ORM, свой SQL), тег нужно
        // регистрировать явно.
        $CACHE_MANAGER->RegisterTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
    }

    private function setPageTitle(): void
    {
        if (!$this->arParams['SET_TITLE']) {
            return;
        }

        $name = (string)($this->arResult['IBLOCK_NAME'] ?? '');
        if ($name === '') {
            return;
        }

        global $APPLICATION;
        $APPLICATION->SetTitle($name);
    }
}
