<?php
/**
 * Файл для регистрации агента синхронизации валют
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Agent;
use Bitrix\Main\Type\DateTime;

require_once __DIR__ . '/agent.php';

// Проверяем, не добавлен ли уже агент
$existingAgent = Agent::getList([
    'filter' => [
        'NAME' => '\\MyCompany\\Agents\\CurrencyRatesAgent::execute();'
    ]
])->fetch();

if ($existingAgent) {
    echo "Агент уже зарегистрирован (ID: {$existingAgent['ID']})<br>";
} else {
    // Добавляем агент
    $agentId = Agent::add([
        'NAME' => '\\MyCompany\\Agents\\CurrencyRatesAgent::execute();',
        'MODULE_ID' => '',
        'ACTIVE' => 'Y',
        'AGENT_INTERVAL' => 3600,           // Каждый час
        'IS_PERIOD' => 'Y',
        'NEXT_EXEC' => new DateTime(),      // Первый запуск сразу
    ]);

    if ($agentId) {
        echo "✅ Агент успешно добавлен (ID: {$agentId})<br>";
        echo "⏱ Интервал запуска: каждый час<br>";
        echo "🚀 Следующий запуск: в ближайшее время<br><br>";

        echo "<strong>Синхронизируемые валюты:</strong><br>";
        echo "• USD (Доллар США)<br>";
        echo "• EUR (Евро)<br>";
        echo "• GBP (Фунт стерлингов)<br>";
        echo "• CNY (Китайский юань)<br>";
        echo "• JPY (Японская иена)<br>";
    } else {
        echo "❌ Ошибка при добавлении агента<br>";
    }
}

echo "<br><a href='/bitrix/admin/agent_list.php'>→ Перейти к списку агентов</a>";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
