<?php
/**
 * Задача 1: Получение списка лидов через Webhook
 *
 * Этот скрипт подключается к Bitrix24 через входящий webhook
 * и получает список всех лидов с фильтрацией.
 *
 * Требуется:
 * 1. URL входящего webhook Bitrix24: https://your-portal.bitrix24.ru/rest/1/abc123xyz789/
 * 2. PHP с cURL или file_get_contents
 */

// ============================================
// КОНФИГУРАЦИЯ
// ============================================

/**
 * ВАЖНО: Замени на реальный URL твоего входящего webhook
 *
 * Как получить webhook:
 * 1. Зайди в Bitrix24
 * 2. Приложения → Вебхуки → Добавить входящий вебхук
 * 3. Выбери права (CRM: чтение)
 * 4. Скопируй URL
 */
define('WEBHOOK_URL', 'https://your-portal.bitrix24.ru/rest/1/abc123xyz789/');

// ============================================
// ФУНКЦИИ ДЛЯ РАБОТЫ С REST API
// ============================================

/**
 * Выполнить запрос к Bitrix24 REST API
 *
 * @param string $method Метод API (например, 'crm.lead.list')
 * @param array $params Параметры запроса
 * @return array Распакованный JSON-ответ
 * @throws Exception Если запрос не удался
 */
function callBitrix24(string $method, array $params = []): array
{
    // Формируем URL для запроса
    $url = WEBHOOK_URL . $method . '.json';

    // Добавляем параметры в URL
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    // Выполняем GET-запрос (можно также использовать cURL)
    $response = @file_get_contents($url);

    if ($response === false) {
        throw new Exception("Не удалось подключиться к Bitrix24. Проверь URL webhook.");
    }

    // Декодируем JSON
    $data = json_decode($response, true);

    // Проверяем на ошибки
    if (isset($data['error'])) {
        throw new Exception("Ошибка Bitrix24: " . $data['error']);
    }

    return $data;
}

/**
 * Получить все лиды с пагинацией
 *
 * @param array $filter Фильтр для поиска (например, ['STATUS_ID' => 'NEW'])
 * @param array $select Поля для выборки
 * @return array Массив всех лидов
 */
function getAllLeads(array $filter = [], array $select = []): array
{
    $allLeads = [];
    $start = 0;

    // Параметры поиска
    $params = [
        'start' => $start,
        'order' => ['ID' => 'DESC'],  // Сортировка: новые лиды первыми
    ];

    // Добавляем фильтр, если указан
    if (!empty($filter)) {
        $params['filter'] = $filter;
    }

    // Добавляем поля для выборки, если указаны
    if (!empty($select)) {
        $params['select'] = $select;
    } else {
        // По умолчанию выбираем основные поля
        $params['select'] = ['ID', 'TITLE', 'NAME', 'PHONE', 'EMAIL', 'STATUS_ID', 'SOURCE_ID'];
    }

    // Получаем лиды постранично (Bitrix24 возвращает по 50 за раз)
    do {
        $params['start'] = $start;

        $response = callBitrix24('crm.lead.list', $params);

        if (empty($response['result'])) {
            break;
        }

        // Добавляем лиды в общий массив
        $allLeads = array_merge($allLeads, $response['result']);

        // Проверяем, есть ли еще лиды
        if (empty($response['more']) || !$response['more']) {
            break;
        }

        // Переходим на следующую страницу
        $start = $response['next'];

    } while (true);

    return $allLeads;
}

/**
 * Получить информацию об одном лиде
 *
 * @param int $leadId ID лида
 * @return array Данные лида
 */
function getLeadDetail(int $leadId): array
{
    $response = callBitrix24('crm.lead.get', ['id' => $leadId]);
    return $response['result'] ?? [];
}

/**
 * Создать новый лид
 *
 * ВАЖНО: Это требует прав на ЗАПИСЬ в webhook, не только чтение
 *
 * @param array $fields Поля лида
 * @return int ID созданного лида
 */
