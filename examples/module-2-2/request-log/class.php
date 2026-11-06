<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;

class RequestLogComponent extends CBitrixComponent
{
    /** @var string Класс для работы с HL-блоком */
    protected $dataClass;

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

        // Получаем данные о HL-блоке
        $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();

        if (!$hlblock) {
            throw new \Exception('Highload-блок не найден');
        }

        // Компилируем Entity и получаем DataClass
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $this->dataClass = $entity->getDataClass();

        return $this->dataClass;
    }

    /**
     * Логирование текущего запроса
     */
    protected function logCurrentRequest()
    {
        global $USER;

        $dataClass = $this->getDataClass();
        $request = Application::getInstance()->getContext()->getRequest();

        // Данные для логирования
        $logData = [
            'UF_USER_ID' => $USER->GetID() ?: 0,
            'UF_REQUEST_URL' => $request->getRequestUri(),
            'UF_REQUEST_METHOD' => $request->getRequestMethod(),
            'UF_IP_ADDRESS' => $request->getRemoteAddress(),
            'UF_USER_AGENT' => $request->getUserAgent(),
            'UF_REQUEST_DATE' => new DateTime(),
        ];

        $result = $dataClass::add($logData);

        if (!$result->isSuccess()) {
            $this->arResult['LOG_ERRORS'] = $result->getErrorMessages();
        }
    }

    /**
     * Получение списка логов
     */
    protected function getLogList()
    {
        $dataClass = $this->getDataClass();

        // Формирование фильтра
        $filter = [];

        // Фильтр по пользователю
        if ($this->arParams['FILTER_USER_ID'] > 0) {
            $filter['=UF_USER_ID'] = (int)$this->arParams['FILTER_USER_ID'];
        }

        // Фильтр по дате
        if ($this->arParams['LOG_PERIOD_DAYS'] > 0) {
            $dateFrom = new DateTime();
            $dateFrom->add('-' . $this->arParams['LOG_PERIOD_DAYS'] . ' days');
            $filter['>=UF_REQUEST_DATE'] = $dateFrom;
        }

        // Параметры выборки
        $params = [
            'select' => ['*'],
            'filter' => $filter,
            'order' => ['UF_REQUEST_DATE' => 'DESC'],
            'limit' => (int)$this->arParams['PAGE_SIZE'],
        ];

        // Получение данных
        $result = $dataClass::getList($params);

        $items = [];
        while ($item = $result->fetch()) {
            // Форматирование даты
            if ($item['UF_REQUEST_DATE'] instanceof DateTime) {
                $item['UF_REQUEST_DATE_FORMATTED'] = $item['UF_REQUEST_DATE']->format('d.m.Y H:i:s');
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Получение статистики
     */
    protected function getStatistics()
    {
        $dataClass = $this->getDataClass();

        // Общее количество запросов
        $totalCount = $dataClass::getCount();

        // Количество уникальных пользователей
        $uniqueUsers = $dataClass::getList([
            'select' => ['UF_USER_ID'],
            'group' => ['UF_USER_ID'],
        ])->getSelectedRowsCount();

        // Топ-5 URL по количеству запросов
        $topUrls = $dataClass::getList([
            'select' => [
                'UF_REQUEST_URL',
                'REQUEST_COUNT'
            ],
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField(
                    'REQUEST_COUNT',
                    'COUNT(*)'
                ),
            ],
            'group' => ['UF_REQUEST_URL'],
            'order' => ['REQUEST_COUNT' => 'DESC'],
            'limit' => 5,
        ])->fetchAll();

        return [
            'TOTAL_REQUESTS' => $totalCount,
            'UNIQUE_USERS' => $uniqueUsers,
            'TOP_URLS' => $topUrls,
        ];
    }

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams)
    {
        // Значения по умолчанию
        $arParams['HLBLOCK_ID'] = (int)($arParams['HLBLOCK_ID'] ?? 1);
        $arParams['PAGE_SIZE'] = (int)($arParams['PAGE_SIZE'] ?? 20);
        $arParams['FILTER_USER_ID'] = (int)($arParams['FILTER_USER_ID'] ?? 0);
        $arParams['LOG_PERIOD_DAYS'] = (int)($arParams['LOG_PERIOD_DAYS'] ?? 7);
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

            // Если кеширование включено
            if ($this->startResultCache()) {
                // Логируем текущий запрос
                $this->logCurrentRequest();

                // Получаем список логов
                $this->arResult['ITEMS'] = $this->getLogList();

                // Получаем статистику
                $this->arResult['STATISTICS'] = $this->getStatistics();

                // Сохраняем параметры
                $this->arResult['PARAMS'] = $this->arParams;

                $this->includeComponentTemplate();
            }
        } catch (\Exception $e) {
            $this->abortResultCache();
            ShowError($e->getMessage());
        }
    }
}
