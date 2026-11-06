<?php
// Виджет-вкладка в карточке сделки
// Отображает детальную информацию о сделке + связанные контакты

$dealId = null;
$authId = null;

// Получаем ID сделки из PLACEMENT_OPTIONS
if (isset($_GET['PLACEMENT_OPTIONS'])) {
    $options = json_decode($_GET['PLACEMENT_OPTIONS'], true);
    $dealId = $options['ID'] ?? null;
}

$authId = $_GET['AUTH_ID'] ?? null;

if (!$dealId) {
    die('ID сделки не найден');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Информация о сделке</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            padding: 20px;
            background: #fff;
            margin: 0;
        }
        .widget-header {
            background: #2e5eff;
            color: white;
            padding: 15px;
            margin: -20px -20px 20px;
            border-radius: 0 0 8px 8px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        .contact {
            background: white;
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #2e5eff;
        }
        .loading {
            text-align: center;
            color: #999;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="widget-header">
        <h2 style="margin: 0;">Сделка #<?= htmlspecialchars($dealId) ?></h2>
    </div>

    <div id="deal-info" class="section">
        <div class="loading">Загрузка данных сделки...</div>
    </div>

    <div id="contacts" class="section">
        <div class="loading">Загрузка контактов...</div>
    </div>

    <div id="products" class="section">
        <div class="loading">Загрузка товаров...</div>
    </div>

    <script>
    BX24.init(function() {
        const dealId = <?= $dealId ?>;

        // Загружаем данные сделки
        BX24.callMethod('crm.deal.get', {id: dealId}, function(result) {
            if (result.error()) {
                document.getElementById('deal-info').innerHTML =
                    'Ошибка: ' + result.error();
                return;
            }

            const deal = result.data();
            document.getElementById('deal-info').innerHTML = `
                <div class="label">Название</div>
                <div class="value">${deal.TITLE}</div>
                <br>
                <div class="label">Сумма</div>
                <div class="value">${deal.OPPORTUNITY} ${deal.CURRENCY_ID}</div>
                <br>
                <div class="label">Стадия</div>
                <div class="value">${deal.STAGE_ID}</div>
                <br>
                <div class="label">Вероятность</div>
                <div class="value">${deal.PROBABILITY}%</div>
            `;
        });

        // Загружаем контакты сделки
        BX24.callMethod('crm.deal.contact.items.get', {id: dealId}, function(result) {
            if (result.error()) {
                document.getElementById('contacts').innerHTML =
                    'Ошибка загрузки контактов';
                return;
            }

            const contacts = result.data();
            if (contacts.length === 0) {
                document.getElementById('contacts').innerHTML =
                    '<div class="label">Контакты не привязаны</div>';
                return;
            }

            let html = '<div class="label">Связанные контакты</div>';
            contacts.forEach(contact => {
                html += `
                    <div class="contact">
                        <strong>${contact.NAME} ${contact.LAST_NAME}</strong><br>
                        <small>${contact.POST || '—'}</small>
                    </div>
                `;
            });

            document.getElementById('contacts').innerHTML = html;
        });

        // Загружаем товары сделки
        BX24.callMethod('crm.deal.productrows.get', {id: dealId}, function(result) {
            if (result.error()) {
                document.getElementById('products').innerHTML =
                    'Ошибка загрузки товаров';
                return;
            }

            const products = result.data();
            if (products.length === 0) {
                document.getElementById('products').innerHTML =
                    '<div class="label">Товары не добавлены</div>';
                return;
            }

            let html = '<div class="label">Товары в сделке</div>';
            products.forEach(product => {
                html += `
                    <div class="contact">
                        <strong>${product.PRODUCT_NAME}</strong><br>
                        ${product.QUANTITY} x ${product.PRICE} = <strong>${product.PRICE * product.QUANTITY}</strong>
                    </div>
                `;
            });

            document.getElementById('products').innerHTML = html;

            // Подгоняем размер iframe под содержимое
            BX24.fitWindow();
        });
    });
    </script>
</body>
</html>
