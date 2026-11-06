<?php
/**
 * Менеджер для работы с контактами через Bitrix24 REST API
 *
 * Расположение в проекте:
 * /local/php_interface/classes/ContactManager.php
 *
 * Подключение в init.php:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/ContactManager.php';
 */

namespace Local\Bitrix24;

class ContactManager
{
    /** @var string URL входящего webhook */
    private $webhookUrl;

    /** @var int Лимит записей на страницу */
    private const PAGE_SIZE = 50;

    /**
     * @param string $webhookUrl URL входящего webhook
     */
    public function __construct(string $webhookUrl)
    {
        $this->webhookUrl = rtrim($webhookUrl, '/');
    }

    /**
     * Создание нового контакта
     *
     * @param array $fields Поля контакта
     * @return int|false ID созданного контакта или false при ошибке
     *
     * @example
     * $contactId = $manager->createContact([
     *     'NAME' => 'Иван',
     *     'LAST_NAME' => 'Петров',
     *     'PHONE' => [
     *         ['VALUE' => '+7 (999) 123-45-67', 'VALUE_TYPE' => 'WORK']
     *     ],
     *     'EMAIL' => [
     *         ['VALUE' => 'petrov@example.com', 'VALUE_TYPE' => 'WORK']
     *     ],
     *     'COMPANY_ID' => 123
     * ]);
     */
    public function createContact(array $fields)
    {
        $response = $this->callMethod('crm.contact.add', [
            'fields' => $fields
        ]);

        if (!$response || isset($response['error'])) {
            error_log('Ошибка создания контакта: ' . ($response['error_description'] ?? 'Unknown error'));
            return false;
        }

        return $response['result'];
    }

    /**
     * Обновление контакта
     *
     * @param int $contactId ID контакта
     * @param array $fields Поля для обновления
     * @return bool Успешность операции
     */
    public function updateContact(int $contactId, array $fields): bool
    {
        $response = $this->callMethod('crm.contact.update', [
            'id' => $contactId,
            'fields' => $fields
        ]);

        if (!$response || isset($response['error'])) {
            error_log("Ошибка обновления контакта #{$contactId}: " . ($response['error_description'] ?? 'Unknown error'));
            return false;
        }

        return (bool)$response['result'];
    }

    /**
     * Получение контакта по ID
     *
     * @param int $contactId ID контакта
     * @return array|false Данные контакта или false при ошибке
     */
    public function getContact(int $contactId)
    {
        $response = $this->callMethod('crm.contact.get', [
            'id' => $contactId
        ]);

        if (!$response || isset($response['error'])) {
            return false;
        }

        return $response['result'];
    }

    /**
     * Поиск контактов с фильтрацией
     *
     * @param array $filter Условия фильтрации
     * @param array $select Поля для выборки
     * @param array $order Сортировка
     * @return array Массив контактов
     *
     * @example
     * // Найти контакты с именем "Иван"
     * $contacts = $manager->searchContacts([
     *     '%NAME' => 'Иван'
     * ]);
     */
    public function searchContacts(
        array $filter = [],
        array $select = ['*'],
        array $order = ['DATE_CREATE' => 'DESC']
    ): array
    {
        $allContacts = [];
        $start = 0;

        do {
            $response = $this->callMethod('crm.contact.list', [
                'filter' => $filter,
                'select' => $select,
                'order' => $order,
                'start' => $start
            ]);

            if (!$response || isset($response['error'])) {
                break;
            }

            $contacts = $response['result'] ?? [];
            $allContacts = array_merge($allContacts, $contacts);

            $start += self::PAGE_SIZE;

        } while (count($contacts) === self::PAGE_SIZE);

        return $allContacts;
    }

    /**
     * Поиск контакта по телефону
     *
     * @param string $phone Номер телефона
     * @return array|null Найденный контакт или null
     */
    public function findByPhone(string $phone): ?array
    {
        $contacts = $this->searchContacts(
            ['PHONE' => $this->normalizePhone($phone)],
            ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL']
        );

        return !empty($contacts) ? $contacts[0] : null;
    }

    /**
     * Поиск контакта по email
     *
     * @param string $email Email
     * @return array|null Найденный контакт или null
     */
    public function findByEmail(string $email): ?array
    {
        $contacts = $this->searchContacts(
            ['EMAIL' => $email],
            ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL']
        );

        return !empty($contacts) ? $contacts[0] : null;
    }

    /**
     * Импорт контактов из CSV файла
     *
     * @param string $csvFilePath Путь к CSV файлу
     * @param bool $updateExisting Обновлять существующие контакты
     * @return array Статистика импорта
     *
     * @example
     * $result = $manager->importFromCsv('/path/to/contacts.csv', true);
     * echo "Создано: {$result['created']}, обновлено: {$result['updated']}";
     */
    public function importFromCsv(string $csvFilePath, bool $updateExisting = false): array
    {
        if (!file_exists($csvFilePath)) {
            return [
                'success' => false,
                'error' => 'Файл не найден'
            ];
        }

        $stats = [
            'success' => true,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            return [
                'success' => false,
                'error' => 'Не удалось открыть файл'
            ];
        }

        // Читаем заголовки
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [
                'success' => false,
                'error' => 'Файл пуст или неверный формат'
            ];
        }

        $lineNumber = 1;

        // Обрабатываем строки
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if (count($row) !== count($headers)) {
                $stats['errors'][] = "Строка {$lineNumber}: неверное количество колонок";
                continue;
            }

            // Преобразуем строку в ассоциативный массив
            $data = array_combine($headers, $row);

            // Формируем поля контакта
            $fields = $this->mapCsvRowToContact($data);

            if (empty($fields['NAME']) && empty($fields['LAST_NAME'])) {
                $stats['skipped']++;
                continue;
            }

