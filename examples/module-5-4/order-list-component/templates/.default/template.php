<?php
// examples/module-5-4/order-list-component/templates/.default/template.php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
?>

<div class="acme-order-list">
    <div class="order-filters">
        <? foreach ($arResult['STATUS_LIST'] as $code => $status): ?>
            <a href="?status=<?= $code ?>"
               class="filter-link <?= $arResult['CURRENT_STATUS'] === $code ? 'active' : '' ?>"
               style="color: <?= $status['COLOR'] ?>">
                <?= $status['NAME'] ?>
            </a>
        <? endforeach ?>
        <? if ($arResult['CURRENT_STATUS']): ?>
            <a href="?" class="filter-link">Все</a>
        <? endif ?>
    </div>

    <? if (empty($arResult['ORDERS'])): ?>
        <p class="empty">У вас пока нет заказов</p>
    <? else: ?>
        <table class="orders-table">
            <thead>
                <tr>
                    <th>№</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th>Сумма</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <? foreach ($arResult['ORDERS'] as $order): ?>
                    <tr>
                        <td>
                            <a href="/personal/order/<?= $order['ID'] ?>/">
                                <?= $order['ACCOUNT_NUMBER'] ?>
                            </a>
                        </td>
                        <td><?= $order['DATE'] ?></td>
                        <td>
                            <span class="status-badge" style="color: <?= $order['STATUS_COLOR'] ?>">
                                <?= $order['STATUS_NAME'] ?>
                            </span>
                            <? if ($order['CANCELED']): ?>
                                <span class="badge">Отменён</span>
                            <? endif ?>
                        </td>
                        <td><?= $order['PRICE'] ?></td>
                        <td>
                            <? if ($order['CAN_REPEAT']): ?>
                                <a href="#" class="repeat-order" data-order="<?= $order['ID'] ?>">Повторить</a>
                            <? endif ?>
                            <? if ($order['CAN_CANCEL']): ?>
                                <a href="#" class="cancel-order" data-order="<?= $order['ID'] ?>">Отменить</a>
                            <? endif ?>
                        </td>
                    </tr>
                    <tr class="items-row">
                        <td colspan="5">
                            <ul class="items">
                                <? foreach ($order['ITEMS'] as $item): ?>
                                    <li>
                                        <?= $item['NAME'] ?> × <?= $item['QUANTITY'] ?> — <?= $item['SUM'] ?>
                                    </li>
                                <? endforeach ?>
                            </ul>
                        </td>
                    </tr>
                <? endforeach ?>
            </tbody>
        </table>

        <? // Пагинация ?>
        <? if ($arResult['NAV']->getPageCount() > 1): ?>
            <div class="pagination">
                <? for ($i = 1; $i <= $arResult['NAV']->getPageCount(); $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i === $arResult['NAV']->getCurrentPage() ? 'class="active"' : '' ?>>
                        <?= $i ?>
                    </a>
                <? endfor ?>
            </div>
        <? endif ?>
    <? endif ?>
</div>
