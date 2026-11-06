<?php
// Кнопка в тулбаре лида — отправка данных лида во внешний сервис
// Открывается как slider (боковая панель) при клике на кнопку

$leadId = null;
if (isset($_GET['PLACEMENT_OPTIONS'])) {
    $options = json_decode($_GET['PLACEMENT_OPTIONS'], true);
    $leadId = $options['ID'] ?? null;
}

if (!$leadId) {
    die('ID лида не найден');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отправить лида</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        .lead-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .field {
            margin: 10px 0;
        }
        .field label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        .field input, .field textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background: #2e5eff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            width: 100%;
        }
        button:hover { background: #1d4ed8; }
        button:disabled { background: #999; cursor: not-allowed; }
        .result { margin-top: 15px; padding: 10px; border-radius: 4px; display: none; }
        .result.ok { background: #d4edda; color: #155724; display: block; }
        .result.err { background: #f8d7da; color: #721c24; display: block; }
    </style>
</head>
<body>
    <h3>Отправка лида во внешний сервис</h3>

    <div class="lead-card" id="lead-info">Загрузка данных лида...</div>

    <div class="field">
        <label>Комментарий для отправки</label>
        <textarea id="comment" rows="3" placeholder="Дополнительная информация..."></textarea>
    </div>

    <button id="send-btn" onclick="sendLead()">Отправить</button>

    <div class="result" id="result"></div>

    <script>
    let lead = null;

    BX24.init(function() {
        BX24.callMethod('crm.lead.get', {id: <?= $leadId ?>}, function(result) {
            if (result.error()) {
                document.getElementById('lead-info').innerHTML = 'Ошибка: ' + result.error();
                return;
            }

            lead = result.data();
            document.getElementById('lead-info').innerHTML = `
                <strong>${lead.TITLE}</strong><br>
                ${lead.NAME || ''} ${lead.LAST_NAME || ''}<br>
                <small>Телефон: ${lead.PHONE ? lead.PHONE[0].VALUE : '—'}</small>
            `;
            BX24.fitWindow();
        });
    });

    function sendLead() {
        const btn = document.getElementById('send-btn');
        btn.disabled = true;
        btn.textContent = 'Отправка...';

        // Здесь был бы запрос на ваш сервер:
        // fetch('https://your-api.com/leads', {method: 'POST', body: JSON.stringify(lead)})
        // Для примера — пишем комментарий в лид через REST API

        BX24.callMethod('crm.timeline.comment.add', {
            fields: {
                ENTITY_ID: <?= $leadId ?>,
                ENTITY_TYPE: 'lead',
                COMMENT: 'Отправлено во внешний сервис. ' + document.getElementById('comment').value
            }
        }, function(result) {
            const res = document.getElementById('result');
            btn.disabled = false;
            btn.textContent = 'Отправить';

            if (result.error()) {
                res.className = 'result err';
                res.textContent = 'Ошибка: ' + result.error();
            } else {
                res.className = 'result ok';
                res.textContent = 'Лид успешно отправлен!';
                BX24.showNotification('Отправлено', 'success');
            }
        });
    }
    </script>
</body>
</html>
