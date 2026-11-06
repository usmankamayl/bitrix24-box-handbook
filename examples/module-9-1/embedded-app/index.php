<?php
// Встраиваемое приложение для Bitrix24
// Этот файл отображается внутри iframe в интерфейсе Bitrix24

// Получаем параметры, переданные Bitrix24
$domain = $_GET['DOMAIN'] ?? '';
$lang = $_GET['LANG'] ?? 'ru';
$authId = $_GET['AUTH_ID'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <title>Мое приложение</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2e5eff;
            margin-bottom: 20px;
        }
        .info {
            background: #e8f0fe;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .data {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #2e5eff;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Привет из встраиваемого приложения!</h1>

        <div class="info">
            <p><strong>Домен портала:</strong> <?= htmlspecialchars($domain) ?></p>
            <p><strong>Язык:</strong> <?= htmlspecialchars($lang) ?></p>
            <p><strong>Auth ID получен:</strong> <?= $authId ? 'Да' : 'Нет' ?></p>
        </div>

        <h2>Данные пользователя</h2>
        <div id="user-data" class="data">Загрузка...</div>

        <h2>Последние 5 лидов</h2>
        <div id="leads-list" class="data">Загрузка...</div>
    </div>

    <script>
    BX24.init(function() {
        // Получаем данные текущего пользователя
        BX24.callMethod('user.current', {}, function(result) {
            if (result.error()) {
                document.getElementById('user-data').innerHTML =
                    'Ошибка: ' + result.error();
                return;
            }

            const user = result.data();
            document.getElementById('user-data').innerHTML = `
                <p><strong>ID:</strong> ${user.ID}</p>
                <p><strong>Имя:</strong> ${user.NAME}</p>
                <p><strong>Фамилия:</strong> ${user.LAST_NAME}</p>
                <p><strong>Email:</strong> ${user.EMAIL}</p>
            `;
        });

        // Получаем последние лиды
        BX24.callMethod('crm.lead.list', {
            order: {DATE_CREATE: 'DESC'},
            limit: 5
        }, function(result) {
            if (result.error()) {
                document.getElementById('leads-list').innerHTML =
                    'Ошибка: ' + result.error();
                return;
            }

            const leads = result.data();
            if (leads.length === 0) {
                document.getElementById('leads-list').innerHTML = 'Лидов нет';
                return;
            }

            let html = '<ol>';
            leads.forEach(lead => {
                html += `<li><strong>${lead.TITLE}</strong> — ${lead.DATE_CREATE}</li>`;
            });
            html += '</ol>';

            document.getElementById('leads-list').innerHTML = html;
        });
    });
    </script>
</body>
</html>
