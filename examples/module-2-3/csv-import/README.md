# Импорт элементов из CSV в инфоблок

Скрипт для массового импорта товаров из CSV-файла в инфоблок Bitrix с валидацией данных, обработкой ошибок и поддержкой обновления существующих элементов.

## Возможности

- ✅ Импорт товаров из CSV-файла
- ✅ Валидация данных перед добавлением
- ✅ Обновление существующих товаров по артикулу
- ✅ Обработка ошибок для каждой строки
- ✅ Автоматическая генерация символьных кодов
- ✅ Поддержка различных кодировок
- ✅ Детальная статистика импорта
- ✅ Транслитерация названий в URL

## Структура файлов

```
csv-import/
├── import.php        # Класс импортера и пример использования
├── products.csv      # Пример CSV-файла с товарами
└── README.md         # Документация (этот файл)
```

## Формат CSV-файла

CSV-файл должен содержать следующие колонки (с заголовком):

```csv
Название;Артикул;Цена;Описание
```

### Описание полей

| Поле | Обязательное | Тип | Описание |
|------|--------------|-----|----------|
| Название | Да | Строка | Название товара |
| Артикул | Да | Строка | Уникальный артикул (для поиска дубликатов) |
| Цена | Да | Число | Цена товара (только цифры) |
| Описание | Нет | Строка | Описание товара |

### Пример CSV

```csv
Название;Артикул;Цена;Описание
Ноутбук ASUS;ASUS-001;45990;Ноутбук с процессором Intel Core i5
Смартфон Samsung;SAM-002;32990;Смартфон с экраном 6.5 дюймов
Наушники Sony;SONY-003;5990;Беспроводные наушники
```

## Установка

### 1. Копирование файлов

Скопируйте `import.php` в директорию скриптов:

```
/local/scripts/import.php
```

### 2. Подготовка CSV-файла

Загрузите CSV-файл в директорию `/upload/`:

```
/upload/products.csv
```

### 3. Настройка инфоблока

Убедитесь, что в инфоблоке созданы свойства:
- **ARTICLE** (Строка) — артикул товара
- **PRICE** (Число) — цена товара

## Использование

### Базовый пример

```php
<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

// Проверка прав администратора
global $USER;
if (!$USER->IsAdmin()) {
    die('Доступ запрещен');
}

// Настройки
$iblockId = 1; // ID инфоблока
$csvFilePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/products.csv';

// Создание импортера
$importer = new CsvProductImporter($iblockId);

// Запуск импорта
$result = $importer->import($csvFilePath, [
    'delimiter' => ';',              // Разделитель полей
    'skip_first_row' => true,        // Пропустить заголовок
    'update_existing' => true,       // Обновлять существующие
    'encoding' => 'UTF-8'            // Кодировка файла
]);

// Вывод результатов
echo "Импортировано: {$result['success']}\n";
echo "Пропущено: {$result['skipped']}\n";
echo "Ошибок: " . count($result['errors']) . "\n";
?>
```

### Импорт с обновлением существующих товаров

```php
$result = $importer->import($csvFilePath, [
    'update_existing' => true  // Обновит товары с существующими артикулами
]);
```

### Импорт только новых товаров

```php
$result = $importer->import($csvFilePath, [
    'update_existing' => false  // Пропустит существующие артикулы
]);
```

### Импорт файла в кодировке Windows-1251

```php
$result = $importer->import($csvFilePath, [
    'encoding' => 'Windows-1251'  // Автоматически конвертирует в UTF-8
]);
```

### Импорт с другим разделителем

```php
$result = $importer->import($csvFilePath, [
    'delimiter' => ','  // Запятая вместо точки с запятой
]);
```

## Запуск импорта

### Через браузер

1. Откройте в браузере: `https://ваш-сайт.ru/local/scripts/import.php`
2. Импорт выполнится автоматически
3. Результат отобразится на странице

### Через CLI (cron)

```bash
php /path/to/site/local/scripts/import.php
```

### Через агента Bitrix

```php
// Добавление агента для регулярного импорта
CAgent::AddAgent(
    "importProductsFromCsv();",
    "",
    "N",
    86400,  // Каждые 24 часа
    "",
    "Y",
    ""
);

function importProductsFromCsv() {
    $importer = new CsvProductImporter(1);
    $importer->import($_SERVER['DOCUMENT_ROOT'] . '/upload/products.csv');
    return "importProductsFromCsv();";
}
```

