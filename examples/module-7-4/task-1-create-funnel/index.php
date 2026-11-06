<?php
/**
 * Задача 1: Создание воронки с кастомными стадиями
 *
 * Описание:
 * Создать новую воронку "VIP-клиенты" с 6 пользовательскими стадиями,
 * настроить цвета и семантику для каждой стадии.
 *
 * Сценарий использования:
 * Компания работает с VIP-клиентами по особому процессу продаж,
 * который отличается от стандартной воронки. Нужна отдельная воронка
 * с уникальными стадиями для премиум-сегмента.
 */

require_once(__DIR__ . '/crest.php');

class VipFunnelCreator
{
    /**
     * Создать воронку VIP-клиентов с кастомными стадиями
     *
     * @return array Результат создания
     */
    public function createVipFunnel()
    {
        // Шаг 1: Создать новую воронку
        $funnel = $this->createFunnel();

        if (!$funnel['success']) {
            return $funnel;
        }

        $categoryId = $funnel['categoryId'];
        echo "✅ Воронка создана (ID: {$categoryId})\n\n";

        // Шаг 2: Создать кастомные стадии
        $stages = $this->createStages($categoryId);

        return [
            'success' => true,
            'categoryId' => $categoryId,
            'stages' => $stages
        ];
    }

    /**
     * Создать воронку "VIP-клиенты"
     *
     * @return array
     */
    private function createFunnel()
    {
        echo "📋 Создаю воронку 'VIP-клиенты'...\n";

        $result = CRest::call('crm.dealcategory.add', [
            'fields' => [
                'NAME' => 'VIP-клиенты',
                'SORT' => 700
            ]
        ]);

        if (isset($result['result'])) {
            return [
                'success' => true,
                'categoryId' => $result['result']
            ];
        }

        return [
            'success' => false,
            'error' => $result['error_description'] ?? 'Неизвестная ошибка'
        ];
    }

    /**
     * Создать 6 кастомных стадий для VIP-воронки
     *
     * Стадии:
     * 1. Первичный контакт (синий, process)
     * 2. Анализ потребностей (фиолетовый, process)
     * 3. Персональное предложение (оранжевый, process)
     * 4. Согласование условий (желтый, process)
     * 5. Подписание контракта (зеленый, success)
     * 6. Отказ (красный, failure)
     *
     * @param int $categoryId ID воронки
     * @return array Созданные стадии
     */
    private function createStages($categoryId)
    {
        echo "🎨 Создаю стадии для воронки...\n\n";

        // Определение стадий с цветами и семантикой
        $stages = [
            [
                'STATUS_ID' => 'FIRST_CONTACT',
                'NAME' => 'Первичный контакт',
                'SORT' => 10,
                'COLOR' => '#2FC6F6', // Синий
                'SEMANTICS' => 'process',
                'DESCRIPTION' => 'Знакомство с VIP-клиентом, сбор первичной информации'
            ],
            [
                'STATUS_ID' => 'NEEDS_ANALYSIS',
                'NAME' => 'Анализ потребностей',
                'SORT' => 20,
                'COLOR' => '#9B59B6', // Фиолетовый
                'SEMANTICS' => 'process',
                'DESCRIPTION' => 'Глубокий анализ бизнес-потребностей клиента'
            ],
            [
                'STATUS_ID' => 'PERSONAL_OFFER',
                'NAME' => 'Персональное предложение',
                'SORT' => 30,
                'COLOR' => '#FF6B00', // Оранжевый
                'SEMANTICS' => 'process',
                'DESCRIPTION' => 'Подготовка индивидуального коммерческого предложения'
            ],
            [
                'STATUS_ID' => 'NEGOTIATION',
                'NAME' => 'Согласование условий',
                'SORT' => 40,
                'COLOR' => '#FFA900', // Желтый
                'SEMANTICS' => 'process',
                'DESCRIPTION' => 'Переговоры и согласование особых условий'
            ],
            [
                'STATUS_ID' => 'CONTRACT_SIGNED',
                'NAME' => 'Контракт подписан',
                'SORT' => 100,
                'COLOR' => '#06D800', // Зеленый
                'SEMANTICS' => 'success',
                'DESCRIPTION' => 'Успешное подписание контракта с VIP-клиентом'
            ],
            [
                'STATUS_ID' => 'DECLINED',
                'NAME' => 'Отказ',
                'SORT' => 110,
                'COLOR' => '#FF5752', // Красный
                'SEMANTICS' => 'failure',
                'DESCRIPTION' => 'Клиент отказался от сотрудничества'
            ]
        ];

        $createdStages = [];

        foreach ($stages as $stage) {
            echo "  ➕ Создаю стадию: {$stage['NAME']}\n";
            echo "     - Цвет: {$stage['COLOR']}\n";
            echo "     - Семантика: {$stage['SEMANTICS']}\n";

            $result = CRest::call('crm.dealcategory.stage.add', [
                'fields' => [
                    'ENTITY_ID' => "DEAL_STAGE_{$categoryId}",
                    'STATUS_ID' => $stage['STATUS_ID'],
                    'NAME' => $stage['NAME'],
                    'SORT' => $stage['SORT'],
                    'COLOR' => $stage['COLOR'],
                    'SEMANTICS' => $stage['SEMANTICS']
                ]
            ]);

            if (isset($result['result'])) {
                $createdStages[] = [
                    'id' => "C{$categoryId}:{$stage['STATUS_ID']}",
                    'name' => $stage['NAME'],
                    'color' => $stage['COLOR'],
                    'semantics' => $stage['SEMANTICS']
                ];
                echo "     ✅ Создана\n\n";
            } else {
                echo "     ❌ Ошибка: " . ($result['error_description'] ?? 'Неизвестная ошибка') . "\n\n";
            }
        }

        return $createdStages;
    }

