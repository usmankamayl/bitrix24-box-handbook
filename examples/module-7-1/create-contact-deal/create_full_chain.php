<?php
/**
 * create_full_chain.php - Создание полной цепочки сущностей CRM
 *
 * Демонстрирует процесс создания связанных сущностей:
 * Компания → Контакт → Сделка
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CrmHelper.php';

// Загружаем конфигурацию
$config = require __DIR__ . '/config.php';

try {
    // Инициализируем хелпер для работы с CRM
    $crm = new CrmHelper($config);

    echo "🚀 Начинаем создание цепочки сущностей CRM\n\n";

    // ========================================
    // Шаг 1: Создаем или находим компанию
    // ========================================
    echo "📋 Шаг 1: Работа с компанией\n";

    $companyData = [
        'TITLE' => 'ООО Рога и Копыта',
        'COMPANY_TYPE' => 'CUSTOMER',  // Тип: Клиент
        'INDUSTRY' => 'IT',            // Отрасль
        'PHONE' => [
            ['VALUE' => '+7 (999) 123-45-67', 'VALUE_TYPE' => 'WORK']
        ],
        'EMAIL' => [
            ['VALUE' => 'info@rogaikopyta.ru', 'VALUE_TYPE' => 'WORK']
        ],
        'WEB' => [
            ['VALUE' => 'https://rogaikopyta.ru', 'VALUE_TYPE' => 'WORK']
        ],
        'COMMENTS' => 'Потенциальный клиент из холодной базы',
    ];

    // Используем метод с дедупликацией
    $companyResult = $crm->getOrCreateCompany($companyData);
    $companyId = $companyResult['id'];

    if ($companyResult['existed']) {
        echo "✅ Компания уже существует: ID {$companyId}\n\n";
    } else {
        echo "✅ Компания создана: ID {$companyId}\n\n";
    }

    // ========================================
    // Шаг 2: Создаем контакт
    // ========================================
    echo "📋 Шаг 2: Создание контакта\n";

    $contactData = [
        'NAME' => 'Иван',
        'LAST_NAME' => 'Петров',
        'SECOND_NAME' => 'Сергеевич',  // Отчество
        'COMPANY_ID' => $companyId,     // Связь с компанией
        'TYPE_ID' => 'CLIENT',          // Тип: Клиент
        'SOURCE_ID' => 'CALL',          // Источник: Звонок
        'PHONE' => [
            ['VALUE' => '+7 (999) 111-22-33', 'VALUE_TYPE' => 'MOBILE']
        ],
        'EMAIL' => [
            ['VALUE' => 'ivan.petrov@rogaikopyta.ru', 'VALUE_TYPE' => 'WORK']
        ],
        'POST' => 'Директор по развитию',  // Должность
        'COMMENTS' => 'ЛПР, принимает решения по закупкам',
    ];

    $contactId = $crm->createContact($contactData);
    echo "✅ Контакт создан: ID {$contactId}\n\n";

    // ========================================
    // Шаг 3: Создаем сделку
    // ========================================
    echo "📋 Шаг 3: Создание сделки\n";

    $dealData = [
        'TITLE' => 'Поставка серверного оборудования',
        'STAGE_ID' => 'C1:NEW',          // Стадия: Новая (начало воронки)
        'COMPANY_ID' => $companyId,       // Связь с компанией
        'CONTACT_IDS' => [$contactId],    // Связь с контактом (массив!)
        'OPENED' => 'Y',                  // Доступна всем
        'PROBABILITY' => 50,              // Вероятность закрытия: 50%
        'CURRENCY_ID' => 'RUB',          // Валюта
        'OPPORTUNITY' => 150000,          // Сумма сделки
        'BEGINDATE' => date('Y-m-d'),    // Дата начала
        'CLOSEDATE' => date('Y-m-d', strtotime('+30 days')),  // Планируемая дата закрытия
        'COMMENTS' => 'Клиент заинтересован в покупке 3 серверов Dell PowerEdge',
    ];

    $dealId = $crm->createDeal($dealData);
    echo "✅ Сделка создана: ID {$dealId}\n\n";

    // ========================================
    // Итоговая информация
    // ========================================
    echo "=" . str_repeat("=", 60) . "\n";
    echo "✨ Цепочка сущностей успешно создана!\n";
    echo "=" . str_repeat("=", 60) . "\n\n";

    // Получаем детальную информацию о созданных сущностях
    $company = $crm->getCompany($companyId);
    $contact = $crm->getContact($contactId);
    $deal = $crm->getDeal($dealId);

    echo "📊 Детали:\n\n";

    echo "🏢 Компания:\n";
    echo "   - ID: {$company['ID']}\n";
    echo "   - Название: {$company['TITLE']}\n";
    echo "   - Отрасль: {$company['INDUSTRY']}\n\n";

    echo "👤 Контакт:\n";
    echo "   - ID: {$contact['ID']}\n";
    echo "   - ФИО: {$contact['NAME']} {$contact['LAST_NAME']}\n";
    echo "   - Должность: {$contact['POST']}\n";
    echo "   - Компания: ID {$contact['COMPANY_ID']}\n\n";

    echo "💼 Сделка:\n";
    echo "   - ID: {$deal['ID']}\n";
    echo "   - Название: {$deal['TITLE']}\n";
    echo "   - Сумма: {$deal['OPPORTUNITY']} {$deal['CURRENCY_ID']}\n";
    echo "   - Стадия: {$deal['STAGE_ID']}\n";
    echo "   - Вероятность: {$deal['PROBABILITY']}%\n";
    echo "   - Компания: ID {$deal['COMPANY_ID']}\n";
    echo "   - Контакты: " . implode(', ', array_map(function($c) {
        return "ID {$c['ID']}";
    }, $deal['CONTACT_ID'] ?? [])) . "\n\n";

    echo "🔗 Связи:\n";
    echo "   Компания (ID {$companyId})\n";
    echo "      └─ Контакт (ID {$contactId})\n";
    echo "           └─ Сделка (ID {$dealId})\n\n";

    echo "✅ Все данные доступны в CRM Bitrix24\n";

} catch (Exception $e) {
    // Обработка ошибок
    echo "\n❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📝 Детали: " . $e->getTraceAsString() . "\n";
    exit(1);
}
