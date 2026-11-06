<?php
/**
 * Задача 1: Helper-класс с автозагрузкой
 *
 * Утилиты для работы со строками: транслитерация, генерация ЧПУ,
 * обрезка текста, очистка ввода и экранирование вывода.
 *
 * Namespace: MyCompany\Helpers
 * Файл: /local/php_interface/classes/Helpers/StringHelper.php
 */

namespace MyCompany\Helpers;

class StringHelper
{
    /**
     * Транслитерация кириллицы в латиницу.
     *
     * @param string $text    Исходный текст
     * @param bool   $toLower Приводить к нижнему регистру
     */
    public static function transliterate(string $text, bool $toLower = true): string
    {
        static $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ];

        $result = strtr($text, $translitMap);

        return $toLower ? mb_strtolower($result) : $result;
    }

    /**
     * Генерация символьного кода (slug) для ЧПУ.
     *
     * Возвращает пустую строку, если из текста нельзя собрать валидный код —
     * вызывающий код должен обработать этот случай (задать fallback).
     *
     * @param string $text      Исходный текст (название товара, новости)
     * @param int    $maxLength Максимальная длина slug (0 — без ограничения)
     * @param string $fallback  Значение, если slug получился пустым (например 'item')
     * @return string slug в нижнем регистре, дефисы вместо разделителей
     */
    public static function generateCode(
        string $text,
        int $maxLength = 100,
        string $fallback = ''
    ): string {
        $code = self::transliterate($text, true);

        // Все небуквенно-цифровые символы — в дефис, схлопываем повторы
        $code = preg_replace('/[^a-z0-9]+/', '-', $code);
        $code = trim($code, '-');

        if ($code === '') {
            return $fallback;
        }

        if ($maxLength > 0 && mb_strlen($code) > $maxLength) {
            // Обрезаем по последнему дефису, чтобы не рвать слово
            $code = mb_substr($code, 0, $maxLength);
            $lastDash = mb_strrpos($code, '-');
            if ($lastDash !== false && $lastDash > 0) {
                $code = mb_substr($code, 0, $lastDash);
            }
        }

        return $code;
    }

    /**
     * Генерация уникального slug на основе уже занятых значений.
     *
     * Типичный кейс: CODE элементов инфоблока должен быть уникальным.
     * Проверяет $checkExists и добавляет -2, -3, ... пока код не освободится.
     *
     * @param string   $text        Исходный текст
     * @param callable $checkExists fn(string $code): bool — true, если код занят
     * @param int      $maxLength
     * @param int      $maxAttempts Лимит итераций, чтобы не уйти в бесконечный цикл
     */
    public static function generateUniqueCode(
        string $text,
        callable $checkExists,
        int $maxLength = 100,
        int $maxAttempts = 50
    ): string {
        $base = self::generateCode($text, $maxLength, 'item');
        $code = $base;

        for ($i = 2; $i <= $maxAttempts && $checkExists($code); $i++) {
            $suffix = '-' . $i;
            $code = mb_substr($base, 0, $maxLength - mb_strlen($suffix)) . $suffix;
        }

        return $code;
    }

    /**
     * Обрезка текста до длины без разрыва слов.
     */
    public static function truncate(string $text, int $length = 100, string $ending = '…'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . $ending;
    }

    /**
     * Очистка пользовательского ввода для ХРАНЕНИЯ.
     *
     * Убирает HTML-теги, управляющие символы, лишние пробелы.
     * НЕ экранирует — экранирование выполняется на выводе через escape().
     *
     * @param string $text Вход из формы/API
     * @return string Чистый текст, безопасный для записи в БД
     */
    public static function sanitize(string $text): string
    {
        // Декодируем сущности, чтобы strip_tags не пропустил &lt;script&gt;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        // Управляющие символы кроме \n и \t
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        // Схлопываем повторные пробелы
        $text = preg_replace('/\s{2,}/u', ' ', $text);

        return trim($text);
    }

    /**
     * Экранирование строки для вывода в HTML.
     *
     * Используем API Bitrix — он корректно обрабатывает UTF-8 и двойное
     * экранирование. Применять на КАЖДОМ выводе пользовательских данных.
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Очистка + экранирование за один шаг — удобно для вывода в шаблоне.
     *
     * @param string $text Пользовательский ввод
     * @return string Безопасная HTML-строка
     */
    public static function sanitizeForOutput(string $text): string
    {
        return self::escape(self::sanitize($text));
    }

    /**
     * Склонение существительных после числительных: 1 товар, 2 товара, 5 товаров.
     *
     * @param int    $count  Число
     * @param array  $forms  [1 => 'товар', 2 => 'товара', 5 => 'товаров']
     */
    public static function plural(int $count, array $forms): string
    {
        $count = abs($count) % 100;
        $rest = $count % 10;

        if ($count > 10 && $count < 20) {
            return $forms[2];
        }
        if ($rest > 1 && $rest < 5) {
            return $forms[1];
        }
        if ($rest === 1) {
            return $forms[0];
        }

        return $forms[2];
    }
}
