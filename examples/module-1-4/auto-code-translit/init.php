<?php

/**
 * Задача 2: символьный код элемента инфоблока из названия.
 *
 * Подключение в /local/php_interface/init.php:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/auto-code-translit/init.php';
 *
 * Почему Compatible: OnBeforeIBlockElementAdd/Update отправляются старым ядром
 * и передают массив полей по ссылке. Через addEventHandler() обработчик получил
 * бы объект Main\Event, а не поля.
 */

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    return;
}

$eventManager = EventManager::getInstance();

$eventManager->addEventHandlerCompatible(
    'iblock',
    'OnBeforeIBlockElementAdd',
    [AutoCodeTranslit::class, 'onBeforeAdd']
);

$eventManager->addEventHandlerCompatible(
    'iblock',
    'OnBeforeIBlockElementUpdate',
    [AutoCodeTranslit::class, 'onBeforeUpdate']
);

/**
 * Генерирует CODE, если он не задан.
 *
 * Что здесь важно понять:
 * - обработчик не перетирает явно заданный код;
 * - код уникален в рамках инфоблока, поэтому проверка идёт с IBLOCK_ID;
 * - старый API инфоблоков не умеет «попробовать и получить ошибку уникальности»,
 *   поэтому уникальность обеспечивается проверкой до записи. Между проверкой и
 *   INSERT остаётся окно гонки — на уровне БД это решается уникальным индексом
 *   (в современных версиях iblock он есть по IBLOCK_ID + CODE).
 */
final class AutoCodeTranslit
{
    private const MAX_LENGTH = 100;
    private const MAX_ATTEMPTS = 100;

    public static function onBeforeAdd(array &$arFields): void
    {
        self::fillCode($arFields);
    }

    public static function onBeforeUpdate(array &$arFields): void
    {
        self::fillCode($arFields);
    }

    private static function fillCode(array &$arFields): void
    {
        $code = trim((string)($arFields['CODE'] ?? ''));
        $name = trim((string)($arFields['NAME'] ?? ''));
        $iblockId = (int)($arFields['IBLOCK_ID'] ?? 0);

        if ($code !== '' || $name === '' || $iblockId <= 0) {
            return;
        }

        $base = \Cutil::translit($name, 'ru', [
            'max_len' => self::MAX_LENGTH,
            'change_case' => 'L',
            'replace_space' => '-',
            'replace_other' => '-',
            'delete_repeat_replace' => true,
            'use_google' => false,
        ]);

        $base = trim($base, '-');
        if ($base === '') {
            return;
        }

        $arFields['CODE'] = self::makeUnique($base, $iblockId, (int)($arFields['ID'] ?? 0));
    }

    private static function makeUnique(string $base, int $iblockId, int $elementId): string
    {
        $code = $base;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            if (!self::codeExists($code, $iblockId, $elementId)) {
                return $code;
            }

            $code = $base . '-' . $attempt;
        }

        // Исчерпали попытки: гарантированно уникальный хвост вместо бесконечного цикла.
        return $base . '-' . substr(md5((string)microtime(true)), 0, 6);
    }

    private static function codeExists(string $code, int $iblockId, int $elementId): bool
    {
        $filter = [
            'IBLOCK_ID' => $iblockId,
            'CODE' => $code,
            'CHECK_PERMISSIONS' => 'N',
        ];

        if ($elementId > 0) {
            $filter['!ID'] = $elementId;
        }

        $row = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        return $row !== false;
    }
}
