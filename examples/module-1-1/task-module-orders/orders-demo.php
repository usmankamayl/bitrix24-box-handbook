<?php

/**
 * Демо-страница модуля mycompany.orders.
 *
 * Путь в проекте: /orders-demo.php (корень сайта)
 *
 * Показывает: список заказов с логином пользователя (JOIN), создание и
 * удаление записи. Любое изменение — только POST + sessid + права админа:
 * это страница, а не скрипт миграции, и открывается кем угодно.
 */

use Bitrix\Main\Loader;
use Mycompany\Orders\OrderTable;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

/** @var CMain $APPLICATION */
/** @var CUser $USER */

$APPLICATION->SetTitle('Заказы mycompany.orders (демо)');

if (!$USER->IsAdmin()) {
    ShowError('Раздел доступен только администратору.');

    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
    return;
}

if (!Loader::includeModule('mycompany.orders')) {
    ShowError(
        'Модуль mycompany.orders не установлен. '
        . 'Скопируйте local/modules/mycompany.orders в сайт и установите модуль '
        . 'в админке: Настройки → Модули.'
    );

    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
    return;
}

$action = (string)($_POST['action'] ?? '');
$messages = [];
$errors = [];

if ($action !== '') {
    // CSRF: без проверки любой сайт может отправить эту форму от имени админа.
    if (!check_bitrix_sessid()) {
        $errors[] = 'Сессия устарела. Обновите страницу и повторите действие.';
    } elseif ($action === 'add') {
        $addResult = OrderTable::add([
            'USER_ID' => (int)$USER->GetID(),
            'PRICE' => 100.00,
            'STATUS' => 'NEW',
        ]);

        if ($addResult->isSuccess()) {
            $messages[] = 'Создан заказ #' . (int)$addResult->getId();
        } else {
            $errors = $addResult->getErrorMessages();
        }
    } elseif ($action === 'delete') {
        // ID приходит строкой; массив и мусор отсекаем до приведения к int,
        // иначе (int)'12abc' даст 12, а (int)[] даст 1.
        $rawId = $_POST['id'] ?? null;
        $id = is_string($rawId)
            ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if ($id === false) {
            $errors[] = 'Некорректный ID заказа.';
        } else {
            $deleteResult = OrderTable::delete($id);

            if ($deleteResult->isSuccess()) {
                $messages[] = 'Удалён заказ #' . $id;
            } else {
                $errors = $deleteResult->getErrorMessages();
            }
        }
    }
}
?>

<h2>Заказы</h2>

<?php foreach ($errors as $error): ?>
    <div class="errortext"><?= htmlspecialcharsbx($error) ?></div>
<?php endforeach; ?>

<?php foreach ($messages as $message): ?>
    <div class="notetext"><?= htmlspecialcharsbx($message) ?></div>
<?php endforeach; ?>

<form method="post" style="margin: 16px 0;">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="action" value="add">
    <button type="submit">Создать тестовый заказ</button>
</form>

<?php
$orders = OrderTable::getList([
    'select' => [
        'ID',
        'USER_ID',
        'DATE_CREATE',
        'PRICE',
        'STATUS',
        'USER_LOGIN' => 'USER.LOGIN',
    ],
    'order' => ['ID' => 'DESC'],
    // limit обязателен: без него страница тянет всю таблицу.
    'limit' => 50,
]);
?>

<table class="data-table" style="width: 100%;">
    <thead>
    <tr>
        <th>ID</th>
        <th>Дата</th>
        <th>Пользователь</th>
        <th>Сумма</th>
        <th>Статус</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php while ($order = $orders->fetch()): ?>
        <tr>
            <td><?= (int)$order['ID'] ?></td>
            <td><?= htmlspecialcharsbx(
                $order['DATE_CREATE'] instanceof \Bitrix\Main\Type\Date
                    ? $order['DATE_CREATE']->toString()
                    : ''
            ) ?></td>
            <td><?= htmlspecialcharsbx((string)$order['USER_LOGIN']) ?></td>
            <td><?= htmlspecialcharsbx((string)$order['PRICE']) ?></td>
            <td><?= htmlspecialcharsbx((string)$order['STATUS']) ?></td>
            <td>
                <form method="post">
                    <?= bitrix_sessid_post() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$order['ID'] ?>">
                    <button type="submit">Удалить</button>
                </form>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
