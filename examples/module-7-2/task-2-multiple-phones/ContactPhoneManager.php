<?php
/**
 * Управление множественными телефонами контакта в Bitrix24 CRM
 *
 * Этот класс демонстрирует:
 * - Корректную работу с множественными полями
 * - Добавление новых значений без потери существующих
 * - Обновление конкретного значения по ID
 * - Удаление отдельного значения из множественного поля
 */

class ContactPhoneManager
{
    private $apiUrl;
    private $accessToken;

    /**
     * Конструктор
     *
     * @param string $apiUrl URL Bitrix24 API
     * @param string $accessToken токен доступа
     */
    public function __construct($apiUrl, $accessToken)
    {
        $this->apiUrl = rtrim($apiUrl, '/') . '/';
        $this->accessToken = $accessToken;
    }

    /**
     * Получить все телефоны контакта
     *
     * Возвращает массив телефонов с их ID, типом и значением.
     *
     * @param int $contactId ID контакта
     * @return array Массив телефонов
     * @throws Exception если контакт не найден
     */
    public function getPhones($contactId)
    {
        // Получаем контакт
        $contact = $this->callApi('crm.contact.get', ['id' => $contactId]);

        if (!isset($contact['result'])) {
            throw new Exception("Контакт с ID {$contactId} не найден");
        }

        // Извлекаем телефоны
        $phones = $contact['result']['PHONE'] ?? [];

        return $phones;
    }

    /**
     * Добавить новый телефон контакту
     *
     * ВАЖНО: Этот метод читает существующие телефоны, добавляет новый
     * и отправляет ВСЕ телефоны обратно (включая старые).
     * Иначе старые телефоны удалятся!
     *
     * @param int $contactId ID контакта
     * @param string $phoneValue Номер телефона (например, "+79991234567")
     * @param string $phoneType Тип телефона (WORK, MOBILE, HOME, OTHER)
     * @return array Результат операции
     */
    public function addPhone($contactId, $phoneValue, $phoneType = 'WORK')
    {
        // 1. Получаем существующие телефоны
        $existingPhones = $this->getPhones($contactId);

        // 2. Валидируем номер телефона
        if (!$this->validatePhone($phoneValue)) {
            throw new Exception("Некорректный формат телефона: {$phoneValue}");
        }

        // 3. Проверяем, нет ли уже такого номера
        foreach ($existingPhones as $phone) {
            if ($phone['VALUE'] === $phoneValue) {
                throw new Exception("Телефон {$phoneValue} уже существует у контакта");
            }
        }

        // 4. Добавляем новый телефон к существующим
        // ВАЖНО: не указываем ID для нового значения
        $existingPhones[] = [
            'VALUE' => $phoneValue,
            'VALUE_TYPE' => $phoneType
        ];

        // 5. Обновляем контакт с ПОЛНЫМ массивом телефонов
        return $this->callApi('crm.contact.update', [
            'id' => $contactId,
            'fields' => [
                'PHONE' => $existingPhones  // Все телефоны (старые + новый)
            ]
        ]);
    }

    /**
     * Обновить существующий телефон
     *
     * Изменяет номер или тип существующего телефона по его ID.
     *
     * @param int $contactId ID контакта
     * @param string $phoneId ID телефона (из структуры множественного поля)
     * @param string|null $newValue Новый номер (или null, если не менять)
     * @param string|null $newType Новый тип (или null, если не менять)
     * @return array Результат операции
     * @throws Exception если телефон не найден
     */
    public function updatePhone($contactId, $phoneId, $newValue = null, $newType = null)
    {
        // 1. Получаем существующие телефоны
        $phones = $this->getPhones($contactId);

        // 2. Находим нужный телефон и обновляем его
        $found = false;
        foreach ($phones as &$phone) {
            if ($phone['ID'] == $phoneId) {
                $found = true;

                // Обновляем значение, если передано
                if ($newValue !== null) {
                    if (!$this->validatePhone($newValue)) {
                        throw new Exception("Некорректный формат телефона: {$newValue}");
                    }
                    $phone['VALUE'] = $newValue;
                }

                // Обновляем тип, если передан
                if ($newType !== null) {
                    $phone['VALUE_TYPE'] = $newType;
                }

                break;
            }
        }

        if (!$found) {
            throw new Exception("Телефон с ID {$phoneId} не найден у контакта");
        }

        // 3. Отправляем обновление с ПОЛНЫМ массивом
        return $this->callApi('crm.contact.update', [
            'id' => $contactId,
            'fields' => [
                'PHONE' => $phones  // Все телефоны (измененный + остальные)
            ]
        ]);
    }