    /**
     * Получить информацию о созданной воронке
     *
     * @param int $categoryId
     * @return void
     */
    public function displayFunnelInfo($categoryId)
    {
        echo "\n📊 Информация о созданной воронке:\n";
        echo "════════════════════════════════════════\n\n";

        // Получить воронку
        $funnel = CRest::call('crm.dealcategory.get', ['id' => $categoryId]);

        if (isset($funnel['result'])) {
            echo "Название: {$funnel['result']['NAME']}\n";
            echo "ID: {$funnel['result']['ID']}\n";
            echo "Сортировка: {$funnel['result']['SORT']}\n\n";
        }

        // Получить стадии
        $stages = CRest::call('crm.dealcategory.stage.list', ['id' => $categoryId]);

        if (isset($stages['result'])) {
            echo "Стадии воронки:\n";
            echo "─────────────────────────────────────\n";

            foreach ($stages['result'] as $stage) {
                $semanticIcon = $this->getSemanticIcon($stage['SEMANTICS']);
                echo "{$semanticIcon} {$stage['NAME']}\n";
                echo "   ID: C{$categoryId}:{$stage['STATUS_ID']}\n";
                echo "   Цвет: {$stage['COLOR']}\n";
                echo "   Семантика: {$stage['SEMANTICS']}\n\n";
            }
        }
    }

    /**
     * Получить иконку для типа семантики
     *
     * @param string $semantics
     * @return string
     */
    private function getSemanticIcon($semantics)
    {
        return match($semantics) {
            'process' => '🔄',
            'success' => '✅',
            'failure' => '❌',
            default => '•'
        };
    }

    /**
     * Создать тестовую сделку в новой воронке
     *
     * @param int $categoryId
     * @return array
     */
    public function createTestDeal($categoryId)
    {
        echo "\n🧪 Создаю тестовую сделку в VIP-воронке...\n";

        $result = CRest::call('crm.deal.add', [
            'fields' => [
                'TITLE' => 'Тестовая VIP-сделка',
                'CATEGORY_ID' => $categoryId,
                'STAGE_ID' => "C{$categoryId}:FIRST_CONTACT",
                'OPPORTUNITY' => 500000,
                'CURRENCY_ID' => 'RUB'
            ]
        ]);

        if (isset($result['result'])) {
            $dealId = $result['result'];
            echo "✅ Тестовая сделка создана (ID: {$dealId})\n";
            echo "   Стадия: Первичный контакт\n";
            echo "   Сумма: 500,000 руб.\n";

            return [
                'success' => true,
                'dealId' => $dealId
            ];
        }

        echo "❌ Ошибка создания сделки\n";
        return [
            'success' => false,
            'error' => $result['error_description'] ?? 'Неизвестная ошибка'
        ];
    }
}

// ==========================================
// ЗАПУСК
// ==========================================

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║   Создание VIP-воронки с кастомными стадиями        ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

$creator = new VipFunnelCreator();

// Создать воронку со стадиями
$result = $creator->createVipFunnel();

if ($result['success']) {
    // Показать информацию о воронке
    $creator->displayFunnelInfo($result['categoryId']);

    // Создать тестовую сделку
    $creator->createTestDeal($result['categoryId']);

    echo "\n";
    echo "╔══════════════════════════════════════════════════════╗\n";
    echo "║   ✅ Воронка успешно создана и готова к работе!     ║\n";
    echo "╚══════════════════════════════════════════════════════╝\n";

    // Дополнительная информация
    echo "\n📌 Что дальше?\n";
    echo "─────────────────────────────────────────────────────\n";
    echo "1. Перейдите в Bitrix24 → CRM → Сделки\n";
    echo "2. Выберите воронку 'VIP-клиенты'\n";
    echo "3. Вы увидите тестовую сделку на стадии 'Первичный контакт'\n";
    echo "4. Настройте роботов для каждой стадии\n";
    echo "5. Начните работать с VIP-клиентами!\n\n";

} else {
    echo "\n❌ Ошибка: {$result['error']}\n";
}
