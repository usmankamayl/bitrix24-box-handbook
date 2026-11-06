<?php
namespace Vettich;

/**
 * Класс для массового импорта контактов из CSV через batch-запросы
 *
 * Использование:
 * $importer = new ContactBatchImporter($bitrix24Client);
 * $importer->importFromCsv('/path/to/contacts.csv');
 * $report = $importer->getReport();
 */
class ContactBatchImporter
{
    /** @var object REST API клиент */
    private $client;

    /** @var int Размер batch-пакета */
    private $batchSize = 50;

    /** @var array Статистика импорта */
    private $stats = [
        'total' => 0,
        'imported' => 0,
        'duplicates' => 0,
        'errors' => 0,
        'updated' => 0,
        'start_time' => null,
        'end_time' => null,
        'log' => []
    ];

    /** @var array Кэш существующих контактов для проверки дублей */
    private $existingContacts = [];

    /**
     * @param object $client REST API клиент
     */
    public function __construct($client)
    {
        $this->client = $client;
        $this->stats['start_time'] = microtime(true);
    }

    /**
     * Установить размер batch-пакета
     *
     * @param int $size Количество операций в одном пакете
     * @return self
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
    }

    /**
     * Импортировать контакты из CSV-файла
     *
     * @param string $filePath Путь к CSV-файлу
     * @param array $options Опции импорта
     *   - skip_duplicates: bool - пропускать дубли (default: true)
     *   - update_duplicates: bool - обновлять существующие (default: false)
     *   - normalize_phones: bool - нормализовать телефоны (default: true)
     *   - delimiter: string - разделитель CSV (default: ',')
     *   - encoding: string - кодировка файла (default: 'UTF-8')
     * @return array Результаты импорта
     */
    public function importFromCsv(string $filePath, array $options = []): array
    {
        // Опции по умолчанию
        $options = array_merge([
            'skip_duplicates' => true,
            'update_duplicates' => false,
            'normalize_phones' => true,
            'delimiter' => ',',
            'encoding' => 'UTF-8'
        ], $options);

        echo "Чтение CSV файла: $filePath\n";

        // Читаем CSV
        $contacts = $this->parseCsv($filePath, $options['delimiter'], $options['encoding']);
        $this->stats['total'] = count($contacts);

        echo "Найдено контактов в CSV: {$this->stats['total']}\n";

        if (empty($contacts)) {
            echo "Файл пуст или не содержит данных\n";
            return [];
        }

        // Загружаем существующие контакты для проверки дублей
        if ($options['skip_duplicates'] || $options['update_duplicates']) {
            echo "Загрузка существующих контактов для проверки дублей...\n";
            $this->loadExistingContacts();
            echo "Загружено контактов: " . count($this->existingContacts) . "\n";
        }

        // Нормализуем данные
        if ($options['normalize_phones']) {
            $contacts = $this->normalizeContactsData($contacts);
        }

        // Импортируем пакетами
        echo "Начинаем импорт...\n\n";
        $results = $this->importContactsBatch($contacts, $options);

        $this->stats['end_time'] = microtime(true);

        return $results;
    }