## Ключевые особенности кода

### 1. Валидация данных

```php
private function validateRow($data)
{
    $name = trim($data[0]);
    $article = trim($data[1]);
    $price = trim($data[2]);

    // Проверка обязательных полей
    if (empty($name)) {
        throw new \Exception('Название не может быть пустым');
    }

    if (!is_numeric($price) || $price < 0) {
        throw new \Exception('Некорректная цена');
    }

    return [
        'NAME' => $name,
        'ARTICLE' => $article,
        'PRICE' => floatval($price)
    ];
}
```

### 2. Поиск дубликатов по артикулу

```php
private function findElementByArticle($article)
{
    $element = \CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $this->iblockId,
            'PROPERTY_ARTICLE' => $article  // Поиск по свойству
        ],
        false,
        ['nTopCount' => 1],
        ['ID', 'NAME']
    )->Fetch();

    return $element ?: null;
}
```

### 3. Генерация символьного кода

```php
private function generateCode($name, $article)
{
    // Транслитерация русских букв
    $code = \CUtil::translit($name, 'ru', [
        'replace_space' => '-',
        'replace_other' => '-'
    ]);

    $code = strtolower($code);
    $code = preg_replace('/-+/', '-', $code);
    
    // Добавляем артикул для уникальности
    return $code . '-' . strtolower($article);
}

// Пример: "Ноутбук ASUS" + "ASUS-001" → "noutbuk-asus-asus-001"
```

### 4. Обработка ошибок для каждой строки

```php
while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
    try {
        $this->processRow($data, $updateExisting);
    } catch (\Exception $e) {
        // Ошибка в одной строке не прерывает весь импорт
        $this->addError($rowNumber, $e->getMessage());
    }
}
```

### 5. Статистика импорта

```php
return [
    'success' => $this->successCount,    // Успешно импортировано
    'skipped' => $this->skipCount,       // Пропущено
    'errors' => $this->errors,           // Массив ошибок
    'total' => $totalRows                // Всего обработано
];
```

## Результат импорта

### Структура ответа

```php
[
    'success' => 8,      // Успешно добавлено/обновлено
    'skipped' => 2,      // Пропущено (существующие при update_existing=false)
    'total' => 10,       // Всего строк обработано
    'errors' => [        // Массив ошибок
        [
            'row' => 5,
            'message' => 'Некорректная цена: abc'
        ]
    ]
]
```

### Пример вывода

```
Результаты импорта
------------------
Всего обработано строк: 10
Успешно импортировано: 8
Пропущено: 1
Ошибок: 1

Ошибки:
- Строка 5: Некорректная цена: abc
```

## Расширение функционала

### Добавление новых полей

1. **Добавьте колонку в CSV:**

```csv
Название;Артикул;Цена;Описание;Бренд;Вес
```

2. **Обновите метод validateRow():**

```php
private function validateRow($data)
{
    // ... существующая валидация ...
    
    $brand = isset($data[4]) ? trim($data[4]) : '';
    $weight = isset($data[5]) ? floatval($data[5]) : 0;

    return [
        'NAME' => $name,
        'ARTICLE' => $article,
        'PRICE' => $price,
        'DESCRIPTION' => $description,
        'BRAND' => $brand,
        'WEIGHT' => $weight
    ];
}
```

3. **Обновите методы addElement() и updateElement():**

```php
'PROPERTY_VALUES' => [
    'ARTICLE' => $data['ARTICLE'],
    'PRICE' => $data['PRICE'],
    'BRAND' => $data['BRAND'],    // Новое
    'WEIGHT' => $data['WEIGHT']   // Новое
]
```

### Импорт изображений

```php
// В CSV добавьте колонку с путем к изображению
$imagePath = $_SERVER['DOCUMENT_ROOT'] . $data['IMAGE_PATH'];

if (file_exists($imagePath)) {
    $fields['PREVIEW_PICTURE'] = CFile::MakeFileArray($imagePath);
}
```

### Логирование в файл

```php
private function logError($rowNumber, $message)
{
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/import_log.txt';
    $logMessage = date('Y-m-d H:i:s') . " | Строка {$rowNumber}: {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
```

### Пакетная обработка больших файлов

