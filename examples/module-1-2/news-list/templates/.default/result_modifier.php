<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * result_modifier.php - модификация результата перед выводом
 *
 * Вызывается ПОСЛЕ component.php, но ДО template.php
 * Используется для:
 * - Дополнительной обработки данных
 * - Добавления новых полей в $arResult
 * - Модификации существующих данных
 *
 * ВАЖНО: Этот файл выполняется ВНУТРИ кеша
 * Не делайте здесь запросы к БД или тяжелые вычисления
 */

/** @var array $arResult */
/** @var array $arParams */
/** @var CBitrixComponentTemplate $this */

// Пример: добавление дополнительной информации к каждому элементу
foreach ($arResult["ITEMS"] as &$arItem) {

    // Добавляем CSS-класс в зависимости от наличия картинки
    $arItem["CSS_CLASS"] = !empty($arItem["PREVIEW_PICTURE"])
        ? "news-item--with-image"
        : "news-item--no-image";

    // Добавляем относительное время ("2 часа назад")
    if (!empty($arItem["ACTIVE_FROM"])) {
        $timestamp = MakeTimeStamp($arItem["ACTIVE_FROM"]);
        $arItem["RELATIVE_TIME"] = getRelativeTime($timestamp);
    }

    // Подсчет количества слов в анонсе
    if (!empty($arItem["PREVIEW_TEXT"])) {
        $arItem["WORDS_COUNT"] = str_word_count(strip_tags($arItem["PREVIEW_TEXT"]));
        $arItem["READ_TIME"] = ceil($arItem["WORDS_COUNT"] / 200); // ~200 слов в минуту
    }
}
unset($arItem);

/**
 * Функция для получения относительного времени
 * @param int $timestamp
 * @return string
 */
function getRelativeTime($timestamp)
{
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "только что";
    }

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return declension($minutes, ["минуту", "минуты", "минут"]) . " назад";
    }

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return declension($hours, ["час", "часа", "часов"]) . " назад";
    }

    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return declension($days, ["день", "дня", "дней"]) . " назад";
    }

    return FormatDate("d F Y", $timestamp);
}

/**
 * Функция склонения числительных
 * @param int $number
 * @param array $forms ["час", "часа", "часов"]
 * @return string
 */
function declension($number, $forms)
{
    $number = abs($number) % 100;
    $n1 = $number % 10;

    if ($number > 10 && $number < 20) {
        return $number . ' ' . $forms[2];
    }

    if ($n1 > 1 && $n1 < 5) {
        return $number . ' ' . $forms[1];
    }

    if ($n1 == 1) {
        return $number . ' ' . $forms[0];
    }

    return $number . ' ' . $forms[2];
}

// Добавляем мета-информацию для SEO (если это первая страница)
if (empty($_REQUEST["PAGEN_1"])) {
    global $APPLICATION;

    if (!empty($arResult["ITEMS"])) {
        // Можно установить дополнительные мета-теги
        // $APPLICATION->SetPageProperty("description", "...");
    }
}
