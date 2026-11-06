<?php

/**
 * Форма регистрации: страница с валидацией через Result.
 *
 * Путь в проекте: /register/index.php
 *
 * Что здесь показано:
 *   - Request используется как источник данных, а не как «очиститель»;
 *   - валидация вынесена в RegistrationValidator и не знает про HTML;
 *   - ошибки возвращаются массивом Error, а не выводятся из глубины валидатора;
 *   - вывод — только в шаблоне, через htmlspecialcharsbx().
 */

use Bitrix\Main\Context;
use MyCompany\Forms\RegistrationValidator;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

/** @var CMain $APPLICATION */
/** @var CUser $USER */

$APPLICATION->SetTitle('Регистрация');

$request = Context::getCurrent()->getRequest();
$errors = [];
$submitted = false;
$values = [];

if ($request->isPost()) {
    // CSRF: без sessid форма принимается от любого сайта от имени пользователя.
    if (!check_bitrix_sessid()) {
        $errors[] = 'Сессия устарела. Обновите страницу и отправьте форму снова.';
    } else {
        $input = [
            'name' => $request->getPost('name'),
            'email' => $request->getPost('email'),
            'age' => $request->getPost('age'),
            'phone' => $request->getPost('phone'),
        ];

        $result = (new RegistrationValidator($input))->validate();

        if ($result->isSuccess()) {
            $submitted = true;
            $values = $result->getData();
            // Дальше — сохранение: репозиторий/сервис. Экранировать здесь нечего:
            // в БД пишутся нормализованные значения, а не HTML-сущности.
        } else {
            foreach ($result->getErrors() as $error) {
                $errors[] = $error->getMessage();
            }
            // Возвращаем введённое, чтобы не заставлять заполнять заново.
            $values = array_filter(
                $input,
                static fn($value) => is_string($value) && $value !== ''
            );
        }
    }
}
?>

<h1>Регистрация</h1>

<?php foreach ($errors as $error): ?>
    <p class="errortext"><?= htmlspecialcharsbx($error) ?></p>
<?php endforeach; ?>

<?php if ($submitted): ?>
    <p class="notetext">
        Данные приняты: <?= htmlspecialcharsbx($values['name']) ?>,
        <?= htmlspecialcharsbx($values['email']) ?>,
        <?= (int)$values['age'] ?> лет,
        <?= htmlspecialcharsbx($values['phone']) ?>
    </p>
<?php endif; ?>

<form method="post" action="">
    <?= bitrix_sessid_post() ?>

    <p>
        <label>Имя<br>
            <input type="text" name="name" maxlength="50"
                   value="<?= htmlspecialcharsbx((string)($values['name'] ?? '')) ?>">
        </label>
    </p>

    <p>
        <label>Email<br>
            <input type="email" name="email"
                   value="<?= htmlspecialcharsbx((string)($values['email'] ?? '')) ?>">
        </label>
    </p>

    <p>
        <label>Возраст<br>
            <input type="number" name="age" min="18" max="100"
                   value="<?= htmlspecialcharsbx((string)($values['age'] ?? '')) ?>">
        </label>
    </p>

    <p>
        <label>Телефон<br>
            <input type="tel" name="phone" placeholder="+79991234567"
                   value="<?= htmlspecialcharsbx((string)($values['phone'] ?? '')) ?>">
        </label>
    </p>

    <button type="submit">Зарегистрироваться</button>
</form>

<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';