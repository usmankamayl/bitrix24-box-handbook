<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;

class OrderHistoryComponent extends CBitrixComponent
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

        $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();

        if (!$hlblock) {
            throw new \Exception('Highload-блок не найден');
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);
        $this->dataClass = $entity->getDataClass();

        return $this->dataClass;
    }

    /**
     * Логирование изменения заказа
     *
     * @param int $orderId ID заказа
     * @param string $changeType Тип изменения
     * @param string $fieldName Название поля
     * @param mixed $oldValue Старое значение
     * @param mixed $newValue Новое значение
     * @param string $comment Комментарий
     */
    public static function logChange($orderId, $changeType, $fieldName, $oldValue, $newValue, $comment = '')
    {
        global $USER;

        if (!Loader::includeModule('highloadblock')) {
            return false;
        }

        // Получаем DataClass (ID блока можно хранить в настройках)
        $hlblockId = 3; // В реальном проекте получать из настроек
        $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();

        if (!$hlblock) {
            return false;
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);
        $dataClass = $entity->getDataClass();

        // Данные для логирования
        $logData = [
            'UF_ORDER_ID' => (int)$orderId,
            'UF_CHANGE_TYPE' => $changeType,
            'UF_FIELD_NAME' => $fieldName,
            'UF_OLD_VALUE' => self::serializeValue($oldValue),
            'UF_NEW_VALUE' => self::serializeValue($newValue),
            'UF_USER_ID' => $USER->GetID() ?: 0,
            'UF_CHANGE_DATE' => new DateTime(),
            'UF_COMMENT' => $comment,
        ];

        $result = $dataClass::add($logData);

        return $result->isSuccess();
    }

    /**
     * Сериализация значения для хранения
     */
    protected static function serializeValue($value)
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string)$value;
    }

    /**
     * Десериализация значения
     */
    protected function unserializeValue($value)
    {
        if (empty($value)) {
            return '';
        }

        // Пытаемся декодировать JSON
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    /**
     * Получение истории изменений
     */
    protected function getHistory()
    {
        $dataClass = $this->getDataClass();

        // Формирование фильтра
        $filter = [];

        // Фильтр по заказу
        if ($this->arParams['ORDER_ID'] > 0) {
            $filter['=UF_ORDER_ID'] = (int)$this->arParams['ORDER_ID'];
        }

        // Фильтр по типу изменения
        if (!empty($this->arParams['FILTER_CHANGE_TYPE'])) {
            $filter['=UF_CHANGE_TYPE'] = $this->arParams['FILTER_CHANGE_TYPE'];
        }

        // Фильтр по дате
        if ($this->arParams['FILTER_DAYS'] > 0) {
            $dateFrom = new DateTime();
            $dateFrom->add('-' . $this->arParams['FILTER_DAYS'] . ' days');
            $filter['>=UF_CHANGE_DATE'] = $dateFrom;
        }

        // Параметры выборки
        $params = [
            'select' => ['*'],
            'filter' => $filter,
            'order' => ['UF_CHANGE_DATE' => 'DESC'],
            'limit' => (int)$this->arParams['PAGE_SIZE'],
        ];

        // Получение данных
        $result = $dataClass::getList($params);

        $items = [];
        while ($item = $result->fetch()) {
            // Форматирование даты
            if ($item['UF_CHANGE_DATE'] instanceof DateTime) {
                $item['UF_CHANGE_DATE_FORMATTED'] = $item['UF_CHANGE_DATE']->format('d.m.Y H:i:s');
            }

            // Десериализация значений
            $item['UF_OLD_VALUE_DISPLAY'] = $this->formatValue(
                $this->unserializeValue($item['UF_OLD_VALUE'])
            );
            $item['UF_NEW_VALUE_DISPLAY'] = $this->formatValue(
                $this->unserializeValue($item['UF_NEW_VALUE'])
            );

            // Получение имени пользователя
            if ($item['UF_USER_ID'] > 0) {
                $user = \CUser::GetByID($item['UF_USER_ID'])->Fetch();
                $item['USER_NAME'] = $user ? $user['NAME'] . ' ' . $user['LAST_NAME'] : 'ID: ' . $item['UF_USER_ID'];
            } else {
                $item['USER_NAME'] = 'Система';
            }

            // Локализация типа изменения
            $item['CHANGE_TYPE_NAME'] = $this->getChangeTypeName($item['UF_CHANGE_TYPE']);

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Форматирование значения для отображения
     */
    protected function formatValue($value)
    {
        if (is_array($value)) {
            return '<pre>' . htmlspecialcharsbx(print_r($value, true)) . '</pre>';
        }

        if (empty($value)) {
            return '<em>пусто</em>';
        }

        return htmlspecialcharsbx($value);
    }

    /**
     * Получение названия типа изменения
     */
    protected function getChangeTypeName($type)
    {
        $types = [
            'status' => 'Изменение статуса',
            'payment' => 'Оплата',
            'shipment' => 'Отгрузка',
            'properties' => 'Свойства заказа',
            'price' => 'Изменение цены',
            'items' => 'Состав заказа',
        ];

        return $types[$type] ?? $type;
    }

    /**
     * Получение статистики по изменениям
     */
    protected function getStatistics()
    {
        $dataClass = $this->getDataClass();

        $filter = [];
        if ($this->arParams['ORDER_ID'] > 0) {
            $filter['=UF_ORDER_ID'] = (int)$this->arParams['ORDER_ID'];
        }

        // Общее количество изменений
        $totalChanges = $dataClass::getCount($filter);

        // Изменения по типам
        $changesByType = $dataClass::getList([
            'select' => [
                'UF_CHANGE_TYPE',
                'CHANGES_COUNT'
            ],
            'filter' => $filter,
            'runtime' => [
                new \Bitrix\Main\Entity\ExpressionField(
                    'CHANGES_COUNT',
                    'COUNT(*)'
                ),
            ],
            'group' => ['UF_CHANGE_TYPE'],
            'order' => ['CHANGES_COUNT' => 'DESC'],
        ])->fetchAll();

        // Последние изменившие пользователи
        $lastUsers = $dataClass::getList([
            'select' => ['UF_USER_ID'],
            'filter' => array_merge($filter, ['>UF_USER_ID' => 0]),
            'order' => ['UF_CHANGE_DATE' => 'DESC'],
            'limit' => 5,
            'group' => ['UF_USER_ID'],
        ])->fetchAll();

        return [
            'TOTAL_CHANGES' => $totalChanges,
            'CHANGES_BY_TYPE' => $changesByType,
            'LAST_USERS' => $lastUsers,
        ];
    }

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams['HLBLOCK_ID'] = (int)($arParams['HLBLOCK_ID'] ?? 3);
        $arParams['ORDER_ID'] = (int)($arParams['ORDER_ID'] ?? 0);
        $arParams['PAGE_SIZE'] = (int)($arParams['PAGE_SIZE'] ?? 50);
        $arParams['FILTER_CHANGE_TYPE'] = trim($arParams['FILTER_CHANGE_TYPE'] ?? '');
        $arParams['FILTER_DAYS'] = (int)($arParams['FILTER_DAYS'] ?? 0);
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
                // Получаем историю изменений
                $this->arResult['ITEMS'] = $this->getHistory();

                // Получаем статистику
                $this->arResult['STATISTICS'] = $this->getStatistics();

                // Параметры
                $this->arResult['PARAMS'] = $this->arParams;

                $this->includeComponentTemplate();
            }
        } catch (\Exception $e) {
            $this->abortResultCache();
            ShowError($e->getMessage());
        }
    }
}
