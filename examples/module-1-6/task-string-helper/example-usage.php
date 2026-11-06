<?php
/**
 * Примеры использования StringHelper.
 * Разместить на тестовой странице, например /test.php
 */

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

use MyCompany\Helpers\StringHelper;

// 1. Транслитерация
echo StringHelper::transliterate("Привет, мир!");  // privet, mir!

// 2. Slug для ЧПУ
echo StringHelper::generateCode("Смартфон Samsung Galaxy S24 Ultra 256GB");
// smartfon-samsung-galaxy-s24-ultra-256gb

// 3. Slug с ограничением длины — обрезка по дефису
echo StringHelper::generateCode("Очень длинное название товара которое не влезает в лимит", 30);

// 4. Уникальный slug с проверкой занятости (например, по элементам инфоблока)
$existsCodes = ['smartfon-samsung', 'smartfon-samsung-2'];
$code = StringHelper::generateUniqueCode(
    "Смартфон Samsung",
    fn(string $c) => in_array($c, $existsCodes, true)
);
// smartfon-samsung-3

// 5. Обрезка текста без разрыва слов
echo StringHelper::truncate(
    "Это очень длинный текст, который нужно обрезать до определенной длины",
    50
);

// 6. sanitize для ХРАНЕНИЯ (в БД) — убирает теги и управляющие символы
$dirty = '<script>alert("XSS")</script>Текст   с   пробелами';
echo StringHelper::sanitize($dirty);        // alert("XSS")Текст с пробелами

// 7. escape для ВЫВОДА — каждый раз при рендере пользовательских данных
echo StringHelper::escape('ООО "Рога и копыта" <b>привет</b>');
// ООО &quot;Рога и копыта&quot; &lt;b&gt;привет&lt;/b&gt;

// 8. sanitize + escape за шаг — готовая строка для шаблона
echo StringHelper::sanitizeForOutput($dirty);
// alert(&quot;XSS&quot;)Текст с пробелами

// 9. Склонение существительных
foreach ([1, 2, 5, 11, 21, 25] as $n) {
    echo "{$n} " . StringHelper::plural($n, ['товар', 'товара', 'товаров']) . "<br>";
}
// 1 товар, 2 товара, 5 товаров, 11 товаров, 21 товар, 25 товаров

// 10. Реальный сценарий — генерация CODE и PREVIEW_TEXT для элемента инфоблока
$title = "1С-Битрикс выпустил новую версию 24.0!";
$arFields = [
    'NAME'         => $title,
    'CODE'         => StringHelper::generateUniqueCode(
        $title,
        fn(string $c) => \CIBlockElement::getList([], ['CODE' => $c], false, false, ['ID'])->fetch() !== false
    ),
    'PREVIEW_TEXT' => StringHelper::sanitize(
        strip_tags($title) // или реальный текст из формы
    ),
];

echo "URL: /catalog/{$arFields['CODE']}/";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
