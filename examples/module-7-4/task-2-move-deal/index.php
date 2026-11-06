<?php
/**
 * Задача 2: Автоматическое перемещение сделки по воронке
 *
 * Описание:
 * Реализация системы автоматического перемещения сделки на следующую стадию
 * с учетом семантики, проверкой возможности перехода и логированием.
 *
 * Сценарий использования:
 * При выполнении определенных условий (оплата счета, подписание документа)
 * сделка должна автоматически перемещаться на следующую стадию воронки.
 */

require_once(__DIR__ . '/crest.php');

class DealStageManager
{
    private $dealId;
    private $logs = [];

    public function __construct($dealId)
    {
        $this->dealId = $dealId;
    }

    /**
     * Переместить сделку на следующую стадию
     *
     * @return array Результат перемещения
     */
    public function moveToNextStage()
    {
        // Шаг 1: Получить текущую сделку
        $deal = $this->getDeal();

        if (!$deal['success']) {
            return $deal;
        }

        $this->log('info', "Текущая стадия: {$deal['data']['STAGE_ID']}");

        // Шаг 2: Проверить, не на финальной ли стадии
        if ($this->isFinalStage($deal['data']['STAGE_SEMANTIC_ID'])) {
            return [
                'success' => false,
                'error' => 'Сделка уже на финальной стадии',
                'currentStage' => $deal['data']['STAGE_ID'],
                'logs' => $this->logs
            ];
        }

        // Шаг 3: Получить следующую стадию
        $nextStage = $this->getNextStage(
            $deal['data']['CATEGORY_ID'],
            $deal['data']['STAGE_ID']
        );

        if (!$nextStage['success']) {
            return $nextStage;
        }

        $this->log('info', "Следующая стадия: {$nextStage['stageId']}");

        // Шаг 4: Переместить сделку
        $result = $this->moveDeal($nextStage['stageId']);

        if ($result['success']) {
            $this->log('success', "Сделка перемещена: {$deal['data']['STAGE_ID']} → {$nextStage['stageId']}");
        }

        return [
            'success' => $result['success'],
            'previousStage' => $deal['data']['STAGE_ID'],
            'currentStage' => $nextStage['stageId'],
            'logs' => $this->logs
        ];
    }

    /**
     * Переместить сделку на определенную стадию (с проверками)
     *
     * @param string $targetStageId ID целевой стадии
     * @return array
     */
    public function moveToStage($targetStageId)
    {
        // Шаг 1: Получить текущую сделку
        $deal = $this->getDeal();

        if (!$deal['success']) {
            return $deal;
        }

        $currentStageId = $deal['data']['STAGE_ID'];
        $this->log('info', "Перемещение: {$currentStageId} → {$targetStageId}");

        // Шаг 2: Проверить существование целевой стадии
        $targetStage = $this->getStageInfo(
            $deal['data']['CATEGORY_ID'],
            $targetStageId
        );

        if (!$targetStage['success']) {
            return $targetStage;
        }

        // Шаг 3: Проверить логику перехода
        $validation = $this->validateStageTransition(
            $deal['data']['STAGE_SEMANTIC_ID'],
            $targetStage['semantics']
        );

        if (!$validation['allowed']) {
            $this->log('warning', "Переход не рекомендуется: {$validation['reason']}");
        }

        // Шаг 4: Переместить
        $result = $this->moveDeal($targetStageId);

        return [
            'success' => $result['success'],
            'previousStage' => $currentStageId,
            'currentStage' => $targetStageId,
            'validation' => $validation,
            'logs' => $this->logs
        ];
    }

    /**
     * Переместить сделку назад (откат)
     *
     * @return array
     */
    public function moveToPreviousStage()
    {
        $deal = $this->getDeal();

        if (!$deal['success']) {
            return $deal;
        }

        $previousStage = $this->getPreviousStage(
            $deal['data']['CATEGORY_ID'],
            $deal['data']['STAGE_ID']
        );

        if (!$previousStage['success']) {
            return $previousStage;
        }

        $this->log('info', "Откат: {$deal['data']['STAGE_ID']} → {$previousStage['stageId']}");

        $result = $this->moveDeal($previousStage['stageId']);

        return [
            'success' => $result['success'],
            'previousStage' => $deal['data']['STAGE_ID'],
            'currentStage' => $previousStage['stageId'],
            'logs' => $this->logs
        ];
    }

