<?php
/**
 * Скрипт импорта товаров из CSV-файла в инфоблок
 *
 * Использование:
 * 1. Разместить файл в /local/scripts/import.php
 * 2. Подготовить CSV-файл с товарами
 * 3. Запустить через браузер или CLI
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Iblock\IblockTable;

// Проверка авторизации (только для администраторов)
global $USER;
if (!$USER->IsAdmin()) {
    die('Доступ запрещен');
}

/**
 * Класс для импорта товаров из CSV
 */
class CsvProductImporter
{
    private $iblockId;
    private $errors = [];
    private $successCount = 0;
    private $skipCount = 0;

    /**
     * Конструктор
     * @param int $iblockId ID инфоблока для импорта
     */
    public function __construct($iblockId)
    {
        $this->iblockId = $iblockId;

        if (!Loader::includeModule('iblock')) {
            throw new \Exception('Модуль iblock не установлен');
        }
    }

    /**
     * Импорт товаров из CSV-файла
     * @param string $filePath Путь к CSV-файлу
     * @param array $options Опции импорта
     * @return array Результат импорта
     */
    public function import($filePath, $options = [])
    {
        // Настройки по умолчанию
        $options = array_merge([
            'delimiter' => ';',           // Разделитель полей
            'enclosure' => '"',           // Обрамление текста
            'skip_first_row' => true,     // Пропустить заголовок
            'update_existing' => true,    // Обновлять существующие товары
            'encoding' => 'UTF-8'         // Кодировка файла
        ], $options);

        // Проверка файла
        if (!file_exists($filePath)) {
            throw new \Exception('Файл не найден: ' . $filePath);
        }

        // Конвертация кодировки если нужно
        if ($options['encoding'] !== 'UTF-8') {
            $this->convertFileEncoding($filePath, $options['encoding']);
        }

        // Открытие файла
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Не удалось открыть файл');
        }

        $rowNumber = 0;

        // Чтение файла построчно
        while (($data = fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'])) !== false) {
            $rowNumber++;

            // Пропускаем заголовок
            if ($rowNumber === 1 && $options['skip_first_row']) {
                continue;
            }

            // Обработка строки
            try {
                $this->processRow($data, $options['update_existing']);
            } catch (\Exception $e) {
                $this->addError($rowNumber, $e->getMessage());
            }
        }

        fclose($handle);

        return [
            'success' => $this->successCount,
            'skipped' => $this->skipCount,
            'errors' => $this->errors,
            'total' => $rowNumber - ($options['skip_first_row'] ? 1 : 0)
        ];
    }

    /**
     * Обработка одной строки CSV
     * @param array $data Данные строки
     * @param bool $updateExisting Обновлять существующие элементы
     */
    private function processRow($data, $updateExisting)
    {
        // Валидация данных
        $productData = $this->validateRow($data);

        if (!$productData) {
            $this->skipCount++;
            return;
        }

        // Проверка существования товара по артикулу
        $existingElement = $this->findElementByArticle($productData['ARTICLE']);

        if ($existingElement) {
            if ($updateExisting) {
                // Обновление существующего товара
                $this->updateElement($existingElement['ID'], $productData);
                $this->successCount++;
            } else {
                // Пропускаем, если обновление отключено
                $this->skipCount++;
            }
        } else {
            // Добавление нового товара
            $this->addElement($productData);
            $this->successCount++;
        }
    }

    /**
     * Валидация данных строки
     * @param array $data Данные строки [Название, Артикул, Цена, Описание]
     * @return array|null Валидированные данные или null
     */
    private function validateRow($data)
    {
        // Проверка количества полей (минимум 3: название, артикул, цена)
        if (count($data) < 3) {
            throw new \Exception('Недостаточно данных в строке');
        }

        $name = trim($data[0]);
        $article = trim($data[1]);
        $price = trim($data[2]);
        $description = isset($data[3]) ? trim($data[3]) : '';

        // Валидация обязательных полей
        if (empty($name)) {
            throw new \Exception('Название товара не может быть пустым');
        }

        if (empty($article)) {
            throw new \Exception('Артикул товара не может быть пустым');
        }

        if (!is_numeric($price) || $price < 0) {
            throw new \Exception('Некорректная цена: ' . $price);
        }

        // Формирование символьного кода из названия
        $code = $this->generateCode($name, $article);

        return [
            'NAME' => $name,
            'ARTICLE' => $article,
            'PRICE' => floatval($price),
            'DESCRIPTION' => $description,
            'CODE' => $code
        ];
    }

