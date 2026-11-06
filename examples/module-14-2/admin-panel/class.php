<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

/**
 * Компонент админ-панели с демонстрацией BX.UI компонентов
 */
class AdminPanelComponent extends CBitrixComponent implements Controllerable
{
    /**
     * Настройка фильтров для AJAX действий
     */
    public function configureActions()
    {
        return [
            'getItems' => [
                'prefilters' => [],
            ],
            'deleteItem' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
            'activateItem' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
            'assignUsers' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
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

        $this->arResult['IBLOCK_ID'] = (int)$this->arParams['IBLOCK_ID'];
        $this->arResult['PAGE_SIZE'] = (int)$this->arParams['PAGE_SIZE'];
        $this->arResult['COMPONENT_ID'] = 'admin-panel-' . uniqid();

        $this->includeComponentTemplate();
    }

    /**
     * AJAX: Получение списка элементов
     *
     * @param int $page Номер страницы
     * @param string $filter Фильтр активности
     * @return array
     */
    public function getItemsAction($page = 1, $filter = 'all')
    {
        if (!Loader::includeModule('iblock')) {
            return ['success' => false, 'error' => 'Модуль инфоблоков не установлен'];
        }

        $iblockId = (int)$this->arParams['IBLOCK_ID'];
        $pageSize = (int)$this->arParams['PAGE_SIZE'];
        $offset = ($page - 1) * $pageSize;

        // Формируем фильтр
        $arFilter = ['IBLOCK_ID' => $iblockId];

        if ($filter === 'active') {
            $arFilter['ACTIVE'] = 'Y';
        } elseif ($filter === 'inactive') {
            $arFilter['ACTIVE'] = 'N';
        }

        // Получаем элементы
        $result = ElementTable::getList([
            'filter' => $arFilter,
            'select' => ['ID', 'NAME', 'ACTIVE', 'TIMESTAMP_X', 'PREVIEW_PICTURE'],
            'order' => ['ID' => 'DESC'],
            'limit' => $pageSize,
            'offset' => $offset,
        ]);

        $items = [];
        while ($element = $result->fetch()) {
            // Миниатюра картинки
            $picture = null;
            if ($element['PREVIEW_PICTURE']) {
                $file = \CFile::GetFileArray($element['PREVIEW_PICTURE']);
                if ($file) {
                    $resized = \CFile::ResizeImageGet(
                        $file,
                        ['width' => 40, 'height' => 40],
                        BX_RESIZE_IMAGE_PROPORTIONAL
                    );
                    $picture = $resized['src'];
                }
            }

            $items[] = [
                'id' => $element['ID'],
                'name' => $element['NAME'],
                'active' => $element['ACTIVE'] === 'Y',
                'date' => $element['TIMESTAMP_X']->format('d.m.Y H:i'),
                'picture' => $picture,
            ];
        }

        // Общее количество
        $totalCount = ElementTable::getCount($arFilter);

        return [
            'success' => true,
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $pageSize),
            ],
        ];
    }

    /**
     * AJAX: Удаление элемента
     *
     * @param int $id ID элемента
     * @return array
     */
    public function deleteItemAction($id)
    {
        if (!Loader::includeModule('iblock')) {
            return ['success' => false, 'error' => 'Модуль инфоблоков не установлен'];
        }

        $id = (int)$id;

        // Проверяем существование элемента
        $element = ElementTable::getById($id)->fetch();
        if (!$element) {
            return ['success' => false, 'error' => 'Элемент не найден'];
        }

        // Удаляем
        $result = \CIBlockElement::Delete($id);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Элемент успешно удален',
                'id' => $id,
            ];
        }

        return [
            'success' => false,
            'error' => 'Ошибка при удалении элемента',
        ];
    }

    /**
     * AJAX: Активация/деактивация элемента
     *
     * @param int $id ID элемента
     * @param bool $active Новое значение активности
     * @return array
     */
    public function activateItemAction($id, $active)
    {
        if (!Loader::includeModule('iblock')) {
            return ['success' => false, 'error' => 'Модуль инфоблоков не установлен'];
        }

        $id = (int)$id;
        $active = (bool)$active;

        // Проверяем существование элемента
        $element = ElementTable::getById($id)->fetch();
        if (!$element) {
            return ['success' => false, 'error' => 'Элемент не найден'];
        }

        // Обновляем
        $el = new \CIBlockElement;
        $result = $el->Update($id, [
            'ACTIVE' => $active ? 'Y' : 'N',
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => $active ? 'Элемент активирован' : 'Элемент деактивирован',
                'id' => $id,
                'active' => $active,
            ];
        }

        return [
            'success' => false,
            'error' => 'Ошибка при обновлении элемента',
        ];
    }

    /**
     * AJAX: Назначение пользователей элементу
     *
     * @param int $itemId ID элемента
     * @param array $userIds Массив ID пользователей
     * @return array
     */
    public function assignUsersAction($itemId, $userIds)
    {
        if (!Loader::includeModule('iblock')) {
            return ['success' => false, 'error' => 'Модуль инфоблоков не установлен'];
        }

        $itemId = (int)$itemId;
        $userIds = array_map('intval', (array)$userIds);

        // Проверяем элемент
        $element = ElementTable::getById($itemId)->fetch();
        if (!$element) {
            return ['success' => false, 'error' => 'Элемент не найден'];
        }

        // Обновляем свойство с пользователями (предполагаем, что есть свойство ASSIGNED_USERS)
        $el = new \CIBlockElement;
        $result = $el->Update($itemId, [
            'PROPERTY_VALUES' => [
                'ASSIGNED_USERS' => $userIds,
            ],
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Пользователи назначены',
                'itemId' => $itemId,
                'userIds' => $userIds,
            ];
        }

        return [
            'success' => false,
            'error' => 'Ошибка при назначении пользователей',
        ];
    }
}
