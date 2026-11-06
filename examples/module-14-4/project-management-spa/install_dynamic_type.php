<?php
/**
 * Скрипт для создания динамического типа "Проекты" (SPA)
 *
 * Этот файл создаёт новый тип CRM сущности со всеми настройками:
 * - Структура типа
 * - Пользовательские поля
 * - Стадии и воронки
 * - Настройки прав доступа
 *
 * Запуск: разместить в /local/tools/ и выполнить через браузер
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

use Bitrix\Main\Loader;
use Bitrix\Crm\Model\Dynamic\TypeTable;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\StatusTable;
use Bitrix\Crm\PhaseSemantics;

// Проверка прав администратора
global $USER;
if (!$USER->IsAdmin()) {
    die('Доступ запрещён. Требуются права администратора.');
}

Loader::includeModule('crm');

echo '<h1>Установка динамического типа "Проекты"</h1>';

// ============================================
// Шаг 1: Создание динамического типа
// ============================================

echo '<h2>Шаг 1: Создание типа</h2>';

$result = TypeTable::add([
    'TITLE' => 'Проекты',           // Название во множественном числе
    'NAME' => 'Проект',              // Название в единственном числе
    'CODE' => 'PROJECT',             // Символьный код (уникальный)

    // Вычисляем следующий доступный ID для динамического типа
    'ENTITY_TYPE_ID' => \CCrmOwnerType::getDynamicTypeBasedStaticId(
        \CCrmOwnerType::FirstDynamicTypeId
    ),

    // Включение функционала
    'IS_STAGES_ENABLED' => 'Y',              // Стадии
    'IS_BEGIN_CLOSE_DATES_ENABLED' => 'Y',   // Даты начала/завершения
    'IS_CLIENT_ENABLED' => 'Y',              // Привязка клиентов (контакты/компании)
    'IS_USE_IN_USERFIELD_ENABLED' => 'Y',    // Использование в пользовательских полях
    'IS_CATEGORIES_ENABLED' => 'N',          // Направления (воронки) - отключено
    'IS_DOCUMENTS_ENABLED' => 'Y',           // Документы и печатные формы
    'IS_SOURCE_ENABLED' => 'Y',              // Источник (откуда пришёл проект)
    'IS_RECYCLEBIN_ENABLED' => 'Y',          // Корзина для удалённых
    'IS_AUTOMATION_ENABLED' => 'Y',          // Роботы и триггеры
    'IS_BIZ_PROC_ENABLED' => 'Y',            // Бизнес-процессы
    'IS_SET_OPEN_PERMISSIONS' => 'Y',        // Открытые права
    'IS_LINK_WITH_PRODUCTS_ENABLED' => 'N',  // Товарные позиции - не нужны для проектов
]);

if (!$result->isSuccess()) {
    die('Ошибка создания типа: ' . implode(', ', $result->getErrorMessages()));
}

$typeId = $result->getId();
$entityTypeId = TypeTable::getById($typeId)->fetch()['ENTITY_TYPE_ID'];

echo "<p style='color: green;'>✓ Тип создан успешно. ID типа: {$typeId}, Entity Type ID: {$entityTypeId}</p>";

// Инвалидировать кеш фабрик для загрузки нового типа
Container::getInstance()->getDynamicTypesMap()->invalidate();

// ============================================
// Шаг 2: Создание пользовательских полей
// ============================================

echo '<h2>Шаг 2: Создание пользовательских полей</h2>';

$userFieldManager = Container::getInstance()->getUserFieldManager();
$entityId = \CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId);

// Поле: Бюджет проекта
$budgetField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PROJECT_BUDGET',
    'USER_TYPE_ID' => 'double',
    'EDIT_FORM_LABEL' => ['ru' => 'Бюджет проекта', 'en' => 'Project Budget'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Бюджет'],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
    'SETTINGS' => [
        'PRECISION' => 2,
        'SIZE' => 20,
        'MIN_VALUE' => 0,
        'DEFAULT_VALUE' => 0,
    ],
];

$budgetId = $userFieldManager->addField($budgetField);
echo "<p>✓ Поле 'Бюджет проекта' создано (ID: {$budgetId})</p>";

// Поле: Приоритет
$priorityField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PRIORITY',
    'USER_TYPE_ID' => 'enumeration',
    'EDIT_FORM_LABEL' => ['ru' => 'Приоритет', 'en' => 'Priority'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Приоритет'],
    'LIST' => [
        ['VALUE' => 'Низкий', 'SORT' => 100, 'XML_ID' => 'LOW'],
        ['VALUE' => 'Средний', 'SORT' => 200, 'DEF' => 'Y', 'XML_ID' => 'MEDIUM'],
        ['VALUE' => 'Высокий', 'SORT' => 300, 'XML_ID' => 'HIGH'],
        ['VALUE' => 'Критический', 'SORT' => 400, 'XML_ID' => 'CRITICAL'],
    ],
    'MANDATORY' => 'Y',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
];

$priorityId = $userFieldManager->addField($priorityField);
echo "<p>✓ Поле 'Приоритет' создано (ID: {$priorityId})</p>";

// Поле: Менеджер проекта
$managerField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PROJECT_MANAGER',
    'USER_TYPE_ID' => 'employee',
    'EDIT_FORM_LABEL' => ['ru' => 'Менеджер проекта', 'en' => 'Project Manager'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Менеджер'],
    'MANDATORY' => 'Y',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'MULTIPLE' => 'N',
];

$managerId = $userFieldManager->addField($managerField);
echo "<p>✓ Поле 'Менеджер проекта' создано (ID: {$managerId})</p>";

// Поле: Команда проекта
$teamField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PROJECT_TEAM',
    'USER_TYPE_ID' => 'employee',
    'EDIT_FORM_LABEL' => ['ru' => 'Команда проекта', 'en' => 'Project Team'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Команда'],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'N',
    'MULTIPLE' => 'Y',
];

$teamId = $userFieldManager->addField($teamField);
echo "<p>✓ Поле 'Команда проекта' создано (ID: {$teamId})</p>";

// Поле: Статус выполнения (процент)
$progressField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PROGRESS',
    'USER_TYPE_ID' => 'integer',
    'EDIT_FORM_LABEL' => ['ru' => 'Прогресс (%)', 'en' => 'Progress (%)'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Прогресс'],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
    'SETTINGS' => [
        'SIZE' => 20,
        'MIN_VALUE' => 0,
        'MAX_VALUE' => 100,
        'DEFAULT_VALUE' => 0,
    ],
];

$progressId = $userFieldManager->addField($progressField);
echo "<p>✓ Поле 'Прогресс' создано (ID: {$progressId})</p>";

// Поле: Тип проекта
$typeField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PROJECT_TYPE',
    'USER_TYPE_ID' => 'enumeration',
    'EDIT_FORM_LABEL' => ['ru' => 'Тип проекта', 'en' => 'Project Type'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Тип'],
    'LIST' => [
        ['VALUE' => 'Разработка ПО', 'SORT' => 100, 'XML_ID' => 'SOFTWARE'],
        ['VALUE' => 'Внедрение', 'SORT' => 200, 'XML_ID' => 'IMPLEMENTATION'],
        ['VALUE' => 'Консалтинг', 'SORT' => 300, 'XML_ID' => 'CONSULTING'],
        ['VALUE' => 'Поддержка', 'SORT' => 400, 'XML_ID' => 'SUPPORT'],
        ['VALUE' => 'Исследование', 'SORT' => 500, 'XML_ID' => 'RESEARCH'],
    ],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
];

$typeFieldId = $userFieldManager->addField($typeField);
echo "<p>✓ Поле 'Тип проекта' создано (ID: {$typeFieldId})</p>";

// Поле: Описание проекта
$descField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_DESCRIPTION',
    'USER_TYPE_ID' => 'string',
    'EDIT_FORM_LABEL' => ['ru' => 'Описание проекта', 'en' => 'Project Description'],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'N',
    'SHOW_IN_LIST' => 'N',
    'SETTINGS' => [
        'ROWS' => 5,
    ],
];

$descId = $userFieldManager->addField($descField);
echo "<p>✓ Поле 'Описание проекта' создано (ID: {$descId})</p>";

// Поле: Файлы проекта
$filesField = [
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PROJECT_FILES',
    'USER_TYPE_ID' => 'file',
    'EDIT_FORM_LABEL' => ['ru' => 'Файлы проекта', 'en' => 'Project Files'],
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'N',
    'SHOW_IN_LIST' => 'N',
    'MULTIPLE' => 'Y',
];

$filesId = $userFieldManager->addField($filesField);
echo "<p>✓ Поле 'Файлы проекта' создано (ID: {$filesId})</p>";

// ============================================
// Шаг 3: Создание стадий
// ============================================

echo '<h2>Шаг 3: Создание стадий</h2>';

// Для типов без направлений categoryId всегда 0
$categoryId = 0;
$statusEntityId = 'DYNAMIC_' . $entityTypeId . '_STAGE_' . $categoryId;

$stages = [
    [
        'STATUS_ID' => 'NEW',
        'NAME' => 'Новый проект',
        'SORT' => 100,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#39A8EF',
    ],
    [
        'STATUS_ID' => 'ANALYSIS',
        'NAME' => 'Анализ требований',
        'SORT' => 200,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#9DCF00',
    ],
    [
        'STATUS_ID' => 'PLANNING',
        'NAME' => 'Планирование',
        'SORT' => 300,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#FFA900',
    ],
    [
        'STATUS_ID' => 'DEVELOPMENT',
        'NAME' => 'Разработка',
        'SORT' => 400,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#55D0E0',
    ],
    [
        'STATUS_ID' => 'TESTING',
        'NAME' => 'Тестирование',
        'SORT' => 500,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#47E4C2',
    ],
    [
        'STATUS_ID' => 'DEPLOYMENT',
        'NAME' => 'Внедрение',
        'SORT' => 600,
        'SEMANTICS' => PhaseSemantics::PROCESS,
        'COLOR' => '#7E58D4',
    ],
    [
        'STATUS_ID' => 'COMPLETED',
        'NAME' => 'Завершён успешно',
        'SORT' => 700,
        'SEMANTICS' => PhaseSemantics::SUCCESS,
        'COLOR' => '#7BD500',
    ],
    [
        'STATUS_ID' => 'CANCELLED',
        'NAME' => 'Отменён',
        'SORT' => 800,
        'SEMANTICS' => PhaseSemantics::FAILURE,
        'COLOR' => '#FF5752',
    ],
    [
        'STATUS_ID' => 'ON_HOLD',
        'NAME' => 'Приостановлен',
        'SORT' => 900,
        'SEMANTICS' => PhaseSemantics::FAILURE,
        'COLOR' => '#A8ADB4',
    ],
];

foreach ($stages as $stage) {
    $stageResult = StatusTable::add([
        'ENTITY_ID' => $statusEntityId,
        'STATUS_ID' => $stage['STATUS_ID'],
        'NAME' => $stage['NAME'],
        'SORT' => $stage['SORT'],
        'SEMANTICS' => $stage['SEMANTICS'],
        'COLOR' => $stage['COLOR'],
    ]);

    if ($stageResult->isSuccess()) {
        echo "<p>✓ Стадия '{$stage['NAME']}' создана</p>";
    } else {
        echo "<p style='color: red;'>✗ Ошибка создания стадии '{$stage['NAME']}': "
            . implode(', ', $stageResult->getErrorMessages()) . "</p>";
    }
}

// ============================================
// Завершение
// ============================================

echo '<hr>';
echo '<h2 style="color: green;">✓ Установка завершена успешно!</h2>';
echo "<p><strong>Entity Type ID:</strong> {$entityTypeId}</p>";
echo "<p><strong>Перейти к списку проектов:</strong> <a href='/crm/type/{$entityTypeId}/list/' target='_blank'>/crm/type/{$entityTypeId}/list/</a></p>";
echo "<p><strong>Создать новый проект:</strong> <a href='/crm/type/{$entityTypeId}/details/0/' target='_blank'>/crm/type/{$entityTypeId}/details/0/</a></p>";

echo '<h3>Созданные поля:</h3>';
echo '<ul>';
echo '<li>UF_PROJECT_BUDGET - Бюджет проекта (число с точкой)</li>';
echo '<li>UF_PRIORITY - Приоритет (список)</li>';
echo '<li>UF_PROJECT_MANAGER - Менеджер проекта (пользователь)</li>';
echo '<li>UF_PROJECT_TEAM - Команда проекта (множественные пользователи)</li>';
echo '<li>UF_PROGRESS - Прогресс в % (целое число 0-100)</li>';
echo '<li>UF_PROJECT_TYPE - Тип проекта (список)</li>';
echo '<li>UF_DESCRIPTION - Описание проекта (текст)</li>';
echo '<li>UF_PROJECT_FILES - Файлы проекта (множественные файлы)</li>';
echo '</ul>';

echo '<h3>Созданные стадии:</h3>';
echo '<ul>';
foreach ($stages as $stage) {
    echo "<li style='color: {$stage['COLOR']};'>{$stage['NAME']}</li>";
}
echo '</ul>';

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
