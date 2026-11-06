<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Data\Cache;
use Bitrix\Highloadblock\HighloadBlockTable;

class ViewCounterComponent extends CBitrixComponent
{
    /** @var string Класс для работы с HL-блоком */
    protected $dataClass;

    /** @var string Ключ для cookie */
    const COOKIE_PREFIX = 'view_';

    /**
     * Проверка подключения необходимых модулей
     */
    protected function checkModules()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \Exception('Модуль highloadblock не установлен');
        }

        return true;
    }

    /**
     * Получение класса для работы с HL-блоком
     */
    protected function getDataClass()
    {
        if ($this->dataClass) {
            return $this->dataClass;
        }

        $hlblockId = (int)$this->arParams['HLBLOCK_ID'];

        if ($hlblockId <= 0) {
            throw new \Exception('Не указан ID Highload-блока');
        }

        $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();

        if (!$hlblock) {
            throw new \Exception('Highload-блок не найден');
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);
        $this->dataClass = $entity->getDataClass();

        return $this->dataClass;
    }

    /**
     * Получение ID текущей сущности
     */
    protected function getEntityId()
    {
        $entityId = (int)$this->arParams['ENTITY_ID'];

        // Если не передан, используем ID текущей страницы (можно расширить)
        if ($entityId <= 0) {
            global $APPLICATION;
            $entityId = crc32($APPLICATION->GetCurPage());
        }

        return $entityId;
    }

    /**
     * Проверка уникальности просмотра
     */
    protected function isUniqueView($entityType, $entityId)
    {
        if ($this->arParams['UNIQUE_VIEWS'] !== 'Y') {
            return true;
        }

        $cookieKey = self::COOKIE_PREFIX . $entityType . '_' . $entityId;
        $request = Application::getInstance()->getContext()->getRequest();

        // Проверяем cookie
        if ($request->getCookie($cookieKey)) {
            return false;
        }

        // Устанавливаем cookie
        $cookie = new \Bitrix\Main\Web\Cookie(
            $cookieKey,
            '1',
            time() + (int)$this->arParams['UNIQUE_PERIOD']
        );
        $cookie->setDomain($request->getHttpHost());
        $cookie->setPath('/');

        Application::getInstance()->getContext()->getResponse()->addCookie($cookie);

        return true;
    }

    /**
     * Подсчет просмотра
     */
    protected function incrementView()
    {
        $dataClass = $this->getDataClass();
        $entityType = $this->arParams['ENTITY_TYPE'];
        $entityId = $this->getEntityId();

        // Проверяем уникальность
        if (!$this->isUniqueView($entityType, $entityId)) {
            return false;
        }

        global $USER;
        $userId = $USER->GetID() ?: 0;
        $request = Application::getInstance()->getContext()->getRequest();

        // Данные просмотра
        $viewData = [
            'UF_ENTITY_TYPE' => $entityType,
            'UF_ENTITY_ID' => $entityId,
            'UF_USER_ID' => $userId,
            'UF_IP_ADDRESS' => $request->getRemoteAddress(),
            'UF_VIEW_DATE' => new DateTime(),
        ];

        $result = $dataClass::add($viewData);

        return $result->isSuccess();
    }

    /**
     * Получение количества просмотров
     */
    protected function getViewCount()
    {
        $dataClass = $this->getDataClass();
        $entityType = $this->arParams['ENTITY_TYPE'];
        $entityId = $this->getEntityId();

        // Кеширование
        $cache = Cache::createInstance();
        $cacheTime = $this->arParams['CACHE_TIME'];
        $cacheId = 'view_count_' . $entityType . '_' . $entityId;
        $cacheDir = '/hlblock/view_counter/';

        if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
            $viewCount = $cache->getVars();
        } else {
            $cache->startDataCache();

            // Подсчет просмотров
            $viewCount = $dataClass::getCount([
                '=UF_ENTITY_TYPE' => $entityType,
                '=UF_ENTITY_ID' => $entityId,
            ]);

            $cache->endDataCache($viewCount);
        }

        return $viewCount;
    }

    /**
     * Получение детальной статистики
     */
    protected function getDetailedStats()
    {
        $dataClass = $this->getDataClass();
        $entityType = $this->arParams['ENTITY_TYPE'];
        $entityId = $this->getEntityId();

        // Кеширование
        $cache = Cache::createInstance();
        $cacheTime = $this->arParams['CACHE_TIME'];
        $cacheId = 'view_stats_' . $entityType . '_' . $entityId;
        $cacheDir = '/hlblock/view_counter/';

        if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
            $stats = $cache->getVars();
        } else {
            $cache->startDataCache();

            $filter = [
                '=UF_ENTITY_TYPE' => $entityType,
                '=UF_ENTITY_ID' => $entityId,
            ];

            // Общее количество
            $totalViews = $dataClass::getCount($filter);

            // Уникальные пользователи
            $uniqueUsers = $dataClass::getList([
                'select' => ['UF_USER_ID'],
                'filter' => array_merge($filter, ['>UF_USER_ID' => 0]),
                'group' => ['UF_USER_ID'],
            ])->getSelectedRowsCount();

            // Просмотры за последние 24 часа
            $dateFrom = new DateTime();
            $dateFrom->add('-1 day');

            $viewsToday = $dataClass::getCount(array_merge($filter, [
                '>=UF_VIEW_DATE' => $dateFrom,
            ]));

            // Просмотры за последнюю неделю
            $dateFrom = new DateTime();
            $dateFrom->add('-7 days');

            $viewsWeek = $dataClass::getCount(array_merge($filter, [
                '>=UF_VIEW_DATE' => $dateFrom,
            ]));

            // Просмотры по дням (последние 7 дней)
            $viewsByDay = $dataClass::getList([
                'select' => [
                    'VIEW_DATE',
                    'VIEWS_COUNT'
                ],
                'filter' => array_merge($filter, [
                    '>=UF_VIEW_DATE' => $dateFrom,
                ]),
                'runtime' => [
                    new \Bitrix\Main\Entity\ExpressionField(
                        'VIEW_DATE',
                        'DATE(%s)',
                        ['UF_VIEW_DATE']
                    ),
                    new \Bitrix\Main\Entity\ExpressionField(
                        'VIEWS_COUNT',
                        'COUNT(*)'
                    ),
                ],
                'group' => ['VIEW_DATE'],
                'order' => ['VIEW_DATE' => 'ASC'],
            ])->fetchAll();

            $stats = [
                'TOTAL_VIEWS' => $totalViews,
                'UNIQUE_USERS' => $uniqueUsers,
                'VIEWS_TODAY' => $viewsToday,
                'VIEWS_WEEK' => $viewsWeek,
                'VIEWS_BY_DAY' => $viewsByDay,
            ];

            $cache->endDataCache($stats);
        }

        return $stats;
    }

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams['HLBLOCK_ID'] = (int)($arParams['HLBLOCK_ID'] ?? 2);
        $arParams['ENTITY_TYPE'] = trim($arParams['ENTITY_TYPE'] ?? 'article');
        $arParams['ENTITY_ID'] = (int)($arParams['ENTITY_ID'] ?? 0);
        $arParams['UNIQUE_VIEWS'] = ($arParams['UNIQUE_VIEWS'] ?? 'Y') === 'Y' ? 'Y' : 'N';
        $arParams['UNIQUE_PERIOD'] = (int)($arParams['UNIQUE_PERIOD'] ?? 3600);
        $arParams['MODE'] = $arParams['MODE'] ?? 'both';
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);

        return $arParams;
    }

    /**
     * Точка входа в компонент
     */
    public function executeComponent()
    {
        try {
            // Проверка модулей
            $this->checkModules();

            $mode = $this->arParams['MODE'];

            // Подсчет просмотра
            if ($mode === 'count' || $mode === 'both') {
                $this->incrementView();
            }

            // Показ статистики
            if ($mode === 'show' || $mode === 'both') {
                // Получаем количество просмотров
                $this->arResult['VIEW_COUNT'] = $this->getViewCount();

                // Получаем детальную статистику
                $this->arResult['STATISTICS'] = $this->getDetailedStats();

                // Параметры
                $this->arResult['ENTITY_TYPE'] = $this->arParams['ENTITY_TYPE'];
                $this->arResult['ENTITY_ID'] = $this->getEntityId();

                $this->includeComponentTemplate();
            }
        } catch (\Exception $e) {
            ShowError($e->getMessage());
        }
    }
}
