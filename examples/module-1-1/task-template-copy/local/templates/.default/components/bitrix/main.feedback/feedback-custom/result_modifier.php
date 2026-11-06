<?php

/**
 * result_modifier.php шаблона feedback-custom.
 *
 * Здесь — только то, что должно быть вычислено до вывода и не является
 * разметкой: нормализация $arParams и вычисляемые поля для шаблона.
 *
 * Что сюда НЕЛЬЗЯ:
 *   - запросы к БД (шаблон рендерится много раз, результат — не бизнес-логика);
 *   - правку данных, которые потом сохраняются (это OnBefore*-события);
 *   - вывод и HTML.
 *
 * Что сюда можно и полезно: необязательные параметры шаблона, вычисляемые
 * подписи, флаги вида «показывать ли блок». Пример — REQUIRED_FIELDS строкой.
 *
 * @var array                    $arParams
 * @var array                    $arResult
 * @var CBitrixComponentTemplate $this
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

// Компонент допускает REQUIRE строкой через запятую; шаблону удобнее массив.
if (isset($arParams['REQUIRED_FIELDS']) && is_string($arParams['REQUIRED_FIELDS'])) {
    $arParams['REQUIRED_FIELDS'] = array_filter(
        array_map('trim', explode(',', $arParams['REQUIRED_FIELDS'])),
        static fn(string $field): bool => $field !== ''
    );
}

if (!isset($arParams['REQUIRED_FIELDS']) || !is_array($arParams['REQUIRED_FIELDS'])) {
    $arParams['REQUIRED_FIELDS'] = [];
}

// Шаблон получает уже нормализованное значение; в component_epilog его нет —
// это ещё одна причина не хранить состояние здесь.
