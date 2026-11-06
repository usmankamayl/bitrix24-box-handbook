<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Bitrix\Main\GroupTable;
use Bitrix\Main\Application;

/**
 * Компонент списка пользователей с фильтрацией
 * Демонстрирует работу с UserTable, фильтрами и пагинацией
 */
class UserListFilterComponent extends CBitrixComponent
{
    /**
     * Проверка подключения необходимых модулей
     */
    protected function checkModules()
    {
        if (!Loader::includeModule('main')) {
            throw new \Exception('Не удалось загрузить модуль main');
        }

        return true;
    }

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams)
    {
        // Количество элементов на странице
        $arParams['PAGE_SIZE'] = (int)($arParams['PAGE_SIZE'] ?? 20);
        if ($arParams['PAGE_SIZE'] <= 0) {
            $arParams['PAGE_SIZE'] = 20;
        }

        // Показывать неактивных
        $arParams['SHOW_INACTIVE'] = $arParams['SHOW_INACTIVE'] ?? 'N';

        // Сортировка по умолчанию
        $arParams['SORT_BY'] = $arParams['SORT_BY'] ?? 'ID';
        $arParams['SORT_ORDER'] = $arParams['SORT_ORDER'] ?? 'DESC';

        // Поля для отображения
        $arParams['DISPLAY_FIELDS'] = !empty($arParams['DISPLAY_FIELDS'])
            ? $arParams['DISPLAY_FIELDS']
            : ['ID', 'NAME', 'LAST_NAME', 'EMAIL', 'ACTIVE', 'DATE_REGISTER'];

        return $arParams;
    }

    /**
     * Получение параметров фильтра из запроса
     */
    protected function getFilterFromRequest()
    {
        $request = Application::getInstance()->getContext()->getRequest();

        return [
            'search' => $request->get('search') ?? '',
            'group_id' => (int)($request->get('group_id') ?? 0),
            'active' => $request->get('active') ?? '',
            'page' => max(1, (int)($request->get('page') ?? 1)),
        ];
    }

    /**
     * Построение фильтра для запроса
     */
    protected function buildFilter($arRequestFilter)
    {
        $arFilter = [];

        // Активность
        if ($arRequestFilter['active'] === 'Y' || $arRequestFilter['active'] === 'N') {
            $arFilter['=ACTIVE'] = $arRequestFilter['active'];
        } elseif ($this->arParams['SHOW_INACTIVE'] === 'N') {
            $arFilter['=ACTIVE'] = 'Y';
        }

        // Поиск по имени, фамилии или email
        if (!empty($arRequestFilter['search'])) {
            $search = trim($arRequestFilter['search']);
            $arFilter[] = [
                'LOGIC' => 'OR',
                ['%NAME' => $search],
                ['%LAST_NAME' => $search],
                ['%EMAIL' => $search],
                ['%LOGIN' => $search],
            ];
        }

        // Фильтр по группе
        if ($arRequestFilter['group_id'] > 0) {
            $arFilter['GROUPS.GROUP_ID'] = $arRequestFilter['group_id'];
        }

        return $arFilter;
    }

    /**
     * Получение списка пользователей
     */
    protected function getUsers($arFilter, $page)
    {
        $offset = ($page - 1) * $this->arParams['PAGE_SIZE'];

        $queryParams = [
            'filter' => $arFilter,
            'select' => array_merge($this->arParams['DISPLAY_FIELDS'], ['PERSONAL_PHOTO']),
            'order' => [$this->arParams['SORT_BY'] => $this->arParams['SORT_ORDER']],
            'limit' => $this->arParams['PAGE_SIZE'],
            'offset' => $offset,
            'count_total' => true,
        ];

        // Если фильтруем по группе, нужен join
        if (isset($arFilter['GROUPS.GROUP_ID'])) {
            $queryParams['runtime'] = [
                new \Bitrix\Main\Entity\ReferenceField(
                    'GROUPS',
                    '\Bitrix\Main\UserGroupTable',
                    ['=this.ID' => 'ref.USER_ID']
                )
            ];
        }

        $result = UserTable::getList($queryParams);

        $arUsers = [];
        while ($user = $result->fetch()) {
            // Получение групп пользователя
            $rsGroups = \CUser::GetUserGroup($user['ID']);
            $user['GROUPS'] = [];
            while ($arGroup = $rsGroups->Fetch()) {
                $user['GROUPS'][] = (int)$arGroup['GROUP_ID'];
            }

            // Форматирование фото
            if ($user['PERSONAL_PHOTO']) {
                $user['PERSONAL_PHOTO_SRC'] = \CFile::GetPath($user['PERSONAL_PHOTO']);
            }

            $arUsers[] = $user;
        }

        return [
            'users' => $arUsers,
            'total' => $result->getCount(),
        ];
    }

    /**
     * Получение списка групп для фильтра
     */
    protected function getGroups()
    {
        $result = GroupTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'select' => ['ID', 'NAME'],
            'order' => ['NAME' => 'ASC'],
        ]);

        return $result->fetchAll();
    }

    /**
     * Расчет пагинации
     */
    protected function calculatePagination($total, $currentPage)
    {
        $totalPages = ceil($total / $this->arParams['PAGE_SIZE']);

        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'items_per_page' => $this->arParams['PAGE_SIZE'],
            'has_prev' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
        ];
    }

    /**
     * Точка входа в компонент
     */
    public function executeComponent()
    {
        try {
            $this->checkModules();

            // Получение параметров фильтра из запроса
            $arRequestFilter = $this->getFilterFromRequest();

            // Построение фильтра
            $arFilter = $this->buildFilter($arRequestFilter);

            // Получение пользователей
            $data = $this->getUsers($arFilter, $arRequestFilter['page']);

            // Получение групп для фильтра
            $arGroups = $this->getGroups();

            // Расчет пагинации
            $arPagination = $this->calculatePagination($data['total'], $arRequestFilter['page']);

            // Заполнение результата
            $this->arResult = [
                'USERS' => $data['users'],
                'GROUPS' => $arGroups,
                'FILTER' => $arRequestFilter,
                'PAGINATION' => $arPagination,
                'DISPLAY_FIELDS' => $this->arParams['DISPLAY_FIELDS'],
            ];

            // Подключение шаблона
            $this->includeComponentTemplate();

        } catch (\Exception $e) {
            ShowError($e->getMessage());
        }
    }
}
