<?php
/**
 * MyCompany\Tools\StringHelper
 *
 * Задача 3: класс модуля. Обратите внимание на две вещи:
 *
 * 1. Файл называется stringhelper.php — в нижнем регистре. Loader приводит
 *    путь к нижнему регистру, поэтому lib/Tools/StringHelper.php на Linux не
 *    найдётся. Это конвенция D7, а не случайность.
 * 2. Namespace из двух первых сегментов совпадает с ID модуля:
 *    MyCompany\Tools → mycompany.tools. Регистрация не нужна вообще.
 */

namespace MyCompany\Tools;

class StringHelper
{
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function transliterate(string $text): string
    {
        return strtr(mb_strtolower($text, 'UTF-8'), self::TRANSLIT);
    }

    /**
     * Slug для ЧПУ. Пустая строка означает «сгенерировать не удалось» —
     * вызывающий код обязан подставить fallback (например, ID элемента).
     */
    public static function generateCode(string $text, int $maxLength = 100): string
    {
        $code = preg_replace('/[^a-z0-9]+/', '-', self::transliterate($text));
        $code = trim((string)$code, '-');

        if ($code === '' || $maxLength <= 0) {
            return $code;
        }

        if (mb_strlen($code) > $maxLength) {
            $code = mb_substr($code, 0, $maxLength);
            $cut = mb_strrpos($code, '-');
            if ($cut !== false && $cut > 0) {
                $code = mb_substr($code, 0, $cut);
            }
        }

        return $code;
    }
}