    /**
     * Получить информацию о сделке
     *
     * @return array
     */
    private function getDeal()
    {
        $result = CRest::call('crm.deal.get', [
            'id' => $this->dealId
        ]);

        if (isset($result['result'])) {
            return [
                'success' => true,
                'data' => $result['result']
            ];
        }

        return [
            'success' => false,
            'error' => 'Сделка не найдена'
        ];
    }

    /**
     * Получить следующую стадию в воронке
     *
     * @param int $categoryId
     * @param string $currentStageId
     * @return array
     */
    private function getNextStage($categoryId, $currentStageId)
    {
        $stages = $this->getAllStages($categoryId);

        if (!$stages['success']) {
            return $stages;
        }

        // Сортировать по SORT
        usort($stages['data'], function($a, $b) {
            return $a['SORT'] <=> $b['SORT'];
        });

        // Найти текущую и взять следующую
        $found = false;
        foreach ($stages['data'] as $stage) {
            if ($found) {
                // Это следующая стадия
                return [
                    'success' => true,
                    'stageId' => "C{$categoryId}:{$stage['STATUS_ID']}",
                    'name' => $stage['NAME'],
                    'semantics' => $stage['SEMANTICS']
                ];
            }

            if ("C{$categoryId}:{$stage['STATUS_ID']}" === $currentStageId) {
                $found = true;
            }
        }

        return [
            'success' => false,
            'error' => 'Следующая стадия не найдена (возможно, это последняя стадия)'
        ];
    }

    /**
     * Получить предыдущую стадию в воронке
     *
     * @param int $categoryId
     * @param string $currentStageId
     * @return array
     */
    private function getPreviousStage($categoryId, $currentStageId)
    {
        $stages = $this->getAllStages($categoryId);

        if (!$stages['success']) {
            return $stages;
        }

        // Сортировать по SORT
        usort($stages['data'], function($a, $b) {
            return $a['SORT'] <=> $b['SORT'];
        });

        $previous = null;
        foreach ($stages['data'] as $stage) {
            $stageId = "C{$categoryId}:{$stage['STATUS_ID']}";

            if ($stageId === $currentStageId) {
                if ($previous) {
                    return [
                        'success' => true,
                        'stageId' => "C{$categoryId}:{$previous['STATUS_ID']}",
                        'name' => $previous['NAME'],
                        'semantics' => $previous['SEMANTICS']
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Это первая стадия, нет предыдущей'
                    ];
                }
            }

            $previous = $stage;
        }

        return [
            'success' => false,
            'error' => 'Текущая стадия не найдена'
        ];
    }

    /**
     * Получить все стадии воронки
     *
     * @param int $categoryId
     * @return array
     */
    private function getAllStages($categoryId)
    {
        $result = CRest::call('crm.dealcategory.stage.list', [
            'id' => $categoryId
        ]);

        if (isset($result['result'])) {
            return [
                'success' => true,
                'data' => $result['result']
            ];
        }

        return [
            'success' => false,
            'error' => 'Не удалось получить стадии'
        ];
    }

