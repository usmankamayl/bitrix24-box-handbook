<?php
// examples/module-5-4/order-list-component/class.php
// Кастомный компонент списка заказов — наследуем штатный,
// расширяем: фильтр по статусам, кеш, пагинация, отображение товаров.

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;
use Bitrix\Main\Engine\Contract\Controllerable;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class AcmeOrderListComponent extends CBitrixPersonalOrderListComponent implements Controllerable
{
    // Включаем действия для AJAX (если шаблон дергает BX.ajax.runComponentAction).
    public function configureActions()
    {
        return [];
    }

    /**
     * Штатный executeComponent переопределяем:
     * - проверяем авторизацию ДО запросов;
     * - кешируем список по пользователю;
     * - дополняем $arResult удобными полями для шаблона.
     */
    public function executeComponent()
    {
        global $USER;

        // 1. Без авторизации — редирект на форму входа, не пустая страница.
        if (!$USER->IsAuthorized()) {
            LocalRedirect('/auth/?backurl=' . urlencode($this->request->getRequestUri()));
            return;
        }

        // 2. Кеш: для ЛК обычно оправдан — заказы меняются редко,
        //    но список большой и с фильтрами. Ключ = user+status+page.
        $cacheTtl = 300; // 5 минут
        $cacheKey = $this->buildCacheKey();
        $cacheDir = '/acme/order_list/' . $USER->GetID();

        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if ($cache->initCache($cacheTtl, $cacheKey, $cacheDir)) {
            $this->arResult = $cache->getVars();
            $this->setResultCacheKeys([]); // шаблон получит полный arResult
            $this->includeComponentTemplate();
            return;
        }

        // 3. Загружаем заказы с фильтрами.
        Loader::includeModule('sale');

        $filter = ['USER_ID' => $USER->GetID()];

        // Фильтр по статусу из параметра компонента или GET.
        $status = $this->request->get('status') ?: $this->arParams['STATUS_FILTER'];
        if ($status && $this->isValidStatus($status)) {
            $filter['STATUS_ID'] = $status;
        }

        // Пагинация — стандартная, через navStart.
        $pageSize = (int)($this->arParams['ORDERS_PER_PAGE'] ?: 10);
        $page = max(1, (int)$this->request->get('page'));

        $nav = new \Bitrix\Main\UI\PageNavigation('order-list');
        $nav->allowAllRecords(false)
            ->setPageSize($pageSize)
            ->initFromUri();

        // Важно: select выбираем только нужные поля — не тащим все 50 колонок.
        $orderList = Order::getList([
            'select' => [
                'ID', 'ACCOUNT_NUMBER', 'STATUS_ID', 'DATE_INSERT',
                'PRICE', 'CURRENCY', 'PAYED', 'CANCELED',
            ],
            'filter' => $filter,
            'order'  => ['DATE_INSERT' => 'DESC'],
            'count_total' => true,
            'offset' => $nav->getOffset(),
            'limit'  => $nav->getLimit(),
        ]);

        $nav->setRecordCount($orderList->getCount());

        $orders = [];
        while ($order = $orderList->fetch()) {
            // Загружаем детали каждого заказа отдельно — для товаров.
            // Это N+1 запрос — в реале оптимизируем через getBasket() join'ом.
            $orders[] = $this->formatOrderForTemplate($order);
        }

        $this->arResult['ORDERS']      = $orders;
        $this->arResult['NAV']         = $nav;
        $this->arResult['STATUS_LIST'] = $this->getStatusList();
        $this->arResult['CURRENT_STATUS'] = $status;

        $cache->startDataCache();
        $cache->endDataCache($this->arResult);

        $this->includeComponentTemplate();
    }

    /**
     * Формируем удобную структуру для шаблона.
     * Шаблон не должен знать про объекты D7 — работает с массивами.
     */
    private function formatOrderForTemplate(array $order): array
    {
        $status = $this->getStatusName($order['STATUS_ID']);

        // Загружаем товары — отдельным запросом.
        // В проде лучше через getBasket() -> getBasketItems() одним проходом.
        $basketItems = [];
        $orderObj = Order::load($order['ID']);
        if ($orderObj && $basket = $orderObj->getBasket()) {
            foreach ($basket->getBasketItems() as $item) {
                $basketItems[] = [
                    'NAME'     => $item->getField('NAME'),
                    'PRICE'    => $item->getPrice(),
                    'QUANTITY' => $item->getQuantity(),
                    'SUM'      => $item->getFinalPrice(),
                ];
            }
        }

        return [
            'ID'             => $order['ID'],
            'ACCOUNT_NUMBER' => $order['ACCOUNT_NUMBER'],
            'STATUS_ID'      => $order['STATUS_ID'],
            'STATUS_NAME'    => $status['NAME'],
            'STATUS_COLOR'   => $status['COLOR'],
            'DATE'           => $order['DATE_INSERT']->format('d.m.Y H:i'),
            'PRICE'          => SaleFormatCurrency($order['PRICE'], $order['CURRENCY']),
            'PAYED'          => $order['PAYED'] === 'Y',
            'CANCELED'       => $order['CANCELED'] === 'Y',
            'ITEMS'          => $basketItems,
            'CAN_CANCEL'     => $this->canCancel($order),
            'CAN_REPEAT'     => $this->canRepeat($order),
        ];
    }

    /**
     * Можно ли отменить заказ — бизнес-логика.
     * Отменить можно только до отгрузки и оплаты.
     */
    private function canCancel(array $order): bool
    {
        // Уже отменён или выполнен — нельзя.
        if ($order['CANCELED'] === 'Y' || in_array($order['STATUS_ID'], ['DE', 'DF'], true)) {
            return false;
        }
        // Оплачен — только через возврат, не отмену.
        if ($order['PAYED'] === 'Y') {
            return false;
        }
        return true;
    }

    private function canRepeat(array $order): bool
    {
        return $order['CANCELED'] !== 'Y';
    }

    /**
     * Статус → читаемое имя и цвет для UI.
     * В реале — из b_sale_status или настроек.
     */
    private function getStatusList(): array
    {
        return [
            'N'  => ['NAME' => 'Новый',           'COLOR' => 'gray'],
            'P'  => ['NAME' => 'Оплачен',          'COLOR' => 'green'],
            'F'  => ['NAME' => 'Выполняется',      'COLOR' => 'blue'],
            'DE' => ['NAME' => 'Доставлен',        'COLOR' => 'green'],
            'DF' => ['NAME' => 'Завершён',         'COLOR' => 'green'],
            'DF' => ['NAME' => 'Выполнен',         'COLOR' => 'green'],
            'DF' => ['NAME' => 'Выполнен',         'COLOR' => 'green'],
            'A'  => ['NAME' => 'Отменён',          'COLOR' => 'red'],
        ];
    }

    private function getStatusName(string $status): array
    {
        $list = $this->getStatusList();
        return $list[$status] ?? ['NAME' => $status, 'COLOR' => 'gray'];
    }

    private function isValidStatus(string $status): bool
    {
        return isset($this->getStatusList()[$status]);
    }

    private function buildCacheKey(): string
    {
        global $USER;
        return md5(serialize([
            'uid'    => $USER->GetID(),
            'status' => $this->request->get('status'),
            'page'   => $this->request->get('page'),
            'params' => $this->arParams,
        ]));
    }
}
