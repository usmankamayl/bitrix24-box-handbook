<?php

namespace BitrixCourse\PartnerPricing;

use Bitrix\Highloadblock as HL;
use Bitrix\Main\Loader;

/**
 * Хранилище наценок: (раздел каталога, группа пользователей) -> % наценки.
 *
 * Используем Highload-блок, а не UF-поле на инфоблоке, т.к. правило
 * «категория x группа партнёра» — это отдельная связная сущность,
 * а не атрибут товара.
 *
 * Поля HL-блока PartnerCategoryMarkup:
 *   UF_SECTION_ID    (число)  — ID раздела инфоблока каталога
 *   UF_USER_GROUP_ID (число)  — ID группы пользователей («Партнёр уровня N»)
 *   UF_MARKUP        (число, с плавающей точкой) — процент наценки, напр. 12.5
 */
class PartnerMarkupTable
{
    private const HL_BLOCK_NAME = 'PartnerCategoryMarkup';

    private static ?string $entityClass = null;

    public static function getEntityClass(): ?string
    {
        if (self::$entityClass !== null) {
            return self::$entityClass;
        }

        if (!Loader::includeModule('highloadblock')) {
            return null;
        }

        $hlBlock = HL\HighloadBlockTable::getRow([
            'filter' => ['NAME' => self::HL_BLOCK_NAME],
        ]);

        if (!$hlBlock) {
            return null;
        }

        $entity = HL\HighloadBlockTable::compileEntity($hlBlock);
        self::$entityClass = $entity->getDataClass();
        return self::$entityClass;
    }

    /**
     * Наценка для конкретного раздела и набора групп пользователя.
     * Если правил несколько (пользователь в нескольких партнёрских группах) —
     * берём максимальную наценку (бизнес-правило: самая невыгодная для партнёра
     * группа не должна давать ему более дешёвую цену случайно).
     */
    public static function getMarkup(int $sectionId, array $userGroupIds): ?float
    {
        $entityClass = self::getEntityClass();
        if ($entityClass === null || $sectionId <= 0 || empty($userGroupIds)) {
            return null;
        }

        $rows = $entityClass::getList([
            'select' => ['UF_MARKUP'],
            'filter' => [
                'UF_SECTION_ID'    => $sectionId,
                'UF_USER_GROUP_ID' => $userGroupIds,
            ],
        ])->fetchAll();

        if (empty($rows)) {
            return null;
        }

        return (float)max(array_column($rows, 'UF_MARKUP'));
    }
}
