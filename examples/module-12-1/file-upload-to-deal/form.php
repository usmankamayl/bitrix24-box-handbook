<?php
/**
 * Публичная форма сайта: клиент прикрепляет файл (например, скан паспорта
 * или подписанный договор) и указывает ID сделки, к которой его нужно
 * привязать. ID сделки обычно приходит не вручную, а из сессии/ссылки,
 * которую клиенту прислал менеджер (например, персональный кабинет).
 */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Загрузка документа по сделке</title>
</head>
<body>
    <h1>Прикрепите документ к сделке</h1>

    <?php if (!empty($_GET['success'])): ?>
        <p style="color: green;">Файл успешно загружен и прикреплён к сделке #<?= (int)$_GET['deal_id'] ?>.</p>
    <?php elseif (!empty($_GET['error'])): ?>
        <p style="color: red;">Ошибка: <?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>

    <form action="handler.php" method="post" enctype="multipart/form-data">
        <p>
            <label>ID сделки:
                <input type="number" name="deal_id" required>
            </label>
        </p>
        <p>
            <label>Файл:
                <input type="file" name="document" required>
            </label>
        </p>
        <button type="submit">Отправить</button>
    </form>
</body>
</html>
