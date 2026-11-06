<?php
/**
 * Файл для регистрации агента рассылки
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

use Bitrix\Main\Agent;
use Bitrix\Main\Type\DateTime;

require_once __DIR__ . '/agent.php';

// Проверяем, не добавлен ли уже агент
$existingAgent = Agent::getList([
    'filter' => [
        'NAME' => '\\MyCompany\\Agents\\EmailNewsletterAgent::execute();'
    ]
])->fetch();

if ($existingAgent) {
    echo "Агент уже зарегистрирован (ID: {$existingAgent['ID']})<br>";
} else {
    // Вычисляем дату первого запуска (следующий понедельник в 10:00)
    $nextMonday = new DateTime();
    $dayOfWeek = (int)$nextMonday->format('N'); // 1 = понедельник, 7 = воскресенье

    if ($dayOfWeek >= 1) {
        // Добавляем дни до следующего понедельника
        $daysToAdd = (8 - $dayOfWeek) % 7;
        if ($daysToAdd === 0) $daysToAdd = 7; // Если сегодня понедельник, берем следующий
        $nextMonday->add($daysToAdd . ' days');
    }

    // Устанавливаем время 10:00
    $nextMonday->setTime(10, 0, 0);

    // Добавляем агент
    $agentId = Agent::add([
        'NAME' => '\\MyCompany\\Agents\\EmailNewsletterAgent::execute();',
        'MODULE_ID' => '',
        'ACTIVE' => 'Y',
        'AGENT_INTERVAL' => 604800,          // Раз в неделю (7 * 24 * 3600)
        'IS_PERIOD' => 'Y',
        'NEXT_EXEC' => $nextMonday,          // Первый запуск в следующий понедельник
    ]);

    if ($agentId) {
        echo "Агент успешно добавлен (ID: {$agentId})<br>";
        echo "Интервал запуска: раз в неделю<br>";
        echo "Первый запуск: " . $nextMonday->toString() . "<br>";
    } else {
        echo "Ошибка при добавлении агента<br>";
    }
}

echo "<br><strong>Не забудьте:</strong><br>";
echo "1. Создать пользовательское поле UF_NEWSLETTER_SUBSCRIBED для таблицы пользователей<br>";
echo "2. Создать почтовое событие WEEKLY_NEWSLETTER (см. email_template.php)<br>";
echo "<br><a href='/bitrix/admin/agent_list.php'>Перейти к списку агентов</a>";

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