    /**
     * Получить информацию о стадии
     *
     * @param int $categoryId
     * @param string $stageId
     * @return array
     */
    private function getStageInfo($categoryId, $stageId)
    {
        $stages = $this->getAllStages($categoryId);

        if (!$stages['success']) {
            return $stages;
        }

        foreach ($stages['data'] as $stage) {
            if ("C{$categoryId}:{$stage['STATUS_ID']}" === $stageId) {
                return [
                    'success' => true,
                    'name' => $stage['NAME'],
                    'semantics' => $stage['SEMANTICS'],
                    'sort' => $stage['SORT']
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Стадия не найдена'
        ];
    }

    /**
     * Переместить сделку на стадию
     *
     * @param string $stageId
     * @return array
     */
    private function moveDeal($stageId)
    {
        $result = CRest::call('crm.deal.update', [
            'id' => $this->dealId,
            'fields' => [
                'STAGE_ID' => $stageId
            ]
        ]);

        if (isset($result['result']) && $result['result'] === true) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => $result['error_description'] ?? 'Ошибка перемещения'
        ];
    }

    /**
     * Проверить, является ли стадия финальной
     *
     * @param string $semantics
     * @return bool
     */
    private function isFinalStage($semantics)
    {
        return in_array($semantics, ['S', 'F']); // Success или Failure
    }

    /**
     * Валидация перехода между стадиями
     *
     * @param string $currentSemantics
     * @param string $targetSemantics
     * @return array
     */
    private function validateStageTransition($currentSemantics, $targetSemantics)
    {
        // Из финальной стадии нельзя никуда
        if (in_array($currentSemantics, ['S', 'F'])) {
            return [
                'allowed' => false,
                'reason' => 'Нельзя перемещать из финальной стадии'
            ];
        }

        // Обычно не перемещают напрямую в провал
        if ($targetSemantics === 'F' && $currentSemantics === 'P') {
            return [
                'allowed' => true,
                'reason' => 'Переход в провал - убедитесь, что это правильное действие',
                'warning' => true
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'Переход допустим'
        ];
    }

    /**
     * Логирование действий
     *
     * @param string $type
     * @param string $message
     * @return void
     */
    private function log($type, $message)
    {
        $this->logs[] = [
            'type' => $type,
            'message' => $message,
            'time' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Получить историю перемещений сделки
     *
     * @return array
     */
    public function getStageHistory()
    {
        $result = CRest::call('crm.timeline.list', [
            'filter' => [
                'ENTITY_ID' => $this->dealId,
                'ENTITY_TYPE' => 'deal'
            ],
            'order' => ['ID' => 'DESC']
        ]);

        if (isset($result['result'])) {
            return [
                'success' => true,
                'history' => $result['result']
            ];
        }

        return [
            'success' => false,
            'error' => 'Не удалось получить историю'
        ];
    }

    /**
     * Вывести логи в консоль
     *
     * @return void
     */
    public function printLogs()
    {
        if (empty($this->logs)) {
            return;
        }

        echo "\n📋 Лог операций:\n";
        echo "════════════════════════════════════════\n";

        foreach ($this->logs as $log) {
            $icon = match($log['type']) {
                'success' => '✅',
                'error' => '❌',
                'warning' => '⚠️',
                'info' => 'ℹ️',
                default => '•'
            };

            echo "{$icon} [{$log['time']}] {$log['message']}\n";
        }

        echo "\n";
    }
}

// ==========================================
// ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
// ==========================================

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║   Автоматическое перемещение сделки по воронке      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

// ID тестовой сделки (замените на реальный)
$dealId = 123;

$manager = new DealStageManager($dealId);

// ==========================================
// Пример 1: Переместить на следующую стадию
// ==========================================

echo "📌 Пример 1: Перемещение на следующую стадию\n";
echo "─────────────────────────────────────────────────\n";

$result = $manager->moveToNextStage();

if ($result['success']) {
    echo "✅ Сделка перемещена успешно!\n";
    echo "   Было: {$result['previousStage']}\n";
    echo "   Стало: {$result['currentStage']}\n";
} else {
    echo "❌ Ошибка: {$result['error']}\n";
}

$manager->printLogs();

// ==========================================
// Пример 2: Переместить на конкретную стадию
// ==========================================

echo "📌 Пример 2: Перемещение на конкретную стадию\n";
echo "─────────────────────────────────────────────────\n";

$manager2 = new DealStageManager($dealId);
$result2 = $manager2->moveToStage('C0:WON');

if ($result2['success']) {
    echo "✅ Сделка перемещена в финальную стадию!\n";
    if (isset($result2['validation']['warning'])) {
        echo "⚠️  {$result2['validation']['reason']}\n";
    }
} else {
    echo "❌ Ошибка: {$result2['error']}\n";
}

$manager2->printLogs();

// ==========================================
// Пример 3: Откат на предыдущую стадию
// ==========================================

echo "📌 Пример 3: Откат на предыдущую стадию\n";
echo "─────────────────────────────────────────────────\n";

$manager3 = new DealStageManager($dealId);
$result3 = $manager3->moveToPreviousStage();

if ($result3['success']) {
    echo "✅ Сделка откачена назад!\n";
    echo "   Было: {$result3['previousStage']}\n";
    echo "   Стало: {$result3['currentStage']}\n";
} else {
    echo "❌ Ошибка: {$result3['error']}\n";
}

$manager3->printLogs();

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║   ✅ Демонстрация завершена                         ║\n";
echo "╚══════════════════════════════════════════════════════╝\n";
