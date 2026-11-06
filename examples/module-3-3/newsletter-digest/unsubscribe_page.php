<?php
/**
 * Страница отписки от рассылки
 *
 * Сохраните как /unsubscribe/index.php
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Отписка от рассылки');

use Bitrix\Main\Loader;

$userId = (int)($_GET['user_id'] ?? 0);
$token = $_GET['token'] ?? '';

$message = '';
$success = false;

if ($userId && $token) {
    // Проверяем токен
    $savedToken = \COption::GetOptionString('main', 'unsubscribe_token_' . $userId, '');

    if ($savedToken === $token) {
        // Отписываем пользователя
        $userObj = new \CUser();
        $updated = $userObj->Update($userId, [
            'UF_NEWSLETTER_SUBSCRIBE' => 0
        ]);

        if ($updated) {
            $success = true;
            $message = 'Вы успешно отписались от рассылки.';

            // Удаляем токен
            \COption::RemoveOption('main', 'unsubscribe_token_' . $userId);

            // Логируем отписку
            \AddMessage2Log(
                "User #{$userId} unsubscribed from newsletter",
                'weekly_digest'
            );
        } else {
            $message = 'Произошла ошибка. Попробуйте позже.';
        }
    } else {
        $message = 'Неверная ссылка для отписки.';
    }
} else {
    $message = 'Неверные параметры.';
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отписка от рассылки</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background: #1e3c72;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        .button:hover {
            background: #2a5298;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="icon success">✓</div>
            <h1>Готово!</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <p>Мы больше не будем отправлять вам еженедельный дайджест.</p>
            <p style="font-size: 14px; color: #999;">
                Если вы передумаете, вы всегда можете подписаться снова в настройках профиля.
            </p>
        <?php else: ?>
            <div class="icon error">✗</div>
            <h1>Ошибка</h1>
            <p><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <a href="/" class="button">Вернуться на главную</a>
    </div>
</body>
</html>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
?>