    /**
     * Поиск элемента по артикулу
     * @param string $article Артикул
     * @return array|null Элемент или null
     */
    private function findElementByArticle($article)
    {
        $element = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->iblockId,
                'PROPERTY_ARTICLE' => $article
            ],
            false,
            ['nTopCount' => 1],
            ['ID', 'NAME']
        )->Fetch();

        return $element ?: null;
    }

    /**
     * Добавление нового элемента
     * @param array $data Данные товара
     * @return int ID созданного элемента
     */
    private function addElement($data)
    {
        $el = new \CIBlockElement;

        $fields = [
            'IBLOCK_ID' => $this->iblockId,
            'NAME' => $data['NAME'],
            'CODE' => $data['CODE'],
            'ACTIVE' => 'Y',
            'PREVIEW_TEXT' => $data['DESCRIPTION'],
            'DETAIL_TEXT' => $data['DESCRIPTION'],
            'PROPERTY_VALUES' => [
                'ARTICLE' => $data['ARTICLE'],
                'PRICE' => $data['PRICE']
            ]
        ];

        $elementId = $el->Add($fields);

        if (!$elementId) {
            throw new \Exception('Ошибка добавления: ' . $el->LAST_ERROR);
        }

        return $elementId;
    }

    /**
     * Обновление существующего элемента
     * @param int $elementId ID элемента
     * @param array $data Новые данные
     */
    private function updateElement($elementId, $data)
    {
        $el = new \CIBlockElement;

        $fields = [
            'NAME' => $data['NAME'],
            'CODE' => $data['CODE'],
            'PREVIEW_TEXT' => $data['DESCRIPTION'],
            'DETAIL_TEXT' => $data['DESCRIPTION'],
            'PROPERTY_VALUES' => [
                'ARTICLE' => $data['ARTICLE'],
                'PRICE' => $data['PRICE']
            ]
        ];

        $success = $el->Update($elementId, $fields);

        if (!$success) {
            throw new \Exception('Ошибка обновления: ' . $el->LAST_ERROR);
        }
    }

    /**
     * Генерация символьного кода из названия
     * @param string $name Название
     * @param string $article Артикул (для уникальности)
     * @return string Символьный код
     */
    private function generateCode($name, $article)
    {
        // Транслитерация
        $code = \CUtil::translit($name, 'ru', [
            'replace_space' => '-',
            'replace_other' => '-'
        ]);

        // Приведение к нижнему регистру
        $code = strtolower($code);

        // Удаление повторяющихся дефисов
        $code = preg_replace('/-+/', '-', $code);

        // Добавляем артикул для уникальности
        $code = $code . '-' . strtolower($article);

        return $code;
    }

    /**
     * Конвертация кодировки файла
     * @param string $filePath Путь к файлу
     * @param string $fromEncoding Исходная кодировка
     */
    private function convertFileEncoding($filePath, $fromEncoding)
    {
        $content = file_get_contents($filePath);
        $content = mb_convert_encoding($content, 'UTF-8', $fromEncoding);
        file_put_contents($filePath, $content);
    }

    /**
     * Добавление ошибки
     * @param int $rowNumber Номер строки
     * @param string $message Сообщение об ошибке
     */
    private function addError($rowNumber, $message)
    {
        $this->errors[] = [
            'row' => $rowNumber,
            'message' => $message
        ];
    }

    /**
     * Получение списка ошибок
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}

// ============================================================================
// Пример использования
// ============================================================================

try {
    // Настройки импорта
    $iblockId = 1; // ID инфоблока товаров
    $csvFilePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/products.csv';

    // Создание экземпляра импортера
    $importer = new CsvProductImporter($iblockId);

    // Запуск импорта
    $result = $importer->import($csvFilePath, [
        'delimiter' => ';',
        'skip_first_row' => true,
        'update_existing' => true,
        'encoding' => 'UTF-8'
    ]);

    // Вывод результатов
    echo '<h2>Результаты импорта</h2>';
    echo '<p><strong>Всего обработано строк:</strong> ' . $result['total'] . '</p>';
    echo '<p><strong>Успешно импортировано:</strong> ' . $result['success'] . '</p>';
    echo '<p><strong>Пропущено:</strong> ' . $result['skipped'] . '</p>';
    echo '<p><strong>Ошибок:</strong> ' . count($result['errors']) . '</p>';

    // Вывод ошибок
    if (!empty($result['errors'])) {
        echo '<h3>Ошибки:</h3>';
        echo '<ul>';
        foreach ($result['errors'] as $error) {
            echo '<li>Строка ' . $error['row'] . ': ' . htmlspecialchars($error['message']) . '</li>';
        }
        echo '</ul>';
    }

} catch (\Exception $e) {
    echo '<h2>Критическая ошибка</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
