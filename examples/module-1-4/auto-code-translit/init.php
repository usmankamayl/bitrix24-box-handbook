<?php
/**
 * Автоматическое заполнение кода элемента транслитом названия
 *
 * Этот файл нужно подключить в /local/php_interface/init.php:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/auto-code-translit/init.php';
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;

$eventManager = EventManager::getInstance();

// Регистрируем обработчики для элементов инфоблоков
$eventManager->addEventHandler(
    'iblock',
    'OnBeforeIBlockElementAdd',
    ['AutoCodeTranslit', 'onBeforeElementAdd']
);

$eventManager->addEventHandler(
    'iblock',
    'OnBeforeIBlockElementUpdate',
    ['AutoCodeTranslit', 'onBeforeElementUpdate']
);

/**
 * Класс для автоматической генерации символьного кода элементов
 */
class AutoCodeTranslit
{
    /**
     * Обработчик добавления элемента
     *
     * @param array &$arFields Массив полей элемента (передаётся по ссылке)
     * @return void
     */
    public static function onBeforeElementAdd(&$arFields)
    {
        self::generateCode($arFields);
    }

    /**
     * Обработчик обновления элемента
     *
     * @param array &$arFields Массив полей элемента (передаётся по ссылке)
     * @return void
     */
    public static function onBeforeElementUpdate(&$arFields)
    {
        self::generateCode($arFields);
    }

    /**
     * Генерирует символьный код из названия элемента
     *
     * @param array &$arFields Массив полей элемента
     * @return void
     */
    private static function generateCode(&$arFields)
    {
        // Проверяем: если CODE не задан или пустой, но есть NAME
        if ((empty($arFields['CODE']) || trim($arFields['CODE']) === '')
            && !empty($arFields['NAME']))
        {
            // Генерируем код из названия
            $code = \Cutil::translit(
                $arFields['NAME'],
                'ru',
                [
                    'max_len' => 100,           // Максимальная длина кода
                    'change_case' => 'L',       // Нижний регистр (L = Lower)
                    'replace_space' => '-',     // Заменяем пробелы на дефисы
                    'replace_other' => '-',     // Другие символы тоже на дефисы
                    'delete_repeat_replace' => true,  // Удаляем повторяющиеся дефисы
                    'use_google' => false,      // Не используем Google Transliteration
                ]
            );

            // Проверяем уникальность кода
            $code = self::makeUniqueCode($code, $arFields);

            // Устанавливаем сгенерированный код
            $arFields['CODE'] = $code;
        }
    }

    /**
     * Проверяет уникальность кода и добавляет суффикс при необходимости
     *
     * @param string $code Исходный код
     * @param array $arFields Массив полей элемента
     * @return string Уникальный код
     */
    private static function makeUniqueCode($code, $arFields)
    {
        if (empty($arFields['IBLOCK_ID'])) {
            return $code;
        }

        \CModule::IncludeModule('iblock');

        $originalCode = $code;
        $counter = 1;

        // Цикл проверки уникальности
        while (true) {
            // Формируем фильтр для проверки
            $filter = [
                'IBLOCK_ID' => $arFields['IBLOCK_ID'],
                'CODE' => $code,
            ];

            // При обновлении исключаем текущий элемент
            if (!empty($arFields['ID'])) {
                $filter['!ID'] = $arFields['ID'];
            }

            // Проверяем наличие элемента с таким кодом
            $existElement = \CIBlockElement::GetList(
                [],
                $filter,
                false,
                ['nTopCount' => 1],
                ['ID', 'CODE']
            )->Fetch();

            // Если элемент не найден — код уникален
            if (!$existElement) {
                break;
            }

            // Добавляем суффикс и пробуем снова
            $code = $originalCode . '-' . $counter;
            $counter++;

            // Защита от бесконечного цикла
            if ($counter > 1000) {
                $code = $originalCode . '-' . time();
                break;
            }
        }

        return $code;
    }
}
