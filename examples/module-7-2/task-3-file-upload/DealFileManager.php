<?php
/**
 * Управление файлами для Сделок в Bitrix24 CRM
 *
 * Этот класс демонстрирует:
 * - Создание пользовательского поля типа "файл"
 * - Кодирование файлов в Base64 для передачи через REST API
 * - Загрузку одного и нескольких файлов
 * - Получение метаданных прикрепленных файлов
 * - Скачивание файлов обратно
 * - Удаление файлов из множественного поля
 */

class DealFileManager
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
     * Создать пользовательское поле для файлов "Договоры"
     *
     * Создает множественное поле типа "file" для хранения договоров и документов.
     *
     * @return array Результат создания поля
     */
    public function createFileField()
    {
        $fieldData = [
            'FIELD_NAME' => 'UF_CRM_DEAL_CONTRACTS',  // Имя поля
            'USER_TYPE_ID' => 'file',                  // Тип: файл
            'EDIT_FORM_LABEL' => 'Договоры',          // Название
            'LIST_COLUMN_LABEL' => 'Договоры',
            'MULTIPLE' => 'Y',  // Y - можно загружать несколько файлов
            'MANDATORY' => 'N'  // N - необязательное поле
        ];

        return $this->callApi('crm.deal.userfield.add', ['fields' => $fieldData]);
    }

    /**
     * Загрузить один файл к сделке
     *
     * @param int $dealId ID сделки
     * @param string $fileName Имя файла (например, "contract.pdf")
     * @param string $filePath Путь к файлу на диске
     * @return array Результат операции
     * @throws Exception если файл не найден или слишком большой
     */
    public function uploadFile($dealId, $fileName, $filePath)
    {
        // Проверяем существование файла
        if (!file_exists($filePath)) {
            throw new Exception("Файл не найден: {$filePath}");
        }

        // Проверяем размер (ограничение ~50 МБ для Base64)
        $fileSize = filesize($filePath);
        $maxSize = 50 * 1024 * 1024;  // 50 МБ
        if ($fileSize > $maxSize) {
            throw new Exception("Файл слишком большой: " . round($fileSize / 1024 / 1024, 2) . " МБ");
        }

        // Читаем файл и кодируем в Base64
        $fileContent = file_get_contents($filePath);
        $base64Content = base64_encode($fileContent);

        // Получаем текущие файлы сделки
        $existingFiles = $this->getFiles($dealId);

        // Добавляем новый файл к существующим
        $existingFiles[] = [$fileName, $base64Content];

        // Обновляем сделку
        return $this->callApi('crm.deal.update', [
            'id' => $dealId,
            'fields' => [
                'UF_CRM_DEAL_CONTRACTS' => $existingFiles
            ]
        ]);
    }

    /**
     * Загрузить несколько файлов одновременно
     *
     * @param int $dealId ID сделки
     * @param array $files Массив: ['имя_файла' => 'путь_к_файлу']
     * @return array Результат операции
     */
    public function uploadMultipleFiles($dealId, array $files)
    {
        // Получаем существующие файлы
        $existingFiles = $this->getFiles($dealId);

        // Кодируем и добавляем новые файлы
        foreach ($files as $fileName => $filePath) {
            if (!file_exists($filePath)) {
                throw new Exception("Файл не найден: {$filePath}");
            }

            $fileContent = file_get_contents($filePath);
            $base64Content = base64_encode($fileContent);

            $existingFiles[] = [$fileName, $base64Content];
        }

        // Обновляем сделку со всеми файлами
        return $this->callApi('crm.deal.update', [
            'id' => $dealId,
            'fields' => [
                'UF_CRM_DEAL_CONTRACTS' => $existingFiles
            ]
        ]);
    }

    /**
     * Получить список файлов сделки
     *
     * Возвращает массив ID файлов, прикрепленных к сделке.
     *
     * @param int $dealId ID сделки
     * @return array Массив ID файлов
     */
    public function getFiles($dealId)
    {
        // Получаем сделку
        $deal = $this->callApi('crm.deal.get', ['id' => $dealId]);

        if (!isset($deal['result'])) {
            throw new Exception("Сделка с ID {$dealId} не найдена");
        }

        // Извлекаем файлы
        $files = $deal['result']['UF_CRM_DEAL_CONTRACTS'] ?? [];

        return $files;
    }

    /**
     * Получить метаданные файла
     *
     * Возвращает информацию о файле: имя, размер, MIME-тип и т.д.
     *
     * @param int $fileId ID файла
     * @return array Метаданные файла
     */
    public function getFileMetadata($fileId)
    {
        $result = $this->callApi('disk.file.get', ['id' => $fileId]);

        if (!isset($result['result'])) {
            throw new Exception("Файл с ID {$fileId} не найден");
        }

        return $result['result'];
    }

    /**
     * Скачать файл
     *
     * Получает файл из Bitrix24 и сохраняет на диск.
     *
     * @param int $fileId ID файла
     * @param string $savePath Путь для сохранения
     * @return bool true в случае успеха
     */
    public function downloadFile($fileId, $savePath)
    {
        // Получаем метаданные файла (включая ссылку на скачивание)
        $metadata = $this->getFileMetadata($fileId);

        if (!isset($metadata['DOWNLOAD_URL'])) {
            throw new Exception("Не удалось получить ссылку на скачивание");
        }

        // Скачиваем файл
        $fileContent = file_get_contents($metadata['DOWNLOAD_URL']);

        if ($fileContent === false) {
            throw new Exception("Не удалось скачать файл");
        }

        // Сохраняем на диск
        $result = file_put_contents($savePath, $fileContent);

        if ($result === false) {
            throw new Exception("Не удалось сохранить файл: {$savePath}");
        }

        return true;
    }

    /**
     * Удалить файл из сделки
     *
     * Удаляет конкретный файл по его ID из множественного поля.
     *
     * @param int $dealId ID сделки
     * @param int $fileId ID файла для удаления
     * @return array Результат операции
     */
    public function deleteFile($dealId, $fileId)
    {
        // Получаем текущие файлы
        $files = $this->getFiles($dealId);

        // Фильтруем: убираем файл с нужным ID
        $found = false;
        $filteredFiles = [];
        foreach ($files as $file) {
            if ($file == $fileId) {
                $found = true;
                continue;  // Пропускаем этот файл
            }
            $filteredFiles[] = $file;
        }

        if (!$found) {
            throw new Exception("Файл с ID {$fileId} не найден у сделки");
        }

        // Обновляем сделку без удаленного файла
        return $this->callApi('crm.deal.update', [
            'id' => $dealId,
            'fields' => [
                'UF_CRM_DEAL_CONTRACTS' => $filteredFiles
            ]
        ]);
    }

    /**
     * Получить размер файла в читаемом формате
     *
     * @param int $bytes Размер в байтах
     * @return string Форматированный размер (например, "5.2 МБ")
     */
    private function formatFileSize($bytes)
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
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
    $manager = new DealFileManager($apiUrl, $accessToken);
    $dealId = 789;  // ID существующей сделки

    // -------------------------------------------------------------------------
    // Пример 1: Создание пользовательского поля для файлов
    // -------------------------------------------------------------------------
    echo "=== Создание поля для файлов ===\n";
    $createFieldResult = $manager->createFileField();
    if (isset($createFieldResult['result'])) {
        echo "✓ Поле 'Договоры' успешно создано. ID: {$createFieldResult['result']}\n\n";
    } else {
        echo "Примечание: поле уже существует или произошла ошибка\n\n";
    }

    // -------------------------------------------------------------------------
    // Пример 2: Загрузка одного файла
    // -------------------------------------------------------------------------
    echo "=== Загрузка одного файла ===\n";

    // Создаем тестовый файл для демонстрации
    $testFilePath = __DIR__ . '/test-contract.txt';
    file_put_contents($testFilePath, "Это тестовый договор\n\nСодержимое документа...\n");

    $uploadResult = $manager->uploadFile($dealId, 'contract.txt', $testFilePath);
    if (isset($uploadResult['result'])) {
        echo "✓ Файл 'contract.txt' успешно загружен\n\n";
    }

    // -------------------------------------------------------------------------
    // Пример 3: Загрузка нескольких файлов
    // -------------------------------------------------------------------------
    echo "=== Загрузка нескольких файлов ===\n";

    // Создаем еще несколько тестовых файлов
    $testFile2 = __DIR__ . '/test-invoice.txt';
    $testFile3 = __DIR__ . '/test-specification.txt';
    file_put_contents($testFile2, "Счет №123\nСумма: 50000 руб.");
    file_put_contents($testFile3, "Техническое задание\n\nПункт 1...\nПункт 2...");

    $multipleFiles = [
        'invoice.txt' => $testFile2,
        'specification.txt' => $testFile3
    ];

    $multiUploadResult = $manager->uploadMultipleFiles($dealId, $multipleFiles);
    if (isset($multiUploadResult['result'])) {
        echo "✓ Загружено файлов: " . count($multipleFiles) . "\n\n";
    }

    // -------------------------------------------------------------------------
    // Пример 4: Получение списка файлов
    // -------------------------------------------------------------------------
    echo "=== Получение списка файлов ===\n";
    $files = $manager->getFiles($dealId);
    echo "Найдено файлов у сделки: " . count($files) . "\n";

    foreach ($files as $fileId) {
        try {
            $metadata = $manager->getFileMetadata($fileId);
            echo "  - Файл ID: {$fileId}\n";
            echo "    Имя: {$metadata['NAME']}\n";
            echo "    Размер: {$metadata['SIZE']} байт\n";
            echo "    Загружен: {$metadata['CREATE_TIME']}\n";
        } catch (Exception $e) {
            echo "  - Файл ID: {$fileId} (метаданные недоступны)\n";
        }
    }
    echo "\n";

    // -------------------------------------------------------------------------
    // Пример 5: Скачивание файла
    // -------------------------------------------------------------------------
    echo "=== Скачивание файла ===\n";
    if (count($files) > 0) {
        $firstFileId = $files[0];
        $downloadPath = __DIR__ . '/downloaded-file.txt';

        try {
            $manager->downloadFile($firstFileId, $downloadPath);
            echo "✓ Файл {$firstFileId} скачан в: {$downloadPath}\n";
            echo "  Размер: " . filesize($downloadPath) . " байт\n\n";
        } catch (Exception $e) {
            echo "✗ Ошибка при скачивании: {$e->getMessage()}\n\n";
        }
    }

    // -------------------------------------------------------------------------
    // Пример 6: Удаление файла
    // -------------------------------------------------------------------------
    echo "=== Удаление файла ===\n";
    $files = $manager->getFiles($dealId);
    if (count($files) > 1) {
        $lastFileId = end($files);
        $deleteResult = $manager->deleteFile($dealId, $lastFileId);
        if (isset($deleteResult['result'])) {
            echo "✓ Файл {$lastFileId} успешно удален\n\n";
        }
    }

    // -------------------------------------------------------------------------
    // Проверка финального состояния
    // -------------------------------------------------------------------------
    echo "=== Финальное состояние ===\n";
    $finalFiles = $manager->getFiles($dealId);
    echo "Осталось файлов у сделки: " . count($finalFiles) . "\n";

    // Очистка тестовых файлов
    @unlink($testFilePath);
    @unlink($testFile2);
    @unlink($testFile3);
    @unlink($downloadPath);

    echo "\n✓ Все операции выполнены успешно!\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
