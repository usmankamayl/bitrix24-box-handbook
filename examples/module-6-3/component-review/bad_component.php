<?php
/**
 * Code Review #1 — «Плохой» компонент списка заказов
 *
 * ЭТОТ КОД — ДЛЯ РАЗБОРА. Содержит 8+ типичных ошибок.
 * Задание: найдите и исправьте все проблемы.
 *
 * Ответы — в файле review_analysis.md (смотреть после попытки).
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class OrderListComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        global $USER, $DB;

        // Проверка авторизации — вроде есть
        if (!$USER->IsAuthorized()) {
            LocalRedirect('/auth/');
        }

        // Получаем заказы пользователя
        $userId = $USER->GetID();

        // ЗАПРОС 1
        $orders = $DB->Query("
            SELECT ID, PRICE, DATE_INSERT, STATUS_ID, USER_ID
            FROM b_sale_order
            WHERE USER_ID = $userId
            ORDER BY DATE_INSERT DESC
            LIMIT 20
        ");

        $arResult = [];
        while ($row = $orders->Fetch()) {
            $orderId = $row['ID'];

            // ЗАПРОС 2 внутри цикла — для каждого заказа
            $items = $DB->Query("
                SELECT NAME, QUANTITY, PRICE
                FROM b_sale_basket
                WHERE ORDER_ID = $orderId
            ");

            $itemsList = [];
            while ($item = $items->Fetch()) {
                $itemsList[] = $item;
            }

            // ЗАПРОС 3 — статус из справочника
            $status = $DB->Query("
                SELECT NAME
                FROM b_sale_status
                WHERE ID = '" . $row['STATUS_ID'] . "'
            ")->Fetch();

            // ЗАПРОС 4 — город из свойств
            $city = $DB->Query("
                SELECT VALUE
                FROM b_sale_order_props_value
                WHERE ORDER_ID = $orderId AND CODE = 'CITY'
            ")->Fetch();

            $arResult[] = [
                'ID' => $row['ID'],
                'PRICE' => $row['PRICE'],
                'DATE' => $row['DATE_INSERT'],
                'STATUS' => $status['NAME'],
                'CITY' => $city['VALUE'],
                'ITEMS' => $itemsList,
                'CAN_CANCEL' => $row['STATUS_ID'] !== 'F', // бизнес-логика в шаблоне
                'CAN_REPEAT' => $row['STATUS_ID'] === 'F',
            ];
        }

        // Бизнес-логика в компоненте — расчёт скидки
        $totalSpent = 0;
        foreach ($arResult as $o) {
            $totalSpent += $o['PRICE'];
        }
        $discount = 0;
        if ($totalSpent > 100000) {
            $discount = 10; // 10% — должно быть в настройках, не в коде
        }

        $this->arResult = $arResult;
        $this->arResult['DISCOUNT'] = $discount;
        $this->arResult['TOTAL_SPENT'] = $totalSpent;

        // Кеширование — вроде есть, но без тегов и с фиксированным ключом
        $cacheId = 'order_list';
        if ($this->startResultCache(3600, $cacheId)) {
            $this->includeComponentTemplate();
        }
    }
}