function createLead(array $fields): int
{
    // TITLE обязателен
    if (empty($fields['TITLE'])) {
        throw new Exception('TITLE (название лида) обязателен');
    }

    $response = callBitrix24('crm.lead.add', ['fields' => $fields]);

    if (empty($response['result'])) {
        throw new Exception('Не удалось создать лид');
    }

    return (int)$response['result'];
}

// ============================================
// ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
// ============================================

try {
    echo "=== Получение списка лидов из Bitrix24 ===\n\n";

    // ========== ПРИМЕР 1: Получить ВСЕ лиды ==========
    echo "1️⃣ Получаю ВСЕ лиды...\n";
    $allLeads = getAllLeads();
    echo "Всего лидов: " . count($allLeads) . "\n\n";

    if (!empty($allLeads)) {
        echo "Первые 3 лида:\n";
        foreach (array_slice($allLeads, 0, 3) as $lead) {
            echo sprintf(
                "  - ID: %s | %s | Email: %s | Статус: %s\n",
                $lead['ID'],
                $lead['TITLE'] ?? 'N/A',
                $lead['EMAIL'] ?? 'N/A',
                $lead['STATUS_ID'] ?? 'N/A'
            );
        }
        echo "\n";
    }

    // ========== ПРИМЕР 2: Получить лиды по фильтру ==========
    echo "2️⃣ Получаю лиды со статусом 'Новый'...\n";
    $newLeads = getAllLeads(['STATUS_ID' => 'NEW']);
    echo "Лидов со статусом 'Новый': " . count($newLeads) . "\n\n";

    // ========== ПРИМЕР 3: Получить лиды с конкретными полями ==========
    echo "3️⃣ Получаю лиды только с ID и названием...\n";
    $leadsMinimal = getAllLeads([], ['ID', 'TITLE']);
    echo "Лиды (минимум информации):\n";
    foreach (array_slice($leadsMinimal, 0, 2) as $lead) {
        echo sprintf("  - ID: %s | %s\n", $lead['ID'], $lead['TITLE']);
    }
    echo "\n";

    // ========== ПРИМЕР 4: Получить детальную информацию об одном лиде ==========
    if (!empty($allLeads)) {
        $firstLeadId = $allLeads[0]['ID'];
        echo "4️⃣ Получаю детальную информацию о лиде #" . $firstLeadId . "...\n";

        $leadDetail = getLeadDetail($firstLeadId);
        echo "Детали лида:\n";
        echo json_encode($leadDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    // ========== ПРИМЕР 5: Создать новый лид ==========
    echo "5️⃣ Создаю новый лид...\n";
    try {
        $newLeadId = createLead([
            'TITLE' => 'Тестовый лид от скрипта',
            'NAME' => 'Иван',
            'PHONE' => '+7 (900) 123-45-67',
            'EMAIL' => 'test@example.com',
        ]);
        echo "✅ Лид создан! ID: " . $newLeadId . "\n\n";
    } catch (Exception $e) {
        echo "⚠️ Не удалось создать лид: " . $e->getMessage() . "\n";
        echo "Возможно, webhook имеет права только на ЧТЕНИЕ, а не на ЗАПИСЬ\n\n";
    }

    // ========== ПРИМЕР 6: Статистика ==========
    echo "6️⃣ Статистика по лидам:\n";

    // Подсчитываем лиды по статусам
    $statusCounts = [];
    foreach ($allLeads as $lead) {
        $status = $lead['STATUS_ID'] ?? 'UNKNOWN';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    foreach ($statusCounts as $status => $count) {
        echo sprintf("  - Статус '%s': %d лидов\n", $status, $count);
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "\nПроблема может быть в:\n";
    echo "  1. Неправильном URL webhook\n";
    echo "  2. Недостаточных правах (нужны права на чтение CRM)\n";
    echo "  3. Портале, который недоступен\n";
}
?>
