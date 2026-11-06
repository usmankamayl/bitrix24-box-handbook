<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Application;

/**
 * Класс компонента "Список новостей"
 *
 * Современный подход к созданию компонентов через наследование от CBitrixComponent
 * Преимущества:
 * - Структурированный код
 * - Разделение ответственности между методами
 * - Удобное тестирование
 * - Переопределение методов в наследниках
 */
class CustomNewsListComponent extends CBitrixComponent
{
    /**
     * Проверка необходимых модулей
     *
     * @return bool
     */
    protected function checkModules(): bool
    {
        try {
            // Проверяем наличие модуля инфоблоков
            if (!Loader::includeModule('iblock')) {
                throw new LoaderException('Модуль инфоблоков не установлен');
            }
        } catch (LoaderException $e) {
            // Прерываем кеширование при ошибке
            $this->abortResultCache();
            ShowError($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Подготовка параметров компонента
     *
     * Вызывается автоматически перед executeComponent()
     * Здесь устанавливаем значения по умолчанию и нормализуем параметры
     *
     * @param array $arParams
     * @return array
     */
    public function onPrepareComponentParams($arParams): array
    {
        // ID инфоблока
        $arParams["IBLOCK_ID"] = intval($arParams["IBLOCK_ID"] ?? 0);

        // Количество элементов
        $arParams["COUNT"] = intval($arParams["COUNT"] ?? 10);
        if ($arParams["COUNT"] <= 0) {
            $arParams["COUNT"] = 10;
        }

        // Сортировка
        $arParams["SORT_BY"] = trim($arParams["SORT_BY"] ?? "ACTIVE_FROM");
        $arParams["SORT_ORDER"] = strtoupper(trim($arParams["SORT_ORDER"] ?? "DESC"));

        // Проверяем корректность порядка сортировки
        if (!in_array($arParams["SORT_ORDER"], ["ASC", "DESC"])) {
            $arParams["SORT_ORDER"] = "DESC";
        }

        // Настройки отображения
        $arParams["SHOW_PICTURE"] = ($arParams["SHOW_PICTURE"] ?? "Y") === "Y";
        $arParams["SHOW_DATE"] = ($arParams["SHOW_DATE"] ?? "Y") === "Y";
        $arParams["SHOW_PREVIEW_TEXT"] = ($arParams["SHOW_PREVIEW_TEXT"] ?? "Y") === "Y";
        $arParams["PREVIEW_TEXT_LENGTH"] = intval($arParams["PREVIEW_TEXT_LENGTH"] ?? 200);

        // Время кеширования
        $arParams["CACHE_TIME"] = intval($arParams["CACHE_TIME"] ?? 3600);

        return $arParams;
    }

    /**
     * Получение списка элементов из инфоблока
     *
     * @return array
     */
    protected function getElements(): array
    {
        // Поля для выборки
        $arSelect = ["ID", "NAME", "DETAIL_PAGE_URL"];

        // Добавляем дополнительные поля в зависимости от параметров
        if ($this->arParams["SHOW_PICTURE"]) {
            $arSelect[] = "PREVIEW_PICTURE";
        }

        if ($this->arParams["SHOW_DATE"]) {
            $arSelect[] = "ACTIVE_FROM";
        }

        if ($this->arParams["SHOW_PREVIEW_TEXT"]) {
            $arSelect[] = "PREVIEW_TEXT";
        }

        // Фильтр
        $arFilter = [
            "IBLOCK_ID" => $this->arParams["IBLOCK_ID"],
            "ACTIVE" => "Y",
        ];

        // Сортировка
        $arOrder = [
            $this->arParams["SORT_BY"] => $this->arParams["SORT_ORDER"]
        ];

        // Выполняем запрос к базе данных
        $rsElements = \CIBlockElement::GetList(
            $arOrder,
            $arFilter,
            false,
            ["nTopCount" => $this->arParams["COUNT"]], // Ограничение количества
            $arSelect
        );

        $items = [];

        // Обрабатываем каждый элемент
        while ($arElement = $rsElements->GetNext()) {
            $items[] = $this->formatElement($arElement);
        }

        return $items;
    }

    /**
     * Форматирование данных элемента перед выводом
     *
     * @param array $element
     * @return array
     */
    protected function formatElement(array $element): array
    {
        // Обработка изображения
        if ($this->arParams["SHOW_PICTURE"] && $element["PREVIEW_PICTURE"]) {
            $element["PREVIEW_PICTURE"] = \CFile::GetFileArray($element["PREVIEW_PICTURE"]);

            // Можно сделать ресайз изображения
            // $element["PREVIEW_PICTURE_RESIZED"] = CFile::ResizeImageGet(
            //     $element["PREVIEW_PICTURE"],
            //     ["width" => 300, "height" => 200],
            //     BX_RESIZE_IMAGE_PROPORTIONAL
            // );
        }

        // Форматирование даты
        if ($this->arParams["SHOW_DATE"] && $element["ACTIVE_FROM"]) {
            // Дата в читаемом формате
            $element["DISPLAY_ACTIVE_FROM"] = FormatDate(
                "d F Y",
                MakeTimeStamp($element["ACTIVE_FROM"])
            );

            // Дата в ISO формате для атрибута datetime
            $element["ACTIVE_FROM_ISO"] = date(
                'c',
                MakeTimeStamp($element["ACTIVE_FROM"])
            );
        }

        // Обрезка текста анонса
        if ($this->arParams["SHOW_PREVIEW_TEXT"] && $element["PREVIEW_TEXT"]) {
            $length = $this->arParams["PREVIEW_TEXT_LENGTH"];

            if ($length > 0 && mb_strlen($element["PREVIEW_TEXT"]) > $length) {
                $element["PREVIEW_TEXT"] = TruncateText(
                    $element["PREVIEW_TEXT"],
                    $length
                );
            }
        }

        return $element;
    }

    /**
     * Основной метод выполнения компонента
     *
     * @return void
     */
    public function executeComponent()
    {
        // Включаем кеширование в рамках (для ajax-компонентов)
        $this->setFrameMode(true);

        // Проверяем наличие необходимых модулей
        if (!$this->checkModules()) {
            return;
        }

        // Проверяем обязательные параметры
        if ($this->arParams["IBLOCK_ID"] <= 0) {
            ShowError("Не указан инфоблок");
            return;
        }

        // Формируем ключ кеширования
        // Можно добавить дополнительные параметры для разных кешей
        $cacheId = $this->arParams["IBLOCK_ID"] . "_" . $this->arParams["COUNT"];

        // Запускаем кеширование
        if ($this->startResultCache(false, $cacheId)) {

            // Получаем элементы из базы данных
            $this->arResult["ITEMS"] = $this->getElements();

            // Если элементов нет - прерываем кеширование
            // Чтобы пустой результат не кешировался
            if (empty($this->arResult["ITEMS"])) {
                $this->abortResultCache();
            }

            // Сохраняем дополнительные данные в результат
            $this->arResult["IBLOCK_ID"] = $this->arParams["IBLOCK_ID"];
            $this->arResult["COUNT"] = count($this->arResult["ITEMS"]);

            // Подключаем шаблон компонента
            $this->includeComponentTemplate();
        }
    }
}
