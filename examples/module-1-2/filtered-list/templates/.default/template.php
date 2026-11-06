<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Шаблон компонента "Список с фильтром"
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $component
 */

$this->addExternalCss($templateFolder . "/style.css");
$this->addExternalJs($templateFolder . "/script.js");

$componentId = "filtered-list-" . randString(6);
?>

<div class="filtered-list" id="<?= $componentId ?>" data-ajax="<?= $arParams["USE_AJAX"] ? "true" : "false" ?>">

    <?php if ($arParams["SHOW_SECTIONS_FILTER"] || $arParams["SHOW_PROPERTIES_FILTER"]): ?>
        <!-- Блок фильтров -->
        <div class="filtered-list__filter">
            <form class="filter-form" method="get" action="<?= $APPLICATION->GetCurPageParam() ?>">

                <div class="filter-form__header">
                    <h3 class="filter-form__title">Фильтр</h3>
                    <button type="button" class="filter-form__reset" data-reset-filter>
                        Сбросить
                    </button>
                </div>

                <div class="filter-form__body">

                    <?php if ($arParams["SHOW_SECTIONS_FILTER"] && !empty($arResult["SECTIONS"])): ?>
                        <!-- Фильтр по разделам -->
                        <div class="filter-form__group">
                            <label class="filter-form__label">Раздел</label>
                            <select name="section" class="filter-form__select" data-filter-field>
                                <option value="">Все разделы</option>
                                <?php foreach ($arResult["SECTIONS"] as $section): ?>
                                    <option
                                        value="<?= $section["ID"] ?>"
                                        <?= ($arResult["CURRENT_FILTER"]["SECTION_ID"] ?? 0) == $section["ID"] ? "selected" : "" ?>
                                    >
                                        <?= htmlspecialcharsEx($section["NAME"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($arParams["SHOW_PROPERTIES_FILTER"] && !empty($arResult["FILTER_PROPERTIES"])): ?>
                        <!-- Фильтр по свойствам -->
                        <?php foreach ($arResult["FILTER_PROPERTIES"] as $property): ?>
                            <div class="filter-form__group">
                                <label class="filter-form__label">
                                    <?= htmlspecialcharsEx($property["NAME"]) ?>
                                </label>

                                <?php if ($property["PROPERTY_TYPE"] === "L" && !empty($property["VALUES"])): ?>
                                    <!-- Список -->
                                    <select
                                        name="property[<?= $property["CODE"] ?>]"
                                        class="filter-form__select"
                                        data-filter-field
                                    >
                                        <option value="">Не выбрано</option>
                                        <?php foreach ($property["VALUES"] as $valueId => $valueName): ?>
                                            <option
                                                value="<?= $valueId ?>"
                                                <?= ($arResult["CURRENT_FILTER"]["PROPERTY_" . $property["CODE"]] ?? "") == $valueId ? "selected" : "" ?>
                                            >
                                                <?= htmlspecialcharsEx($valueName) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($property["PROPERTY_TYPE"] === "S"): ?>
                                    <!-- Строка -->
                                    <input
                                        type="text"
                                        name="property[<?= $property["CODE"] ?>]"
                                        class="filter-form__input"
                                        value="<?= htmlspecialcharsEx($arResult["CURRENT_FILTER"]["PROPERTY_" . $property["CODE"]] ?? "") ?>"
                                        placeholder="Введите значение"
                                        data-filter-field
                                    >

                                <?php elseif ($property["PROPERTY_TYPE"] === "N"): ?>
                                    <!-- Число -->
                                    <div class="filter-form__range">
                                        <input
                                            type="number"
                                            name="property[<?= $property["CODE"] ?>][MIN]"
                                            class="filter-form__input"
                                            placeholder="От"
                                            data-filter-field
                                        >
                                        <span>—</span>
                                        <input
                                            type="number"
                                            name="property[<?= $property["CODE"] ?>][MAX]"
                                            class="filter-form__input"
                                            placeholder="До"
                                            data-filter-field
                                        >
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

                <div class="filter-form__footer">
                    <button type="submit" class="filter-form__submit">
                        Применить фильтр
                    </button>
                </div>

            </form>
        </div>
    <?php endif; ?>

    <!-- Контейнер для списка (обновляется через Ajax) -->
    <div class="filtered-list__content" data-list-container>

        <?php if (!empty($arResult["ITEMS"])): ?>

            <div class="items-grid">
                <?php foreach ($arResult["ITEMS"] as $arItem): ?>

                    <?php
                    $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], "Редактировать");
                    $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], "Удалить", ["CONFIRM" => "Удалить элемент?"]);
                    ?>

                    <article class="item-card" id="<?= $this->GetEditAreaId($arItem['ID']) ?>">

                        <?php if (!empty($arItem["PREVIEW_PICTURE"])): ?>
                            <div class="item-card__image">
                                <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>">
                                    <img
                                        src="<?= $arItem["PREVIEW_PICTURE"]["SRC"] ?>"
                                        alt="<?= htmlspecialcharsEx($arItem["NAME"]) ?>"
                                        loading="lazy"
                                    >
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="item-card__body">
                            <h3 class="item-card__title">
                                <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>">
                                    <?= htmlspecialcharsEx($arItem["NAME"]) ?>
                                </a>
                            </h3>

                            <?php if (!empty($arItem["PREVIEW_TEXT"])): ?>
                                <div class="item-card__text">
                                    <?= $arItem["PREVIEW_TEXT"] ?>
                                </div>
                            <?php endif; ?>

                            <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>" class="item-card__link">
                                Подробнее →
                            </a>
                        </div>

                    </article>

                <?php endforeach; ?>
            </div>

            <?php if ($arParams["SHOW_PAGINATION"] && !empty($arResult["NAV_STRING"])): ?>
                <!-- Пагинация -->
                <div class="filtered-list__pagination">
                    <?= $arResult["NAV_STRING"] ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Пустой результат -->
            <div class="filtered-list__empty">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="2" opacity="0.2"/>
                    <path d="M32 20v24M20 32h24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <p>По заданным критериям ничего не найдено</p>
                <button type="button" class="btn-reset" data-reset-filter>
                    Сбросить фильтр
                </button>
            </div>
        <?php endif; ?>

    </div>

    <!-- Loader для Ajax -->
    <div class="filtered-list__loader" data-loader style="display: none;">
        <div class="loader-spinner"></div>
        <p>Загрузка...</p>
    </div>

</div>

<script>
// Передаем ID компонента в JavaScript
window.filteredListComponents = window.filteredListComponents || {};
window.filteredListComponents['<?= $componentId ?>'] = {
    ajax: <?= $arParams["USE_AJAX"] ? "true" : "false" ?>,
    url: '<?= $APPLICATION->GetCurPageParam("ajax=Y", ["ajax"]) ?>'
};
</script>