```php
public function importBatch($filePath, $batchSize = 100)
{
    $handle = fopen($filePath, 'r');
    $batch = [];
    
    while (($data = fgetcsv($handle, 0, ';')) !== false) {
        $batch[] = $data;
        
        if (count($batch) >= $batchSize) {
            $this->processBatch($batch);
            $batch = [];
            sleep(1); // Пауза между пакетами
        }
    }
    
    if (!empty($batch)) {
        $this->processBatch($batch);
    }
    
    fclose($handle);
}
```

## Оптимизация производительности

### 1. Отключение событий при массовом импорте

```php
// Перед импортом
$GLOBALS["DISABLE_EVENTS"] = true;

// Импорт
$importer->import($csvFilePath);

// После импорта
$GLOBALS["DISABLE_EVENTS"] = false;
```

### 2. Использование транзакций

```php
global $DB;
$DB->StartTransaction();

try {
    $importer->import($csvFilePath);
    $DB->Commit();
} catch (\Exception $e) {
    $DB->Rollback();
    throw $e;
}
```

### 3. Кеширование данных инфоблока

```php
private $propertyCache = [];

private function getPropertyId($code)
{
    if (!isset($this->propertyCache[$code])) {
        // Загружаем свойства один раз
        $this->propertyCache = $this->loadProperties();
    }
    
    return $this->propertyCache[$code];
}
```

## Частые ошибки

### ❌ Неправильная кодировка файла

**Проблема:** Кириллица отображается как "??????"

**Решение:**
```php
$result = $importer->import($csvFilePath, [
    'encoding' => 'Windows-1251'  // или CP1251
]);
```

### ❌ Неправильный разделитель

**Проблема:** Все данные попадают в одну колонку

**Решение:** Проверьте разделитель в CSV и укажите правильный:
```php
'delimiter' => ','  // или ';' или '\t'
```

### ❌ Дублирование товаров

**Проблема:** При каждом запуске создаются дубликаты

**Решение:** Включите обновление существующих:
```php
'update_existing' => true
```

### ❌ Превышено время выполнения скрипта

**Проблема:** `Maximum execution time exceeded`

**Решение:**
```php
set_time_limit(0);  // Отключить лимит времени
ini_set('memory_limit', '512M');  // Увеличить лимит памяти
```

## Безопасность

### Ограничение доступа

```php
// Только для администраторов
global $USER;
if (!$USER->IsAdmin()) {
    die('Доступ запрещен');
}
```

### Валидация пути к файлу

```php
// Проверка, что файл находится в разрешенной директории
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/';
if (strpos(realpath($csvFilePath), realpath($uploadDir)) !== 0) {
    throw new \Exception('Недопустимый путь к файлу');
}
```

### Очистка входных данных

```php
$name = htmlspecialchars(trim($data[0]), ENT_QUOTES);
$description = strip_tags(trim($data[3]));
```

## Тестирование

### Тестовый CSV с ошибками

```csv
Название;Артикул;Цена;Описание
Товар 1;ART-001;1000;Нормальный товар
;ART-002;2000;Пустое название - ошибка
Товар 3;;3000;Пустой артикул - ошибка
Товар 4;ART-004;abc;Некорректная цена - ошибка
Товар 5;ART-005;5000;Нормальный товар
```

Ожидаемый результат:
- Успешно: 2
- Ошибок: 3

## Что изучается в этом примере

1. ✅ Чтение и парсинг CSV-файлов
2. ✅ Валидация данных перед добавлением
3. ✅ Работа с CIBlockElement (Add, Update, GetList)
4. ✅ Поиск элементов по свойствам
5. ✅ Обработка ошибок с продолжением импорта
6. ✅ Транслитерация и генерация символьных кодов
7. ✅ Конвертация кодировок файлов
8. ✅ Статистика и логирование операций
9. ✅ Работа с файловой системой PHP

## Полезные ссылки

- [Работа с CSV в PHP](https://www.php.net/manual/ru/function.fgetcsv.php)
- [CIBlockElement::Add](https://dev.1c-bitrix.ru/api_help/iblock/classes/ciblockelement/add.php)
- [CUtil::translit](https://dev.1c-bitrix.ru/api_help/main/reference/cutil/translit.php)
- [Работа с файлами в Bitrix](https://dev.1c-bitrix.ru/api_help/main/reference/cfile/index.php)

---

**Автор:** Учебный курс подготовки Bitrix-разработчика  
**Модуль:** 2.3 — Инфоблоки и их API  
**Сложность:** Средняя
