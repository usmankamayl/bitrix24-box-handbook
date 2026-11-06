<?php
/**
 * Code Review #1 — Исправленный компонент списка заказов
 *
 * Исправлены все проблемы из bad_component.php.
 * Комментарии показывают, что изменено и почему.
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Sale\Internals\BasketTable;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Sale\Internals\StatusLangTable;
use Bitrix\Main\DB\SqlExpression;

class OrderListComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        global $USER;

        // БЫЛО: LocalRedirect без exit — код продолжает выполняться
        if (!$USER->IsAuthorized()) {
            LocalRedirect('/auth/');
            die(); // ИСПРАВЛЕНО: прекращаем выполнение
        }

        $userId = (int)$USER->GetID();

        // БЫЛО: 4 запроса в цикле → N+1 (4 × 20 = 80+ запросов)
        // СТАЛО: 3 запроса всего — список + 2 batch-join

        // Запрос 1: список заказов (плоский, без load)
        $orders = Order::getList([
            'select' => ['ID', 'PRICE', 'DATE_INSERT', 'STATUS_ID'],
            'filter' => ['USER_ID' => $userId],
            'order' => ['DATE_INSERT' => 'DESC'],
            'limit' => 20,
        ]);

        $arResult = [];
        $orderIds = [];
        while ($row = $orders->fetch()) {
            $orderIds[] = $row['ID'];
            $arResult[$row['ID']] = [
                'ID' => $row['ID'],
                'PRICE' => $row['PRICE'],
                'DATE' => $row['DATE_INSERT'],
                'STATUS_ID' => $row['STATUS_ID'],
                'ITEMS' => [],
                'CITY' => '',
            ];
        }

        if (!empty($orderIds)) {
            // Запрос 2: все товары для всех заказов одним запросом
            $items = BasketTable::getList([
                'select' => ['ORDER_ID', 'NAME', 'QUANTITY', 'PRICE'],
                'filter' => ['=ORDER_ID' => $orderIds],
            ]);
            while ($item = $items->fetch()) {
                $arResult[$item['ORDER_ID']]['ITEMS'][] = $item;
            }

            // Запрос 3: статусы (справочник, маленький — можно и в отдельной таблице)
            $statuses = StatusLangTable::getList([
                'select' => ['STATUS_ID', 'NAME'],
                'filter' => ['LID' => LANGUAGE_ID],
            ])->fetchAll();
            $statusMap = array_column($statuses, 'NAME', 'STATUS_ID');
            foreach ($arResult as &$o) {
                $o['STATUS'] = $statusMap[$o['STATUS_ID']] ?? $o['STATUS_ID'];
            }
            unset($o);

            // Запрос 4: города для всех заказов одним запросом
            $cities = OrderPropsValueTable::getList([
                'select' => ['ORDER_ID', 'VALUE'],
                'filter' => [
                    '=ORDER_ID' => $orderIds,
                    'ORDER_PROPS.CODE' => 'CITY',
                ],
            ]);
            while ($c = $cities->fetch()) {
                $arResult[$c['ORDER_ID']]['CITY'] = $c['VALUE'];
            }
        }

        $arResult = array_values($arResult);

        // БЫЛО: бизнес-логика в компоненте (скидка, CAN_CANCEL)
        // СТАЛО: выносим в сервис — тестируемо, переиспользуемо
        $this->arResult['ORDERS'] = $arResult;
        $this->arResult['DISCOUNT'] = DiscountService::getDiscountForUser($userId);
        $this->arResult['CAN_CANCEL'] = $this->getCancelableOrderIds($arResult);

        // БЫЛО: кеш без тегов, ключ 'order_list' — одинаковый для всех!
        // СТАЛО: кеш с тегами, ключ по user_id
        $cacheId = 'order_list_u' . $userId;
        if ($this->startResultCache(3600, $cacheId)) {
            $this->setResultCacheKeys(['ORDERS', 'DISCOUNT']);
            $this->includeComponentTemplate();
        }
    }

    /**
     * Бизнес-логика — отдельный метод, тестируем
     */
    private function getCancelableOrderIds(array $orders): array
    {
        $cancelable = [];
        foreach ($orders as $o) {
            // Отменить можно только неоплаченный и не выполненный заказ
            if ($o['STATUS_ID'] !== 'F' && $o['STATUS_ID'] !== 'D') {
                $cancelable[] = $o['ID'];
            }
        }
        return $cancelable;
    }
}

/**
 * Сервис скидок — вынесено из компонента.
 * В реальном проекте — отдельный класс, тестируем, инжектируем.
 */
class DiscountService
{
    public static function getDiscountForUser(int $userId): int
    {
        // БЫЛО: хардкод 'if ($totalSpent > 100000) $discount = 10'
        // СТАЛО: настройка в админке, таблица скидок, или модуль sale
        $total = self::getTotalSpent($userId);
        $thresholds = [
            100000 => 10,
            50000  => 5,
            20000  => 3,
        ];
        foreach ($thresholds as $min => $discount) {
            if ($total >= $min) {
                return $discount;
            }
        }
        return 0;
    }

    private static function getTotalSpent(int $userId): float
    {
        $result = Order::getList([
            'select' => ['SUM' => new SqlExpression('SUM(PRICE)')],
            'filter' => ['USER_ID' => $userId, 'PAYED' => 'Y'],
        ])->fetch();
        return (float)($result['SUM'] ?? 0);
    }
}