    /**
     * Удалить телефон
     *
     * Удаляет конкретный телефон по его ID, не затрагивая остальные.
     *
     * @param int $contactId ID контакта
     * @param string $phoneId ID телефона для удаления
     * @return array Результат операции
     * @throws Exception если телефон не найден
     */
    public function deletePhone($contactId, $phoneId)
    {
        // 1. Получаем существующие телефоны
        $phones = $this->getPhones($contactId);

        // 2. Фильтруем: убираем телефон с нужным ID
        $found = false;
        $filteredPhones = [];
        foreach ($phones as $phone) {
            if ($phone['ID'] == $phoneId) {
                $found = true;
                // Пропускаем этот телефон (не добавляем в новый массив)
                continue;
            }
            $filteredPhones[] = $phone;
        }

        if (!$found) {
            throw new Exception("Телефон с ID {$phoneId} не найден у контакта");
        }

        // 3. Отправляем обновление БЕЗ удаленного телефона
        return $this->callApi('crm.contact.update', [
            'id' => $contactId,
            'fields' => [
                'PHONE' => $filteredPhones  // Все телефоны кроме удаленного
            ]
        ]);
    }

    /**
     * Получить телефон определенного типа
     *
     * Возвращает первый найденный телефон указанного типа.
     *
     * @param int $contactId ID контакта
     * @param string $phoneType Тип телефона (WORK, MOBILE, HOME, OTHER)
     * @return array|null Данные телефона или null, если не найден
     */
    public function getPhoneByType($contactId, $phoneType)
    {
        $phones = $this->getPhones($contactId);

        foreach ($phones as $phone) {
            if ($phone['VALUE_TYPE'] === $phoneType) {
                return $phone;
            }
        }

        return null;
    }

    /**
     * Валидация номера телефона
     *
     * Проверяет формат номера телефона (простая проверка).
     *
     * @param string $phone Номер телефона
     * @return bool true если валиден, иначе false
     */
    private function validatePhone($phone)
    {
        // Простая валидация: номер должен содержать от 10 до 15 цифр
        // Может начинаться с "+" или без него
        return preg_match('/^\+?[0-9]{10,15}$/', $phone) === 1;
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
        $url = $this->apiUrl . $method;
        $params['auth'] = $this->accessToken;

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);

        return $result;
    }
}

// ============================================================================
// ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
// ============================================================================

$apiUrl = 'https://your-bitrix24.bitrix24.ru/rest/';
$accessToken = 'YOUR_ACCESS_TOKEN';

try {
    $manager = new ContactPhoneManager($apiUrl, $accessToken);
    $contactId = 123;  // ID существующего контакта

    // -------------------------------------------------------------------------
    // Пример 1: Получение всех телефонов
    // -------------------------------------------------------------------------
    echo "=== Получение всех телефонов ===\n";
    $phones = $manager->getPhones($contactId);
    echo "Найдено телефонов: " . count($phones) . "\n";
    foreach ($phones as $phone) {
        echo "  - ID: {$phone['ID']}, Тип: {$phone['VALUE_TYPE']}, Номер: {$phone['VALUE']}\n";
    }
    echo "\n";

    // -------------------------------------------------------------------------
    // Пример 2: Добавление нового телефона
    // -------------------------------------------------------------------------
    echo "=== Добавление нового телефона ===\n";
    $addResult = $manager->addPhone($contactId, '+79991234567', 'WORK');
    if (isset($addResult['result'])) {
        echo "✓ Телефон успешно добавлен\n\n";
    }

    // -------------------------------------------------------------------------
    // Пример 3: Обновление телефона
    // -------------------------------------------------------------------------
    echo "=== Обновление телефона ===\n";
    $phones = $manager->getPhones($contactId);
    if (count($phones) > 0) {
        $firstPhoneId = $phones[0]['ID'];
        $updateResult = $manager->updatePhone(
            $contactId,
            $firstPhoneId,
            '+79999999999',  // Новый номер
            'MOBILE'         // Новый тип
        );
        if (isset($updateResult['result'])) {
            echo "✓ Телефон {$firstPhoneId} успешно обновлен\n\n";
        }
    }

    // -------------------------------------------------------------------------
    // Пример 4: Получение телефона по типу
    // -------------------------------------------------------------------------
    echo "=== Поиск рабочего телефона ===\n";
    $workPhone = $manager->getPhoneByType($contactId, 'WORK');
    if ($workPhone) {
        echo "✓ Найден: {$workPhone['VALUE']}\n\n";
    } else {
        echo "✗ Рабочий телефон не найден\n\n";
    }

    // -------------------------------------------------------------------------
    // Пример 5: Удаление телефона
    // -------------------------------------------------------------------------
    echo "=== Удаление телефона ===\n";
    $phones = $manager->getPhones($contactId);
    if (count($phones) > 1) {
        $lastPhoneId = end($phones)['ID'];
        $deleteResult = $manager->deletePhone($contactId, $lastPhoneId);
        if (isset($deleteResult['result'])) {
            echo "✓ Телефон {$lastPhoneId} успешно удален\n\n";
        }
    }

    // -------------------------------------------------------------------------
    // Проверка финального состояния
    // -------------------------------------------------------------------------
    echo "=== Финальное состояние ===\n";
    $finalPhones = $manager->getPhones($contactId);
    echo "Осталось телефонов: " . count($finalPhones) . "\n";
    foreach ($finalPhones as $phone) {
        echo "  - {$phone['VALUE_TYPE']}: {$phone['VALUE']}\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
