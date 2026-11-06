<?php
/**
 * Примеры использования ProjectService
 *
 * Демонстрирует все основные операции с проектами через Factory API
 */

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
require_once __DIR__ . '/ProjectService.php';

use MyCompany\ProjectManager\ProjectService;

// Проверка прав
global $USER;
if (!$USER->IsAuthorized()) {
    die('Требуется авторизация');
}

try {
    $projectService = new ProjectService();

    echo '<h1>Примеры работы с проектами через Factory API</h1>';

    // ============================================
    // Пример 1: Создание нового проекта
    // ============================================
    echo '<h2>Пример 1: Создание проекта</h2>';

    $newProjectData = [
        'TITLE' => 'Разработка мобильного приложения',
        'STAGE_ID' => 'NEW',
        'ASSIGNED_BY_ID' => $USER->GetID(),
        'BEGIN_DATE' => date('Y-m-d'),
        'CLOSE_DATE' => date('Y-m-d', strtotime('+6 months')),

        // Пользовательские поля
        'UF_PROJECT_BUDGET' => 500000.00,
        'UF_PRIORITY' => 'HIGH', // Нужно заменить на ID значения из enumeration
        'UF_PROJECT_MANAGER' => $USER->GetID(),
        'UF_PROJECT_TEAM' => [1, 2, 3], // ID сотрудников
        'UF_PROGRESS' => 0,
        'UF_PROJECT_TYPE' => 'SOFTWARE', // Нужно заменить на ID значения
        'UF_DESCRIPTION' => 'Разработка iOS и Android приложения для клиента',

        // Привязки
        'CONTACT_IDS' => [1, 2], // ID контактов
        'COMPANY_ID' => 5,       // ID компании
    ];

    $result = $projectService->createProject($newProjectData);

    if ($result->isSuccess()) {
        $projectId = $result->getData()['ID'];
        echo "<p style='color: green;'>✓ Проект создан успешно! ID: {$projectId}</p>";
    } else {
        echo "<p style='color: red;'>✗ Ошибка создания проекта:</p>";
        echo '<ul>';
        foreach ($result->getErrorMessages() as $error) {
            echo "<li>{$error}</li>";
        }
        echo '</ul>';
    }

    // ============================================
    // Пример 2: Получение проекта по ID
    // ============================================
    echo '<h2>Пример 2: Получение проекта</h2>';

    if (isset($projectId)) {
        $project = $projectService->getProject($projectId);

        if ($project) {
            echo '<table border="1" cellpadding="5">';
            echo '<tr><th>Поле</th><th>Значение</th></tr>';
            echo "<tr><td>ID</td><td>{$project->getId()}</td></tr>";
            echo "<tr><td>Название</td><td>{$project->getTitle()}</td></tr>";
            echo "<tr><td>Стадия</td><td>{$project->getStageId()}</td></tr>";
            echo "<tr><td>Ответственный</td><td>{$project->getAssignedById()}</td></tr>";
            echo "<tr><td>Дата начала</td><td>{$project->getBegindate()}</td></tr>";
            echo "<tr><td>Дата окончания</td><td>{$project->getClosedate()}</td></tr>";
            echo "<tr><td>Бюджет</td><td>{$project->get('UF_PROJECT_BUDGET')}</td></tr>";
            echo "<tr><td>Прогресс</td><td>{$project->get('UF_PROGRESS')}%</td></tr>";
            echo '</table>';
        } else {
            echo "<p style='color: red;'>Проект не найден</p>";
        }
    }

    // ============================================
    // Пример 3: Обновление проекта
    // ============================================
    echo '<h2>Пример 3: Обновление проекта</h2>';

    if (isset($projectId)) {
        $updateResult = $projectService->updateProject($projectId, [
            'TITLE' => 'Разработка мобильного приложения (ОБНОВЛЕНО)',
            'UF_PROGRESS' => 25,
            'STAGE_ID' => 'PLANNING',
        ]);

        if ($updateResult->isSuccess()) {
            echo "<p style='color: green;'>✓ Проект обновлён</p>";
        } else {
            echo "<p style='color: red;'>✗ Ошибка обновления:</p>";
            foreach ($updateResult->getErrorMessages() as $error) {
                echo "<p>{$error}</p>";
            }
        }
    }

    // ============================================
    // Пример 4: Изменение стадии
    // ============================================
    echo '<h2>Пример 4: Изменение стадии</h2>';

    if (isset($projectId)) {
        $stageResult = $projectService->changeStage($projectId, 'DEVELOPMENT');

        if ($stageResult->isSuccess()) {
            echo "<p style='color: green;'>✓ Стадия изменена на 'Разработка'</p>";
        }
    }

    // ============================================
    // Пример 5: Обновление прогресса
    // ============================================
    echo '<h2>Пример 5: Обновление прогресса</h2>';

    if (isset($projectId)) {
        $progressResult = $projectService->updateProgress($projectId, 50);

        if ($progressResult->isSuccess()) {
            echo "<p style='color: green;'>✓ Прогресс обновлён до 50%</p>";
        }
    }

    // ============================================
    // Пример 6: Добавление членов команды
    // ============================================
    echo '<h2>Пример 6: Добавление членов команды</h2>';

    if (isset($projectId)) {
        $teamResult = $projectService->addTeamMembers($projectId, [4, 5, 6]);

        if ($teamResult->isSuccess()) {
            echo "<p style='color: green;'>✓ Добавлены новые члены команды</p>";
        }
    }

    // ============================================
    // Пример 7: Получение списка проектов
    // ============================================
    echo '<h2>Пример 7: Список проектов</h2>';

    $projects = $projectService->getProjects(
        ['!@STAGE_SEMANTIC_ID' => ['S', 'F']], // Активные проекты
        ['ID' => 'DESC'],
        10
    );

    echo "<p>Найдено проектов: " . count($projects) . "</p>";

    if (!empty($projects)) {
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>ID</th><th>Название</th><th>Стадия</th><th>Прогресс</th><th>Ответственный</th></tr>';

        foreach ($projects as $proj) {
            echo '<tr>';
            echo "<td>{$proj->getId()}</td>";
            echo "<td>{$proj->getTitle()}</td>";
            echo "<td>{$proj->getStageId()}</td>";
            echo "<td>{$proj->get('UF_PROGRESS')}%</td>";
            echo "<td>{$proj->getAssignedById()}</td>";
            echo '</tr>';
        }

        echo '</table>';
    }

    // ============================================
    // Пример 8: Подсчёт проектов
    // ============================================
    echo '<h2>Пример 8: Статистика</h2>';

    $stats = $projectService->getStatistics();

    echo '<ul>';
    echo "<li>Всего проектов: {$stats['total']}</li>";
    echo "<li>Активных: {$stats['active']}</li>";
    echo "<li>Завершённых: {$stats['completed']}</li>";
    echo "<li>Отменённых: {$stats['cancelled']}</li>";
    echo '</ul>';

    echo '<h3>По стадиям:</h3>';
    echo '<ul>';
    foreach ($stats['by_stage'] as $stageId => $count) {
        echo "<li>{$stageId}: {$count}</li>";
    }
    echo '</ul>';

    // ============================================
    // Пример 9: Получение активных проектов
    // ============================================
    echo '<h2>Пример 9: Активные проекты</h2>';

    $activeProjects = $projectService->getActiveProjects();
    echo "<p>Активных проектов: " . count($activeProjects) . "</p>";

    // ============================================
    // Пример 10: Проекты конкретного менеджера
    // ============================================
    echo '<h2>Пример 10: Проекты менеджера</h2>';

    $managerProjects = $projectService->getProjectsByManager($USER->GetID());
    echo "<p>Ваших проектов: " . count($managerProjects) . "</p>";

    // ============================================
    // Пример 11: Проекты с высоким приоритетом
    // ============================================
    echo '<h2>Пример 11: Проекты с высоким приоритетом</h2>';

    $highPriorityProjects = $projectService->getHighPriorityProjects();
    echo "<p>Высокоприоритетных проектов: " . count($highPriorityProjects) . "</p>";

    // ============================================
    // Пример 12: Работа с Factory напрямую
    // ============================================
    echo '<h2>Пример 12: Прямая работа с Factory</h2>';

    $factory = $projectService->getFactory();

    // Получить информацию о полях
    $fieldsInfo = $factory->getFieldsInfo();
    echo '<p>Доступные поля:</p>';
    echo '<ul>';
    foreach (array_keys($fieldsInfo) as $fieldName) {
        echo "<li>{$fieldName}</li>";
    }
    echo '</ul>';

    // Получить стадии
    $stages = $factory->getStages();
    echo '<p>Доступные стадии:</p>';
    echo '<ul>';
    foreach ($stages as $stage) {
        echo "<li>{$stage->getStatusId()} - {$stage->getName()}</li>";
    }
    echo '</ul>';

    // ============================================
    // Пример 13: Построение URL
    // ============================================
    echo '<h2>Пример 13: URL для элементов</h2>';

    $router = \Bitrix\Crm\Service\Container::getInstance()->getRouter();
    $entityTypeId = 128; // Замените на свой

    if (isset($projectId)) {
        $detailUrl = $router->getItemDetailUrl($entityTypeId, $projectId);
        echo "<p><a href='{$detailUrl}' target='_blank'>Открыть проект #{$projectId}</a></p>";
    }

    $listUrl = $router->getItemListUrl($entityTypeId);
    echo "<p><a href='{$listUrl}' target='_blank'>Список всех проектов</a></p>";

    $kanbanUrl = $router->getKanbanUrl($entityTypeId);
    echo "<p><a href='{$kanbanUrl}' target='_blank'>Канбан</a></p>";

    // ============================================
    // Пример 14: Удаление проекта (закомментировано)
    // ============================================
    echo '<h2>Пример 14: Удаление проекта</h2>';
    echo '<p><em>Закомментировано для безопасности</em></p>';

    /*
    if (isset($projectId)) {
        $deleteResult = $projectService->deleteProject($projectId);

        if ($deleteResult->isSuccess()) {
            echo "<p style='color: green;'>✓ Проект удалён</p>";
        } else {
            echo "<p style='color: red;'>✗ Ошибка удаления:</p>";
            foreach ($deleteResult->getErrorMessages() as $error) {
                echo "<p>{$error}</p>";
            }
        }
    }
    */

    // ============================================
    // Пример 15: Пакетное создание проектов
    // ============================================
    echo '<h2>Пример 15: Пакетное создание</h2>';

    $projectsToCreate = [
        [
            'TITLE' => 'Проект A',
            'STAGE_ID' => 'NEW',
            'UF_PROJECT_BUDGET' => 100000,
            'UF_PROGRESS' => 0,
        ],
        [
            'TITLE' => 'Проект B',
            'STAGE_ID' => 'PLANNING',
            'UF_PROJECT_BUDGET' => 200000,
            'UF_PROGRESS' => 10,
        ],
        [
            'TITLE' => 'Проект C',
            'STAGE_ID' => 'DEVELOPMENT',
            'UF_PROJECT_BUDGET' => 300000,
            'UF_PROGRESS' => 30,
        ],
    ];

    $createdIds = [];
    foreach ($projectsToCreate as $data) {
        $result = $projectService->createProject($data);
        if ($result->isSuccess()) {
            $createdIds[] = $result->getData()['ID'];
        }
    }

    echo "<p style='color: green;'>✓ Создано проектов: " . count($createdIds) . "</p>";
    echo "<p>ID созданных проектов: " . implode(', ', $createdIds) . "</p>";

} catch (\Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Ошибка: {$e->getMessage()}</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
