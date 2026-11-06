<?php
/**
 * Пример использования полученных курсов валют
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Web\Json;

$APPLICATION->SetTitle("Курсы валют");

// Список валют
$currencies = ['USD', 'EUR', 'GBP', 'CNY', 'JPY'];

?>

<style>
    .currency-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .currency-table th {
        background: #0066cc;
        color: white;
        padding: 12px;
        text-align: left;
    }
    .currency-table td {
        padding: 12px;
        border-bottom: 1px solid #ddd;
    }
    .currency-table tr:hover {
        background: #f5f5f5;
    }
    .rate-up { color: #22c55e; }
    .rate-down { color: #ef4444; }
</style>

<h1>Текущие курсы валют</h1>

<table class="currency-table">
    <thead>
        <tr>
            <th>Код</th>
            <th>Название</th>
            <th>Курс (руб.)</th>
            <th>Номинал</th>
            <th>Дата обновления</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($currencies as $code): ?>
            <?php
            $rateJson = COption::GetOptionString('currency', "rate_{$code}", '');

            if (empty($rateJson)) {
                echo "<tr><td colspan='5'>Нет данных для {$code}</td></tr>";
                continue;
            }

            try {
                $rate = Json::decode($rateJson);
                $dateTime = new DateTime($rate['DATE']);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($code) ?></strong></td>
                    <td><?= htmlspecialchars($rate['NAME']) ?></td>
                    <td><?= number_format($rate['RATE'], 4, '.', ' ') ?> ₽</td>
                    <td><?= $rate['NOMINAL'] ?></td>
                    <td><?= $dateTime->format('d.m.Y H:i') ?></td>
                </tr>
                <?php
            } catch (Exception $e) {
                echo "<tr><td colspan='5'>Ошибка чтения данных для {$code}</td></tr>";
            }
            ?>
        <?php endforeach; ?>
    </tbody>
</table>

<p><em>Данные обновляются автоматически каждый час</em></p>

<h2>Использование в коде</h2>

<pre><code>&lt;?php
use Bitrix\Main\Web\Json;

// Получить курс доллара
$usdJson = COption::GetOptionString('currency', 'rate_USD', '');
$usdRate = Json::decode($usdJson);

echo "1 USD = {$usdRate['RATE']} руб.";

// Конвертация суммы
$amountUSD = 100;
$amountRUB = $amountUSD * $usdRate['RATE'];
echo "{$amountUSD} USD = {$amountRUB} RUB";
?&gt;</code></pre>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