            // Проверяем на дубли по телефону или email
            $existing = null;

            if (!empty($data['phone'])) {
                $existing = $this->findByPhone($data['phone']);
            }

            if (!$existing && !empty($data['email'])) {
                $existing = $this->findByEmail($data['email']);
            }

            if ($existing) {
                if ($updateExisting) {
                    // Обновляем существующий контакт
                    if ($this->updateContact($existing['ID'], $fields)) {
                        $stats['updated']++;
                    } else {
                        $stats['errors'][] = "Строка {$lineNumber}: ошибка обновления контакта #{$existing['ID']}";
                    }
                } else {
                    $stats['skipped']++;
                }
            } else {
                // Создаем новый контакт
                $contactId = $this->createContact($fields);

                if ($contactId) {
                    $stats['created']++;
                } else {
                    $stats['errors'][] = "Строка {$lineNumber}: ошибка создания контакта";
                }
            }

            // Защита от превышения лимита (пауза каждые 10 запросов)
            if (($stats['created'] + $stats['updated']) % 10 === 0) {
                sleep(1);
            }
        }

        fclose($handle);

        return $stats;
    }

    /**
     * Экспорт контактов в CSV
     *
     * @param string $outputPath Путь для сохранения файла
     * @param array $filter Фильтр контактов
     * @return bool Успешность операции
     */
    public function exportToCsv(string $outputPath, array $filter = []): bool
    {
        $contacts = $this->searchContacts($filter);

        if (empty($contacts)) {
            return false;
        }

        $handle = fopen($outputPath, 'w');
        if (!$handle) {
            return false;
        }

        // Заголовки
        fputcsv($handle, [
            'id', 'name', 'last_name', 'second_name',
            'phone', 'email', 'company_id', 'type_id',
            'source_id', 'assigned_by_id', 'date_create'
        ]);

        // Данные
        foreach ($contacts as $contact) {
            $phone = !empty($contact['PHONE']) ? $contact['PHONE'][0]['VALUE'] : '';
            $email = !empty($contact['EMAIL']) ? $contact['EMAIL'][0]['VALUE'] : '';

            fputcsv($handle, [
                $contact['ID'],
                $contact['NAME'] ?? '',
                $contact['LAST_NAME'] ?? '',
                $contact['SECOND_NAME'] ?? '',
                $phone,
                $email,
                $contact['COMPANY_ID'] ?? '',
                $contact['TYPE_ID'] ?? '',
                $contact['SOURCE_ID'] ?? '',
                $contact['ASSIGNED_BY_ID'] ?? '',
                $contact['DATE_CREATE'] ?? ''
            ]);
        }

        fclose($handle);

        return true;
    }

    /**
     * Удаление контакта
     *
     * @param int $contactId ID контакта
     * @return bool Успешность операции
     */
    public function deleteContact(int $contactId): bool
    {
        $response = $this->callMethod('crm.contact.delete', [
            'id' => $contactId
        ]);

        if (!$response || isset($response['error'])) {
            return false;
        }

        return (bool)$response['result'];
    }

    /**
     * Маппинг CSV строки в поля контакта
     *
     * @param array $data Данные из CSV
     * @return array Поля для API
     */
    private function mapCsvRowToContact(array $data): array
    {
        $fields = [
            'NAME' => trim($data['name'] ?? ''),
            'LAST_NAME' => trim($data['last_name'] ?? ''),
            'SECOND_NAME' => trim($data['second_name'] ?? ''),
            'TYPE_ID' => $data['type'] ?? 'CLIENT',
            'SOURCE_ID' => $data['source'] ?? 'OTHER',
            'OPENED' => 'Y'
        ];

        // Телефон
        if (!empty($data['phone'])) {
            $fields['PHONE'] = [
                [
                    'VALUE' => $this->normalizePhone($data['phone']),
                    'VALUE_TYPE' => $data['phone_type'] ?? 'WORK'
                ]
            ];
        }

        // Email
        if (!empty($data['email'])) {
            $fields['EMAIL'] = [
                [
                    'VALUE' => trim($data['email']),
                    'VALUE_TYPE' => $data['email_type'] ?? 'WORK'
                ]
            ];
        }

        // Компания
        if (!empty($data['company_id'])) {
            $fields['COMPANY_ID'] = (int)$data['company_id'];
        }

        // Ответственный
        if (!empty($data['assigned_by_id'])) {
            $fields['ASSIGNED_BY_ID'] = (int)$data['assigned_by_id'];
        }

        // Комментарий
        if (!empty($data['comments'])) {
            $fields['COMMENTS'] = trim($data['comments']);
        }

        return $fields;
    }

    /**
     * Нормализация номера телефона
     *
     * @param string $phone Исходный номер
     * @return string Нормализованный номер
     */
    private function normalizePhone(string $phone): string
    {
        // Удаляем все символы кроме цифр и +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Если номер начинается с 8, заменяем на +7
        if (preg_match('/^8(\d{10})$/', $phone, $matches)) {
            $phone = '+7' . $matches[1];
        }

        // Если номер начинается с 7 (без +), добавляем +
        if (preg_match('/^7(\d{10})$/', $phone, $matches)) {
            $phone = '+7' . $matches[1];
        }

        return $phone;
    }

    /**
     * Вызов REST API метода
     *
     * @param string $method Название метода
     * @param array $params Параметры запроса
     * @return array|false Ответ API или false при ошибке
     */
    private function callMethod(string $method, array $params = [])
    {
        $url = $this->webhookUrl . '/' . $method . '.json';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("HTTP error {$httpCode} при вызове {$method}");
            return false;
        }

        return json_decode($response, true);
    }
}
