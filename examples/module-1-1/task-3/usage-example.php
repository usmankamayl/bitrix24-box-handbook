<?php
/**
 * Пример использования класса RequestValidator
 */

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

use MyCompany\Forms\RequestValidator;

$validator = new RequestValidator();

// Обработка формы регистрации
if ($validator->isPost() && check_bitrix_sessid()) {

    // Валидация полей
    $name = $validator->getString('name', [
        'required' => true,
        'min_length' => 2,
        'max_length' => 50,
    ]);

    $email = $validator->getEmail('email', true);

    $age = $validator->getInt('age', [
        'required' => true,
        'min' => 18,
        'max' => 100,
    ]);

    $phone = $validator->getString('phone', [
        'required' => true,
        'pattern' => '/^\+?[0-9]{10,12}$/',
    ]);

    $avatar = $validator->getFile('avatar', [
        'required' => false,
        'max_size' => 5 * 1024 * 1024, // 5 MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
    ]);

    // Проверка на ошибки
    if ($validator->hasErrors()) {
        // AJAX ответ с ошибками
        if ($validator->isAjax()) {
            $validator->jsonResponse([
                'success' => false,
                'errors' => $validator->getErrors(),
            ], 400);
        }

        // Обычный ответ
        foreach ($validator->getErrors() as $error) {
            ShowError($error);
        }

    } else {
        // Все данные валидны
        $data = $validator->getData();

        // Сохранение данных
        // ...

        // AJAX ответ успеха
        if ($validator->isAjax()) {
            $validator->jsonResponse([
                'success' => true,
                'message' => 'Регистрация успешно завершена',
                'data' => $data,
            ]);
        }

        ShowNote('Регистрация успешно завершена!');
    }
}

$APPLICATION->SetTitle("Пример использования RequestValidator");
?>

<form method="post" enctype="multipart/form-data">
    <?= bitrix_sessid_post() ?>

    <div>
        <label>Имя:</label>
        <input type="text" name="name" required>
    </div>

    <div>
        <label>Email:</label>
        <input type="email" name="email" required>
    </div>

    <div>
        <label>Возраст:</label>
        <input type="number" name="age" min="18" max="100" required>
    </div>

    <div>
        <label>Телефон:</label>
        <input type="tel" name="phone" placeholder="+79991234567" required>
    </div>

    <div>
        <label>Аватар:</label>
        <input type="file" name="avatar" accept="image/*">
    </div>

    <button type="submit">Зарегистрироваться</button>
</form>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>