    /**
     * Парсинг CSV-файла
     *
     * @param string $filePath Путь к файлу
     * @param string $delimiter Разделитель
     * @param string $encoding Кодировка
     * @return array Массив контактов
     */
    private function parseCsv(string $filePath, string $delimiter, string $encoding): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Файл не найден: $filePath");
        }

        $contacts = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \Exception("Не удалось открыть файл: $filePath");
        }

        // Читаем заголовки
        $headers = fgetcsv($handle, 0, $delimiter);

        if ($headers === false) {
            fclose($handle);
            throw new \Exception("Не удалось прочитать заголовки CSV");
        }

        // Конвертируем кодировку если нужно
        if (strtoupper($encoding) !== 'UTF-8') {
            $headers = array_map(function($header) use ($encoding) {
                return mb_convert_encoding($header, 'UTF-8', $encoding);
            }, $headers);
        }

        // Читаем строки
        $lineNumber = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            // Пропускаем пустые строки
            if (empty(array_filter($row))) {
                continue;
            }

            // Конвертируем кодировку
            if (strtoupper($encoding) !== 'UTF-8') {
                $row = array_map(function($value) use ($encoding) {
                    return mb_convert_encoding($value, 'UTF-8', $encoding);
                }, $row);
            }

            // Формируем ассоциативный массив
            $contact = [];
            foreach ($headers as $index => $header) {
                $contact[$header] = $row[$index] ?? '';
            }

            $contact['_line'] = $lineNumber; // Сохраняем номер строки для логов
            $contacts[] = $contact;
        }

        fclose($handle);

        return $contacts;
    }

    /**
     * Нормализация данных контактов
     *
     * @param array $contacts Массив контактов
     * @return array Нормализованные контакты
     */
    private function normalizeContactsData(array $contacts): array
    {
        foreach ($contacts as &$contact) {
            // Нормализация телефона (8xxx -> +7xxx)
            if (!empty($contact['phone'])) {
                $contact['phone'] = $this->normalizePhone($contact['phone']);
            }

            // Приведение email к нижнему регистру
            if (!empty($contact['email'])) {
                $contact['email'] = strtolower(trim($contact['email']));
            }

            // Очистка пробелов в имени
            if (!empty($contact['name'])) {
                $contact['name'] = trim($contact['name']);
            }
        }

        return $contacts;
    }

    /**
     * Нормализация телефона
     *
     * @param string $phone Телефон
     * @return string Нормализованный телефон
     */
    private function normalizePhone(string $phone): string
    {
        // Удаляем все кроме цифр и +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // 8xxx -> +7xxx
        if (preg_match('/^8(\d{10})$/', $phone, $matches)) {
            $phone = '+7' . $matches[1];
        }

        // Если начинается с 7 без +
        if (preg_match('/^7(\d{10})$/', $phone)) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Загрузить существующие контакты для проверки дублей
     */
    private function loadExistingContacts(): void
    {
        $start = 0;
        $limit = 50;

        do {
            $response = $this->client->call('crm.contact.list', [
                'select' => ['ID', 'NAME', 'PHONE', 'EMAIL'],
                'start' => $start
            ]);

            if (isset($response['result'])) {
                foreach ($response['result'] as $contact) {
                    // Индексируем по телефону
                    if (!empty($contact['PHONE'])) {
                        foreach ($contact['PHONE'] as $phone) {
                            $normalized = $this->normalizePhone($phone['VALUE']);
                            $this->existingContacts['phone'][$normalized] = $contact['ID'];
                        }
                    }

                    // Индексируем по email
                    if (!empty($contact['EMAIL'])) {
                        foreach ($contact['EMAIL'] as $email) {
                            $normalized = strtolower(trim($email['VALUE']));
                            $this->existingContacts['email'][$normalized] = $contact['ID'];
                        }
                    }
                }

                $start += $limit;
                $hasMore = isset($response['next']) || count($response['result']) === $limit;
            } else {
                break;
            }
        } while ($hasMore);
    }

    /**
     * Проверить, существует ли контакт
     *
     * @param array $contact Данные контакта
     * @return int|null ID существующего контакта или null
     */
    private function checkDuplicate(array $contact): ?int
    {
        // Проверка по телефону
        if (!empty($contact['phone'])) {
            $phone = $this->normalizePhone($contact['phone']);
            if (isset($this->existingContacts['phone'][$phone])) {
                return $this->existingContacts['phone'][$phone];
            }
        }

        // Проверка по email
        if (!empty($contact['email'])) {
            $email = strtolower(trim($contact['email']));
            if (isset($this->existingContacts['email'][$email])) {
                return $this->existingContacts['email'][$email];
            }
        }

        return null;
    }

    /**
     * Импортировать контакты через batch-запросы
     *
     * @param array $contacts Массив контактов
     * @param array $options Опции импорта
     * @return array Результаты импорта
     */
    private function importContactsBatch(array $contacts, array $options): array
    {
        $results = [];
        $chunks = array_chunk($contacts, $this->batchSize);
        $processed = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $batch = [];

            foreach ($chunk as $i => $contact) {
                $processed++;

                // Проверка дублей
                $existingId = $this->checkDuplicate($contact);

                if ($existingId !== null) {
                    if ($options['update_duplicates']) {
                        // Обновляем существующий контакт
                        $batch["update_$i"] = [
                            'method' => 'crm.contact.update',
                            'params' => [
                                'ID' => $existingId,
                                'fields' => $this->formatContactFields($contact)
                            ]
                        ];

                        $this->log("Строка {$contact['_line']}: будет обновлен контакт ID $existingId");
                    } else {
                        // Пропускаем дубль
                        $this->stats['duplicates']++;
                        $this->log("Строка {$contact['_line']}: пропущен дубль (ID $existingId)");
                        continue;
                    }
                } else {
                    // Создаем новый контакт
                    $batch["create_$i"] = [
                        'method' => 'crm.contact.add',
                        'params' => [
                            'fields' => $this->formatContactFields($contact)
                        ]
                    ];
                }

                // Выводим прогресс
                $this->logProgress($processed, $this->stats['total']);
            }

            // Выполняем batch, если есть операции
            if (!empty($batch)) {
                try {
                    $response = $this->client->call('batch', ['cmd' => $batch]);

                    // Обрабатываем результаты
                    if (isset($response['result']['result'])) {
                        foreach ($response['result']['result'] as $key => $result) {
                            if (isset($result['error'])) {
                                $this->stats['errors']++;
                                $this->log("Ошибка в $key: " . $result['error']);
                            } else {
                                if (strpos($key, 'create_') === 0) {
                                    $this->stats['imported']++;
                                    $results[] = ['action' => 'created', 'id' => $result['result']];
                                } else {
                                    $this->stats['updated']++;
                                    $results[] = ['action' => 'updated', 'id' => $result['result']];
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->stats['errors'] += count($batch);
                    $this->log("Ошибка batch #{$chunkIndex}: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Форматировать поля контакта для API
     *
     * @param array $contact Данные контакта из CSV
     * @return array Поля для API
     */
    private function formatContactFields(array $contact): array
    {
        $fields = [];

        // Имя (обязательное поле)
        if (!empty($contact['name'])) {
            $fields['NAME'] = $contact['name'];
        }

        // Телефон
        if (!empty($contact['phone'])) {
            $fields['PHONE'] = [
                ['VALUE' => $contact['phone'], 'VALUE_TYPE' => 'WORK']
            ];
        }

        // Email
        if (!empty($contact['email'])) {
            $fields['EMAIL'] = [
                ['VALUE' => $contact['email'], 'VALUE_TYPE' => 'WORK']
            ];
        }

        // Компания
        if (!empty($contact['company'])) {
            $fields['COMPANY_TITLE'] = $contact['company'];
        }

        // Должность
        if (!empty($contact['position'])) {
            $fields['POST'] = $contact['position'];
        }

        // Комментарий
        if (!empty($contact['comment'])) {
            $fields['COMMENTS'] = $contact['comment'];
        }

        return $fields;
    }

    /**
     * Логирование
     *
     * @param string $message Сообщение
     */
    private function log(string $message): void
    {
        $this->stats['log'][] = [
            'time' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
    }

    /**
     * Вывести прогресс
     *
     * @param int $processed Обработано
     * @param int $total Всего
     */
    private function logProgress(int $processed, int $total): void
    {
        $percentage = round(($processed / $total) * 100, 2);
        $bar = str_repeat('=', (int)($percentage / 2)) . str_repeat(' ', 50 - (int)($percentage / 2));

        echo "\rПрогресс: [$bar] $processed/$total ($percentage%)";

        if ($processed === $total) {
            echo "\n\n";
        }
    }

    /**
     * Получить отчет об импорте
     *
     * @return array Статистика
     */
    public function getReport(): array
    {
        $executionTime = $this->stats['end_time']
            ? round($this->stats['end_time'] - $this->stats['start_time'], 2)
            : 0;

        return [
            'total' => $this->stats['total'],
            'imported' => $this->stats['imported'],
            'updated' => $this->stats['updated'],
            'duplicates' => $this->stats['duplicates'],
            'errors' => $this->stats['errors'],
            'success_rate' => $this->stats['total'] > 0
                ? round((($this->stats['imported'] + $this->stats['updated']) / $this->stats['total']) * 100, 2)
                : 0,
            'execution_time' => $executionTime,
            'log' => $this->stats['log']
        ];
    }

    /**
     * Вывести отчет в консоль
     */
    public function printReport(): void
    {
        $report = $this->getReport();

        echo str_repeat('=', 60) . "\n";
        echo "ОТЧЕТ ОБ ИМПОРТЕ КОНТАКТОВ\n";
        echo str_repeat('=', 60) . "\n";
        echo "Всего записей в CSV: {$report['total']}\n";
        echo "Импортировано новых: {$report['imported']}\n";
        echo "Обновлено существующих: {$report['updated']}\n";
        echo "Пропущено дублей: {$report['duplicates']}\n";
        echo "Ошибок: {$report['errors']}\n";
        echo "Процент успеха: {$report['success_rate']}%\n";
        echo "Время выполнения: {$report['execution_time']} сек\n";

        if (!empty($report['log'])) {
            echo "\nЛог операций:\n";
            foreach ($report['log'] as $entry) {
                echo "  [{$entry['time']}] {$entry['message']}\n";
            }
        }

        echo str_repeat('=', 60) . "\n";
    }
}
