<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * Шаблон компонента "Список новостей"
 *
 * Доступные переменные:
 * @var array $arParams Параметры компонента
 * @var array $arResult Результат работы компонента
 * @var CBitrixComponent $component Объект компонента
 * @var CBitrixComponentTemplate $this Объект шаблона
 * @var string $templateName Название шаблона
 * @var string $templateFile Путь к файлу шаблона
 * @var string $templateFolder Путь к папке шаблона
 * @var string $componentPath Путь к компоненту
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

// Подключаем CSS и JS файлы шаблона
$this->addExternalCss($templateFolder . "/style.css");
$this->addExternalJs($templateFolder . "/script.js");

?>

<div class="news-list" data-component="news-list">

    <?php if (!empty($arResult["ITEMS"])): ?>

        <div class="news-list__items">

            <?php foreach ($arResult["ITEMS"] as $key => $arItem): ?>

                <?php
                // ID элемента для редактирования в режиме правки
                $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), ["CONFIRM" => "Удалить элемент?"]);
                ?>

                <article class="news-item" id="<?= $this->GetEditAreaId($arItem['ID']) ?>">

                    <?php if ($arParams["SHOW_PICTURE"] && !empty($arItem["PREVIEW_PICTURE"])): ?>
                        <div class="news-item__image">
                            <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>">
                                <img
                                    src="<?= $arItem["PREVIEW_PICTURE"]["SRC"] ?>"
                                    width="<?= $arItem["PREVIEW_PICTURE"]["WIDTH"] ?>"
                                    height="<?= $arItem["PREVIEW_PICTURE"]["HEIGHT"] ?>"
                                    alt="<?= htmlspecialcharsEx($arItem["NAME"]) ?>"
                                    title="<?= htmlspecialcharsEx($arItem["NAME"]) ?>"
                                    loading="lazy"
                                >
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="news-item__content">

                        <h3 class="news-item__title">
                            <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>">
                                <?= htmlspecialcharsEx($arItem["NAME"]) ?>
                            </a>
                        </h3>

                        <?php if ($arParams["SHOW_DATE"] && !empty($arItem["DISPLAY_ACTIVE_FROM"])): ?>
                            <time class="news-item__date" datetime="<?= $arItem["ACTIVE_FROM_ISO"] ?>">
                                <?= $arItem["DISPLAY_ACTIVE_FROM"] ?>
                            </time>
                        <?php endif; ?>

                        <?php if ($arParams["SHOW_PREVIEW_TEXT"] && !empty($arItem["PREVIEW_TEXT"])): ?>
                            <div class="news-item__text">
                                <?= $arItem["PREVIEW_TEXT"] ?>
                            </div>
                        <?php endif; ?>

                        <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>" class="news-item__link">
                            Подробнее
                            <svg class="news-item__link-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M6 3L11 8L6 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <?php
        // Информация о количестве элементов (может быть скрыта через CSS)
        ?>
        <div class="news-list__info">
            Показано элементов: <?= $arResult["COUNT"] ?>
        </div>

    <?php else: ?>

        <div class="news-list__empty">
            <p>Новостей не найдено</p>
        </div>

    <?php endif; ?>

</div>

<?php
/**
 * ВАЖНО: Для режима правки (редактирование на сайте)
 * нужно добавлять метки редактирования через AddEditAction и AddDeleteAction
 *
 * Это позволит редактировать элементы прямо на странице
 * в режиме "Правка сайта"
 */
?>
