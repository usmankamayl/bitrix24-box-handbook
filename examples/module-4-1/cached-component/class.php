<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

/**
 * Компонент "Список статей с детальным кешированием".
 *
 * Senior-фичи:
 * - CACHE_GROUPS => 'Y' (обязательно для персонализированного контента)
 * - Регистрация тегов для автоинвалидации
 * - SetPageProperty для SEO
 * - Умная логика кеша: аборт при пустом результате
 */
class ArticlesListComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($params)
    {
        $params['IBLOCK_ID']    = (int)$params['IBLOCK_ID'];
        $params['SECTION_ID']   = (int)$params['SECTION_ID'] ?: null;
        $params['PAGE_SIZE']    = max(1, min(100, (int)$params['PAGE_SIZE'] ?: 20));
        $params['CACHE_TYPE']   = $params['CACHE_TYPE'] ?? 'A';
        $params['CACHE_TIME']   = (int)($params['CACHE_TIME'] ?? 3600);
        $params['CACHE_GROUPS'] = $params['CACHE_GROUPS'] ?? 'Y';

        // Проверяем обязательные параметры
        if ($params['IBLOCK_ID'] <= 0) {
            ShowError('IBLOCK_ID is required');
            return false;
        }

        return $params;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('iblock module required');
            return;
        }

        // startResultCache — начало кеширования результата.
        // Внутри скобки: [время жизни], [доп. ключи кеша], [путь кеша]
        if ($this->startResultCache(
            $this->arParams['CACHE_TIME'],
            [
                // Дополнительные параметры ключа кеша.
                // ВАЖНО: только то, что влияет на РЕЗУЛЬТАТ, но не на URL.
                // Например, ID пользователя — уже встроен в CACHE_GROUPS,
                // но свои параметры добавляем сюда.
                $this->arParams['SECTION_ID'],
                $_GET['PAGEN_1'] ?? 1,
            ],
            '/articles.list/' . $this->arParams['IBLOCK_ID']
        )) {
            $this->arResult = $this->fetchData();

            // Пустой результат — не кешируем (иначе "фантомная" пустая страница
            // при временной недоступности БД сохранится на час)
            if (empty($this->arResult['ITEMS'])) {
                $this->abortResultCache();
                $this->arResult['EMPTY'] = true;
            } else {
                // Регистрируем теги ДО includeComponentTemplate —
                // шаблон тоже попадает в кеш
                $this->registerResultTags();
            }

            $this->includeComponentTemplate();
        }
    }

    /**
     * Основная логика получения данных.
     */
    private function fetchData(): array
    {
        global $USER;

        $page    = (int)($_GET['PAGEN_1'] ?? 1);
        $perPage = $this->arParams['PAGE_SIZE'];
        $offset  = ($page - 1) * $perPage;

        $filter = [
            'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
            'ACTIVE'    => 'Y',
        ];

        if ($this->arParams['SECTION_ID']) {
            $filter['IBLOCK_SECTION_ID'] = $this->arParams['SECTION_ID'];
        }

        // Для администраторов/редакторов показываем ещё и неактивные
        if ($USER->IsAdmin() || $this->isEditor()) {
            unset($filter['ACTIVE']);
        }

        // ВАЖНО: эта логика работает ТОЛЬКО если CACHE_GROUPS = 'Y'.
        // Без 'Y' админ увидит кеш гостя (только активные элементы).

        $items = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'ACTIVE_FROM' => 'DESC'],
            $filter,
            false,
            ['nPageSize' => $perPage, 'iNumPage' => $page],
            ['ID', 'IBLOCK_ID', 'NAME', 'PREVIEW_TEXT', 'DETAIL_PAGE_URL',
             'DATE_ACTIVE_FROM', 'PREVIEW_PICTURE', 'IBLOCK_SECTION_ID']
        );

        $result = ['ITEMS' => []];
        while ($item = $items->GetNext()) {
            // Ресайз превью — тоже дорогая операция, кешируем результат
            if ($item['PREVIEW_PICTURE']) {
                $item['PREVIEW_PICTURE_SRC'] = $this->resizeImage($item['PREVIEW_PICTURE']);
            }
            $result['ITEMS'][] = $item;
        }

        // Мета-данные для пагинации
        $result['NAV_RESULT'] = [
            'NavRecordCount'  => $items->NavRecordCount,
            'NavPageCount'    => $items->NavPageCount,
            'NavPageNomer'    => $items->NavPageNomer,
        ];

        return $result;
    }

    /**
     * Регистрация тегов для инвалидации кеша.
     * При изменении ЛЮБОГО зарегистрированного элемента кеш сбросится.
     */
    private function registerResultTags(): void
    {
        // Тег инфоблока — при изменении настроек ИБ
        $this->registerTag('iblock_id_' . $this->arParams['IBLOCK_ID']);

        // Теги каждого элемента — при изменении любого элемента
        // в списке эта страница сбросится
        foreach ($this->arResult['ITEMS'] as $item) {
            $this->registerTag('iblock_element_' . $item['ID']);
        }

        // Тег раздела — при изменении раздела
        if ($this->arParams['SECTION_ID']) {
            $this->registerTag('iblock_section_' . $this->arParams['SECTION_ID']);
        }
    }

    private function isEditor(): bool
    {
        global $USER;
        // Проверяем, есть ли у пользователя право на изменение элементов
        // этого инфоблока (роль "редактор")
        return \CIBlock::GetPermission($this->arParams['IBLOCK_ID']) >= 'U';
    }

    private function resizeImage(int $fileId): ?string
    {
        // \CFile::ResizeImageGet сам кеширует результат на диске —
        // повторный вызов быстрый
        $file = \CFile::ResizeImageGet(
            $fileId,
            ['width' => 300, 'height' => 200],
            BX_RESIZE_IMAGE_PROPORTIONAL,
            true
        );
        return $file['src'] ?? null;
    }
}
