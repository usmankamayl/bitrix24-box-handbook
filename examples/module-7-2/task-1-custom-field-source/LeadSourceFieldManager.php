<?php
/**
 * Создание пользовательского поля "Источник лида" с выпадающим списком
 *
 * Этот скрипт демонстрирует:
 * - Создание пользовательского поля через REST API
 * - Определение типа enumeration (выпадающий список)
 * - Установку списка значений со статусами и сортировкой
 */

class LeadSourceFieldManager
{
    private $apiUrl;
    private $accessToken;

    /**
     * Конструктор
     *
     * @param string $apiUrl URL Bitrix24 API (например, https://b24domain.bitrix24.ru/rest/)
     * @param string $accessToken токен доступа
     */
    public function __construct($apiUrl, $accessToken)
    {
        $this->apiUrl = rtrim($apiUrl, '/') . '/';
        $this->accessToken = $accessToken;
    }

    /**
     * Создание пользовательского поля "Источник лида"
     *
     * Создает поле с типом enumeration (выпадающий список),
     * которое можно использовать для отслеживания источника происхождения лида.
     *
     * @return array Результат API запроса
     */
    public function createSourceField()
    {
        // Данные нового поля
        $fieldData = [
            // Обязательные параметры
            'FIELD_NAME' => 'UF_CRM_LEAD_SOURCE',  // Уникальное имя (префикс UF_CRM_ обязателен)
            'USER_TYPE_ID' => 'enumeration',        // Тип: выпадающий список
            'EDIT_FORM_LABEL' => 'Источник лида',  // Название для отображения в форме
            'LIST_COLUMN_LABEL' => 'Источник',     // Название для отображения в списке

            // Параметры поля
            'MULTIPLE' => 'N',  // N - одиночное значение, Y - множественное
            'MANDATORY' => 'Y',  // Y - обязательное поле, N - необязательное

            // Список значений
            'LIST' => [
                // Каждое значение имеет VALUE и SORT (порядок в списке)
                [
                    'VALUE' => 'Google Ads',
                    'SORT' => 100,
                    'DEF' => 'N'  // Не является значением по умолчанию
                ],
                [
                    'VALUE' => 'Яндекс.Директ',
                    'SORT' => 200,
                    'DEF' => 'N'
                ],
                [
                    'VALUE' => 'Facebook',
                    'SORT' => 300,
                    'DEF' => 'N'
                ],
                [
                    'VALUE' => 'Instagram',
                    'SORT' => 400,
                    'DEF' => 'N'
                ],
                [
                    'VALUE' => 'Сайт (органика)',
                    'SORT' => 500,
                    'DEF' => 'N'
                ],
                [
                    'VALUE' => 'Рекомендация',
                    'SORT' => 600,
                    'DEF' => 'N'
                ]
            ]
        ];

        // Отправляем запрос к API для создания поля
        return $this->callApi('crm.lead.userfield.add', ['fields' => $fieldData]);
    }

    /**
     * Получение всех полей (стандартные и пользовательские) для Лидов
     *
     * Используется для получения метаданных полей, включая новое созданное поле,
     * и для определения ID значений выпадающего списка.
     *
     * @return array Массив полей с метаданными
     */
    public function getLeadFields()
    {
        return $this->callApi('crm.lead.fields', []);
    }

    /**
     * Получение ID значения из списка по его названию
     *
     * Используется для установки значения при создании/обновлении лида.
     *
     * @param array $fieldData Метаданные поля (из getLeadFields)
     * @param string $valueName Название значения (например, "Google Ads")
     * @return int|null ID значения или null если не найдено
     */
    public function getListValueId($fieldData, $valueName)
    {
        // Проверяем, есть ли поле и его тип enumeration
        if (!isset($fieldData['UF_CRM_LEAD_SOURCE'])) {
            return null;
        }

        $field = $fieldData['UF_CRM_LEAD_SOURCE'];

        // Возвращаем список возможных значений
        if (isset($field['settings']['items'])) {
            foreach ($field['settings']['items'] as $id => $item) {
                if ($item['NAME'] === $valueName) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * Создание лида с установкой значения пользовательского поля
     *
     * Демонстрирует установку значения для нового пользовательского поля.
     *
     * @param string $title Название лида
     * @param string $name Имя контакта
     * @param string $source Источник (название из списка)
     * @return array Результат создания лида
     */
    public function createLeadWithSource($title, $name, $source)
    {
        // Получаем метаданные полей для определения ID значения источника
        $fieldsMetadata = $this->getLeadFields();

        // Получаем ID значения "Google Ads" из списка
        $sourceId = $this->getListValueId($fieldsMetadata, $source);

        if ($sourceId === null) {
            throw new Exception("Источник '{$source}' не найден в списке");
        }

        // Создаем лид с заполненным пользовательским полем
        $leadData = [
            'TITLE' => $title,
            'NAME' => $name,
            'UF_CRM_LEAD_SOURCE' => $sourceId,  // Используем ID значения из списка
        ];

        return $this->callApi('crm.lead.add', ['fields' => $leadData]);
    }

    /**
     * Вспомогательный метод для выполнения REST API запросов
     *
     * @param string $method Название метода API
     * @param array $params Параметры запроса
     * @return array Результат запроса
     */
    private function callApi($method, $params)
    {
        // Формируем URL запроса
        $url = $this->apiUrl . $method;

        // Добавляем токен доступа
        $params['auth'] = $this->accessToken;

        // Выполняем POST запрос
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        // Парсим результат (обычно JSON или XML)
        $result = json_decode($response, true);

        return $result;
    }
}

// ============================================================================
// ПРИМЕР ИСПОЛЬЗОВАНИЯ
// ============================================================================

// Конфигурация
$apiUrl = 'https://your-bitrix24.bitrix24.ru/rest/';  // Замените на ваш URL
$accessToken = 'YOUR_ACCESS_TOKEN';  // Замените на ваш токен

try {
    // Создаем менеджер полей
    $manager = new LeadSourceFieldManager($apiUrl, $accessToken);

    // 1. Создаем пользовательское поле "Источник лида"
    echo "=== Создание пользовательского поля ===\n";
    $createResult = $manager->createSourceField();

    if (isset($createResult['result'])) {
        $fieldId = $createResult['result'];
        echo "✓ Поле успешно создано. ID: {$fieldId}\n\n";
    } else {
        echo "✗ Ошибка при создании поля:\n";
        var_dump($createResult);
        exit;
    }

    // 2. Получаем метаданные полей для проверки
    echo "=== Получение метаданных ===\n";
    $fields = $manager->getLeadFields();

    if (isset($fields['result']['UF_CRM_LEAD_SOURCE'])) {
        $sourceField = $fields['result']['UF_CRM_LEAD_SOURCE'];
        echo "✓ Поле найдено\n";
        echo "  Название: " . $sourceField['title'] . "\n";
        echo "  Тип: " . $sourceField['type'] . "\n\n";
    }

    // 3. Создаем лид с заполненным полем "Источник"
    echo "=== Создание лида с источником ===\n";
    $leadResult = $manager->createLeadWithSource(
        'Новый клиент от Google Ads',
        'Иван Петров',
        'Google Ads'
    );

    if (isset($leadResult['result'])) {
        $leadId = $leadResult['result'];
        echo "✓ Лид успешно создан. ID: {$leadId}\n";
        echo "  Источник: Google Ads\n";
    } else {
        echo "✗ Ошибка при создании лида:\n";
        var_dump($leadResult);
    }

    echo "\nВсе операции выполнены успешно!\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
