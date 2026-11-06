<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

/**
 * Компонент live-поиска с автодополнением через BX.ajax
 */
class LiveSearchComponent extends CBitrixComponent implements Controllerable
{
    /**
     * Настройка фильтров для AJAX действий
     */
    public function configureActions()
    {
        return [
            'search' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_GET,
                        ActionFilter\HttpMethod::METHOD_POST
                    ]),
                ],
                'postfilters' => []
            ],
        ];
    }

    /**
     * Выполнение компонента
     */
    public function executeComponent()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль инфоблоков не установлен');
            return;
        }

        $this->arResult['SEARCH_ID'] = 'live-search-' . uniqid();
        $this->arResult['IBLOCK_ID'] = (int)$this->arParams['IBLOCK_ID'];
        $this->arResult['MIN_QUERY_LENGTH'] = (int)$this->arParams['MIN_QUERY_LENGTH'];
        $this->arResult['MAX_RESULTS'] = (int)$this->arParams['MAX_RESULTS'];

        $this->includeComponentTemplate();
    }

    /**
     * AJAX метод: поиск элементов
     *
     * @param string $query Поисковый запрос
     * @param int $limit Лимит результатов
     * @return array
     */
    public function searchAction($query, $limit = null)
    {
        if (!Loader::includeModule('iblock')) {
            return [
                'success' => false,
                'error' => 'Модуль инфоблоков не установлен',
            ];
        }

        // Валидация запроса
        $query = trim($query);
        $minLength = (int)$this->arParams['MIN_QUERY_LENGTH'];

        if (mb_strlen($query) < $minLength) {
            return [
                'success' => true,
                'items' => [],
                'count' => 0,
                'message' => "Введите минимум {$minLength} символа",
            ];
        }

        // Лимит результатов
        $limit = $limit ?: (int)$this->arParams['MAX_RESULTS'];
        $limit = min($limit, 50); // Максимум 50 элементов

        // Поиск в инфоблоке
        $items = $this->searchInIblock($query, $limit);

        return [
            'success' => true,
            'items' => $items,
            'count' => count($items),
            'query' => $query,
            'timestamp' => time(),
        ];
    }

    /**
     * Поиск элементов в инфоблоке
     *
     * @param string $query Поисковый запрос
     * @param int $limit Лимит
     * @return array
     */
    private function searchInIblock($query, $limit)
    {
        $iblockId = (int)$this->arParams['IBLOCK_ID'];

        // Формируем фильтр
        $filter = [
            'IBLOCK_ID' => $iblockId,
            'ACTIVE' => 'Y',
            [
                'LOGIC' => 'OR',
                ['%NAME' => $query],           // Поиск по названию
                ['%PREVIEW_TEXT' => $query],   // Поиск по анонсу
                ['%DETAIL_TEXT' => $query],    // Поиск по детальному тексту
            ]
        ];

        // Получаем элементы через D7 ORM
        $result = ElementTable::getList([
            'filter' => $filter,
            'select' => ['ID', 'NAME', 'PREVIEW_TEXT', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE'],
            'limit' => $limit,
            'order' => ['NAME' => 'ASC'],
        ]);

        $items = [];
        while ($element = $result->fetch()) {
            // Подготавливаем превью картинки
            $previewPicture = null;
            if ($element['PREVIEW_PICTURE']) {
                $file = \CFile::GetFileArray($element['PREVIEW_PICTURE']);
                if ($file) {
                    $previewPicture = \CFile::ResizeImageGet(
                        $file,
                        ['width' => 50, 'height' => 50],
                        BX_RESIZE_IMAGE_PROPORTIONAL
                    )['src'];
                }
            }

            // Обрезаем анонс для превью
            $previewText = strip_tags($element['PREVIEW_TEXT']);
            if (mb_strlen($previewText) > 100) {
                $previewText = mb_substr($previewText, 0, 100) . '...';
            }

            $items[] = [
                'id' => $element['ID'],
                'name' => $element['NAME'],
                'preview' => $previewText,
                'url' => $element['DETAIL_PAGE_URL'],
                'picture' => $previewPicture,
            ];
        }

        return $items;
    }
}
